<?php
/**
 * Derived odometer index maintained inside authoritative write transactions.
 *
 * @package MF\VeLog\Common\Storage
 */

namespace MF\VeLog\Common\Storage;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\Service\ServiceChronology;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private projections accelerate reminder filtering, never replace history. */
final class CurrentOdometerIndex {

	/**
	 * Update an already initialized index after a service write.
	 *
	 * @param WriteUnit            $unit     Pinned unit.
	 * @param int                  $id       Service identity.
	 * @param array<string, mixed> $envelope Persisted candidate.
	 */
	public static function service_changed( WriteUnit $unit, int $id, array $envelope ): bool|\WP_Error {
		unset( $id );
		return 'finalized' === $envelope['state'] ? self::refresh_existing( $unit, $envelope['fields']['vehicle_id'] ) : true;
	}

	/**
	 * Keep baseline/state changes coherent with reminder queries.
	 *
	 * @param WriteUnit            $unit     Pinned unit.
	 * @param int                  $id       Vehicle identity.
	 * @param array<string, mixed> $envelope Persisted candidate.
	 */
	public static function vehicle_changed( WriteUnit $unit, int $id, array $envelope ): bool|\WP_Error {
		unset( $envelope );
		return self::refresh_existing( $unit, $id );
	}

	/**
	 * Do not backfill unrelated historical vehicles as a side effect.
	 *
	 * @param WriteUnit $unit Pinned unit.
	 * @param int       $id   Vehicle identity.
	 */
	private static function refresh_existing( WriteUnit $unit, int $id ): bool|\WP_Error {
		$prefix = $unit->get_prefix();
		$rows   = WriteCoordinator::query_direct(
			$unit->get_dbh(),
			"SELECT meta_id FROM `{$prefix}postmeta` WHERE post_id={$id} AND meta_key='_mf_velog_current_mm'"
		);
		return false === $rows ? new \WP_Error( 'storage_unavailable' ) : ( empty( $rows ) ? true : self::refresh( $unit, $id ) );
	}

	/**
	 * Initialize/refresh exact current projections; errors roll back caller writes.
	 *
	 * @param WriteUnit $unit       Pinned unit.
	 * @param int       $vehicle_id Vehicle identity.
	 */
	public static function refresh( WriteUnit $unit, int $vehicle_id ): bool|\WP_Error {
		$current = ServiceChronology::current( $unit, $vehicle_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$values = array(
			'_mf_velog_current_mm'         => $current['reading']['canonical_mm'] ?? '',
			'_mf_velog_current_date'       => $current['date'] ?? '',
			'_mf_velog_current_service_id' => (string) ( $current['service_id'] ?? 0 ),
		);
		$dbh    = $unit->get_dbh();
		$prefix = $unit->get_prefix();
		foreach ( $values as $key => $value ) {
			$rows = WriteCoordinator::query_direct(
				$dbh,
				"SELECT meta_id, meta_value FROM `{$prefix}postmeta` WHERE post_id={$vehicle_id} AND meta_key='{$key}'"
			);
			if ( false === $rows || count( $rows ) > 1 ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			// phpcs:ignore WordPress.DB.RestrictedFunctions -- Exact escaping on the existing pinned handle.
			$escaped = mysqli_real_escape_string( $dbh, $value );
			$sql     = empty( $rows )
				? "INSERT INTO `{$prefix}postmeta` (post_id, meta_key, meta_value) VALUES ({$vehicle_id}, '{$key}', '{$escaped}')"
				: "UPDATE `{$prefix}postmeta` SET meta_value='{$escaped}' WHERE meta_id=" . (int) $rows[0]['meta_id'];
			if ( ! WriteCoordinator::exec_direct( $dbh, $sql ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$check = WriteCoordinator::query_direct(
				$dbh,
				"SELECT meta_value FROM `{$prefix}postmeta` WHERE post_id={$vehicle_id} AND meta_key='{$key}'"
			);
			if ( false === $check || 1 !== count( $check ) || $check[0]['meta_value'] !== $value ) {
				return new \WP_Error( 'storage_unavailable' );
			}
		}
		$unit->touch( $vehicle_id );
		return true;
	}
}
