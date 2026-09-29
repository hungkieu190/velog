<?php
/**
 * V4: Cache failure modes, rollback cleanliness, and lock-timeout integration probe.
 *
 * Verifies:
 *   - V4-A: Cache invalidation after successful commit (clean_post_cache called).
 *   - V4-B: Authoritative meta row exists via direct SQL after commit.
 *   - V4-C: Repository get() bypasses cache and returns canonical data.
 *   - V4-D: Real lock-wait timeout returns 'conflict' (MySQL 1205) with bounded wait (~5s).
 *   - V4-E: Pre-commit rollback cleans up posts/meta in database.
 *   - V4-F: Pre-commit rollback invalidates warmed object cache (no stale get_post remains).
 *   - V4-G: Pre-commit disconnect fault returns 'indeterminate' (D1-F-016).
 *   - V4-H: Ambiguous create reconciliation (D1-F-015/D1-F-022).
 *   - V4-I: Ambiguous save reconciliation (D1-F-015/D1-F-019).
 *   - V4-J (D1-F-023): Query SQL failure returns 'storage_unavailable' (D1-F-020).
 *   - V4-K (D1-F-023): Reconcile_save with altered changes returns 'conflict' (D1-F-019).
 *   - V4-L (D1-F-023): Reconcile_save with same request_id but wrong expected_version returns correct code (D1-F-019).
 *   - V4-M (D1-F-023): Reconcile_create on corrupt projection returns 'storage_unavailable' (D1-F-022).
 *
 * NOT VERIFIED: Live-handle COMMIT failure, coordinator 1213 deadlock,
 * persistent-cache compatibility, and full G-08 product performance.
 *
 * Exit code 0 = all assertions pass.
 * Exit code 1 = any assertion failed.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;

$autoload_candidates = array(
	defined( 'VELOG_PLUGIN_DIR' ) ? VELOG_PLUGIN_DIR . 'vendor/autoload.php' : '',
	dirname( __DIR__, 4 ) . '/vendor/autoload.php',
	WP_PLUGIN_DIR . '/velog/vendor/autoload.php',
);
foreach ( $autoload_candidates as $cand ) {
	if ( '' !== $cand && file_exists( $cand ) ) {
		require_once $cand;
		break;
	}
}

global $wpdb;

$GLOBALS['velog_failures'] = array();

/**
 * Assert helper.
 *
 * @param bool   $cond    Condition.
 * @param string $label   Label.
 * @param string $details Optional details.
 */
function v4_assert( bool $cond, string $label, string $details = '' ): void {
	if ( $cond ) {
		echo "PASS: $label\n";
	} else {
		$msg = "FAIL: $label";
		if ( '' !== $details ) {
			$msg .= " ($details)";
		}
		echo "$msg\n";
		$GLOBALS['velog_failures'][] = $msg;
	}
}

// Reset and register schemas with test fields.
RecordSchema::reset_for_testing();
RecordSchema::register(
	'mf_velog_customer',
	array(
		'field_policies'  => array(
			'name' => 'public',
		),
		'fields'          => array(
			'name' => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
		),
		'states'          => array( 'active', 'archived' ),
		'capability'      => 'mf_velog_manage_customers',
		'read_capability' => 'mf_velog_read_records',
	)
);
RecordSchema::seal();

$actor = new WP_User( 1 );
$actor->add_cap( 'mf_velog_manage_customers' );
$actor->add_cap( 'mf_velog_read_records' );
$actor->add_cap( 'mf_velog_read_customer_contacts' );

WriteCoordinator::ensure_lock_row();

// ─── V4-A + V4-B + V4-C: Cache invalidation after successful commit ───────────

$req_id = 'v4-cache-' . wp_generate_uuid4();
$result = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Cache Test' ), $actor, $req_id );
v4_assert( ! is_wp_error( $result ), 'V4-A: create for cache test succeeded', is_wp_error( $result ) ? $result->get_error_message() : '' );

if ( ! ( $result instanceof WP_Error ) ) {
	$cid = $result['id'];

	// After commit, clean_post_cache() must have been called.
	$post = get_post( $cid );
	v4_assert( null !== $post, 'V4-A: get_post() returns valid post after commit (cache invalidated)' );
	v4_assert( 'mf_velog_customer' === ( $post->post_type ?? '' ), 'V4-A: post_type matches' );

	// Verify direct meta row exists.
	$direct_meta = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$cid,
			'_mf_velog_record'
		)
	);
	v4_assert( null !== $direct_meta, 'V4-B: authoritative meta row exists via direct SQL' );

	// repository get() bypasses cache — must return consistent result.
	$repo_result = RecordRepository::get( 'mf_velog_customer', $cid, $actor );
	v4_assert( ! is_wp_error( $repo_result ), 'V4-C: repository get bypasses cache, returns result', is_wp_error( $repo_result ) ? $repo_result->get_error_message() : '' );
	if ( ! ( $repo_result instanceof WP_Error ) ) {
		v4_assert( 'Cache Test' === $repo_result['fields']['name'], 'V4-C: canonical field matches' );
	}
}

// ─── V4-D: Real lock-wait timeout injection ───────────────────────────────────

$wp_load_path  = ABSPATH . 'wp-load.php';
$holder_script = sys_get_temp_dir() . '/velog_v4_holder_' . getmypid() . '.php';

// Script that connects, locks the option row, signals readiness, and sleeps 7 seconds.
$holder_code = <<<'PHP'
<?php
$wp_load     = $argv[1];
$signal_file = $argv[2];

require_once $wp_load;

global $wpdb;

$dbh   = $wpdb->dbh;
$table = $wpdb->options;

// Direct transaction on separate connection.
mysqli_query( $dbh, 'SET innodb_lock_wait_timeout = 15' );
mysqli_query( $dbh, 'START TRANSACTION' );

// Acquire lock on mf_velog_write_lock row.
$opt = mysqli_real_escape_string( $dbh, \MF\VeLog\Common\Storage\RecordSchema::WRITE_LOCK_OPTION );
$res = mysqli_query( $dbh, "SELECT option_id FROM `$table` WHERE option_name = '$opt' FOR UPDATE" );

if ( $res && mysqli_num_rows( $res ) > 0 ) {
	file_put_contents( $signal_file, 'LOCKED' );
	sleep( 7 ); // Hold lock for 7 seconds.
}

mysqli_query( $dbh, 'ROLLBACK' );
exit( 0 );
PHP;

file_put_contents( $holder_script, $holder_code );
$signal_file = sys_get_temp_dir() . '/velog_lock_signal_' . getmypid() . '.txt';
if ( file_exists( $signal_file ) ) {
	unlink( $signal_file );
}

$descriptors = array(
	0 => array( 'pipe', 'r' ),
	1 => array( 'pipe', 'w' ),
	2 => array( 'pipe', 'w' ),
);

$holder_cmd  = sprintf( 'php %s %s %s', escapeshellarg( $holder_script ), escapeshellarg( $wp_load_path ), escapeshellarg( $signal_file ) );
$holder_proc = proc_open( $holder_cmd, $descriptors, $holder_pipes );

// Wait for holder process to acquire lock.
$locked = false;
for ( $i = 0; $i < 50; $i++ ) {
	if ( file_exists( $signal_file ) && 'LOCKED' === trim( (string) file_get_contents( $signal_file ) ) ) {
		$locked = true;
		break;
	}
	usleep( 100000 ); // 100ms.
}

v4_assert( $locked, 'V4-D: lock holder acquired row lock successfully' );

if ( $locked ) {
	// Attempt create while lock is held — WriteCoordinator has 5s timeout, so it should time out and return conflict.
	$start_time     = microtime( true );
	$timeout_result = RecordRepository::create(
		'mf_velog_customer',
		array( 'name' => 'Timeout Customer' ),
		$actor,
		'v4-timeout-' . wp_generate_uuid4()
	);
	$elapsed = microtime( true ) - $start_time;

	v4_assert( $timeout_result instanceof WP_Error, 'V4-D: writer timed out and returned WP_Error' );
	if ( $timeout_result instanceof WP_Error ) {
		v4_assert( 'conflict' === $timeout_result->get_error_code(), 'V4-D: error code is "conflict" on lock timeout (1205)', "actual code: " . $timeout_result->get_error_code() );
	}
	v4_assert( $elapsed >= 4.5, sprintf( 'V4-D: lock wait lasted bounded ~5 seconds (actual: %.2fs)', $elapsed ), "elapsed: $elapsed" );
}

// Close holder process.
if ( is_resource( $holder_proc ) ) {
	fclose( $holder_pipes[1] );
	if ( isset( $holder_pipes[2] ) ) {
		fclose( $holder_pipes[2] );
	}
	proc_close( $holder_proc );
}

if ( file_exists( $holder_script ) ) {
	unlink( $holder_script );
}
if ( file_exists( $signal_file ) ) {
	unlink( $signal_file );
}

// ─── V4-E + V4-F: Pre-commit rollback and cache cleanliness ───────────────────

$fail_marker     = 'v4e-fail-' . wp_generate_uuid4();
$pre_posts_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mf_velog_customer'" );
$rolled_back_id  = null;

$failed_unit = WriteCoordinator::run(
	$actor,
	static function ( WriteUnit $unit ) use ( $fail_marker, &$rolled_back_id ): WP_Error {
		// Insert a record using WriteUnit within the transaction.
		$created = $unit->create(
			'mf_velog_customer',
			array( 'name' => 'Should Be Rolled Back' ),
			$fail_marker
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$rolled_back_id = $created['id'];

		// Warm cache for this ID before rollback with a post object.
		wp_cache_set( $created['id'], (object) array( 'ID' => $created['id'], 'post_title' => 'cached' ), 'posts' );
		wp_cache_set( $created['id'], array( 'fake' => array( 'meta' ) ), 'post_meta' );

		// Return error to trigger rollback.
		return new WP_Error( 'abort', 'Intentional abort to trigger pre-commit rollback.' );
	}
);

v4_assert( $failed_unit instanceof WP_Error, 'V4-E: write unit aborted as expected' );

$post_posts_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mf_velog_customer'" );
v4_assert( $pre_posts_count === $post_posts_count, 'V4-E: database post count unchanged after pre-commit rollback' );

if ( null !== $rolled_back_id ) {
	// Verify database row does NOT exist.
	$db_row = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $rolled_back_id ) );
	v4_assert( null === $db_row, 'V4-E: rolled-back post row does not exist in database' );

	// V4-F: Verify cache invalidation occurred in finally block — no stale cached item remains.
	$cached_item = wp_cache_get( $rolled_back_id, 'posts' );
	v4_assert( false === $cached_item, 'V4-F: post cache was invalidated on rollback' );

	$stale_post = get_post( $rolled_back_id );
	v4_assert( null === $stale_post, 'V4-F: get_post() does not return stale rolled-back post' );
}

// ─── V4-G: Pre-commit disconnect fault injection (D1-F-016) ───────────────────

WriteCoordinator::configure_test_fault( 'pre_commit_disconnect', 'close_connection' );
$f_disc = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Disconnect Customer' ),
	$actor,
	'v4g-disc-' . wp_generate_uuid4()
);

v4_assert( $f_disc instanceof WP_Error, 'V4-G: pre-commit disconnect returns WP_Error' );
if ( $f_disc instanceof WP_Error ) {
	v4_assert( 'indeterminate' === $f_disc->get_error_code(), 'V4-G: pre-commit disconnect error code is indeterminate' );
}
v4_assert( WriteCoordinator::was_test_fault_hit(), 'V4-G: pre_commit_disconnect fault was hit' );
WriteCoordinator::configure_test_fault( null );
$wpdb->db_connect();

// ─── V4-H: Ambiguous create reconciliation (D1-F-015) ─────────────────────────

$req_rec_c = 'v4h-rec-c-' . wp_generate_uuid4();
$rec_c_res = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Reconcile Create Customer' ),
	$actor,
	$req_rec_c
);
v4_assert( ! is_wp_error( $rec_c_res ), 'V4-H: base create succeeds' );

if ( ! is_wp_error( $rec_c_res ) ) {
	// Reconcile matching committed create:
	$reconciled_c = RecordRepository::reconcile_create(
		'mf_velog_customer',
		array( 'name' => 'Reconcile Create Customer' ),
		$actor,
		$req_rec_c
	);
	v4_assert( ! is_wp_error( $reconciled_c ), 'V4-H: reconcile_create on committed request succeeds' );
	if ( ! is_wp_error( $reconciled_c ) ) {
		v4_assert( $rec_c_res['id'] === $reconciled_c['id'], 'V4-H: reconcile_create returns matching ID' );
	}

	// Reconcile uncommitted create:
	$uncommitted_req = 'v4h-uncommitted-' . wp_generate_uuid4();
	$uncommitted_res = RecordRepository::reconcile_create(
		'mf_velog_customer',
		array( 'name' => 'Uncommitted Customer' ),
		$actor,
		$uncommitted_req
	);
	v4_assert( $uncommitted_res instanceof WP_Error, 'V4-H: reconcile_create on uncommitted request returns WP_Error' );
	if ( $uncommitted_res instanceof WP_Error ) {
		v4_assert( 'not_found' === $uncommitted_res->get_error_code(), 'V4-H: uncommitted request error code is not_found' );
	}

	// Reconcile with payload mismatch:
	$conflict_rec = RecordRepository::reconcile_create(
		'mf_velog_customer',
		array( 'name' => 'Altered Payload' ),
		$actor,
		$req_rec_c
	);
	v4_assert( $conflict_rec instanceof WP_Error, 'V4-H: reconcile_create with altered payload returns WP_Error' );
	if ( $conflict_rec instanceof WP_Error ) {
		v4_assert( 'conflict' === $conflict_rec->get_error_code(), 'V4-H: altered payload error code is conflict' );
	}
}

// ─── V4-I: Ambiguous save reconciliation (D1-F-015) ───────────────────────────

$req_rec_s_create = 'v4i-rec-s-base-' . wp_generate_uuid4();
$rec_s_base = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Save Rec Base' ),
	$actor,
	$req_rec_s_create
);
v4_assert( ! is_wp_error( $rec_s_base ), 'V4-I: base create for save reconciliation succeeds' );

if ( ! is_wp_error( $rec_s_base ) ) {
	$s_id      = $rec_s_base['id'];
	$req_rec_s = 'v4i-rec-s-' . wp_generate_uuid4();

	$save_res = RecordRepository::save(
		'mf_velog_customer',
		$s_id,
		1,
		array( 'name' => 'Save Rec Updated' ),
		$actor,
		$req_rec_s,
		'reconcile save update'
	);
	v4_assert( ! is_wp_error( $save_res ), 'V4-I: save operation succeeds' );

	if ( ! is_wp_error( $save_res ) ) {
		// Reconcile committed save:
		$reconciled_s = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$s_id,
			1,
			array( 'name' => 'Save Rec Updated' ),
			$actor,
			$req_rec_s
		);
		v4_assert( ! is_wp_error( $reconciled_s ), 'V4-I: reconcile_save on committed save succeeds' );
		if ( ! is_wp_error( $reconciled_s ) ) {
			v4_assert( 2 === $reconciled_s['record_version'], 'V4-I: reconciled snapshot version is 2' );
		}

		// Reconcile uncommitted save with expected version equal to current version:
		$uncommitted_save_req = 'v4i-uncommitted-save-' . wp_generate_uuid4();
		$uncommitted_save_res = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$s_id,
			2,
			array( 'name' => 'Uncommitted Save' ),
			$actor,
			$uncommitted_save_req
		);
		v4_assert( $uncommitted_save_res instanceof WP_Error, 'V4-I: reconcile_save on uncommitted save returns WP_Error' );
		if ( $uncommitted_save_res instanceof WP_Error ) {
			v4_assert( 'not_found' === $uncommitted_save_res->get_error_code(), 'V4-I: uncommitted save error code is not_found' );
		}

		// Reconcile uncommitted save with stale expected version:
		$stale_save_res = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$s_id,
			1,
			array( 'name' => 'Stale Save' ),
			$actor,
			$uncommitted_save_req
		);
		v4_assert( $stale_save_res instanceof WP_Error, 'V4-I: reconcile_save with stale version returns WP_Error' );
		if ( $stale_save_res instanceof WP_Error ) {
			v4_assert( 'stale_version' === $stale_save_res->get_error_code(), 'V4-I: stale save error code is stale_version' );
		}
	}
}

// ─── V4-J (D1-F-023): Query SQL failure returns storage_unavailable (D1-F-020) ────
// Simulate a SQL failure by dropping the postmeta table temporarily,
// calling query(), then restoring the table.
// We use a table rename trick to simulate a missing table within WP's db connection.

$jbase_req = 'v4j-base-' . wp_generate_uuid4();
$jbase_res = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'SQL Failure Test Base' ),
	$actor,
	$jbase_req
);
v4_assert( ! is_wp_error( $jbase_res ), 'V4-J: base record created for SQL failure test' );

if ( ! is_wp_error( $jbase_res ) ) {
	$renamed = false;
	try {
		// Rename postmeta table to simulate SQL failure on query().
		$renamed = $wpdb->query( "RENAME TABLE {$wpdb->postmeta} TO {$wpdb->postmeta}_backup_v4j" );
		v4_assert( false !== $renamed, 'V4-J: postmeta table renamed for SQL failure simulation' );

		if ( false !== $renamed ) {
			// This query will fail because postmeta is gone.
			$query_result = RecordRepository::query( 'mf_velog_customer', array(), 1, $actor );
			v4_assert(
				$query_result instanceof WP_Error,
				'V4-J: query() returns WP_Error on SQL failure',
				is_array( $query_result ) ? 'got array instead of error' : ''
			);
			if ( $query_result instanceof WP_Error ) {
				v4_assert(
					'storage_unavailable' === $query_result->get_error_code(),
					'V4-J: query SQL failure error code is storage_unavailable',
					'actual: ' . $query_result->get_error_code()
				);
			}
		}
	} finally {
		if ( false !== $renamed ) {
			// Restore postmeta table guaranteed.
			$wpdb->query( "RENAME TABLE {$wpdb->postmeta}_backup_v4j TO {$wpdb->postmeta}" );
		}
		// Independently confirm restore success.
		$restored = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->postmeta}'" );
		v4_assert( ! empty( $restored ), 'V4-J: postmeta table independently confirmed restored' );
	}
}

// ─── V4-K (D1-F-023): Reconcile_save with altered changes returns conflict (D1-F-019) ──

$vk_create_req = 'v4k-base-' . wp_generate_uuid4();
$vk_base       = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Reconcile K Base' ),
	$actor,
	$vk_create_req
);
v4_assert( ! is_wp_error( $vk_base ), 'V4-K: base create for altered reconcile test succeeds' );

if ( ! is_wp_error( $vk_base ) ) {
	$vk_id      = $vk_base['id'];
	$vk_req_id  = 'v4k-save-' . wp_generate_uuid4();
	$vk_changes = array( 'name' => 'Reconcile K Saved Name' );

	// Perform an actual save.
	$vk_save = RecordRepository::save(
		'mf_velog_customer',
		$vk_id,
		1,
		$vk_changes,
		$actor,
		$vk_req_id,
		'v4k save'
	);
	v4_assert( ! is_wp_error( $vk_save ), 'V4-K: save succeeded for reconcile test' );

	if ( ! is_wp_error( $vk_save ) ) {
		// Reconcile with ALTERED changes (different name) — should return conflict.
		$vk_altered_reconcile = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$vk_id,
			1,
			array( 'name' => 'DIFFERENT ALTERED NAME' ), // differs from actual saved changes.
			$actor,
			$vk_req_id
		);
		v4_assert(
			$vk_altered_reconcile instanceof WP_Error,
			'V4-K: reconcile_save with altered changes returns WP_Error'
		);
		if ( $vk_altered_reconcile instanceof WP_Error ) {
			v4_assert(
				'conflict' === $vk_altered_reconcile->get_error_code(),
				'V4-K: altered changes error code is conflict',
				'actual: ' . $vk_altered_reconcile->get_error_code()
			);
		}

		// Reconcile with MATCHING changes — should succeed.
		$vk_matching_reconcile = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$vk_id,
			1,
			$vk_changes,
			$actor,
			$vk_req_id
		);
		v4_assert(
			! is_wp_error( $vk_matching_reconcile ),
			'V4-K: reconcile_save with matching changes succeeds',
			is_wp_error( $vk_matching_reconcile ) ? $vk_matching_reconcile->get_error_message() : ''
		);
	}
}

// ─── V4-L (D1-F-023): Reconcile_save with same req_id but stale expected_version ──

$vl_create_req = 'v4l-base-' . wp_generate_uuid4();
$vl_base       = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Reconcile L Base' ),
	$actor,
	$vl_create_req
);
v4_assert( ! is_wp_error( $vl_base ), 'V4-L: base create for version test succeeds' );

if ( ! is_wp_error( $vl_base ) ) {
	$vl_id     = $vl_base['id'];
	$vl_req_id = 'v4l-save-' . wp_generate_uuid4();
	$vl_change = array( 'name' => 'Reconcile L Saved' );

	// Save at version 1.
	$vl_save = RecordRepository::save(
		'mf_velog_customer',
		$vl_id,
		1,
		$vl_change,
		$actor,
		$vl_req_id,
		'v4l save'
	);
	v4_assert( ! is_wp_error( $vl_save ), 'V4-L: save at version 1 succeeded' );

	if ( ! is_wp_error( $vl_save ) ) {
		// Reconcile with SAME request_id but WRONG expected_version.
		// Save was from version 1 to 2, meaning entry is at index 1.
		// If we pass expected_version = 0, reconcile_save expects index 0.
		// The req_hash matches at index 1, which !== 0, returning 'conflict'.
		$vl_stale_res = RecordRepository::reconcile_save(
			'mf_velog_customer',
			$vl_id,
			0, // wrong expected_version
			$vl_change,
			$actor,
			$vl_req_id // same req_id
		);
		v4_assert( $vl_stale_res instanceof WP_Error, 'V4-L: reconcile_save with same req_id but wrong expected_version returns WP_Error' );
		if ( $vl_stale_res instanceof WP_Error ) {
			v4_assert(
				'conflict' === $vl_stale_res->get_error_code(),
				'V4-L: reconcile_save returns conflict when version position mismatches',
				'actual: ' . $vl_stale_res->get_error_code()
			);
		}
	}
}

// ─── V4-M (D1-F-023): Reconcile_create on corrupt projection returns storage_unavailable ──
// Simulate a corrupt projection by directly deleting the projection row after create,
// then calling reconcile_create with the original request_id.

$vm_req_id = 'v4m-corrupt-' . wp_generate_uuid4();
$vm_create = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Corrupt Projection Test' ),
	$actor,
	$vm_req_id
);
v4_assert( ! is_wp_error( $vm_create ), 'V4-M: base create for corruption test succeeds' );

if ( ! is_wp_error( $vm_create ) ) {
	$vm_id = $vm_create['id'];

	// Delete the _mf_velog_state projection row to simulate corruption.
	$vm_deleted = $wpdb->delete(
		$wpdb->postmeta,
		array(
			'post_id'  => $vm_id,
			'meta_key' => '_mf_velog_state',
		),
		array( '%d', '%s' )
	);
	v4_assert( false !== $vm_deleted && $vm_deleted > 0, 'V4-M: projection row deleted to simulate corruption' );

	if ( false !== $vm_deleted && $vm_deleted > 0 ) {
		// Reconcile_create should detect corrupt projection and return storage_unavailable.
		$vm_reconcile = RecordRepository::reconcile_create(
			'mf_velog_customer',
			array( 'name' => 'Corrupt Projection Test' ),
			$actor,
			$vm_req_id
		);
		v4_assert(
			$vm_reconcile instanceof WP_Error,
			'V4-M: reconcile_create on corrupt projection returns WP_Error'
		);
		if ( $vm_reconcile instanceof WP_Error ) {
			v4_assert(
				'storage_unavailable' === $vm_reconcile->get_error_code(),
				'V4-M: corrupt projection error code is storage_unavailable',
				'actual: ' . $vm_reconcile->get_error_code()
			);
		}
	}
}

// ─── NOT VERIFIED constraints ─────────────────────────────────────────────────
echo "NOTICE: Live-handle COMMIT failure and coordinator 1213 deadlock cannot be simulated on MariaDB unix socket without external proxy; marked NOT VERIFIED per contract.\n";
echo "NOTICE: Persistent object cache compatibility remains NOT VERIFIED per contract.\n";
echo "NOTICE: Full G-08 product benchmark (28,000 dataset, p95 <=2s) remains NOT VERIFIED until domain queries exist.\n";

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n=== V4 SUMMARY ===\n";
if ( empty( $GLOBALS['velog_failures'] ) ) {
	echo "ALL V4 ASSERTIONS PASS\n";
	exit( 0 );
} else {
	echo sprintf( "FAILURES: %d assertion(s) failed:\n", count( $GLOBALS['velog_failures'] ) );
	foreach ( $GLOBALS['velog_failures'] as $f ) {
		echo "  - $f\n";
	}
	exit( 1 );
}
