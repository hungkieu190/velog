<?php
/**
 * Customer to vehicle relation contract.
 *
 * @package MF\VeLog\Common\Customer
 */

namespace MF\VeLog\Common\Customer;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the reserved current-customer vehicle projection.
 */
final class CustomerRelations {

	/** Validate an optional positive customer ID. */
	public static function validate_customer_id( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return 0;
		}
		return is_int( $value ) && $value > 0 ? $value : null;
	}

	/**
	 * Build the current-customer projection.
	 *
	 * @param array<string, mixed> $fields Vehicle fields.
	 */
	public static function project_customer_id( array $fields ): ?string {
		$id = $fields['current_customer_id'] ?? 0;
		return is_int( $id ) && $id > 0 ? (string) $id : null;
	}

	/** Check an active vehicle relation on the active pinned transaction handle. */
	public static function has_active_vehicle( \mysqli $dbh, string $prefix, int $customer_id ): bool|\WP_Error {
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_real_escape_string
		$table_posts = mysqli_real_escape_string( $dbh, $prefix . 'posts' );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_real_escape_string
		$table_meta = mysqli_real_escape_string( $dbh, $prefix . 'postmeta' );
		// phpcs:disable Generic.Files.LineLength.TooLong
		$sql = sprintf(
			"SELECT p.ID FROM `%s` p INNER JOIN `%s` owner_meta ON owner_meta.post_id = p.ID AND owner_meta.meta_key = '_mf_velog_current_customer_id' INNER JOIN `%s` state_meta ON state_meta.post_id = p.ID AND state_meta.meta_key = '_mf_velog_state' WHERE p.post_type = 'mf_velog_vehicle' AND p.post_status = 'private' AND owner_meta.meta_value = '%d' AND state_meta.meta_value = 'active' LIMIT 1",
			$table_posts,
			$table_meta,
			$table_meta,
			$customer_id
		);
		// phpcs:enable Generic.Files.LineLength.TooLong
		$rows = \MF\VeLog\Common\Storage\WriteCoordinator::query_direct( $dbh, $sql );
		if ( false === $rows ) {
			return new \WP_Error( 'storage_unavailable', 'Unable to verify active vehicle relations.' );
		}
		return ! empty( $rows );
	}
}

// phpcs:enable Squiz.Commenting.FunctionComment.MissingParamTag
