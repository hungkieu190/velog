<?php
/**
 * Bounded service operational reads with authoritative draft privacy.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

// phpcs:disable Generic.Files.LineLength.TooLong -- Closed tuples and pinned SQL contracts are kept together for review.

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** List/detail/current reading share the accepted staff and creator boundaries. */
final class ServiceQuery {

	/**
	 * Read one authorized snapshot.
	 *
	 * @param int      $id    Service identity.
	 * @param \WP_User $actor Trusted reader.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( int $id, \WP_User $actor ): array|\WP_Error {
		return RecordRepository::get( 'mf_velog_service', $id, $actor );
	}

	/**
	 * Query a bounded stable page; other technicians' drafts never enter results.
	 *
	 * @param \WP_User $actor      Trusted reader.
	 * @param string   $state      Optional draft/finalized filter.
	 * @param int      $vehicle_id Optional vehicle filter.
	 * @param int      $page       Positive page number.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function search( \WP_User $actor, string $state = '', int $vehicle_id = 0, int $page = 1 ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		if ( ! in_array( $state, array( '', 'draft', 'finalized' ), true ) || $vehicle_id < 0 || $page < 1 || $page > 1000000 ) {
			return new \WP_Error( 'invalid_input' );
		}
		global $wpdb;
		$manager = $actor->has_cap( 'mf_velog_correct_services' ) ? 1 : 0;
		$from    = " FROM {$wpdb->postmeta} s FORCE INDEX (meta_key)
			STRAIGHT_JOIN {$wpdb->posts} p ON p.ID = s.post_id
			WHERE s.meta_key = '_mf_velog_state' AND p.post_type = 'mf_velog_service' AND p.post_status = 'private'
			AND (%s = '' OR s.meta_value = %s) AND (%d = 1 OR s.meta_value = 'finalized' OR p.post_author = %d)
			AND (%d = 0 OR EXISTS (SELECT m.meta_id FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID
			AND m.meta_key = '_mf_velog_service_vehicle_id' AND m.meta_value = %s))";
		$params  = array( $state, $state, $manager, (int) $actor->ID, $vehicle_id, (string) $vehicle_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT p.ID)' . $from, ...$params ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT DISTINCT p.ID' . $from . ' ORDER BY p.ID DESC LIMIT 50 OFFSET %d',
				...array_merge( $params, array( ( $page - 1 ) * 50 ) )
			)
		);
		if ( null === $total || '' !== $wpdb->last_error || ! is_array( $ids ) ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$item = $this->get( (int) $id, $actor );
			if ( is_wp_error( $item ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$items[] = $item;
		}
		return array(
			'items' => $items,
			'total' => (int) $total,
			'page'  => $page,
		);
	}

	/**
	 * Resolve a consistent current reading from authoritative related records.
	 *
	 * @param int      $vehicle_id Vehicle identity.
	 * @param \WP_User $actor      Trusted reader.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function current( int $vehicle_id, \WP_User $actor ): array|\WP_Error {
		if ( $vehicle_id < 1 || ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		return WriteCoordinator::run( $actor, static fn ( WriteUnit $unit ): array|\WP_Error => ServiceChronology::current( $unit, $vehicle_id ) );
	}
}
