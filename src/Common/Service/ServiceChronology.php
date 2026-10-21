<?php
/**
 * Exact chronological service readings on the existing pinned write boundary.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

// phpcs:disable Generic.Files.LineLength.TooLong -- Closed tuples and pinned SQL contracts are kept together for review.

use MF\VeLog\Common\Regional\DecimalMath;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authoritative chronology; reminder projections derive from this read model. */
final class ServiceChronology {

	/**
	 * Read validated finalized services for one vehicle, excluding a correction.
	 *
	 * @param WriteUnit $unit       Active serialized transaction.
	 * @param int       $vehicle_id Vehicle identity.
	 * @param int       $exclude    Candidate service, zero for baseline.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function history( WriteUnit $unit, int $vehicle_id, int $exclude = 0 ): array|\WP_Error {
		$prefix = $unit->get_prefix();
		// Never silently omit private services whose authoritative/projection rows disappeared.
		$invalid = WriteCoordinator::query_direct(
			$unit->get_dbh(),
			"SELECT p.ID FROM `{$prefix}posts` p WHERE p.post_type = 'mf_velog_service' AND p.post_status = 'private'
			AND ((SELECT COUNT(*) FROM `{$prefix}postmeta` m WHERE m.post_id = p.ID AND m.meta_key = '_mf_velog_state') <> 1
			OR (SELECT COUNT(*) FROM `{$prefix}postmeta` m WHERE m.post_id = p.ID AND m.meta_key = '_mf_velog_record') <> 1
			OR (SELECT COUNT(*) FROM `{$prefix}postmeta` m WHERE m.post_id = p.ID AND m.meta_key = '_mf_velog_vehicle_visible') <> 1
			OR (SELECT COUNT(*) FROM `{$prefix}postmeta` m WHERE m.post_id = p.ID AND m.meta_key = '_mf_velog_service_vehicle_id') <> 1) LIMIT 1"
		);
		if ( false === $invalid || ! empty( $invalid ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}

		$rows = WriteCoordinator::query_direct(
			$unit->get_dbh(),
			"SELECT p.ID FROM `{$prefix}posts` p INNER JOIN `{$prefix}postmeta` v ON v.post_id = p.ID
			AND v.meta_key = '_mf_velog_service_vehicle_id' AND v.meta_value = '{$vehicle_id}'
			INNER JOIN `{$prefix}postmeta` s ON s.post_id = p.ID AND s.meta_key = '_mf_velog_state'
			AND s.meta_value = 'finalized' WHERE p.post_type = 'mf_velog_service' AND p.post_status = 'private'
			ORDER BY p.ID ASC LIMIT 10001"
		);
		if ( false === $rows || count( $rows ) > 10000 ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$items = array();
		$seen  = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['ID'];
			if ( isset( $seen[ $id ] ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$seen[ $id ] = true;
			$record      = $unit->get( 'mf_velog_service', $id );
			if ( is_wp_error( $record ) || 'finalized' !== $record['state'] || ( $record['fields']['vehicle_id'] ?? null ) !== $vehicle_id
				|| empty( $record['fields']['reading'] ) || empty( $record['fields']['service_date'] ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			if ( $id !== $exclude ) {
				$items[] = array(
					'service_id' => $id,
					'date'       => $record['fields']['service_date'],
					'reading'    => $record['fields']['reading'],
				);
			}
		}
		return $items;
	}

	/**
	 * Sort service/date keys with baseline after same-day finalized services.
	 *
	 * @param array<int, array<string, mixed>> $items Reading rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ordered( array $items ): array {
		usort(
			$items,
			static function ( array $a, array $b ): int {
				$dates = strcmp( $b['date'], $a['date'] );
				return 0 !== $dates ? $dates : $b['service_id'] <=> $a['service_id'];
			}
		);
		return $items;
	}

	/**
	 * Validate affected reading against both neighbours, under one lock.
	 *
	 * @param WriteUnit            $unit       Active unit.
	 * @param int                  $vehicle_id Vehicle identity.
	 * @param int                  $service_id Candidate service, zero for baseline.
	 * @param array<string, mixed> $baseline   Candidate/retained vehicle baseline.
	 * @param string               $date       Candidate date.
	 * @param array<string, mixed> $reading    Candidate reading.
	 * @param string               $reason     Manager reason.
	 */
	public static function validate(
		WriteUnit $unit,
		int $vehicle_id,
		int $service_id,
		array $baseline,
		string $date,
		array $reading,
		string $reason
	): bool|\WP_Error {
		$items = self::history( $unit, $vehicle_id, $service_id );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		if ( $service_id > 0 && ! empty( $baseline ) ) {
			$items[] = array(
				'service_id' => 0,
				'date'       => $baseline['date'],
				'reading'    => $baseline,
			);
		}
		if ( empty( $reading ) ) {
			return true;
		}
		$items[] = array(
			'service_id' => $service_id,
			'date'       => $date,
			'reading'    => $reading,
		);
		$items   = self::ordered( $items );
		foreach ( $items as $index => $item ) {
			if ( $item['service_id'] !== $service_id ) {
				continue;
			}
			$newer = $items[ $index - 1 ]['reading']['canonical_mm'] ?? null;
			$older = $items[ $index + 1 ]['reading']['canonical_mm'] ?? null;
			$mm    = $reading['canonical_mm'];
			if ( ( null !== $newer && DecimalMath::compare( $mm, $newer ) > 0 )
				|| ( null !== $older && DecimalMath::compare( $mm, $older ) < 0 ) ) {
				return $unit->get_actor()->has_cap( 'mf_velog_correct_services' ) && '' !== trim( $reason )
					? true : new \WP_Error( 'chronology_conflict', 'A manager reason is required for an odometer chronology exception.' );
			}
			break;
		}
		return true;
	}

	/**
	 * Compute latest finalized reading or baseline consistently, without caching.
	 *
	 * @param WriteUnit $unit       Active unit.
	 * @param int       $vehicle_id Vehicle identity.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function current( WriteUnit $unit, int $vehicle_id ): array|\WP_Error {
		$vehicle = $unit->get( 'mf_velog_vehicle', $vehicle_id );
		$items   = self::history( $unit, $vehicle_id );
		if ( is_wp_error( $vehicle ) || is_wp_error( $items ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$baseline = $vehicle['fields']['baseline'] ?? array();
		if ( ! empty( $baseline ) ) {
			$items[] = array(
				'service_id' => 0,
				'date'       => $baseline['date'],
				'reading'    => $baseline,
			);
		}
		return self::ordered( $items )[0] ?? array();
	}
}
