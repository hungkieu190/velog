<?php
/**
 * Authoritative reminder lifecycle and storage guard.
 *
 * @package MF\VeLog\Common\Reminder
 */

namespace MF\VeLog\Common\Reminder;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Service\ServiceFields;
use MF\VeLog\Common\Storage\CurrentOdometerIndex;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No delivery, cron, recurrence, service creation or terminal-state reopening. */
final class ReminderService {

	/**
	 * Validate one required plain single-line title.
	 *
	 * @param mixed $value Candidate title.
	 */
	public static function title( mixed $value ): ?string {
		$text = ServiceFields::text( $value, 200 );
		return null !== $text && '' !== trim( $text ) && 0 === preg_match( '/[\r\n\t]/', $text ) ? trim( $text ) : null;
	}

	/**
	 * Validate optional UTC completion timestamp.
	 *
	 * @param mixed $value Candidate timestamp.
	 */
	public static function timestamp( mixed $value ): ?string {
		if ( '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone( 'UTC' ) );
		return false !== $date && $date->format( 'Y-m-d\TH:i:s\Z' ) === $value ? $value : null;
	}

	/**
	 * Revalidate a complete reminder, including trigger presence.
	 *
	 * @param array<string, mixed> $fields Canonical fields.
	 */
	public static function validate_record( array $fields ): bool {
		$canonical = RecordSchema::validate_fields( 'mf_velog_reminder', $fields );
		return ! is_wp_error( $canonical ) && $canonical === $fields
			&& ( $fields['vehicle_id'] ?? 0 ) > 0 && isset( $fields['title'] ) && '' !== $fields['title']
			&& isset(
				$fields['due_date'],
				$fields['threshold'],
				$fields['snooze_until'],
				$fields['completed_by'],
				$fields['completed_at_utc']
			)
			&& ( '' !== $fields['due_date'] || ! empty( $fields['threshold'] ) );
	}

	/**
	 * Build a fail-closed private read context without relation lookup growth.
	 *
	 * @param array<string, mixed> $envelope Stored envelope.
	 * @param \WP_User             $actor    Trusted reader.
	 * @return array<string, mixed>
	 */
	public static function context( array $envelope, \WP_User $actor ): array {
		unset( $actor );
		$valid = is_array( $envelope['fields'] ?? null ) && self::validate_record( $envelope['fields'] )
			&& in_array( $envelope['state'] ?? null, array( 'active', 'snoozed', 'completed' ), true );
		if ( $valid ) {
			$f     = $envelope['fields'];
			$valid = 'completed' === $envelope['state']
				? $f['completed_by'] > 0 && '' !== $f['completed_at_utc']
				: 0 === $f['completed_by'] && '' === $f['completed_at_utc'];
			if ( 'snoozed' === $envelope['state'] ) {
				$valid = $valid && '' !== $f['snooze_until'];
			}
		}
		return array(
			'type'  => $valid ? 'mf_velog_reminder' : '',
			'state' => $envelope['state'] ?? '',
		);
	}

	/**
	 * Create a manager-owned reminder and initialize its derived vehicle index.
	 *
	 * @param array<string, mixed> $fields     Canonical user-editable fields.
	 * @param \WP_User             $actor      Manager actor.
	 * @param string               $request_id Request UUID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $fields, \WP_User $actor, string $request_id ): array|\WP_Error {
		if ( ! wp_is_uuid( $request_id, 4 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$fields = array_merge(
			$fields,
			array(
				'snooze_until'     => '',
				'completed_by'     => 0,
				'completed_at_utc' => '',
			)
		);
		return RecordRepository::create( 'mf_velog_reminder', $fields, $actor, $request_id );
	}

	/**
	 * Edit, snooze, resume or complete without reopening terminal records.
	 *
	 * @param int                  $id         Reminder identity.
	 * @param int                  $version    Expected version.
	 * @param string               $operation  edit/snooze/resume/complete.
	 * @param array<string, mixed> $fields     Edit fields or snooze date.
	 * @param \WP_User             $actor      Manager actor.
	 * @param string               $request_id Request UUID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function change(
		int $id,
		int $version,
		string $operation,
		array $fields,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		if ( $id < 1 || $version < 1 || ! wp_is_uuid( $request_id, 4 ) || ! in_array(
			$operation,
			array(
				'edit',
				'snooze',
				'resume',
				'complete',
			),
			true
		) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$allowed = match ( $operation ) {
			'edit' => array( 'vehicle_id', 'title', 'due_date', 'threshold' ),
			'snooze' => array( 'snooze_until' ),
			default => array(),
		};
		if ( array_diff( array_keys( $fields ), $allowed ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$fields = RecordSchema::validate_fields( 'mf_velog_reminder', $fields );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use ( $id, $version, $operation, $fields, $request_id ): array|\WP_Error {
				$before = $unit->get( 'mf_velog_reminder', $id );
				if ( is_wp_error( $before ) ) {
					return $before;
				}
				// Match the original committed operation before applying terminal/stale checks.
				foreach ( $before['audit'] as $index => $entry ) {
					if ( ( $entry['request_id_sha256'] ?? '' ) !== hash( 'sha256', $request_id ) ) {
						continue;
					}
					if ( $index !== $version || ( $entry['actor_id'] ?? 0 ) !== (int) $unit->get_actor()->ID
						|| ( $entry['reason'] ?? '' ) !== 'reminder_' . $operation ) {
						return new \WP_Error( 'invalid_input' );
					}
					foreach ( $fields as $key => $value ) {
						if ( ! array_key_exists( $key, $entry['after'] ) || $entry['after'][ $key ] !== $value ) {
							return new \WP_Error( 'invalid_input' );
						}
					}
					return $before;
				}
				if ( 'completed' === $before['state'] ) {
					return new \WP_Error( 'invalid_state' );
				}
				$changes = 'edit' === $operation ? array_intersect_key(
					$fields,
					array_flip(
						array(
							'vehicle_id',
							'title',
							'due_date',
							'threshold',
						)
					)
				) : array();
				$state   = $before['state'];
				if ( 'snooze' === $operation ) {
					$state                   = 'snoozed';
					$changes['snooze_until'] = $fields['snooze_until'] ?? '';
				} elseif ( 'resume' === $operation ) {
					$state                   = 'active';
					$changes['snooze_until'] = '';
				} elseif ( 'complete' === $operation ) {
					$state                       = 'completed';
					$changes['completed_by']     = (int) $unit->get_actor()->ID;
					$changes['completed_at_utc'] = gmdate( 'Y-m-d\TH:i:s\Z' );
				}
				return $unit->save( 'mf_velog_reminder', $id, $version, $changes, $request_id, 'reminder_' . $operation, $state );
			}
		);
	}

	/**
	 * Enforce all lifecycle rules even for direct repository callers.
	 *
	 * @param WriteUnit            $unit   Pinned transaction.
	 * @param array<string, mixed> $fields Candidate fields.
	 * @param array<string, mixed> $before Previous envelope or empty.
	 * @param string               $state  Candidate stored state.
	 * @param string               $reason Audit reason.
	 * @param int                  $id     Record identity.
	 */
	public static function guard_write(
		WriteUnit $unit,
		array $fields,
		array $before,
		string $state,
		string $reason,
		int $id = 0
	): bool|\WP_Error {
		unset( $reason, $id );
		if ( ! $unit->get_actor()->has_cap( 'mf_velog_manage_reminders' ) || ! self::validate_record( $fields ) ) {
			return new \WP_Error( 'forbidden' );
		}
		if ( 'completed' === ( $before['state'] ?? '' ) || ( ! empty( $before )
			&& $fields['vehicle_id'] !== $before['fields']['vehicle_id'] ) ) {
			return new \WP_Error( 'invalid_state' );
		}
		if ( empty( $before ) && 'active' !== $state ) {
			return new \WP_Error( 'invalid_state' );
		}
		$vehicle = $unit->get( 'mf_velog_vehicle', $fields['vehicle_id'] );
		if ( is_wp_error( $vehicle ) || ( empty( $before ) && 'active' !== $vehicle['state'] ) ) {
			return new \WP_Error( 'invalid_vehicle' );
		}
		if ( 'completed' === $state ) {
			$at = strtotime( $fields['completed_at_utc'] );
			if ( $fields['completed_by'] !== (int) $unit->get_actor()->ID || false === $at || abs( time() - $at ) > 30 ) {
				return new \WP_Error( 'invalid_input' );
			}
		} elseif ( 0 !== $fields['completed_by'] || '' !== $fields['completed_at_utc'] ) {
			return new \WP_Error( 'invalid_input' );
		}
		if ( 'active' === $state && '' !== $fields['snooze_until'] ) {
			return new \WP_Error( 'invalid_input' );
		}
		if ( 'snoozed' === $state && ( ( $before['state'] ?? '' ) !== 'snoozed'
			|| ( $before['fields']['snooze_until'] ?? '' ) !== $fields['snooze_until'] ) ) {
			$today = CalendarDate::today( time(), wp_timezone() );
			if ( '' === $fields['snooze_until'] || $fields['snooze_until'] <= $today ) {
				return new \WP_Error( 'invalid_input' );
			}
		}
		return CurrentOdometerIndex::refresh( $unit, $fields['vehicle_id'] );
	}
}
