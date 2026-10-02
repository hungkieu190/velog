<?php
/**
 * RecordRepository — versioned, authoritative record persistence.
 *
 * Satisfies contract §Public Common API, §Storage map, §Transaction and cache
 * sequence, and §Retention and benchmark (V1–V5).
 *
 * Public surface:
 *   get(type, id, actor)                                         → array|WP_Error
 *   query(type, filters, page, actor)                           → array|WP_Error
 *   create(type, validated_fields, actor, request_id)           → array|WP_Error
 *   save(type, id, expected_version, changes, actor, request_id, reason?) → array|WP_Error
 *
 * Invariants (enforced here):
 *  - Registry must be sealed before any operation.
 *  - Actor must hold the type's required CORE-003 capability.
 *  - Reads bypass WordPress object cache (direct SQL on $wpdb).
 *  - Writes go through WriteCoordinator; no $wpdb helpers inside transactions.
 *  - Exact one _mf_velog_record meta row per post; no duplicate authoritative rows.
 *  - Serialization: maybe_serialize/maybe_unserialize; PHP objects in stored data
 *    are rejected on decode.
 *  - create() stores idempotency option in same transaction; repeated identical
 *    request_id returns existing result; differing payload fails with 'conflict'.
 *  - save() stores request_id hash in audit; on indeterminate, caller must reconcile.
 *  - No hard delete, no automatic purge.
 *
 * @package MF\VeLog\Common\Storage
 */

namespace MF\VeLog\Common\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed, versioned repository for VeLog private records.
 *
 * @since 0.2.0
 */
final class RecordRepository {

	/**
	 * WP post status used for all VeLog records.
	 *
	 * @var string
	 */
	private const POST_STATUS = 'private';

	/**
	 * Schema version stored inside the envelope.
	 * Increment only when the envelope structure changes.
	 *
	 * @var int
	 */
	private const ENVELOPE_SCHEMA_VERSION = 1;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Retrieves a single record by ID.
	 *
	 * Bypasses WordPress object cache. Returns a sanitised snapshot array on
	 * success; WP_Error on authorization, not-found, or data error.
	 *
	 * @param string   $type  Registered CPT slug.
	 * @param int      $id    WordPress post ID.
	 * @param \WP_User $actor Authenticated actor.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get( string $type, int $id, \WP_User $actor ): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		$definition = RecordSchema::get( $type );

		// Read post row directly (bypass cache).
		$post = self::read_post_direct( $id, $type );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Decode envelope.
		$envelope = self::decode_envelope( $id );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		// Authorization.
		$auth = self::authorize_read( $actor, $type, $definition, $envelope );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		return self::build_snapshot( $id, $post, $envelope, $actor, $type );
	}

	/**
	 * Queries records of a type with allowlisted filters.
	 *
	 * Max 50 per page; stable ID tie-break; no SELECT *; no per-row lookup.
	 *
	 * @param string               $type    Registered CPT slug.
	 * @param array<string, mixed> $filters Allowlisted filter map.
	 * @param int                  $page    1-indexed page number.
	 * @param \WP_User             $actor   Authenticated actor.
	 * @return array<string, mixed>|\WP_Error Result map or WP_Error.
	 */
	public static function query( string $type, array $filters, int $page, \WP_User $actor ): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		$definition = RecordSchema::get( $type );

		// Require read capability.
		$read_cap = $definition['read_capability'] ?? 'mf_velog_read_records';
		if ( ! $actor->has_cap( $read_cap ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to query this record type.' );
		}

		global $wpdb;

		if ( $page < 1 ) {
			return new \WP_Error( 'invalid_input', 'Page must be >= 1.' );
		}

		// Allowlisted filter: only 'state' is supported in DATA-001.
		foreach ( array_keys( $filters ) as $filter_key ) {
			if ( 'state' !== $filter_key ) {
				return new \WP_Error( 'invalid_input', 'Unsupported query filter.' );
			}
		}

		if ( ! $actor->has_cap( 'mf_velog_read_records' ) ) {
			return new \WP_Error( 'forbidden', 'Actor lacks read capability.' );
		}

		$state_val = '';
		if ( isset( $filters['state'] ) ) {
			if ( ! is_string( $filters['state'] ) || ! in_array( $filters['state'], $definition['states'], true ) ) {
				return new \WP_Error( 'invalid_input', 'Invalid state filter value.' );
			}
			$state_val = (string) $filters['state'];
		}

		// Single shared prepared SQL predicate for both count and page.
		if ( 'mf_velog_service' === $type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count_sql = $wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_state
				   ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
				 INNER JOIN {$wpdb->postmeta} pm_vis
				   ON pm_vis.post_id = p.ID
				   AND pm_vis.meta_key = '_mf_velog_vehicle_visible'
				   AND pm_vis.meta_value = '1'
				 WHERE p.post_type = 'mf_velog_service'
				   AND p.post_status = %s
				   AND p.post_author > 0
				   AND pm_state.meta_value IN ('draft', 'finalized')
				   AND (%s = '' OR pm_state.meta_value = %s)",
				self::POST_STATUS,
				$state_val,
				$state_val
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$count_sql = $wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_state
				   ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (%s = '' OR pm_state.meta_value = %s)",
				$type,
				self::POST_STATUS,
				$state_val,
				$state_val
			);
		}

		// D1-F-020: Distinguish SQL failure from genuinely zero count.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_raw = $wpdb->get_var( $count_sql );
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Count query failed due to database error.' );
		}
		$total = (int) $total_raw;

		$per_page = RecordSchema::MAX_PAGE_SIZE;
		$offset   = ( $page - 1 ) * $per_page;

		if ( 'mf_velog_service' === $type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$page_sql = $wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date_gmt, p.post_modified_gmt FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_state
				   ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
				 INNER JOIN {$wpdb->postmeta} pm_vis
				   ON pm_vis.post_id = p.ID
				   AND pm_vis.meta_key = '_mf_velog_vehicle_visible'
				   AND pm_vis.meta_value = '1'
				 WHERE p.post_type = 'mf_velog_service'
				   AND p.post_status = %s
				   AND p.post_author > 0
				   AND pm_state.meta_value IN ('draft', 'finalized')
				   AND (%s = '' OR pm_state.meta_value = %s)
				 GROUP BY p.ID
				 ORDER BY p.ID ASC
				 LIMIT %d OFFSET %d",
				self::POST_STATUS,
				$state_val,
				$state_val,
				$per_page,
				$offset
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$page_sql = $wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date_gmt, p.post_modified_gmt FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_state
				   ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (%s = '' OR pm_state.meta_value = %s)
				 ORDER BY p.ID ASC
				 LIMIT %d OFFSET %d",
				$type,
				self::POST_STATUS,
				$state_val,
				$state_val,
				$per_page,
				$offset
			);
		}

		// D1-F-020: get_results() returns empty array on SQL failure; distinguish using last_error.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_rows = $wpdb->get_results( $page_sql, ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Page query failed due to database error.' );
		}

		if ( ! is_array( $page_rows ) || empty( $page_rows ) ) {
			return array(
				'items' => array(),
				'total' => $total,
				'page'  => $page,
			);
		}

		$ids   = array_map( 'intval', wp_list_pluck( $page_rows, 'ID' ) );
		$id_in = implode( ',', $ids );

		// D1-F-020: Preflight duplicate check — treat SQL failure as storage_unavailable,
		// not as "no duplicates". get_results() returns empty array on error; check last_error.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$dup_rows = $wpdb->get_results(
			"SELECT post_id, meta_key, COUNT(*) as cnt
			 FROM {$wpdb->postmeta}
			 WHERE post_id IN ({$id_in})
			   AND meta_key IN ('_mf_velog_state', '_mf_velog_vehicle_visible', '_mf_velog_record')
			 GROUP BY post_id, meta_key
			 HAVING cnt > 1",
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Duplicate preflight query failed due to database error.' );
		}
		if ( ! empty( $dup_rows ) ) {
			return new \WP_Error( 'storage_unavailable', 'Duplicate projection row detected on query page.' );
		}

		// D1-F-020: Fetch all relevant metadata for page IDs in a single batch.
		// Treat SQL failure as storage_unavailable, not as empty meta. Check last_error.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$meta_batch = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE post_id IN ({$id_in})
			   AND meta_key IN ('_mf_velog_record', '_mf_velog_state', '_mf_velog_vehicle_visible')",
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'storage_unavailable', 'Batch meta query failed due to database error.' );
		}
		$pm_by_id = array();
		if ( is_array( $meta_batch ) ) {
			foreach ( $meta_batch as $mb ) {
				$pm_by_id[ (int) $mb['post_id'] ][ $mb['meta_key'] ][] = $mb['meta_value'];
			}
		}

		$items = array();
		foreach ( $page_rows as $prow ) {
			$pid = (int) $prow['ID'];

			// Preflight check: must have exactly 1 record envelope and 1 state projection.
			if (
				! isset( $pm_by_id[ $pid ][ RecordSchema::META_KEY ] )
				|| 1 !== count( $pm_by_id[ $pid ][ RecordSchema::META_KEY ] )
			) {
				return new \WP_Error( 'storage_unavailable', 'Envelope missing or duplicated on query page.' );
			}
			if (
				! isset( $pm_by_id[ $pid ]['_mf_velog_state'] )
				|| 1 !== count( $pm_by_id[ $pid ]['_mf_velog_state'] )
			) {
				return new \WP_Error( 'storage_unavailable', 'State projection missing or duplicated on query page.' );
			}
			if ( 'mf_velog_service' === $type ) {
				if (
					! isset( $pm_by_id[ $pid ]['_mf_velog_vehicle_visible'] )
					|| 1 !== count( $pm_by_id[ $pid ]['_mf_velog_vehicle_visible'] )
				) {
					return new \WP_Error(
						'storage_unavailable',
						'Service visibility projection missing or duplicated.'
					);
				}
			}

			$envelope = self::safe_unserialize( $pm_by_id[ $pid ][ RecordSchema::META_KEY ][0] );
			if ( ! is_array( $envelope ) ) {
				return new \WP_Error( 'storage_unavailable', 'Corrupted envelope payload on query page.' );
			}

			// Invariant: p.post_author equals created_by.
			$created_by = (int) ( $envelope['created_by'] ?? 0 );
			if ( (int) $prow['post_author'] !== $created_by ) {
				return new \WP_Error( 'storage_unavailable', 'Envelope author contradicts post row.' );
			}

			// Invariant: projection state matches envelope state.
			$envelope_state = (string) ( $envelope['state'] ?? '' );
			if ( (string) $pm_by_id[ $pid ]['_mf_velog_state'][0] !== $envelope_state ) {
				return new \WP_Error( 'storage_unavailable', 'Envelope state contradicts state projection.' );
			}

			// Build context and verify object-level policy.
			$context = RecordSchema::build_context( $type, $envelope, $actor );

			if ( 'mf_velog_service' === $type ) {
				if ( (int) $prow['post_author'] <= 0 ) {
					return new \WP_Error( 'storage_unavailable', 'Service record author must be > 0.' );
				}
				$expected_vis = ( ! empty( $context['vehicle_visible'] ) ) ? '1' : '0';
				if ( (string) $pm_by_id[ $pid ]['_mf_velog_vehicle_visible'][0] !== $expected_vis ) {
					return new \WP_Error( 'storage_unavailable', 'Service visibility contradicts projection.' );
				}
			}

			$auth = self::authorize_read( $actor, $type, $definition, $envelope );
			if ( is_wp_error( $auth ) ) {
				return new \WP_Error( 'storage_unavailable', 'Envelope state contradicts authorization.' );
			}

			$items[] = self::build_snapshot( $pid, $prow, $envelope, $actor, $type );
		}

		return array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
		);
	}

	/**
	 * Creates a new record under a serialized write lock.
	 *
	 * @param string               $type       Registered CPT slug.
	 * @param array<string, mixed> $fields     Domain field values (already validated by caller or schema).
	 * @param \WP_User             $actor      Authenticated actor.
	 * @param string               $request_id Caller-generated UUID for idempotency.
	 * @return array<string, mixed>|\WP_Error Committed snapshot or stable error code.
	 */
	public static function create( string $type, array $fields, \WP_User $actor, string $request_id ): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$definition = RecordSchema::get( $type );

		if ( ! $actor->has_cap( $definition['capability'] ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to create this record type.' );
		}

		$canonical = RecordSchema::validate_fields( $type, $fields );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		$env = WriteCoordinator::check_environment();
		if ( is_wp_error( $env ) ) {
			return $env;
		}

		// Run under write lock.
		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use ( $type, $canonical, $request_id ): array|\WP_Error {
				return $unit->create( $type, $canonical, $request_id );
			}
		);
	}

	/**
	 * Updates an existing record under a serialized write lock.
	 *
	 * @param string               $type             Registered CPT slug.
	 * @param int                  $id               WordPress post ID.
	 * @param int                  $expected_version Caller's last-known record_version.
	 * @param array<string, mixed> $changes          Field changes (validated by caller or schema).
	 * @param \WP_User             $actor            Authenticated actor.
	 * @param string               $request_id       Caller UUID (stored in audit; for reconciliation).
	 * @param string               $reason           Human-readable reason.
	 * @return array<string, mixed>|\WP_Error Committed snapshot or stable error code.
	 */
	public static function save(
		string $type,
		int $id,
		int $expected_version,
		array $changes,
		\WP_User $actor,
		string $request_id,
		string $reason = ''
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$definition = RecordSchema::get( $type );

		if ( ! $actor->has_cap( $definition['capability'] ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to save this record type.' );
		}

		$canonical_changes = RecordSchema::validate_fields( $type, $changes );
		if ( is_wp_error( $canonical_changes ) ) {
			return $canonical_changes;
		}

		$env = WriteCoordinator::check_environment();
		if ( is_wp_error( $env ) ) {
			return $env;
		}

		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use (
				$type,
				$id,
				$expected_version,
				$canonical_changes,
				$request_id,
				$reason
			): array|\WP_Error {
				return $unit->save( $type, $id, $expected_version, $canonical_changes, $request_id, $reason );
			}
		);
	}

	/**
	 * Executes a create operation on a pinned database handle within a write unit.
	 *
	 * @internal Called only by WriteUnit.
	 *
	 * @param \mysqli              $dbh        Pinned mysqli handle.
	 * @param string               $prefix     WordPress table prefix.
	 * @param string               $type       Registered CPT slug.
	 * @param array<string, mixed> $fields     Domain fields.
	 * @param \WP_User             $actor      Authenticated actor.
	 * @param string               $request_id Caller UUID for idempotency.
	 * @param WriteUnit            $unit       Active write unit.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute_create(
		\mysqli $dbh,
		string $prefix,
		string $type,
		array $fields,
		\WP_User $actor,
		string $request_id,
		WriteUnit $unit
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$definition = RecordSchema::get( $type );

		if ( ! $actor->has_cap( $definition['capability'] ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to create this record type.' );
		}

		$canonical = RecordSchema::validate_fields( $type, $fields );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		// Re-check idempotency: has this request_id been used?
		$idem_key    = 'mf_velog_create_' . hash( 'sha256', $request_id );
		$idem_result = self::check_idempotency_under_lock(
			$dbh,
			$prefix,
			$idem_key,
			$type,
			$canonical,
			$actor
		);
		if ( is_wp_error( $idem_result ) ) {
			return $idem_result;
		}
		if ( null !== $idem_result ) {
			return $idem_result;
		}

		// Check schema-defined uniqueness under lock.
		$uniq_check = self::check_unique_keys_under_lock( $dbh, $prefix, $type, $definition, $canonical );
		if ( is_wp_error( $uniq_check ) ) {
			return $uniq_check;
		}

		// Build audit entry.
		$audit = AuditEntry::for_create( $actor, $request_id, $canonical );
		$now   = $audit->get_at_utc();

		// Insert post row (direct SQL, pinned handle; no wp_insert_post).
		$post_id = self::insert_post_direct( $dbh, $prefix, $type, $actor, $now );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$unit->touch( $post_id );

		// Build envelope.
		$envelope = array(
			'schema_version' => self::ENVELOPE_SCHEMA_VERSION,
			'record_version' => 1,
			'state'          => self::initial_state( $definition ),
			'fields'         => $canonical,
			'created_by'     => (int) $actor->ID,
			'created_at_utc' => $now,
			'updated_by'     => (int) $actor->ID,
			'updated_at_utc' => $now,
			'audit'          => array( $audit->to_array() ),
		);

		// Insert meta row.
		$meta_result = self::insert_meta_direct( $dbh, $prefix, $post_id, RecordSchema::META_KEY, $envelope );
		if ( is_wp_error( $meta_result ) ) {
			return $meta_result;
		}

		// Write projections (schema-defined).
		$proj_result = self::write_projections_direct( $dbh, $prefix, $post_id, $type, $definition, $envelope );
		if ( is_wp_error( $proj_result ) ) {
			return $proj_result;
		}

		// Record idempotency marker in the same transaction.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$payload_fingerprint = hash( 'sha256', serialize( $canonical ) );
		$idem_store          = self::store_idempotency_marker(
			$dbh,
			$prefix,
			$idem_key,
			$type,
			$post_id,
			$payload_fingerprint
		);
		if ( is_wp_error( $idem_store ) ) {
			return $idem_store;
		}

		// Verify row counts and integrity before commit.
		$verify = self::verify_write( $dbh, $prefix, $post_id, $type, $definition, $envelope, $idem_key );
		if ( is_wp_error( $verify ) ) {
			return $verify;
		}

		return self::build_snapshot( $post_id, null, $envelope, $actor, $type );
	}

	/**
	 * Executes a save operation on a pinned database handle within a write unit.
	 *
	 * @internal Called only by WriteUnit.
	 *
	 * @param \mysqli              $dbh              Pinned mysqli handle.
	 * @param string               $prefix           WordPress table prefix.
	 * @param string               $type             Registered CPT slug.
	 * @param int                  $id               Post ID.
	 * @param int                  $expected_version Optimistic lock version.
	 * @param array<string, mixed> $changes          Fields to update.
	 * @param \WP_User             $actor            Authenticated actor.
	 * @param string               $request_id       Caller UUID.
	 * @param string               $reason           Audit reason.
	 * @param WriteUnit            $unit             Active write unit.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute_save(
		\mysqli $dbh,
		string $prefix,
		string $type,
		int $id,
		int $expected_version,
		array $changes,
		\WP_User $actor,
		string $request_id,
		string $reason,
		WriteUnit $unit
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$definition = RecordSchema::get( $type );

		if ( ! $actor->has_cap( $definition['capability'] ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to save this record type.' );
		}

		$canonical_changes = RecordSchema::validate_fields( $type, $changes );
		if ( is_wp_error( $canonical_changes ) ) {
			return $canonical_changes;
		}

		// Re-read envelope under lock.
		$envelope = self::decode_envelope_direct( $dbh, $prefix, $id );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		// Verify post type matches.
		$post = self::read_post_direct( $id, $type );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Stale version check.
		if ( (int) ( $envelope['record_version'] ?? 0 ) !== $expected_version ) {
			return new \WP_Error( 'stale_version', 'Record was modified by another writer.' );
		}

		// Authorization with envelope context.
		$auth = self::authorize_read( $actor, $type, $definition, $envelope );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$unit->touch( $id );

		$before_fields = $envelope['fields'] ?? array();
		$now           = gmdate( 'Y-m-d\TH:i:s\Z' );

		// Merge changes into existing fields.
		$new_fields = array_merge( $before_fields, $canonical_changes );

		// Check schema-defined uniqueness for updated fields under lock.
		$uniq_check = self::check_unique_keys_under_lock( $dbh, $prefix, $type, $definition, $new_fields, $id );
		if ( is_wp_error( $uniq_check ) ) {
			return $uniq_check;
		}

		// Build audit entry.
		$audit = AuditEntry::for_save( $actor, $request_id, $reason, $before_fields, $new_fields );

		// Build new envelope.
		$new_envelope = array(
			'schema_version' => $envelope['schema_version'],
			'record_version' => ( (int) $envelope['record_version'] ) + 1,
			'state'          => $envelope['state'],
			'fields'         => $new_fields,
			'created_by'     => $envelope['created_by'],
			'created_at_utc' => $envelope['created_at_utc'],
			'updated_by'     => (int) $actor->ID,
			'updated_at_utc' => $now,
			'audit'          => array_merge( $envelope['audit'] ?? array(), array( $audit->to_array() ) ),
		);

		// Update meta row.
		$meta_result = self::update_meta_direct( $dbh, $prefix, $id, RecordSchema::META_KEY, $new_envelope );
		if ( is_wp_error( $meta_result ) ) {
			return $meta_result;
		}

		// Update projections.
		$proj_result = self::write_projections_direct( $dbh, $prefix, $id, $type, $definition, $new_envelope );
		if ( is_wp_error( $proj_result ) ) {
			return $proj_result;
		}

		// Verify row counts and integrity before commit.
		$verify = self::verify_write( $dbh, $prefix, $id, $type, $definition, $new_envelope );
		if ( is_wp_error( $verify ) ) {
			return $verify;
		}

		return self::build_snapshot( $id, null, $new_envelope, $actor, $type );
	}

	/**
	 * Reads a record snapshot directly within a transaction on the pinned handle.
	 *
	 * @internal Called only by WriteUnit.
	 *
	 * @param \mysqli  $dbh    Pinned mysqli handle.
	 * @param string   $prefix WordPress table prefix.
	 * @param string   $type   Registered CPT slug.
	 * @param int      $id     Post ID.
	 * @param \WP_User $actor  Authenticated actor.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute_get(
		\mysqli $dbh,
		string $prefix,
		string $type,
		int $id,
		\WP_User $actor
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		$definition = RecordSchema::get( $type );
		$post       = self::read_post_direct( $id, $type );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$envelope = self::decode_envelope_direct( $dbh, $prefix, $id );
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		$auth = self::authorize_read( $actor, $type, $definition, $envelope );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		return self::build_snapshot( $id, $post, $envelope, $actor, $type );
	}

	// -------------------------------------------------------------------------
	// Internal helpers — NOT part of the public API.
	// -------------------------------------------------------------------------

	/**
	 * Asserts registry is sealed and type is registered.
	 *
	 * @param string $type CPT slug.
	 * @return true|\WP_Error
	 */
	private static function require_sealed_and_registered( string $type ): true|\WP_Error {
		if ( ! RecordSchema::is_sealed() ) {
			return new \WP_Error( 'storage_unavailable', 'RecordSchema registry is not yet sealed.' );
		}

		if ( ! RecordSchema::is_registered( $type ) ) {
			return new \WP_Error( 'not_found', sprintf( 'Unknown record type "%s".', $type ) );
		}

		return true;
	}

	/**
	 * Reads a post row by ID + type directly from the database, bypassing cache.
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Expected post type.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function read_post_direct( int $id, string $type ): array|\WP_Error {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_author, post_date_gmt, post_modified_gmt
				 FROM {$wpdb->posts}
				 WHERE ID = %d
				 AND post_type = %s
				 AND post_status = %s",
				$id,
				$type,
				self::POST_STATUS
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return new \WP_Error( 'not_found', sprintf( 'Record %d not found.', $id ) );
		}

		return $row;
	}

	/**
	 * Decodes the authoritative envelope for a post, bypassing cache.
	 *
	 * @param int $id Post ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function decode_envelope( int $id ): array|\WP_Error {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
				 WHERE post_id = %d AND meta_key = %s",
				$id,
				RecordSchema::META_KEY
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || 0 === count( $rows ) ) {
			return new \WP_Error( 'not_found', sprintf( 'No record envelope for post %d.', $id ) );
		}

		// Reject duplicate authoritative rows.
		if ( count( $rows ) > 1 ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Duplicate authoritative meta rows for post %d; failing closed.', $id )
			);
		}

		$envelope = self::safe_unserialize( $rows[0]['meta_value'] );
		if ( ! is_array( $envelope ) ) {
			return new \WP_Error( 'storage_unavailable', 'Malformed record envelope.' );
		}

		// Validate schema version.
		if ( (int) ( $envelope['schema_version'] ?? 0 ) !== self::ENVELOPE_SCHEMA_VERSION ) {
			return new \WP_Error( 'storage_unavailable', 'Unknown envelope schema version; failing closed.' );
		}

		return $envelope;
	}

	/**
	 * Decodes envelope on the pinned handle (inside a transaction).
	 *
	 * @param \mysqli $dbh    Pinned handle.
	 * @param string  $prefix Table prefix.
	 * @param int     $id     Post ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function decode_envelope_direct( \mysqli $dbh, string $prefix, int $id ): array|\WP_Error {
		$meta_key = mysqli_real_escape_string( $dbh, RecordSchema::META_KEY );
		$sql      = sprintf(
			"SELECT meta_id, meta_value FROM `%spostmeta`
			 WHERE post_id = %d AND meta_key = '%s'",
			mysqli_real_escape_string( $dbh, $prefix ),
			$id,
			$meta_key
		);

		$rows = WriteCoordinator::query_direct( $dbh, $sql );

		if ( false === $rows || 0 === count( $rows ) ) {
			return new \WP_Error( 'not_found', sprintf( 'No record envelope for post %d.', $id ) );
		}

		if ( count( $rows ) > 1 ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Duplicate authoritative meta rows for post %d; failing closed.', $id )
			);
		}

		$envelope = self::safe_unserialize( $rows[0]['meta_value'] );
		if ( ! is_array( $envelope ) ) {
			return new \WP_Error( 'storage_unavailable', 'Malformed record envelope.' );
		}

		if ( (int) ( $envelope['schema_version'] ?? 0 ) !== self::ENVELOPE_SCHEMA_VERSION ) {
			return new \WP_Error( 'storage_unavailable', 'Unknown envelope schema version; failing closed.' );
		}

		return $envelope;
	}

	/**
	 * Checks an idempotency key under the write lock.
	 *
	 * Returns null if key not found (proceed with create).
	 * Returns existing snapshot array if found with matching payload.
	 * Returns WP_Error('conflict') if found with different payload or type.
	 *
	 * @param \mysqli              $dbh       Pinned handle.
	 * @param string               $prefix    Table prefix.
	 * @param string               $idem_key  Option name.
	 * @param string               $type      CPT slug.
	 * @param array<string, mixed> $canonical Canonical fields (for fingerprint).
	 * @param \WP_User             $actor     Authenticated actor.
	 * @return array<string, mixed>|null|\WP_Error
	 */
	private static function check_idempotency_under_lock(
		\mysqli $dbh,
		string $prefix,
		string $idem_key,
		string $type,
		array $canonical,
		\WP_User $actor
	): array|null|\WP_Error {
		$ek  = mysqli_real_escape_string( $dbh, $idem_key );
		$sql = sprintf(
			"SELECT option_value FROM `%soptions` WHERE option_name = '%s' LIMIT 1",
			mysqli_real_escape_string( $dbh, $prefix ),
			$ek
		);

		$rows = WriteCoordinator::query_direct( $dbh, $sql );

		if ( false === $rows || 0 === count( $rows ) ) {
			return null; // Not found — proceed.
		}

		$stored = self::safe_unserialize( $rows[0]['option_value'] );
		if (
			! is_array( $stored )
			|| ! isset( $stored['type'], $stored['created_id'], $stored['payload_fingerprint'] )
		) {
			return new \WP_Error( 'conflict', 'Malformed or corrupted idempotency marker; failing closed.' );
		}

		// Enforce binding to type.
		if ( $stored['type'] !== $type ) {
			return new \WP_Error(
				'conflict',
				sprintf(
					'request_id reused with a different type ("%s" vs "%s"); failing closed.',
					$stored['type'],
					$type
				)
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$payload_fingerprint = hash( 'sha256', serialize( $canonical ) );

		if ( $stored['payload_fingerprint'] !== $payload_fingerprint ) {
			return new \WP_Error(
				'conflict',
				'request_id reused with a different payload; failing closed.'
			);
		}

		// Verify existing post in DB.
		$existing_id = (int) $stored['created_id'];
		$post_table  = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'posts`';
		$post_sql    = sprintf(
			'SELECT post_type, post_status FROM %s WHERE ID = %d',
			$post_table,
			$existing_id
		);
		$post_rows   = WriteCoordinator::query_direct( $dbh, $post_sql );
		if ( false === $post_rows || 1 !== count( $post_rows ) ) {
			return new \WP_Error( 'conflict', 'Idempotency marker references nonexistent record; failing closed.' );
		}
		if ( $post_rows[0]['post_type'] !== $type || self::POST_STATUS !== $post_rows[0]['post_status'] ) {
			return new \WP_Error( 'conflict', 'Idempotency marker references corrupt record state; failing closed.' );
		}

		// Decode envelope.
		$existing_env = self::decode_envelope_direct( $dbh, $prefix, $existing_id );
		if ( is_wp_error( $existing_env ) ) {
			return $existing_env;
		}

		return self::build_snapshot( $existing_id, null, $existing_env, $actor, $type );
	}

	/**
	 * Checks schema-defined uniqueness keys under the write lock.
	 *
	 * @param \mysqli              $dbh             Pinned handle.
	 * @param string               $prefix          Table prefix.
	 * @param string               $type            CPT slug.
	 * @param array<string, mixed> $definition      Schema definition.
	 * @param array<string, mixed> $canonical       Canonical fields.
	 * @param int|null             $current_post_id Excluded post ID for updates.
	 * @return true|\WP_Error
	 */
	private static function check_unique_keys_under_lock(
		\mysqli $dbh,
		string $prefix,
		string $type,
		array $definition,
		array $canonical,
		?int $current_post_id = null
	): true|\WP_Error {
		$unique_keys = $definition['unique_keys'] ?? array();

		foreach ( $unique_keys as $rule_name => $field_names ) {
			if ( empty( $field_names ) ) {
				continue;
			}

			// Every field in the unique rule must be present in canonical and scalar. Missing fields fail closed.
			foreach ( $field_names as $fn ) {
				if (
					! array_key_exists( $fn, $canonical )
					|| null === $canonical[ $fn ]
					|| ! is_scalar( $canonical[ $fn ] )
				) {
					return new \WP_Error(
						'invalid_input',
						sprintf( 'Unique key field "%s" missing or non-scalar in input for type "%s".', $fn, $type )
					);
				}
			}

			// Construct multi-INNER JOIN prepared tuple query.
			$p_table  = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'posts`';
			$pm_table = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'postmeta`';

			$joins  = array();
			$types  = '';
			$params = array();

			foreach ( array_values( $field_names ) as $idx => $fn ) {
				$alias    = 'm' . $idx;
				$joins[]  = sprintf(
					'INNER JOIN %s %s ON %s.post_id = p.ID AND %s.meta_key = ? AND %s.meta_value = ?',
					$pm_table,
					$alias,
					$alias,
					$alias,
					$alias
				);
				$types   .= 'ss';
				$params[] = (string) $fn;
				$params[] = (string) $canonical[ $fn ];
			}

			$sql = sprintf(
				"SELECT p.ID FROM %s p %s
				 WHERE p.post_type = ? AND p.post_status = '%s' AND p.ID != ?
				 LIMIT 1 FOR UPDATE",
				$p_table,
				implode( ' ', $joins ),
				self::POST_STATUS
			);

			$types   .= 'si';
			$params[] = $type;
			$params[] = (int) ( $current_post_id ?? 0 );

			$stmt = $dbh->prepare( $sql );
			if ( false === $stmt ) {
				return new \WP_Error(
					'storage_unavailable',
					sprintf( 'Failed to prepare unique query: %s', mysqli_error( $dbh ) )
				);
			}

			$stmt->bind_param( $types, ...$params );
			if ( ! $stmt->execute() ) {
				$err = mysqli_error( $dbh );
				$stmt->close();
				return new \WP_Error(
					'storage_unavailable',
					sprintf( 'Failed to execute unique query: %s', $err )
				);
			}

			$res = $stmt->get_result();
			if ( false === $res ) {
				$stmt->close();
				return new \WP_Error( 'storage_unavailable', 'Failed to get unique query result.' );
			}

			$candidate_row = $res->fetch_assoc();
			$stmt->close();

			if ( ! $candidate_row ) {
				continue;
			}

			$candidate_id = (int) $candidate_row['ID'];

			// Candidate verification path (D1-F-014 / Architect review 5 Constraint 1).
			$cp_sql  = sprintf(
				'SELECT post_type, post_status FROM `%sposts` WHERE ID = %d',
				mysqli_real_escape_string( $dbh, $prefix ),
				$candidate_id
			);
			$cp_rows = WriteCoordinator::query_direct( $dbh, $cp_sql );
			if (
				false === $cp_rows
				|| 1 !== count( $cp_rows )
				|| $cp_rows[0]['post_type'] !== $type
				|| self::POST_STATUS !== $cp_rows[0]['post_status']
			) {
				return new \WP_Error( 'storage_unavailable', 'Corrupted candidate record post row encountered.' );
			}

			$cpm_sql  = sprintf(
				'SELECT meta_key, meta_value FROM `%spostmeta` WHERE post_id = %d',
				mysqli_real_escape_string( $dbh, $prefix ),
				$candidate_id
			);
			$cpm_rows = WriteCoordinator::query_direct( $dbh, $cpm_sql );
			if ( false === $cpm_rows ) {
				return new \WP_Error( 'storage_unavailable', 'Failed to query candidate postmeta.' );
			}

			$cpm_by_key = array();
			foreach ( $cpm_rows as $cpm ) {
				$cpm_by_key[ $cpm['meta_key'] ][] = $cpm['meta_value'];
			}

			if (
				! isset( $cpm_by_key[ RecordSchema::META_KEY ] )
				|| 1 !== count( $cpm_by_key[ RecordSchema::META_KEY ] )
			) {
				return new \WP_Error( 'storage_unavailable', 'Corrupted candidate envelope row count.' );
			}

			$cand_envelope = self::safe_unserialize( $cpm_by_key[ RecordSchema::META_KEY ][0] );
			if ( ! is_array( $cand_envelope ) || ! isset( $cand_envelope['fields'] ) ) {
				return new \WP_Error( 'storage_unavailable', 'Corrupted candidate envelope payload.' );
			}

			// Derive expected projections and check exact cardinality per pinned rule.
			$cand_expected_projections = RecordSchema::derive_projections( $type, $cand_envelope );
			$all_proj_keys             = RecordSchema::get_registered_projection_keys( $type );

			foreach ( $all_proj_keys as $pkey ) {
				if ( array_key_exists( $pkey, $cand_expected_projections ) ) {
					if ( ! isset( $cpm_by_key[ $pkey ] ) || 1 !== count( $cpm_by_key[ $pkey ] ) ) {
						return new \WP_Error(
							'storage_unavailable',
							sprintf( 'Corrupted candidate projection "%s" missing or duplicate.', $pkey )
						);
					}
					if ( (string) $cpm_by_key[ $pkey ][0] !== (string) $cand_expected_projections[ $pkey ] ) {
						return new \WP_Error(
							'storage_unavailable',
							sprintf( 'Corrupted candidate projection "%s" value mismatch.', $pkey )
						);
					}
				} elseif ( ! empty( $cpm_by_key[ $pkey ] ) ) {
					return new \WP_Error(
						'storage_unavailable',
						sprintf( 'Corrupted candidate projection "%s" should not exist.', $pkey )
					);
				}
			}

			// If candidate passed integrity check, it is a valid collision.
			if ( $candidate_id !== $current_post_id ) {
				return new \WP_Error(
					'conflict',
					sprintf( 'Unique constraint "%s" violated on type "%s".', $rule_name, $type )
				);
			}
		}

		return true;
	}

	/**
	 * Inserts a post row via direct SQL on the pinned handle.
	 *
	 * Contract: "Insert one wp_posts row per record with post type from the
	 * closed registry, post_status=private, post_author=actor ID, local and
	 * UTC timestamps, closed comments/pings, and no public slug or content.
	 * No later record save mutates the post row."
	 *
	 * @param \mysqli  $dbh    Pinned handle.
	 * @param string   $prefix Table prefix.
	 * @param string   $type   CPT slug.
	 * @param \WP_User $actor  Author.
	 * @param string   $now    UTC timestamp.
	 * @return int|\WP_Error New post ID.
	 */
	private static function insert_post_direct(
		\mysqli $dbh,
		string $prefix,
		string $type,
		\WP_User $actor,
		string $now
	): int|\WP_Error {
		$table     = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'posts`';
		$post_type = mysqli_real_escape_string( $dbh, $type );
		$actor_id  = (int) $actor->ID;
		$status    = mysqli_real_escape_string( $dbh, self::POST_STATUS );
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$local_time = date( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$utc_time = date( 'Y-m-d H:i:s', (int) strtotime( $now ) );

		$sql = "INSERT INTO $table
			(post_author, post_date, post_date_gmt,
			 post_content, post_title, post_excerpt,
			 post_status, comment_status, ping_status,
			 post_password, post_name, to_ping, pinged,
			 post_modified, post_modified_gmt,
			 post_content_filtered, post_parent, guid,
			 menu_order, post_type, post_mime_type, comment_count)
			VALUES
			($actor_id, '$local_time', '$utc_time',
			 '', '', '',
			 '$status', 'closed', 'closed',
			 '', '', '', '',
			 '$local_time', '$utc_time',
			 '', 0, '',
			 0, '$post_type', '', 0)";

		if ( false === WriteCoordinator::exec_direct( $dbh, $sql ) ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Failed to insert post row: %s', mysqli_error( $dbh ) )
			);
		}

		$post_id = (int) mysqli_insert_id( $dbh );

		if ( $post_id <= 0 ) {
			return new \WP_Error( 'storage_unavailable', 'Inserted post returned invalid ID.' );
		}

		WriteCoordinator::maybe_trigger_test_fault( 'wp_posts_insert', $dbh );

		return $post_id;
	}

	/**
	 * Inserts a single meta row via direct SQL.
	 *
	 * @param \mysqli              $dbh       Pinned handle.
	 * @param string               $prefix    Table prefix.
	 * @param int                  $post_id   Post ID.
	 * @param string               $meta_key  Meta key.
	 * @param array<string, mixed> $value     Value to serialize.
	 * @return true|\WP_Error
	 */
	private static function insert_meta_direct(
		\mysqli $dbh,
		string $prefix,
		int $post_id,
		string $meta_key,
		array $value
	): true|\WP_Error {
		$serialized = serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$table      = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'postmeta`';
		$key_esc    = mysqli_real_escape_string( $dbh, $meta_key );
		$val_esc    = mysqli_real_escape_string( $dbh, $serialized );

		$sql = "INSERT INTO $table (post_id, meta_key, meta_value)
			VALUES ($post_id, '$key_esc', '$val_esc')";

		if ( false === WriteCoordinator::exec_direct( $dbh, $sql ) ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Failed to insert meta row: %s', mysqli_error( $dbh ) )
			);
		}

		WriteCoordinator::maybe_trigger_test_fault( 'envelope_meta_insert', $dbh );

		return true;
	}

	/**
	 * Updates a single meta row via direct SQL (expects exactly one row to exist).
	 *
	 * @param \mysqli              $dbh      Pinned handle.
	 * @param string               $prefix   Table prefix.
	 * @param int                  $post_id  Post ID.
	 * @param string               $meta_key Meta key.
	 * @param array<string, mixed> $value    New value.
	 * @return true|\WP_Error
	 */
	private static function update_meta_direct(
		\mysqli $dbh,
		string $prefix,
		int $post_id,
		string $meta_key,
		array $value
	): true|\WP_Error {
		$serialized = serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$table      = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'postmeta`';
		$key_esc    = mysqli_real_escape_string( $dbh, $meta_key );
		$val_esc    = mysqli_real_escape_string( $dbh, $serialized );

		$sql = "UPDATE $table
			SET meta_value = '$val_esc'
			WHERE post_id = $post_id AND meta_key = '$key_esc'";

		if ( false === WriteCoordinator::exec_direct( $dbh, $sql ) ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Failed to update meta row: %s', mysqli_error( $dbh ) )
			);
		}

		if ( 0 === mysqli_affected_rows( $dbh ) ) {
			return new \WP_Error( 'storage_unavailable', 'Meta update matched no rows; envelope missing.' );
		}

		WriteCoordinator::maybe_trigger_test_fault( 'envelope_meta_update', $dbh );

		return true;
	}

	/**
	 * Writes schema-defined projection meta rows in the same transaction.
	 *
	 * Projection rows are derived from the envelope; they are always kept in
	 * sync with the authoritative meta. One row per projection key is maintained.
	 *
	 * @param \mysqli              $dbh        Pinned handle.
	 * @param string               $prefix     Table prefix.
	 * @param int                  $post_id    Post ID.
	 * @param string               $type       Record type.
	 * @param array<string, mixed> $definition Schema definition.
	 * @param array<string, mixed> $envelope   New authoritative envelope.
	 * @return true|\WP_Error
	 */
	private static function write_projections_direct(
		\mysqli $dbh,
		string $prefix,
		int $post_id,
		string $type,
		array $definition,
		array $envelope
	): true|\WP_Error {
		$expected_projections = RecordSchema::derive_projections( $type, $envelope );
		$all_keys             = RecordSchema::get_registered_projection_keys( $type );

		if ( empty( $all_keys ) ) {
			return true;
		}

		$table = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'postmeta`';

		foreach ( $all_keys as $proj_key ) {
			$key_esc = mysqli_real_escape_string( $dbh, $proj_key );

			// Check current row count for this projection.
			$check_sql     = sprintf(
				"SELECT meta_id FROM %s WHERE post_id = %d AND meta_key = '%s'",
				$table,
				$post_id,
				$key_esc
			);
			$existing_rows = WriteCoordinator::query_direct( $dbh, $check_sql );
			if ( false === $existing_rows ) {
				return new \WP_Error( 'storage_unavailable', sprintf( 'Failed to query projection "%s".', $proj_key ) );
			}

			// Reject duplicate projection rows.
			if ( count( $existing_rows ) > 1 ) {
				return new \WP_Error(
					'storage_unavailable',
					sprintf( 'Duplicate projection rows detected for key "%s" on post %d.', $proj_key, $post_id )
				);
			}

			$has_expected = array_key_exists( $proj_key, $expected_projections );

			if ( ! $has_expected ) {
				if ( 1 === count( $existing_rows ) ) {
					$del_sql = sprintf(
						"DELETE FROM %s WHERE post_id = %d AND meta_key = '%s'",
						$table,
						$post_id,
						$key_esc
					);
					if ( false === WriteCoordinator::exec_direct( $dbh, $del_sql ) ) {
						return new \WP_Error(
							'storage_unavailable',
							sprintf( 'Failed to delete projection "%s".', $proj_key )
						);
					}
				}
				continue;
			}

			$val_esc = mysqli_real_escape_string( $dbh, (string) $expected_projections[ $proj_key ] );

			if ( 1 === count( $existing_rows ) ) {
				$update_sql = sprintf(
					"UPDATE %s SET meta_value = '%s' WHERE meta_id = %d",
					$table,
					$val_esc,
					(int) $existing_rows[0]['meta_id']
				);
				if ( false === WriteCoordinator::exec_direct( $dbh, $update_sql ) ) {
					return new \WP_Error(
						'storage_unavailable',
						sprintf( 'Failed to update projection "%s".', $proj_key )
					);
				}
			} else {
				$insert_sql = sprintf(
					"INSERT INTO %s (post_id, meta_key, meta_value) VALUES (%d, '%s', '%s')",
					$table,
					$post_id,
					$key_esc,
					$val_esc
				);
				if ( false === WriteCoordinator::exec_direct( $dbh, $insert_sql ) ) {
					return new \WP_Error(
						'storage_unavailable',
						sprintf( 'Failed to insert projection "%s".', $proj_key )
					);
				}
			}

			// Statement fault hooks.
			if ( '_mf_velog_state' === $proj_key ) {
				WriteCoordinator::maybe_trigger_test_fault( 'state_projection_insert', $dbh );
			} else {
				WriteCoordinator::maybe_trigger_test_fault( 'custom_projection_insert', $dbh );
			}
		}

		return true;
	}

	/**
	 * Stores the idempotency marker option in the same transaction.
	 *
	 * @param \mysqli $dbh                 Pinned handle.
	 * @param string  $prefix              Table prefix.
	 * @param string  $idem_key            option_name.
	 * @param string  $type                CPT slug.
	 * @param int     $created_id          New post ID.
	 * @param string  $payload_fingerprint SHA-256 of canonical fields.
	 * @return true|\WP_Error
	 */
	private static function store_idempotency_marker(
		\mysqli $dbh,
		string $prefix,
		string $idem_key,
		string $type,
		int $created_id,
		string $payload_fingerprint
	): true|\WP_Error {
		$table   = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'options`';
		$key_esc = mysqli_real_escape_string( $dbh, $idem_key );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$marker = serialize(
			array(
				'type'                => $type,
				'created_id'          => $created_id,
				'payload_fingerprint' => $payload_fingerprint,
			)
		);

		$val_esc = mysqli_real_escape_string( $dbh, $marker );

		$sql = "INSERT INTO $table (option_name, option_value, autoload)
			VALUES ('$key_esc', '$val_esc', 'no')";

		if ( false === WriteCoordinator::exec_direct( $dbh, $sql ) ) {
			return new \WP_Error(
				'storage_unavailable',
				sprintf( 'Failed to store idempotency marker: %s', mysqli_error( $dbh ) )
			);
		}

		WriteCoordinator::maybe_trigger_test_fault( 'create_marker_insert', $dbh );

		return true;
	}

	/**
	 * Verifies row counts and data integrity before COMMIT (per contract §4: verify before commit).
	 *
	 * @param \mysqli              $dbh        Pinned handle.
	 * @param string               $prefix     Table prefix.
	 * @param int                  $post_id    Post ID.
	 * @param string               $type       CPT slug.
	 * @param array<string, mixed> $definition Schema definition.
	 * @param array<string, mixed> $envelope   New envelope to verify.
	 * @param string               $idem_key   Optional idempotency marker option name.
	 * @return true|\WP_Error
	 */
	private static function verify_write(
		\mysqli $dbh,
		string $prefix,
		int $post_id,
		string $type,
		array $definition,
		array $envelope,
		string $idem_key = ''
	): true|\WP_Error {
		// 1. Verify post row exists and has correct post_type and post_status.
		$post_sql  = sprintf(
			'SELECT post_type, post_status FROM `%sposts` WHERE ID = %d',
			mysqli_real_escape_string( $dbh, $prefix ),
			$post_id
		);
		$post_rows = WriteCoordinator::query_direct( $dbh, $post_sql );
		if ( false === $post_rows || 1 !== count( $post_rows ) ) {
			return new \WP_Error( 'storage_unavailable', 'Post row verification failed before commit.' );
		}
		if ( $post_rows[0]['post_type'] !== $type || self::POST_STATUS !== $post_rows[0]['post_status'] ) {
			return new \WP_Error( 'storage_unavailable', 'Post type or status mismatch before commit.' );
		}

		// 2. Fetch all postmeta rows and group in PHP (avoids ONLY_FULL_GROUP_BY issues).
		$meta_sql  = sprintf(
			'SELECT meta_key, meta_value FROM `%spostmeta` WHERE post_id = %d',
			mysqli_real_escape_string( $dbh, $prefix ),
			$post_id
		);
		$meta_rows = WriteCoordinator::query_direct( $dbh, $meta_sql );
		if ( false === $meta_rows ) {
			return new \WP_Error( 'storage_unavailable', 'Failed to query postmeta before commit.' );
		}

		$pm_by_key = array();
		foreach ( $meta_rows as $mr ) {
			$pm_by_key[ $mr['meta_key'] ][] = $mr['meta_value'];
		}

		// Verify authoritative envelope.
		if ( ! isset( $pm_by_key[ RecordSchema::META_KEY ] ) || 1 !== count( $pm_by_key[ RecordSchema::META_KEY ] ) ) {
			return new \WP_Error(
				'storage_unavailable',
				'Authoritative meta row count verification failed before commit.'
			);
		}
		$stored_env = self::safe_unserialize( $pm_by_key[ RecordSchema::META_KEY ][0] );
		if (
			! is_array( $stored_env )
			|| ( $stored_env['record_version'] ?? null ) !== ( $envelope['record_version'] ?? null )
		) {
			return new \WP_Error( 'storage_unavailable', 'Envelope payload verification failed before commit.' );
		}

		// 3. Verify projections against trusted derivation rule.
		$expected_projections = RecordSchema::derive_projections( $type, $envelope );
		$all_proj_keys        = RecordSchema::get_registered_projection_keys( $type );

		foreach ( $all_proj_keys as $proj_key ) {
			if ( array_key_exists( $proj_key, $expected_projections ) ) {
				// Expected present: exactly 1 row, value matches.
				if ( ! isset( $pm_by_key[ $proj_key ] ) || 1 !== count( $pm_by_key[ $proj_key ] ) ) {
					return new \WP_Error(
						'storage_unavailable',
						sprintf( 'Projection "%s" cardinality error before commit (expected 1 row).', $proj_key )
					);
				}
				if ( (string) $pm_by_key[ $proj_key ][0] !== (string) $expected_projections[ $proj_key ] ) {
					return new \WP_Error(
						'storage_unavailable',
						sprintf( 'Projection "%s" value mismatch before commit.', $proj_key )
					);
				}
			} elseif ( ! empty( $pm_by_key[ $proj_key ] ) ) {
				// Expected absent: 0 rows ("absent projections have no row").
				return new \WP_Error(
					'storage_unavailable',
					sprintf( 'Projection "%s" should not exist before commit (expected 0 rows).', $proj_key )
				);
			}
		}

		// 4. Verify idempotency marker if key provided.
		if ( '' !== $idem_key ) {
			$opt_esc  = mysqli_real_escape_string( $dbh, $idem_key );
			$opt_sql  = sprintf(
				"SELECT option_value FROM `%soptions` WHERE option_name = '%s'",
				mysqli_real_escape_string( $dbh, $prefix ),
				$opt_esc
			);
			$opt_rows = WriteCoordinator::query_direct( $dbh, $opt_sql );
			if ( false === $opt_rows || 1 !== count( $opt_rows ) ) {
				return new \WP_Error( 'storage_unavailable', 'Idempotency marker verification failed before commit.' );
			}
		}

		WriteCoordinator::maybe_trigger_test_fault( 'verify_write', $dbh );

		return true;
	}

	/**
	 * Reconciles an ambiguous create operation under a fresh coordinator lock.
	 *
	 * Satisfies contract §4 and D1-F-015:
	 *  - Checks wp_options under lock for mf_velog_create_<sha256(request_id)>.
	 *  - If marker exists: verifies payload fingerprint and type. If matches, verifies
	 *    candidate record rows and returns snapshot. If mismatches, returns conflict.
	 *  - If marker is missing: certifies transaction definitely did not commit; returns
	 *    not_found indicating safe retry with the same request ID.
	 *
	 * @param string               $type       Registered CPT slug.
	 * @param array<string, mixed> $fields     Original create fields.
	 * @param \WP_User             $actor      Authenticated actor.
	 * @param string               $request_id Original caller UUID.
	 * @return array<string, mixed>|\WP_Error Committed snapshot, not_found, or conflict.
	 */
	public static function reconcile_create(
		string $type,
		array $fields,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$canonical = RecordSchema::validate_fields( $type, $fields );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		$env = WriteCoordinator::check_environment();
		if ( is_wp_error( $env ) ) {
			return $env;
		}

		$idem_key = 'mf_velog_create_' . hash( 'sha256', $request_id );

		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use (
				$type,
				$canonical,
				$actor,
				$idem_key,
				$request_id
			): array|\WP_Error {
				$dbh    = $unit->get_dbh();
				$prefix = $unit->get_prefix();

				$opt_table = '`' . mysqli_real_escape_string( $dbh, $prefix ) . 'options`';
				$key_esc   = mysqli_real_escape_string( $dbh, $idem_key );
				$opt_sql   = sprintf(
					"SELECT option_value FROM %s WHERE option_name = '%s' FOR UPDATE",
					$opt_table,
					$key_esc
				);
				$opt_rows  = WriteCoordinator::query_direct( $dbh, $opt_sql );

				if ( false === $opt_rows ) {
					return new \WP_Error( 'storage_unavailable', 'Failed to query create idempotency marker.' );
				}

				if ( empty( $opt_rows ) ) {
					// Under coordinator lock, marker absence proves create transaction did not commit.
					return new \WP_Error(
						'not_found',
						'Request was not committed; safe to retry with the same request ID.'
					);
				}

				$stored = self::safe_unserialize( $opt_rows[0]['option_value'] );
				if (
					! is_array( $stored )
					|| ! isset( $stored['type'], $stored['created_id'], $stored['payload_fingerprint'] )
				) {
					return new \WP_Error( 'indeterminate', 'Corrupt request marker.' );
				}

				if ( $stored['type'] !== $type ) {
					return new \WP_Error( 'conflict', 'Request ID reused with different type.' );
				}

				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				$fp = hash( 'sha256', serialize( $canonical ) );
				if ( $stored['payload_fingerprint'] !== $fp ) {
					return new \WP_Error( 'conflict', 'Request ID reused with different payload.' );
				}

				$created_id = (int) $stored['created_id'];

				// Verify post row.
				$post_sql  = sprintf(
					'SELECT post_type, post_status FROM `%sposts` WHERE ID = %d',
					mysqli_real_escape_string( $dbh, $prefix ),
					$created_id
				);
				$post_rows = WriteCoordinator::query_direct( $dbh, $post_sql );
				if (
					false === $post_rows
					|| 1 !== count( $post_rows )
					|| $post_rows[0]['post_type'] !== $type
					|| self::POST_STATUS !== $post_rows[0]['post_status']
				) {
					return new \WP_Error( 'storage_unavailable', 'Corrupted post row for committed create.' );
				}

				// Decode envelope.
				$envelope = self::decode_envelope_direct( $dbh, $prefix, $created_id );
				if ( is_wp_error( $envelope ) ) {
					return $envelope;
				}

				// D1-F-022: Verify marker shape and bind to audit log.
				$audit_entries  = (array) ( $envelope['audit'] ?? array() );
				$record_version = (int) ( $envelope['record_version'] ?? 0 );
				if ( empty( $audit_entries ) || $record_version < 1 ) {
					return new \WP_Error( 'storage_unavailable', 'Candidate record missing audit history.' );
				}
				if ( count( $audit_entries ) !== $record_version ) {
					return new \WP_Error(
						'storage_unavailable',
						'Audit sequence length does not match record version.'
					);
				}

				$previous_after = array();
				foreach ( $audit_entries as $idx => $entry ) {
					if (
						! is_array( $entry )
						|| ! isset( $entry['actor_id'], $entry['request_id_sha256'], $entry['op'] )
					) {
						return new \WP_Error( 'storage_unavailable', 'Malformed audit entry detected.' );
					}
					if ( 0 === $idx ) {
						if ( 'create' !== $entry['op'] ) {
							return new \WP_Error( 'storage_unavailable', 'First audit entry must be create.' );
						}
						if (
							! isset( $entry['before'] )
							|| ! is_array( $entry['before'] )
							|| ! empty( $entry['before'] )
						) {
							return new \WP_Error( 'storage_unavailable', 'Create audit entry must have empty before.' );
						}
						if ( ! isset( $entry['after'] ) || ! is_array( $entry['after'] ) ) {
							return new \WP_Error(
								'storage_unavailable',
								'Candidate record missing valid create audit entry.'
							);
						}
						$req_hash = hash( 'sha256', $request_id );
						if ( $entry['request_id_sha256'] !== $req_hash ) {
							return new \WP_Error( 'storage_unavailable', 'Create audit entry request hash mismatch.' );
						}
						if ( (int) $entry['actor_id'] !== (int) $actor->ID ) {
							return new \WP_Error( 'storage_unavailable', 'Create audit entry actor mismatch.' );
						}
						$previous_after = $entry['after'];
					} else {
						if ( 'save' !== $entry['op'] ) {
							return new \WP_Error( 'storage_unavailable', 'Subsequent audit entries must be save.' );
						}
						if (
							! isset( $entry['before'] )
							|| ! is_array( $entry['before'] )
							|| ! isset( $entry['after'] )
							|| ! is_array( $entry['after'] )
						) {
							return new \WP_Error(
								'storage_unavailable',
								'Malformed save audit entry: missing before/after.'
							);
						}
						$current_before = $entry['before'];
						ksort( $current_before );
						ksort( $previous_after );
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
						if ( serialize( $current_before ) !== serialize( $previous_after ) ) {
							return new \WP_Error(
								'storage_unavailable',
								'Audit chain gap: before does not match previous after.'
							);
						}
						$previous_after = $entry['after'];
					}
				}

				$first_after = $audit_entries[0]['after'];
				$canon_copy  = $canonical;
				ksort( $canon_copy );
				ksort( $first_after );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( serialize( $first_after ) !== serialize( $canon_copy ) ) {
					return new \WP_Error( 'storage_unavailable', 'Create audit entry fields mismatch.' );
				}

				$final_fields = $envelope['fields'] ?? array();
				ksort( $final_fields );
				ksort( $previous_after );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( serialize( $final_fields ) !== serialize( $previous_after ) ) {
					return new \WP_Error( 'storage_unavailable', 'Final audit after does not match envelope fields.' );
				}

				$first_entry = $audit_entries[0];

				// D1-F-022: Verify projection rows on the pinned handle using the
				// bounded integrity check (same logic as verify_write).
				$definition  = RecordSchema::get( $type );
				$proj_verify = self::verify_write( $dbh, $prefix, $created_id, $type, $definition, $envelope );
				if ( is_wp_error( $proj_verify ) ) {
					// Corrupt committed candidate — do not return as valid snapshot.
					$reason = $proj_verify->get_error_message();
					return new \WP_Error(
						'storage_unavailable',
						'Committed create candidate failed projection integrity check: ' . $reason
					);
				}

				// Check authorization.
				$auth = self::authorize_read( $actor, $type, $definition, $envelope );
				if ( is_wp_error( $auth ) ) {
					return $auth;
				}

				return self::build_snapshot( $created_id, null, $envelope, $actor, $type );
			}
		);
	}

	/**
	 * Reconciles an ambiguous save operation under a fresh coordinator lock.
	 *
	 * Satisfies contract §4, D1-F-015, and Architect Review 5 Constraint 3:
	 *  - Queries target record under fresh lock.
	 *  - Inspects all audit entries for request_id_sha256 matching hash( 'sha256', $request_id ).
	 *  - If matched -> save committed; returns committed snapshot.
	 *  - If unmatched and record_version === expected_version -> save did not commit; returns not_found.
	 *  - If unmatched and record_version > expected_version -> another save committed; returns stale_version.
	 *  - If audit history is malformed or gap detected -> returns indeterminate.
	 *
	 * @param string               $type             Registered CPT slug.
	 * @param int                  $id               Post ID.
	 * @param int                  $expected_version Expected record version.
	 * @param array<string, mixed> $changes          Expected changes.
	 * @param \WP_User             $actor            Authenticated actor.
	 * @param string               $request_id       Original caller UUID.
	 * @return array<string, mixed>|\WP_Error Committed snapshot, not_found, or stale_version.
	 */
	public static function reconcile_save(
		string $type,
		int $id,
		int $expected_version,
		array $changes,
		\WP_User $actor,
		string $request_id
	): array|\WP_Error {
		$env_check = self::require_sealed_and_registered( $type );
		if ( is_wp_error( $env_check ) ) {
			return $env_check;
		}

		if ( '' === $request_id ) {
			return new \WP_Error( 'invalid_input', 'request_id must not be empty.' );
		}

		$canonical_changes = RecordSchema::validate_fields( $type, $changes );
		if ( is_wp_error( $canonical_changes ) ) {
			return $canonical_changes;
		}

		$env = WriteCoordinator::check_environment();
		if ( is_wp_error( $env ) ) {
			return $env;
		}

		$req_hash = hash( 'sha256', $request_id );

		// D1-F-019: Pass canonical_changes into the closure so the matching audit
		// entry's before/after transition and version position can be validated.
		return WriteCoordinator::run(
			$actor,
			static function ( WriteUnit $unit ) use (
				$type,
				$id,
				$expected_version,
				$canonical_changes,
				$actor,
				$req_hash
			): array|\WP_Error {
				$dbh    = $unit->get_dbh();
				$prefix = $unit->get_prefix();

				// Read post row.
				$post_sql  = sprintf(
					'SELECT post_type, post_status FROM `%sposts` WHERE ID = %d',
					mysqli_real_escape_string( $dbh, $prefix ),
					$id
				);
				$post_rows = WriteCoordinator::query_direct( $dbh, $post_sql );
				if ( false === $post_rows || 1 !== count( $post_rows ) ) {
					return new \WP_Error( 'not_found', 'Record does not exist.' );
				}

				if ( $post_rows[0]['post_type'] !== $type || self::POST_STATUS !== $post_rows[0]['post_status'] ) {
					return new \WP_Error( 'storage_unavailable', 'Post type or status mismatch.' );
				}

				// Read envelope under lock.
				$envelope = self::decode_envelope_direct( $dbh, $prefix, $id );
				if ( is_wp_error( $envelope ) ) {
					return $envelope;
				}

				$current_version = (int) ( $envelope['record_version'] ?? 0 );
				$audit_entries   = (array) ( $envelope['audit'] ?? array() );

				if ( empty( $audit_entries ) || $current_version < 1 ) {
					return new \WP_Error( 'indeterminate', 'Corrupt audit history on record.' );
				}

				// D1-F-019: Validate audit entries are well-formed and sequence is gap-free.
				if ( count( $audit_entries ) !== $current_version ) {
					return new \WP_Error( 'indeterminate', 'Audit sequence length does not match record version.' );
				}

				$matched_entry  = null;
				$matched_idx    = -1;
				$match_count    = 0;
				$previous_after = array();

				foreach ( $audit_entries as $idx => $entry ) {
					if (
						! is_array( $entry )
						|| ! isset( $entry['actor_id'], $entry['request_id_sha256'], $entry['op'] )
					) {
						return new \WP_Error( 'indeterminate', 'Malformed audit entry detected.' );
					}

					if ( 0 === $idx ) {
						if ( 'create' !== $entry['op'] ) {
							return new \WP_Error( 'indeterminate', 'First audit entry must be create.' );
						}
						if (
							! isset( $entry['before'] )
							|| ! is_array( $entry['before'] )
							|| ! empty( $entry['before'] )
						) {
							return new \WP_Error( 'indeterminate', 'Create audit entry must have empty before.' );
						}
						if ( ! isset( $entry['after'] ) || ! is_array( $entry['after'] ) ) {
							return new \WP_Error(
								'indeterminate',
								'Candidate record missing valid create audit entry.'
							);
						}
						$previous_after = $entry['after'];
					} else {
						if ( 'save' !== $entry['op'] ) {
							return new \WP_Error( 'indeterminate', 'Subsequent audit entries must be save.' );
						}
						if (
							! isset( $entry['before'] )
							|| ! is_array( $entry['before'] )
							|| ! isset( $entry['after'] )
							|| ! is_array( $entry['after'] )
						) {
							return new \WP_Error(
								'indeterminate',
								'Malformed save audit entry: missing before/after.'
							);
						}
						$current_before = $entry['before'];
						ksort( $current_before );
						ksort( $previous_after );
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
						if ( serialize( $current_before ) !== serialize( $previous_after ) ) {
							return new \WP_Error(
								'indeterminate',
								'Audit chain gap: before does not match previous after.'
							);
						}
						$previous_after = $entry['after'];
					}

					if (
						$entry['request_id_sha256'] === $req_hash
						&& (int) $entry['actor_id'] === (int) $actor->ID
						&& 'save' === $entry['op']
					) {
						$matched_entry = $entry;
						$matched_idx   = $idx;
						$match_count++;
					}
				}

				if ( $match_count > 1 ) {
					return new \WP_Error( 'indeterminate', 'Duplicate request identity found in audit history.' );
				}

				$final_fields = $envelope['fields'] ?? array();
				ksort( $final_fields );
				ksort( $previous_after );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( serialize( $final_fields ) !== serialize( $previous_after ) ) {
					return new \WP_Error( 'indeterminate', 'Final audit after does not match envelope fields.' );
				}

				if ( null !== $matched_entry ) {
					// D1-F-019: The matched entry must correspond exactly to the transition
					// from expected_version to expected_version + 1. Since $idx is 0-based and create is at 0
					// transitioning to version 1, a save at $idx transitions from $idx to $idx+1.
					// So $expected_version must equal $matched_idx.
					if ( $matched_idx !== $expected_version ) {
						return new \WP_Error( 'conflict', 'Matched audit entry found at wrong version position.' );
					}

					// D1-F-019: Reconcile using the original change subset against the matched transition.
					// This correctly handles partial updates with multiple fields.
					$matched_before = (array) $matched_entry['before'];
					$matched_after  = (array) $matched_entry['after'];

					$simulated_after = array_merge( $matched_before, $canonical_changes );
					ksort( $simulated_after );
					ksort( $matched_after );

					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
					if ( serialize( $simulated_after ) !== serialize( $matched_after ) ) {
						return new \WP_Error(
							'conflict',
							'Matched audit entry transition differs from requested changes; ' .
							'request ID reused with different payload.'
						);
					}

					// Save committed with matching changes.
					$definition = RecordSchema::get( $type );
					$auth       = self::authorize_read( $actor, $type, $definition, $envelope );
					if ( is_wp_error( $auth ) ) {
						return $auth;
					}
					return self::build_snapshot( $id, null, $envelope, $actor, $type );
				}

				// Not matched.
				if ( $current_version === $expected_version ) {
					return new \WP_Error(
						'not_found',
						'Save was not committed; safe to retry with the same request ID.'
					);
				}

				if ( $current_version > $expected_version ) {
					return new \WP_Error( 'stale_version', 'Target record version has advanced.' );
				}

				return new \WP_Error( 'indeterminate', 'Audit version inconsistency detected.' );
			}
		);
	}

	/**
	 * Authorizes a read access for an actor on a decoded envelope.
	 *
	 * @param \WP_User             $actor      Actor.
	 * @param string               $type       CPT slug.
	 * @param array<string, mixed> $definition Schema definition.
	 * @param array<string, mixed> $envelope   Decoded envelope.
	 * @return true|\WP_Error
	 */
	private static function authorize_read(
		\WP_User $actor,
		string $type,
		array $definition,
		array $envelope
	): true|\WP_Error {
		$read_cap = $definition['read_capability'] ?? 'mf_velog_read_records';
		if ( ! $actor->has_cap( $read_cap ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to access this record.' );
		}

		// Additional object-level authorization via AccessPolicy.
		$context = RecordSchema::build_context( $type, $envelope, $actor );

		if ( ! \MF\VeLog\Common\AccessPolicy::authorize( $actor, $read_cap, $context ) ) {
			return new \WP_Error( 'forbidden', 'Object-level access denied.' );
		}

		return true;
	}

	/**
	 * Builds a sanitised snapshot array to return to callers.
	 *
	 * Never returns raw WP_Post or unfiltered postmeta (contract §Public Common API).
	 *
	 * @param int                       $id       Post ID.
	 * @param array<string, mixed>|null $post_row Optional post row data.
	 * @param array<string, mixed>      $envelope Decoded envelope.
	 * @param \WP_User                  $actor    Authenticated actor.
	 * @param string                    $type     CPT slug.
	 * @return array<string, mixed>
	 */
	private static function build_snapshot(
		int $id,
		?array $post_row,
		array $envelope,
		\WP_User $actor,
		string $type = ''
	): array {
		$fields = (array) ( $envelope['fields'] ?? array() );
		$audit  = (array) ( $envelope['audit'] ?? array() );

		// Strip contact fields if actor does not have mf_velog_read_customer_contacts.
		if ( '' !== $type && RecordSchema::is_registered( $type ) ) {
			$definition     = RecordSchema::get( $type );
			$contact_fields = $definition['contact_fields'] ?? array();
			if ( ! empty( $contact_fields ) && ! $actor->has_cap( 'mf_velog_read_customer_contacts' ) ) {
				foreach ( $contact_fields as $cf ) {
					unset( $fields[ $cf ] );
				}
				foreach ( $audit as $idx => $entry ) {
					if ( is_array( $entry ) ) {
						if ( isset( $entry['before'] ) && is_array( $entry['before'] ) ) {
							foreach ( $contact_fields as $cf ) {
								unset( $entry['before'][ $cf ] );
							}
						}
						if ( isset( $entry['after'] ) && is_array( $entry['after'] ) ) {
							foreach ( $contact_fields as $cf ) {
								unset( $entry['after'][ $cf ] );
							}
						}
						$audit[ $idx ] = $entry;
					}
				}
			}
		}

		return array(
			'id'             => $id,
			'record_version' => (int) ( $envelope['record_version'] ?? 0 ),
			'schema_version' => (int) ( $envelope['schema_version'] ?? 0 ),
			'state'          => (string) ( $envelope['state'] ?? '' ),
			'fields'         => $fields,
			'created_by'     => (int) ( $envelope['created_by'] ?? 0 ),
			'created_at_utc' => (string) ( $envelope['created_at_utc'] ?? '' ),
			'updated_by'     => (int) ( $envelope['updated_by'] ?? 0 ),
			'updated_at_utc' => (string) ( $envelope['updated_at_utc'] ?? '' ),
			'audit'          => $audit,
		);
	}

	/**
	 * Returns the initial state for a newly created record per schema definition.
	 *
	 * @param array<string, mixed> $definition Schema definition.
	 * @return string
	 */
	private static function initial_state( array $definition ): string {
		$states = $definition['states'] ?? array();
		return ! empty( $states ) ? (string) reset( $states ) : '';
	}

	/**
	 * Safely unserializes a string, rejecting PHP objects.
	 *
	 * @param mixed $value Possibly serialized value.
	 * @return mixed Unserialized value or false on failure.
	 */
	private static function safe_unserialize( mixed $value ): mixed {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Detect serialized string.
		if ( 'a:' !== substr( $value, 0, 2 ) && 'N;' !== $value && 's:' !== substr( $value, 0, 2 ) ) {
			// Not a serialized array/null/string; return as-is for scalars.
			// For stored envelopes we always expect arrays starting with 'a:'.
			if ( 'a:' !== substr( $value, 0, 2 ) ) {
				// Try JSON decode as fallback — not expected in storage, but safe.
				return $value;
			}
		}

		// Unserialize with class allowlist = false (only arrays/scalars).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$result = unserialize( $value, array( 'allowed_classes' => false ) );

		return $result;
	}
}
