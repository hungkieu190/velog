<?php
/**
 * Bounded private customer search.
 *
 * @package MF\VeLog\Common\Customer
 */

namespace MF\VeLog\Common\Customer;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong

use MF\VeLog\Common\Storage\RecordRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Executes allowlisted customer list queries. */
final class CustomerQuery {

	/**
	 * Query customers for an authorized manager.
	 *
	 * @param array<string, mixed> $input Query input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function search( array $input, \WP_User $actor ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_manage_customers' ) || ! $actor->has_cap( 'mf_velog_read_customer_contacts' ) ) {
			return new \WP_Error( 'forbidden', 'Customer search requires manager access.' );
		}
		$args = self::normalize_args( $input );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		global $wpdb;
		$offset    = ( $args['page'] - 1 ) * 50;
		$order_key = 'id' === $args['sort'] ? 'p.ID' : 'name_meta.meta_value';
		$direction = 'desc' === $args['direction'] ? 'DESC' : 'ASC';
		$like      = '%' . $wpdb->esc_like( $args['term'] ) . '%';
		$sql_from  = " FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} state_meta ON state_meta.post_id = p.ID AND state_meta.meta_key = '_mf_velog_state' INNER JOIN {$wpdb->postmeta} name_meta ON name_meta.post_id = p.ID AND name_meta.meta_key = '_mf_velog_customer_name' LEFT JOIN {$wpdb->postmeta} phone_meta ON phone_meta.post_id = p.ID AND phone_meta.meta_key = '_mf_velog_customer_phone' LEFT JOIN {$wpdb->postmeta} email_meta ON email_meta.post_id = p.ID AND email_meta.meta_key = '_mf_velog_customer_email' WHERE p.post_type = 'mf_velog_customer' AND p.post_status = 'private' AND (%s = '' OR state_meta.meta_value = %s) AND (%s = '' OR name_meta.meta_value LIKE %s OR phone_meta.meta_value LIKE %s OR email_meta.meta_value LIKE %s)";
		$params    = array( $args['state'], $args['state'], $args['term'], $like, $like, $like );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT p.ID)' . $sql_from, ...$params ) );
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Customer count query failed.' );
		}
		$page_sql = 'SELECT DISTINCT p.ID' . $sql_from . " ORDER BY {$order_key} {$direction}, p.ID {$direction} LIMIT 50 OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( $page_sql, ...array_merge( $params, array( $offset ) ) ) );
		if ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Customer page query failed.' );
		}
		$items = array();
		foreach ( $ids as $id ) {
			$item = RecordRepository::get( 'mf_velog_customer', (int) $id, $actor );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$items[] = $item;
		}
		return array(
			'items' => $items,
			'total' => (int) $total,
			'page'  => $args['page'],
		);
	}

	/**
	 * Normalize and strictly allowlist list request arguments.
	 *
	 * @param array<string, mixed> $input Query input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function normalize_args( array $input ): array|\WP_Error {
		foreach ( array_keys( $input ) as $key ) {
			if ( ! in_array( $key, array( 'term', 'state', 'sort', 'direction', 'page' ), true ) ) {
				return new \WP_Error( 'invalid_input', 'Unsupported customer query argument.' );
			}
		}
		$term = $input['term'] ?? '';
		if ( ! is_string( $term ) || strlen( $term ) > 400 || preg_match( '/[\x00-\x1F\x7F<>]/u', $term ) ) {
			return new \WP_Error( 'invalid_input', 'Invalid customer search term.' );
		}
		$term = trim( $term );
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $term, 'UTF-8' ) : strlen( $term ) ) > 100 ) {
			return new \WP_Error( 'invalid_input', 'Customer search term is too long.' );
		}
		$state     = $input['state'] ?? '';
		$sort      = $input['sort'] ?? 'name';
		$direction = $input['direction'] ?? 'asc';
		$page      = $input['page'] ?? 1;
		if ( ! is_string( $state ) || ! in_array( $state, array( '', 'active', 'archived' ), true ) || ! is_string( $sort ) || ! in_array( $sort, array( 'name', 'id' ), true ) || ! is_string( $direction ) || ! in_array( $direction, array( 'asc', 'desc' ), true ) || ! is_int( $page ) || $page < 1 ) {
			return new \WP_Error( 'invalid_input', 'Invalid customer query arguments.' );
		}
		return compact( 'term', 'state', 'sort', 'direction', 'page' );
	}
}

// phpcs:enable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong
