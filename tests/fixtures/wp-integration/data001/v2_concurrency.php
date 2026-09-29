<?php
/**
 * V2: Concurrency, race conditions, stale versions, and idempotency integration probe.
 *
 * Real multi-process tests:
 *   V2-A: Stale version save returns 'stale_version' WP_Error.
 *   V2-B: Same request_id + same payload = idempotent create returns existing snapshot.
 *   V2-C: Same request_id + different payload = 'conflict' WP_Error.
 *   V2-C2: Same request_id + different type = 'conflict' WP_Error (D1-F-004).
 *   V2-C3: Corrupted idempotency marker = 'conflict' WP_Error (D1-F-004).
 *   V2-D: Multi-record atomic rollback: failure in write unit rolls back all records (D1-F-012).
 *   V2-D2: Multi-record atomic commit: successful write unit commits multiple records.
 *   V2-E: Real concurrent race on unique key (2 subprocesses compete on same VIN) -> 1 win, 1 conflict (D1-F-003, D1-F-005).
 *   V2-F: Real concurrent version race on save (2 subprocesses compete on same record) -> 1 win, 1 stale_version (D1-F-005).
 *
 * Exit 0 = PASS, Exit 1 = FAIL.
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
function v2_assert( bool $cond, string $label, string $details = '' ): void {
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

// Reset and register explicit test schemas.
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
RecordSchema::register(
	'mf_velog_vehicle',
	array(
		'field_policies'  => array(
			'vin'  => 'public',
			'make' => 'public',
		),
		'fields'          => array(
			'vin'  => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
			'make' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
		),
		'states'          => array( 'active', 'archived' ),
		'capability'      => 'mf_velog_manage_vehicles',
		'read_capability' => 'mf_velog_read_records',
		'projections'     => array( 'vin' ),
		'unique_keys'     => array( 'unique_vin' => array( 'vin' ) ),
	)
);
RecordSchema::seal();

// Ensure test admin actor has all capabilities.
$actor = new WP_User( 1 );
$actor->add_cap( 'mf_velog_manage_customers' );
$actor->add_cap( 'mf_velog_manage_vehicles' );
$actor->add_cap( 'mf_velog_read_records' );
$actor->add_cap( 'mf_velog_read_customer_contacts' );

WriteCoordinator::ensure_lock_row();

// ─── V2-B: Idempotent create (same request_id, same payload) ──────────────────

$req_id_b = 'v2b-idem-' . wp_generate_uuid4();
$r1       = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Idem Customer' ), $actor, $req_id_b );
v2_assert( ! is_wp_error( $r1 ), 'V2-B: first create succeeds', is_wp_error( $r1 ) ? $r1->get_error_message() : '' );

if ( ! ( $r1 instanceof WP_Error ) ) {
	$r1_id = $r1['id'];

	$r2 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Idem Customer' ), $actor, $req_id_b );
	v2_assert( ! is_wp_error( $r2 ), 'V2-B: idempotent create returns success', is_wp_error( $r2 ) ? $r2->get_error_message() : '' );

	if ( ! ( $r2 instanceof WP_Error ) ) {
		v2_assert( $r1_id === $r2['id'], 'V2-B: idempotent create returns same record ID' );
		v2_assert( $r1['record_version'] === $r2['record_version'], 'V2-B: record_version unchanged' );
	}
}

// ─── V2-C: Conflicting create (same request_id, different payload) ────────────

$req_id_c = 'v2c-conflict-' . wp_generate_uuid4();
$orig = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Original Name' ), $actor, $req_id_c );
v2_assert( ! is_wp_error( $orig ), 'V2-C: initial create succeeds', is_wp_error( $orig ) ? $orig->get_error_message() : '' );

$conflict = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Forged Name' ), $actor, $req_id_c );
v2_assert( $conflict instanceof WP_Error, 'V2-C: conflicting payload returns WP_Error' );
if ( $conflict instanceof WP_Error ) {
	v2_assert( 'conflict' === $conflict->get_error_code(), 'V2-C: error code is conflict' );
}

// ─── V2-C2: Conflicting create (same request_id, different type) ──────────────

$req_id_c2 = 'v2c2-crosstype-' . wp_generate_uuid4();
$orig_c2 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Customer Type' ), $actor, $req_id_c2 );
v2_assert( ! is_wp_error( $orig_c2 ), 'V2-C2: initial create succeeds', is_wp_error( $orig_c2 ) ? $orig_c2->get_error_message() : '' );

$cross_type = RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => 'VIN-CROSS-001' ), $actor, $req_id_c2 );
v2_assert( $cross_type instanceof WP_Error, 'V2-C2: cross-type request_id reuse returns WP_Error' );
if ( $cross_type instanceof WP_Error ) {
	v2_assert( 'conflict' === $cross_type->get_error_code(), 'V2-C2: error code is conflict' );
}

// ─── V2-C3: Corrupted idempotency marker returns conflict ─────────────────────

$req_id_c3 = 'v2c3-corrupt-' . wp_generate_uuid4();
$corrupt_key = 'mf_velog_create_' . hash( 'sha256', $req_id_c3 );
update_option( $corrupt_key, 'corrupted_string_not_an_array', false );

$corrupt_res = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Corrupt Test' ), $actor, $req_id_c3 );
v2_assert( $corrupt_res instanceof WP_Error, 'V2-C3: corrupted marker returns WP_Error' );
if ( $corrupt_res instanceof WP_Error ) {
	v2_assert( 'conflict' === $corrupt_res->get_error_code(), 'V2-C3: error code is conflict' );
}

// ─── V2-A: Stale version save ─────────────────────────────────────────────────

$req_id_a = 'v2a-stale-' . wp_generate_uuid4();
$r_a      = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Stale Test' ), $actor, $req_id_a );
v2_assert( ! is_wp_error( $r_a ), 'V2-A: created record for stale test', is_wp_error( $r_a ) ? $r_a->get_error_message() : '' );

if ( ! ( $r_a instanceof WP_Error ) ) {
	$stale_id = $r_a['id'];

	// Save with correct version first.
	$save1 = RecordRepository::save(
		'mf_velog_customer',
		$stale_id,
		1,
		array( 'name' => 'Updated Name' ),
		$actor,
		'v2a-save1-' . wp_generate_uuid4(),
		'first update'
	);
	v2_assert( ! is_wp_error( $save1 ), 'V2-A: first save succeeds', is_wp_error( $save1 ) ? $save1->get_error_message() : '' );

	// Save with stale version (still 1, but now it's 2).
	$save2_stale = RecordRepository::save(
		'mf_velog_customer',
		$stale_id,
		1,
		array( 'name' => 'Stale Write' ),
		$actor,
		'v2a-save2-' . wp_generate_uuid4(),
		'stale update'
	);
	v2_assert( $save2_stale instanceof WP_Error, 'V2-A: stale save returns WP_Error' );
	if ( $save2_stale instanceof WP_Error ) {
		v2_assert( 'stale_version' === $save2_stale->get_error_code(), 'V2-A: error code is stale_version' );
	}
}

// ─── V2-D: Multi-record atomic rollback (D1-F-012) ───────────────────────────

$marker_id    = 'v2d-multi-' . wp_generate_uuid4();
$posts_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mf_velog_customer'" );

$rollback_result = WriteCoordinator::run(
	$actor,
	static function ( WriteUnit $unit ) use ( $marker_id ): WP_Error {
		// Create first record inside transaction using WriteUnit.
		$res1 = $unit->create(
			'mf_velog_customer',
			array( 'name' => 'Rollback Customer 1' ),
			$marker_id . '-1'
		);
		if ( is_wp_error( $res1 ) ) {
			return $res1;
		}

		// Force failure on second record.
		return new WP_Error( 'forced_failure', 'Simulated failure in multi-record unit.' );
	}
);

v2_assert( $rollback_result instanceof WP_Error, 'V2-D: write unit returned error as expected' );
$posts_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mf_velog_customer'" );
v2_assert( $posts_before === $posts_after, 'V2-D: post count unchanged after multi-record rollback' );

// ─── V2-D2: Multi-record atomic commit ────────────────────────────────────────

$commit_marker = 'v2d2-commit-' . wp_generate_uuid4();
$commit_result = WriteCoordinator::run(
	$actor,
	static function ( WriteUnit $unit ) use ( $commit_marker ): array|WP_Error {
		$c1 = $unit->create(
			'mf_velog_customer',
			array( 'name' => 'Multi Customer 1' ),
			$commit_marker . '-c1'
		);
		if ( is_wp_error( $c1 ) ) {
			return $c1;
		}
		$v1 = $unit->create(
			'mf_velog_vehicle',
			array( 'vin' => 'VIN-MULTI-' . wp_generate_uuid4() ),
			$commit_marker . '-v1'
		);
		if ( is_wp_error( $v1 ) ) {
			return $v1;
		}
		return array( 'customer' => $c1, 'vehicle' => $v1 );
	}
);
v2_assert( ! is_wp_error( $commit_result ), 'V2-D2: multi-record unit commits both records successfully', is_wp_error( $commit_result ) ? $commit_result->get_error_message() : '' );

// ─── Real concurrent subprocess tests (V2-E, V2-F) ────────────────────────────

$wp_load_path  = ABSPATH . 'wp-load.php';
$worker_script = sys_get_temp_dir() . '/velog_v2_worker_' . getmypid() . '.php';

// Generate worker script for concurrent testing.
$worker_code = <<<'PHP'
<?php
define( 'WP_USE_THEMES', false );
define( 'WP_CLI', true );
$_SERVER['HTTP_HOST']      = 'velog-test.local';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $argv[1];

$action = $argv[2] ?? '';
$param1 = $argv[3] ?? '';
$param2 = $argv[4] ?? '';

use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\RecordRepository;

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
RecordSchema::register(
	'mf_velog_vehicle',
	array(
		'field_policies'  => array(
			'vin'  => 'public',
			'make' => 'public',
		),
		'fields'          => array(
			'vin'  => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
			'make' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
		),
		'states'          => array( 'active', 'archived' ),
		'capability'      => 'mf_velog_manage_vehicles',
		'read_capability' => 'mf_velog_read_records',
		'projections'     => array( 'vin' ),
		'unique_keys'     => array( 'unique_vin' => array( 'vin' ) ),
	)
);
RecordSchema::seal();

$actor = new WP_User( 1 );
$actor->add_cap( 'mf_velog_manage_vehicles' );
$actor->add_cap( 'mf_velog_manage_customers' );
$actor->add_cap( 'mf_velog_read_records' );

if ( 'create_vehicle' === $action ) {
	$vin = $param1;
	$req = 'race-' . wp_generate_uuid4();
	$res = RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => $vin ), $actor, $req );
	if ( $res instanceof WP_Error ) {
		echo json_encode( array( 'status' => 'error', 'code' => $res->get_error_code() ) );
		exit( 2 );
	}
	echo json_encode( array( 'status' => 'ok', 'id' => $res['id'] ) );
	exit( 0 );
}

if ( 'save_customer' === $action ) {
	$id       = (int) $param1;
	$expected = (int) $param2;
	$req      = 'race-save-' . wp_generate_uuid4();
	$res      = RecordRepository::save(
		'mf_velog_customer',
		$id,
		$expected,
		array( 'name' => 'Winner-' . getmypid() ),
		$actor,
		$req,
		'concurrent race'
	);
	if ( $res instanceof WP_Error ) {
		echo json_encode( array( 'status' => 'error', 'code' => $res->get_error_code() ) );
		exit( 3 );
	}
	echo json_encode( array( 'status' => 'ok', 'id' => $res['id'], 'version' => $res['record_version'] ) );
	exit( 0 );
}

echo json_encode( array( 'status' => 'unknown_action', 'action' => $action ) );
exit( 1 );
PHP;

file_put_contents( $worker_script, $worker_code );

// V2-E: Concurrent Unique Key Race.
$race_vin = 'VIN-RACE-' . wp_generate_uuid4();

$descriptors = array(
	0 => array( 'pipe', 'r' ),
	1 => array( 'pipe', 'w' ),
	2 => array( 'pipe', 'w' ),
);

$cmd1 = sprintf( 'php %s %s create_vehicle %s', escapeshellarg( $worker_script ), escapeshellarg( $wp_load_path ), escapeshellarg( $race_vin ) );
$cmd2 = sprintf( 'php %s %s create_vehicle %s', escapeshellarg( $worker_script ), escapeshellarg( $wp_load_path ), escapeshellarg( $race_vin ) );

$p1 = proc_open( $cmd1, $descriptors, $pipes1 );
$p2 = proc_open( $cmd2, $descriptors, $pipes2 );

$out1 = stream_get_contents( $pipes1[1] );
$out2 = stream_get_contents( $pipes2[1] );
fclose( $pipes1[1] );
fclose( $pipes2[1] );
$err1 = isset( $pipes1[2] ) ? stream_get_contents( $pipes1[2] ) : '';
$err2 = isset( $pipes2[2] ) ? stream_get_contents( $pipes2[2] ) : '';
if ( isset( $pipes1[2] ) ) {
	fclose( $pipes1[2] );
}
if ( isset( $pipes2[2] ) ) {
	fclose( $pipes2[2] );
}

$exit1 = proc_close( $p1 );
$exit2 = proc_close( $p2 );

$exits = array( $exit1, $exit2 );
sort( $exits );

v2_assert( array( 0, 2 ) === $exits, 'V2-E: exactly one worker succeeded (exit 0) and one failed with conflict (exit 2)', "actual exits: " . implode( ',', $exits ) . " | out1: $out1 | out2: $out2 | err1: $err1 | err2: $err2" );

$vin_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'vin' AND meta_value = %s",
		$race_vin
	)
);
v2_assert( 1 === $vin_count, 'V2-E: exactly one vehicle record exists in database for race VIN', "actual count: $vin_count" );

// V2-F: Concurrent Save Version Race.
$race_customer = RecordRepository::create(
	'mf_velog_customer',
	array( 'name' => 'Race Base Customer' ),
	$actor,
	'v2f-base-' . wp_generate_uuid4()
);
v2_assert( ! is_wp_error( $race_customer ), 'V2-F: created base customer for save race', is_wp_error( $race_customer ) ? $race_customer->get_error_message() : '' );

if ( ! ( $race_customer instanceof WP_Error ) ) {
	$cid = $race_customer['id'];

	$cmd_save1 = sprintf( 'php %s %s save_customer %d 1', escapeshellarg( $worker_script ), escapeshellarg( $wp_load_path ), $cid );
	$cmd_save2 = sprintf( 'php %s %s save_customer %d 1', escapeshellarg( $worker_script ), escapeshellarg( $wp_load_path ), $cid );

	$sp1 = proc_open( $cmd_save1, $descriptors, $spipes1 );
	$sp2 = proc_open( $cmd_save2, $descriptors, $spipes2 );

	$sout1 = stream_get_contents( $spipes1[1] );
	$sout2 = stream_get_contents( $spipes2[1] );
	fclose( $spipes1[1] );
	fclose( $spipes2[1] );
	$serr1 = isset( $spipes1[2] ) ? stream_get_contents( $spipes1[2] ) : '';
	$serr2 = isset( $spipes2[2] ) ? stream_get_contents( $spipes2[2] ) : '';
	if ( isset( $spipes1[2] ) ) {
		fclose( $spipes1[2] );
	}
	if ( isset( $spipes2[2] ) ) {
		fclose( $spipes2[2] );
	}

	$sexit1 = proc_close( $sp1 );
	$sexit2 = proc_close( $sp2 );

	$sexits = array( $sexit1, $sexit2 );
	sort( $sexits );

	v2_assert( array( 0, 3 ) === $sexits, 'V2-F: exactly one save succeeded (exit 0) and one received stale_version (exit 3)', "actual exits: " . implode( ',', $sexits ) . " | sout1: $sout1 | sout2: $sout2 | serr1: $serr1 | serr2: $serr2" );

	$final_customer = RecordRepository::get( 'mf_velog_customer', $cid, $actor );
	v2_assert( ! is_wp_error( $final_customer ), 'V2-F: re-read customer succeeds', is_wp_error( $final_customer ) ? $final_customer->get_error_message() : '' );
	if ( ! ( $final_customer instanceof WP_Error ) ) {
		v2_assert( 2 === $final_customer['record_version'], 'V2-F: record_version is exactly 2' );
		v2_assert( 2 === count( $final_customer['audit'] ), 'V2-F: audit log has exactly 2 entries (create + 1 save)' );
	}
}

// ─── V2-G: Statement fault injection controls (D1-F-016) ──────────────────────

$g_posts_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('mf_velog_customer', 'mf_velog_vehicle')" );
$g_pm_before    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );

// 1. Fault: wp_posts_insert
WriteCoordinator::configure_test_fault( 'wp_posts_insert' );
$f1 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Fault 1' ), $actor, 'v2g-f1-' . wp_generate_uuid4() );
v2_assert( $f1 instanceof WP_Error, 'V2-G1: wp_posts_insert triggers WP_Error' );
if ( $f1 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f1->get_error_code(), 'V2-G1: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G1: wp_posts_insert fault seam was hit' );

// 2. Fault: envelope_meta_insert
WriteCoordinator::configure_test_fault( 'envelope_meta_insert' );
$f2 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Fault 2' ), $actor, 'v2g-f2-' . wp_generate_uuid4() );
v2_assert( $f2 instanceof WP_Error, 'V2-G2: envelope_meta_insert triggers WP_Error' );
if ( $f2 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f2->get_error_code(), 'V2-G2: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G2: envelope_meta_insert fault seam was hit' );

// 3. Fault: custom_projection_insert
WriteCoordinator::configure_test_fault( 'custom_projection_insert' );
$f3 = RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => 'VIN-FAULT-PROJ' ), $actor, 'v2g-f3-' . wp_generate_uuid4() );
v2_assert( $f3 instanceof WP_Error, 'V2-G3: custom_projection_insert triggers WP_Error' );
if ( $f3 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f3->get_error_code(), 'V2-G3: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G3: custom_projection_insert fault seam was hit' );

// 4. Fault: create_marker_insert
WriteCoordinator::configure_test_fault( 'create_marker_insert' );
$f4 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Fault 4' ), $actor, 'v2g-f4-' . wp_generate_uuid4() );
v2_assert( $f4 instanceof WP_Error, 'V2-G4: create_marker_insert triggers WP_Error' );
if ( $f4 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f4->get_error_code(), 'V2-G4: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G4: create_marker_insert fault seam was hit' );

// 5. Fault: verify_write
WriteCoordinator::configure_test_fault( 'verify_write' );
$f5 = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Fault 5' ), $actor, 'v2g-f5-' . wp_generate_uuid4() );
v2_assert( $f5 instanceof WP_Error, 'V2-G5: verify_write triggers WP_Error' );
if ( $f5 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f5->get_error_code(), 'V2-G5: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G5: verify_write fault seam was hit' );

// Verify database clean rollback after all create faults:
$g_posts_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('mf_velog_customer', 'mf_velog_vehicle')" );
$g_pm_after    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );
v2_assert( $g_posts_before === $g_posts_after, 'V2-G: post count unchanged after create fault rollbacks' );
v2_assert( $g_pm_before === $g_pm_after, 'V2-G: postmeta count unchanged after create fault rollbacks' );

// Now test save statement faults:
WriteCoordinator::configure_test_fault( null );
$base_cust = RecordRepository::create( 'mf_velog_customer', array( 'name' => 'Save Fault Base' ), $actor, 'v2g-save-base-' . wp_generate_uuid4() );
v2_assert( ! is_wp_error( $base_cust ), 'V2-G: created base customer for save fault tests' );
$base_id = $base_cust['id'];

// 6. Fault: envelope_meta_update
WriteCoordinator::configure_test_fault( 'envelope_meta_update' );
$f6 = RecordRepository::save( 'mf_velog_customer', $base_id, 1, array( 'name' => 'Envelope Fail' ), $actor, 'v2g-f6-' . wp_generate_uuid4() );
v2_assert( $f6 instanceof WP_Error, 'V2-G6: envelope_meta_update triggers WP_Error' );
if ( $f6 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f6->get_error_code(), 'V2-G6: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G6: envelope_meta_update fault seam was hit' );

// 7. Fault: custom_projection_insert on save
$base_veh = RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => 'VIN-SAVE-FAULT' ), $actor, 'v2g-save-veh-' . wp_generate_uuid4() );
v2_assert( ! is_wp_error( $base_veh ), 'V2-G: created base vehicle for projection save fault test' );
$veh_id = $base_veh['id'];

WriteCoordinator::configure_test_fault( 'custom_projection_insert' );
$f7 = RecordRepository::save( 'mf_velog_vehicle', $veh_id, 1, array( 'vin' => 'VIN-SAVE-FAULT2' ), $actor, 'v2g-f7-' . wp_generate_uuid4() );
v2_assert( $f7 instanceof WP_Error, 'V2-G7: custom_projection_insert on save triggers WP_Error' );
if ( $f7 instanceof WP_Error ) {
	v2_assert( 'storage_unavailable' === $f7->get_error_code(), 'V2-G7: error code is storage_unavailable' );
}
v2_assert( WriteCoordinator::was_test_fault_hit(), 'V2-G7: custom_projection_insert on save fault seam was hit' );

// Verify records remained at version 1:
$check_c = RecordRepository::get( 'mf_velog_customer', $base_id, $actor );
v2_assert( 1 === ( $check_c['record_version'] ?? 0 ), 'V2-G: customer remained at version 1 after failed save' );

$check_v = RecordRepository::get( 'mf_velog_vehicle', $veh_id, $actor );
v2_assert( 1 === ( $check_v['record_version'] ?? 0 ), 'V2-G: vehicle remained at version 1 after failed save' );

// Clear fault seam:
WriteCoordinator::configure_test_fault( null );

// Clean up temp worker script.
if ( file_exists( $worker_script ) ) {
	unlink( $worker_script );
}

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n=== V2 SUMMARY ===\n";
if ( empty( $GLOBALS['velog_failures'] ) ) {
	echo "ALL V2 ASSERTIONS PASS\n";
	exit( 0 );
} else {
	echo sprintf( "FAILURES: %d assertion(s) failed:\n", count( $GLOBALS['velog_failures'] ) );
	foreach ( $GLOBALS['velog_failures'] as $f ) {
		echo "  - $f\n";
	}
	exit( 1 );
}
