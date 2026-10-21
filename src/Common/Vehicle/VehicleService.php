<?php
/**
 * Vehicle operations and authoritative transaction guards.
 *
 * @package MF\VeLog\Common\Vehicle
 */

namespace MF\VeLog\Common\Vehicle;

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enforces vehicle invariants for service and direct repository callers. */
final class VehicleService {

	/** Vehicle post type. */
	private const TYPE = 'mf_velog_vehicle';

	/**
	 * Validate optional bounded plain text.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function validate_text( mixed $value ): ?string {
		if ( ! is_string( $value ) || 0 !== preg_match( '/[\x00-\x1F\x7F<>]/u', $value ) ) {
			return null;
		}
		$value  = trim( $value );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		return $length <= 100 ? $value : null;
	}

	/**
	 * Validate the shorter optional color.
	 *
	 * @param mixed $value Submitted color.
	 */
	public static function validate_color( mixed $value ): ?string {
		$text = self::validate_text( $value );
		if ( null === $text ) {
			return null;
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		return null !== $text && $length <= 64 ? $text : null;
	}

	/**
	 * Create a vehicle through authoritative repository checks.
	 *
	 * @param array<string, mixed> $fields     Vehicle fields.
	 * @param \WP_User             $actor      Trusted staff actor.
	 * @param string               $request_id Unique request identity.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $fields, \WP_User $actor, string $request_id ): array|\WP_Error {
		return RecordRepository::create( self::TYPE, $fields, $actor, $request_id );
	}

	/**
	 * Update a versioned vehicle.
	 *
	 * @param int                  $id         Vehicle ID.
	 * @param int                  $version    Expected version.
	 * @param array<string, mixed> $fields     Changes.
	 * @param \WP_User             $actor      Trusted staff actor.
	 * @param string               $request_id Unique request identity.
	 * @param string               $reason     Optional manager correction reason.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update(
		int $id,
		int $version,
		array $fields,
		\WP_User $actor,
		string $request_id,
		string $reason = ''
	): array|\WP_Error {
		if ( $id < 1 || $version < 1 ) {
			return new \WP_Error( 'invalid_input', 'Vehicle ID and version must be positive.' );
		}
		return RecordRepository::save( self::TYPE, $id, $version, $fields, $actor, $request_id, $reason );
	}

	/**
	 * Archive or restore without deleting identifiers or history.
	 *
	 * @param int      $id         Vehicle ID.
	 * @param int      $version    Expected version.
	 * @param string   $state      Allowlisted resulting state.
	 * @param \WP_User $actor      Trusted staff actor.
	 * @param string   $request_id Unique request identity.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function transition(
		int $id,
		int $version,
		string $state,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_vehicles' ) ) {
			return new \WP_Error( 'forbidden', 'Vehicle mutation requires manager access.' );
		}
		if ( $id < 1 || $version < 1 || '' === $request_id
			|| ! in_array( $state, array( 'active', 'archived' ), true ) ) {
			return new \WP_Error( 'invalid_input', 'Invalid vehicle transition.' );
		}
		return WriteCoordinator::run(
			$actor,
			static fn ( WriteUnit $unit ): array|\WP_Error => $unit->save(
				self::TYPE,
				$id,
				$version,
				array(),
				$request_id,
				'vehicle_' . $state,
				$state
			)
		);
	}

	/**
	 * Validate candidate state inside the existing pinned write transaction.
	 *
	 * @param WriteUnit            $unit   Active transaction.
	 * @param array<string, mixed> $fields Full candidate fields.
	 * @param array<string, mixed> $before Previous envelope, empty for create.
	 * @param string               $state  Resulting state.
	 * @param string               $reason Correction reason.
	 * @param int                  $id     Existing vehicle ID, zero for create.
	 */
	public static function guard_write(
		WriteUnit $unit,
		array $fields,
		array $before,
		string $state,
		string $reason,
		int $id = 0
	): bool|\WP_Error {
		if ( strlen( $reason ) > 2000 || 0 !== preg_match( '/[\x00-\x1F\x7F<>]/u', $reason )
			|| ( function_exists( 'mb_strlen' ) ? mb_strlen( $reason, 'UTF-8' ) : strlen( $reason ) ) > 500 ) {
			return new \WP_Error( 'invalid_input', 'Invalid correction reason.' );
		}
		$owner     = $fields['current_customer_id'] ?? 0;
		$old_owner = $before['fields']['current_customer_id'] ?? 0;
		if ( $owner > 0 && ( 'active' === $state || $owner !== $old_owner ) ) {
			$customer = $unit->get( 'mf_velog_customer', $owner );
			if ( is_wp_error( $customer ) ) {
				return 'storage_unavailable' === $customer->get_error_code()
					? $customer : new \WP_Error( 'invalid_customer', 'Select an existing active private customer.' );
			}
			if ( 'active' !== $customer['state'] ) {
				return new \WP_Error( 'invalid_customer', 'Select an active private customer.' );
			}
		}
		$old_baseline = $before['fields']['baseline'] ?? array();
		$baseline     = $fields['baseline'] ?? array();
		if ( $old_baseline !== $baseline && ! empty( $before ) ) {
			if ( OdometerPolicy::requires_reason( $old_baseline, $baseline ) && '' === trim( $reason ) ) {
				return new \WP_Error( 'reason_required', 'A baseline decrease or removal requires a reason.' );
			}
			$prefix      = $unit->get_prefix();
			$unsupported = WriteCoordinator::query_direct(
				$unit->get_dbh(),
				"SELECT p.ID FROM `{$prefix}posts` p INNER JOIN `{$prefix}postmeta` s ON s.post_id = p.ID
				AND s.meta_key = '_mf_velog_state' AND s.meta_value = 'finalized'
				WHERE p.post_type = 'mf_velog_service' AND p.post_status = 'private'
				AND NOT EXISTS (SELECT v.meta_id FROM `{$prefix}postmeta` v WHERE v.post_id = p.ID
				AND v.meta_key = '_mf_velog_service_vehicle_id') LIMIT 1"
			);
			if ( false === $unsupported ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			if ( ! empty( $unsupported ) ) {
				return new \WP_Error( 'service_history_locked' );
			}
			return \MF\VeLog\Common\Service\ServiceChronology::validate(
				$unit,
				$id,
				0,
				$baseline,
				$baseline['date'] ?? '',
				$baseline,
				$reason
			);
		}
		return true;
	}
}
