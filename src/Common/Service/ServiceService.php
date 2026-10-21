<?php
/**
 * Authoritative service workflow and repository write guard.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

// phpcs:disable Generic.Files.LineLength.TooLong -- Closed tuples and pinned SQL contracts are kept together for review.

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Domain transitions apply equally to HTTP, service and direct repository paths. */
final class ServiceService {

	/**
	 * Create a draft attributed to the authenticated actor.
	 *
	 * @param array<string, mixed> $fields     Canonical draft.
	 * @param \WP_User             $actor      Trusted staff actor.
	 * @param string               $request_id Unique request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $fields, \WP_User $actor, string $request_id ): array|\WP_Error {
		if ( ! wp_is_uuid( $request_id, 4 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$fields['vehicle_visible'] = true;
		return RecordRepository::create( 'mf_velog_service', $fields, $actor, $request_id );
	}

	/**
	 * Save a draft, finalize it, or correct an already-finalized record.
	 *
	 * @param int                  $id         Service ID.
	 * @param int                  $version    Expected version.
	 * @param array<string, mixed> $fields     Changed domain fields.
	 * @param string               $operation  save/finalize/correct.
	 * @param \WP_User             $actor      Trusted actor.
	 * @param string               $request_id Unique request.
	 * @param string               $reason     Correction/exception reason.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function change( int $id, int $version, array $fields, string $operation, \WP_User $actor, string $request_id, string $reason = '' ): array|\WP_Error {
		if ( $id < 1 || $version < 1 || ! in_array( $operation, array( 'save', 'finalize', 'correct' ), true ) || ! wp_is_uuid( $request_id, 4 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use ( $id, $version, $fields, $operation, $request_id, $reason ): array|\WP_Error {
				$before = $unit->get( 'mf_velog_service', $id );
				if ( is_wp_error( $before ) ) {
					return $before;
				}
				if ( ( 'correct' === $operation ) !== ( 'finalized' === $before['state'] ) ) {
					return new \WP_Error( 'invalid_state' );
				}
				$changes = $fields;
				if ( 'finalize' === $operation || ( 'correct' === $operation && isset( $fields['service_type_id'] ) && $fields['service_type_id'] !== $before['fields']['service_type_id'] ) ) {
					$catalog = ServiceTypes::all( $unit->get_actor(), $unit );
					$type_id = $changes['service_type_id'] ?? $before['fields']['service_type_id'] ?? 0;
					$type    = is_wp_error( $catalog ) ? null : ( $catalog[ $type_id ] ?? null );
					if ( null === $type || 'active' !== $type['state'] ) {
						return new \WP_Error( 'invalid_type' );
					}
					$changes['type_snapshot'] = array(
						'id'   => $type_id,
						'name' => $type['name'],
					);
				}
				if ( 'finalize' === $operation ) {
					$vehicle  = $unit->get( 'mf_velog_vehicle', $changes['vehicle_id'] ?? $before['fields']['vehicle_id'] );
					$owner    = is_wp_error( $vehicle ) ? 0 : ( $vehicle['fields']['current_customer_id'] ?? 0 );
					$customer = $owner > 0 ? $unit->get( 'mf_velog_customer', $owner ) : null;
					if ( ! is_array( $customer ) || 'active' !== $customer['state'] ) {
						return new \WP_Error( 'invalid_customer' );
					}
					$changes['customer_snapshot'] = array(
						'id'   => $owner,
						'name' => $customer['fields']['name'],
					);
				}
				return $unit->save( 'mf_velog_service', $id, $version, $changes, $request_id, $reason, 'finalize' === $operation ? 'finalized' : null );
			}
		);
	}

	/**
	 * Revalidate the complete stored field map, including fields not in a patch.
	 *
	 * @param array<string, mixed> $fields Full candidate or stored fields.
	 */
	public static function validate_record( array $fields ): bool {
		$canonical = RecordSchema::validate_fields( 'mf_velog_service', $fields );
		return ! is_wp_error( $canonical ) && $canonical === $fields
			&& is_int( $fields['vehicle_id'] ?? null ) && $fields['vehicle_id'] > 0
			&& true === ( $fields['vehicle_visible'] ?? false );
	}

	/**
	 * Derive visibility from the persisted vehicle relation, never posted flags.
	 *
	 * @param array<string, mixed> $envelope Canonical envelope.
	 * @param \WP_User             $actor    Trusted reader.
	 * @return array<string, mixed>
	 */
	public static function context( array $envelope, \WP_User $actor ): array {
		$fields = $envelope['fields'] ?? array();
		$valid  = is_array( $fields ) && self::validate_record( $fields );
		if ( 'finalized' === ( $envelope['state'] ?? '' ) ) {
			$valid = $valid && ! empty( $fields['reading'] ) && ! empty( $fields['service_date'] )
				&& ! empty( $fields['customer_snapshot'] ) && ! empty( $fields['type_snapshot'] )
				&& ( $fields['type_snapshot']['id'] ?? 0 ) === ( $fields['service_type_id'] ?? -1 );
		}
		$vehicle_id = $valid ? $fields['vehicle_id'] : 0;
		$vehicle    = is_int( $vehicle_id ) && $vehicle_id > 0 ? RecordRepository::get( 'mf_velog_vehicle', $vehicle_id, $actor ) : null;
		return array(
			'type'            => 'mf_velog_service',
			'state'           => $envelope['state'] ?? '',
			'author'          => $envelope['created_by'] ?? 0,
			'vehicle_visible' => is_array( $vehicle ),
		);
	}

	/**
	 * Guard every service write on the serialized pinned connection.
	 *
	 * @param WriteUnit            $unit   Active unit.
	 * @param array<string, mixed> $fields Full candidate fields.
	 * @param array<string, mixed> $before Previous envelope or empty.
	 * @param string               $state  Resulting state.
	 * @param string               $reason Audit reason.
	 * @param int                  $id     Current record ID, zero for create.
	 */
	public static function guard_write( WriteUnit $unit, array $fields, array $before, string $state, string $reason, int $id = 0 ): bool|\WP_Error {
		$actor   = $unit->get_actor();
		$manager = $actor->has_cap( 'mf_velog_correct_services' );
		$old     = $before['fields'] ?? array();
		if ( null === ServiceFields::text( $reason, 1000 ) || true !== ( $fields['vehicle_visible'] ?? false ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		if ( empty( $before ) ) {
			if ( 'draft' !== $state || ! empty( $fields['customer_snapshot'] ) || ! empty( $fields['type_snapshot'] ) ) {
				return new \WP_Error( 'invalid_input' );
			}
		} elseif ( 'finalized' === $before['state'] ) {
			if ( ! $manager || 'finalized' !== $state || '' === trim( $reason ) ) {
				return new \WP_Error( 'reason_required' );
			}
			if ( ( $old['vehicle_id'] ?? null ) !== ( $fields['vehicle_id'] ?? null ) || ( $old['customer_snapshot'] ?? array() ) !== ( $fields['customer_snapshot'] ?? array() ) ) {
				return new \WP_Error( 'invalid_input' );
			}
		} else {
			$capability = 'finalized' === $state ? 'mf_velog_finalize_own_services' : 'mf_velog_edit_own_service_drafts';
			if ( ! $manager && ( $before['created_by'] !== (int) $actor->ID || ! $actor->has_cap( $capability ) ) ) {
				return new \WP_Error( 'forbidden' );
			}
		}
		$vehicle_id = $fields['vehicle_id'] ?? 0;
		$vehicle    = $vehicle_id > 0 ? $unit->get( 'mf_velog_vehicle', $vehicle_id ) : null;
		if ( ! is_array( $vehicle ) || ( 'active' !== $vehicle['state'] && 'finalized' !== ( $before['state'] ?? '' ) ) ) {
			return new \WP_Error( 'invalid_vehicle' );
		}
		if ( 'draft' === $state ) {
			return empty( $fields['customer_snapshot'] ) && empty( $fields['type_snapshot'] ) ? true : new \WP_Error( 'invalid_input' );
		}
		if ( empty( $fields['service_date'] ) || empty( $fields['reading'] ) || empty( $fields['service_type_id'] )
			|| empty( $fields['customer_snapshot'] ) || empty( $fields['type_snapshot'] ) ) {
			return new \WP_Error( 'incomplete_service' );
		}
		if ( 'finalized' !== ( $before['state'] ?? '' ) ) {
			$owner    = $vehicle['fields']['current_customer_id'] ?? 0;
			$customer = $owner > 0 ? $unit->get( 'mf_velog_customer', $owner ) : null;
			if ( ! is_array( $customer ) || 'active' !== $customer['state'] || array(
				'id'   => $owner,
				'name' => $customer['fields']['name'],
			) !== $fields['customer_snapshot'] ) {
				return new \WP_Error( 'invalid_customer' );
			}
		}
		if ( 'finalized' !== ( $before['state'] ?? '' ) || ( $old['service_type_id'] ?? 0 ) !== $fields['service_type_id'] ) {
			$catalog = ServiceTypes::all( $actor, $unit );
			$type    = is_wp_error( $catalog ) ? null : ( $catalog[ $fields['service_type_id'] ] ?? null );
			if ( null === $type || 'active' !== $type['state'] || array(
				'id'   => $type['id'],
				'name' => $type['name'],
			) !== $fields['type_snapshot'] ) {
				return new \WP_Error( 'invalid_type' );
			}
		} elseif ( ( $old['type_snapshot'] ?? array() ) !== $fields['type_snapshot'] ) {
			return new \WP_Error( 'invalid_input' );
		}
		return ServiceChronology::validate( $unit, $vehicle_id, $id, $vehicle['fields']['baseline'] ?? array(), $fields['service_date'], $fields['reading'], $reason );
	}
}
