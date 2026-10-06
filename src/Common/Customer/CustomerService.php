<?php
/**
 * Customer domain validation and mutation service.
 *
 * @package MF\VeLog\Common\Customer
 */

namespace MF\VeLog\Common\Customer;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and persists private customer records.
 */
final class CustomerService {

	private const TYPE = 'mf_velog_customer';

	/** Validate a customer name. */
	public static function validate_name( mixed $value ): ?string {
		return self::validate_text( $value, 1, 200 );
	}

	/** Validate an optional international phone string. */
	public static function validate_phone( mixed $value ): ?string {
		return self::validate_text( $value, 0, 64 );
	}

	/** Validate an optional email address without silently changing it. */
	public static function validate_email( mixed $value ): ?string {
		$email = self::validate_text( $value, 0, 254 );
		if ( null === $email || '' === $email ) {
			return $email;
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : null;
	}

	/**
	 * Build the normalized name search projection.
	 *
	 * @param array<string, mixed> $fields Customer fields.
	 */
	public static function project_name( array $fields ): ?string {
		return isset( $fields['name'] ) ? self::lower( (string) $fields['name'] ) : null;
	}

	/**
	 * Build the normalized phone search projection.
	 *
	 * @param array<string, mixed> $fields Customer fields.
	 */
	public static function project_phone( array $fields ): ?string {
		return isset( $fields['phone'] ) && '' !== $fields['phone'] ? self::lower( (string) $fields['phone'] ) : null;
	}

	/**
	 * Build the normalized email search projection.
	 *
	 * @param array<string, mixed> $fields Customer fields.
	 */
	public static function project_email( array $fields ): ?string {
		return isset( $fields['email'] ) && '' !== $fields['email'] ? self::lower( (string) $fields['email'] ) : null;
	}

	/**
	 * Create a customer.
	 *
	 * @param array<string, mixed> $fields Customer fields.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $fields, \WP_User $actor, string $request_id ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_customers' ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to create customers.' );
		}

		return RecordRepository::create( self::TYPE, $fields, $actor, $request_id );
	}

	/**
	 * Update customer fields with optimistic locking.
	 *
	 * @param int                  $id         Customer ID.
	 * @param int                  $version    Expected version.
	 * @param array<string, mixed> $fields     Customer fields.
	 * @param \WP_User             $actor      Authenticated actor.
	 * @param string               $request_id Unique request ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update(
		int $id,
		int $version,
		array $fields,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		if ( $id <= 0 || $version <= 0 ) {
			return new \WP_Error( 'invalid_input', 'Customer ID and version must be positive.' );
		}

		return RecordRepository::save( self::TYPE, $id, $version, $fields, $actor, $request_id, 'customer_update' );
	}

	/**
	 * Archive a customer when no active vehicle links to it.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function archive( int $id, int $version, \WP_User $actor, string $request_id ): array|\WP_Error {
		return $this->transition( $id, $version, 'archived', $actor, $request_id );
	}

	/**
	 * Restore a valid archived customer.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function restore( int $id, int $version, \WP_User $actor, string $request_id ): array|\WP_Error {
		return $this->transition( $id, $version, 'active', $actor, $request_id );
	}

	/**
	 * Run a transition inside the DATA-001 write lock.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private function transition(
		int $id,
		int $version,
		string $state,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_customers' ) || $id <= 0 || $version <= 0 || '' === $request_id ) {
			return new \WP_Error( 'forbidden', 'Customer transition is not permitted.' );
		}
		$environment = WriteCoordinator::check_environment();
		if ( is_wp_error( $environment ) ) {
			return $environment;
		}

		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use ( $id, $version, $state, $request_id ): array|\WP_Error {
				if ( 'archived' === $state ) {
					$linked = CustomerRelations::has_active_vehicle( $unit->get_dbh(), $unit->get_prefix(), $id );
					if ( is_wp_error( $linked ) ) {
						return $linked;
					}
					if ( $linked ) {
						return new \WP_Error( 'active_vehicle_link', 'Reassign or archive active vehicles first.' );
					}
				}
				return $unit->save( self::TYPE, $id, $version, array(), $request_id, 'customer_' . $state, $state );
			}
		);
	}

	/**
	 * Validate bounded plain text without accepting a modified value.
	 */
	private static function validate_text( mixed $value, int $minimum, int $maximum ): ?string {
		if ( ! is_string( $value ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', $value ) ) {
			return null;
		}
		$trimmed = trim( $value );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $trimmed, 'UTF-8' ) : strlen( $trimmed );
		return $length >= $minimum && $length <= $maximum ? $trimmed : null;
	}

	/** Lowercase Unicode when mbstring is available. */
	private static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}

// phpcs:enable Squiz.Commenting.FunctionComment.MissingParamTag
