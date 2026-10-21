<?php
/**
 * Bounded, contact-free finalized history without per-row relation lookups.
 *
 * @package MF\VeLog\Common\Service
 */

namespace MF\VeLog\Common\Service;

// phpcs:disable Generic.Files.LineLength.TooLong -- Keep native HTML, translation messages and SQL contracts intact.

use MF\VeLog\Common\AccessPolicy;
use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Storage\RecordRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authoritative snapshots are filtered before pagination; no read-side writes. */
final class ServiceHistoryQuery {

	/**
	 * Read finalized history and creator/audit labels for one vehicle.
	 *
	 * @param int                  $vehicle_id Private vehicle identity.
	 * @param array<string, mixed> $filters    Closed date/type/text/page filters.
	 * @param \WP_User             $actor      Trusted staff actor.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function search( int $vehicle_id, array $filters, \WP_User $actor ): array|\WP_Error {
		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden' );
		}
		if ( array_diff( array_keys( $filters ), array( 'from', 'to', 'type', 'term', 'page' ) ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		$f = array_merge(
			array(
				'from' => '',
				'to'   => '',
				'type' => 0,
				'term' => '',
				'page' => 1,
			),
			$filters
		);
		if ( ! is_int( $f['type'] ) || $f['type'] < 0 || ! is_int( $f['page'] ) || $f['page'] < 1 || $f['page'] > 1000000
			|| ! is_string( $f['from'] ) || ! is_string( $f['to'] ) || ! is_string( $f['term'] )
			|| null === ServiceFields::text( $f['term'], 100 ) ) {
			return new \WP_Error( 'invalid_input' );
		}
		try {
			foreach ( array( 'from', 'to' ) as $key ) {
				if ( '' !== $f[ $key ] ) {
					CalendarDate::parse( $f[ $key ] );
				}
			}
		} catch ( \InvalidArgumentException $exception ) {
			return new \WP_Error( 'invalid_input' );
		}
		if ( '' !== $f['from'] && '' !== $f['to'] && $f['from'] > $f['to'] ) {
			return new \WP_Error( 'invalid_input' );
		}
		$vehicle = $vehicle_id > 0 ? RecordRepository::get( 'mf_velog_vehicle', $vehicle_id, $actor ) : null;
		if ( ! is_array( $vehicle ) ) {
			return new \WP_Error( 'invalid_vehicle' );
		}
		global $wpdb;
		// Corrupt private rows must not disappear silently behind inner joins.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$invalid = $wpdb->get_var(
			"SELECT p.ID FROM {$wpdb->posts} p
			WHERE p.post_type='mf_velog_service' AND p.post_status='private'
			AND ((SELECT COUNT(*) FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_mf_velog_record')<>1
			OR (SELECT COUNT(*) FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_mf_velog_state')<>1
			OR (SELECT COUNT(*) FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_mf_velog_service_vehicle_id')<>1)
			LIMIT 1"
		);
		if ( ! empty( $wpdb->last_error ) || null !== $invalid ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		// Filter and sort small projection rows before loading potentially large audit envelopes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, s.meta_value AS record_state
				FROM {$wpdb->postmeta} v FORCE INDEX (meta_key)
				STRAIGHT_JOIN {$wpdb->posts} p ON p.ID=v.post_id
				STRAIGHT_JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_mf_velog_state'
				WHERE v.meta_key='_mf_velog_service_vehicle_id' AND v.meta_value=%s
				AND p.post_type='mf_velog_service' AND p.post_status='private' AND s.meta_value='finalized'
				ORDER BY p.ID DESC LIMIT 10001",
				(string) $vehicle_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) || count( $rows ) > 10000 ) {
			return new \WP_Error( 'storage_unavailable' );
		}
		$envelopes = array();
		if ( ! empty( $rows ) ) {
			$id_list = implode( ',', array_map( static fn ( array $row ): int => (int) $row['ID'], $rows ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bounded validated integer IDs.
			$stored = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$id_list}) AND meta_key='_mf_velog_record'", ARRAY_A );
			if ( ! is_array( $stored ) || ! empty( $wpdb->last_error ) || count( $stored ) !== count( $rows ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			foreach ( $stored as $row ) {
				$id = (int) $row['post_id'];
				if ( isset( $envelopes[ $id ] ) ) {
					return new \WP_Error( 'storage_unavailable' );
				}
				$envelopes[ $id ] = $row['meta_value'];
			}
		}
		$items        = array();
		$types        = array();
		$current_rows = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['ID'];
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
			$record = @unserialize( $envelopes[ $id ] ?? '', array( 'allowed_classes' => false ) );
			if ( isset( $items[ $id ] ) || ! is_array( $record ) || 1 !== ( $record['schema_version'] ?? null )
				|| 'finalized' !== ( $record['state'] ?? '' ) || ! is_array( $record['fields'] ?? null )
				|| ! ServiceService::validate_record( $record['fields'] ) || $record['fields']['vehicle_id'] !== $vehicle_id
				|| ( $record['created_by'] ?? null ) !== (int) $row['post_author'] || ! is_array( $record['audit'] ?? null )
				|| ! is_int( $record['record_version'] ?? null ) || $record['record_version'] < 1
				|| count( $record['audit'] ) !== $record['record_version']
				|| ( $record['fields']['type_snapshot']['id'] ?? 0 ) !== $record['fields']['service_type_id']
				|| empty( $record['fields']['reading'] ) || empty( $record['fields']['service_date'] )
				|| empty( $record['fields']['customer_snapshot'] ) || empty( $record['fields']['type_snapshot'] ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$context = array(
				'type'            => 'mf_velog_service',
				'state'           => 'finalized',
				'author'          => $record['created_by'],
				'vehicle_visible' => true,
			);
			if ( ! AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context ) ) {
				return new \WP_Error( 'forbidden' );
			}
			$record['id']                        = $id;
			$items[ $id ]                        = $record;
			$fields                              = $record['fields'];
			$types[ $fields['service_type_id'] ] = $fields['type_snapshot']['name'];
			$current_rows[]                      = array(
				'service_id' => $id,
				'date'       => $fields['service_date'],
				'reading'    => $fields['reading'],
			);
		}
		$baseline = $vehicle['fields']['baseline'] ?? array();
		if ( ! empty( $baseline ) ) {
			$current_rows[] = array(
				'service_id' => 0,
				'date'       => $baseline['date'],
				'reading'    => $baseline,
			);
		}
		$ordered  = ServiceChronology::ordered(
			array_map(
				static fn ( array $r ): array => array(
					'service_id' => $r['id'],
					'date'       => $r['fields']['service_date'],
					'record'     => $r,
				),
				array_values( $items )
			)
		);
		$filtered = array();
		foreach ( $ordered as $entry ) {
			$record = $entry['record'];
			$fields = $record['fields'];
			if ( ( '' !== $f['from'] && $fields['service_date'] < $f['from'] ) || ( '' !== $f['to']
				&& $fields['service_date'] > $f['to'] )
				|| ( $f['type'] > 0 && $fields['service_type_id'] !== $f['type'] ) ) {
				continue;
			}
			$haystack = ( $fields['notes'] ?? '' ) . ' ' . $fields['type_snapshot']['name'];
			if ( '' !== $f['term'] && false === ( function_exists( 'mb_stripos' ) ? mb_stripos(
				$haystack,
				$f['term'],
				0,
				'UTF-8'
			) : stripos( $haystack, $f['term'] ) ) ) {
				continue;
			}
			$filtered[] = $record;
		}
		$page      = array_slice( $filtered, ( $f['page'] - 1 ) * 50, 50 );
		$actor_ids = array();
		foreach ( $page as &$record ) {
			$actor_ids[] = $record['created_by'];
			$audit       = self::audit_projection( $record['audit'] );
			if ( is_wp_error( $audit ) ) {
				return $audit;
			}
			$record['audit'] = $audit;
			foreach ( $audit as $entry ) {
				$actor_ids[] = $entry['actor_id'];
			}
		}
		unset( $record );
		$actor_ids = array_values( array_unique( array_map( 'intval', $actor_ids ) ) );
		$labels    = array();
		if ( ! empty( $actor_ids ) ) {
			$id_list = implode( ',', $actor_ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only validated integer actor IDs.
			$users = $wpdb->get_results( "SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ({$id_list})", ARRAY_A );
			if ( ! empty( $wpdb->last_error ) || ! is_array( $users ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			foreach ( $users as $user ) {
				$labels[ (int) $user['ID'] ] = $user['display_name'];
			}
		}
		return array(
			'items'   => $page,
			'total'   => count( $filtered ),
			'page'    => $f['page'],
			'filters' => $f,
			'types'   => $types,
			'actors'  => $labels,
			'vehicle' => $vehicle,
			'current' => ServiceChronology::ordered( $current_rows )[0] ?? array(),
		);
	}

	/**
	 * Expose only approved historical fields, never arbitrary audit/contact keys.
	 *
	 * @param array<mixed> $audit Stored audit.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function audit_projection( array $audit ): array|\WP_Error {
		$result = array();
		foreach ( $audit as $entry ) {
			if ( ! is_array( $entry ) || ! is_int( $entry['actor_id'] ?? null ) || $entry['actor_id'] < 1
				|| ! is_string( $entry['at_utc'] ?? null ) || ! is_string( $entry['reason'] ?? null )
				|| ! is_array( $entry['before'] ?? null ) || ! is_array( $entry['after'] ?? null ) ) {
				return new \WP_Error( 'storage_unavailable' );
			}
			$projected = array(
				'actor_id' => $entry['actor_id'],
				'at_utc'   => $entry['at_utc'],
				'reason'   => $entry['reason'],
			);
			foreach ( array( 'before', 'after' ) as $side ) {
				$allowed = array_intersect_key(
					$entry[ $side ],
					array_flip(
						array(
							'vehicle_id',
							'service_date',
							'reading',
							'service_type_id',
							'notes',
							'cost',
							'customer_snapshot',
							'type_snapshot',
							'__state',
						)
					)
				);
				$state   = $allowed['__state'] ?? '';
				unset( $allowed['__state'] );
				$validated = \MF\VeLog\Common\Storage\RecordSchema::validate_fields( 'mf_velog_service', $allowed );
				if ( is_wp_error( $validated ) ) {
					return new \WP_Error( 'storage_unavailable' );
				}
				$projected[ $side ] = array_merge( $validated, array( 'state' => is_string( $state ) ? $state : '' ) );
			}
			$result[] = $projected;
		}
		return $result;
	}
}
