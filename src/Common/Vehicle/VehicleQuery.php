<?php
/**
 * Bounded private vehicle reads and contact-free customer summaries.
 *
 * @package MF\VeLog\Common\Vehicle
 */

namespace MF\VeLog\Common\Vehicle;

use MF\VeLog\Common\Storage\RecordRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides authorized vehicle list and detail snapshots. */
final class VehicleQuery {

	/**
	 * Normalize bounded search criteria.
	 *
	 * @param array<string, mixed> $input Request criteria.
	 * @return array{term:string,state:string,page:int}|\WP_Error
	 */
	public static function normalize_args( array $input ): array|\WP_Error {
		if ( array_diff( array_keys( $input ), array( 'term', 'state', 'page' ) ) ) {
			return new \WP_Error( 'invalid_input', 'Unsupported vehicle query argument.' );
		}
		$term  = $input['term'] ?? '';
		$state = $input['state'] ?? 'active';
		$page  = $input['page'] ?? 1;
		if ( ! is_string( $term ) || strlen( $term ) > 400 || 0 !== preg_match( '/[\x00-\x1F\x7F<>]/u', $term )
			|| ( function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) : strlen( $term ) ) > 100
			|| ! is_string( $state ) || ! in_array( $state, array( '', 'active', 'archived' ), true )
			|| ! is_int( $page ) || $page < 1 || $page > 1000000 ) {
			return new \WP_Error( 'invalid_input', 'Invalid vehicle query criteria.' );
		}
		return array(
			'term'  => trim( $term ),
			'state' => $state,
			'page'  => $page,
		);
	}

	/**
	 * Query up to fifty operational vehicles, with no contact search.
	 *
	 * @param array<string, mixed> $input Request criteria.
	 * @param \WP_User             $actor Trusted staff actor.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function search( array $input, \WP_User $actor ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden', 'Vehicle reads require staff access.' );
		}
		$args = self::normalize_args( $input );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $args['term'] ) . '%';
		// Only trusted WordPress table identifiers are interpolated; all request values are prepared.
		$from   = " FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID
			AND s.meta_key = '_mf_velog_state' WHERE p.post_type = 'mf_velog_vehicle'
			AND p.post_status = 'private' AND (%s = '' OR s.meta_value = %s)
			AND (%s = '' OR EXISTS (SELECT m.meta_id FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID
			AND m.meta_key IN ('_mf_velog_vehicle_plate', '_mf_velog_vehicle_jurisdiction', '_mf_velog_vehicle_vin')
			AND m.meta_value LIKE %s))";
		$params = array( $args['state'], $args['state'], $args['term'], $like );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery
		$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT p.ID)' . $from, ...$params ) );
		if ( ! empty( $wpdb->last_error ) || null === $total ) {
			return new \WP_Error( 'storage_unavailable', 'Vehicle count failed.' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted query template with prepared request values.
				'SELECT DISTINCT p.ID' . $from . ' ORDER BY p.ID ASC LIMIT 50 OFFSET %d',
				...array_merge( $params, array( ( $args['page'] - 1 ) * 50 ) )
			)
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Vehicle page failed.' );
		}
		$items = RecordRepository::get_many( 'mf_velog_vehicle', array_map( 'intval', $ids ), $actor );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$items = $this->with_customer_summaries( $items, $actor );
		return is_wp_error( $items ) ? $items : array(
			'items' => $items,
			'total' => (int) $total,
			'page'  => $args['page'],
		);
	}

	/**
	 * Read one vehicle with a safe relation summary.
	 *
	 * @param int      $id    Vehicle ID.
	 * @param \WP_User $actor Trusted staff actor.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( int $id, \WP_User $actor ): array|\WP_Error {
		if ( $id < 1 || ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden', 'Vehicle detail is unavailable.' );
		}
		$item = RecordRepository::get( 'mf_velog_vehicle', $id, $actor );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		$items = $this->with_customer_summaries( array( $item ), $actor );
		return is_wp_error( $items ) ? $items : $items[0];
	}

	/**
	 * Batch hydrate names only; never return customer contacts or audit details.
	 *
	 * @param array<int, array<string, mixed>> $items Vehicle snapshots.
	 * @param \WP_User                         $actor Trusted staff actor.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function with_customer_summaries( array $items, \WP_User $actor ): array|\WP_Error {
		$ids = array();
		foreach ( $items as $item ) {
			$id = (int) ( $item['fields']['current_customer_id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$customers = RecordRepository::get_many( 'mf_velog_customer', array_values( array_unique( $ids ) ), $actor );
		if ( is_wp_error( $customers ) ) {
			return $customers;
		}
		$summaries = array();
		foreach ( $customers as $customer ) {
			$summaries[ $customer['id'] ] = array(
				'id'    => $customer['id'],
				'name'  => $customer['fields']['name'] ?? '',
				'state' => $customer['state'],
			);
		}
		foreach ( $items as &$item ) {
			$item['customer'] = $summaries[ $item['fields']['current_customer_id'] ?? 0 ] ?? null;
			if ( ! $actor->has_cap( 'mf_velog_manage_vehicles' ) ) {
				unset( $item['audit'] );
			}
		}
		unset( $item );
		return $items;
	}
}
