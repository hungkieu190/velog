<?php
/**
 * V3: Retention integration probe — deactivation, uninstall, direct DB inspection, and reactivation.
 *
 * Verifies contract §Retention:
 *   - Custom post types rows retained in wp_posts
 *   - Authoritative metadata (_mf_velog_record) retained in wp_postmeta
 *   - Projections retained
 *   - Settings and markers retained
 *   - Reactivation succeeds and records can be read via repository
 *
 * Exit code 0 = all assertions pass.
 * Exit code 1 = any assertion failed.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;

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
 * @param string $details Optional failure details.
 */
function v3_assert( bool $cond, string $label, string $details = '' ): void {
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

// Reset and register test schemas.
function register_v3_test_schemas(): void {
	RecordSchema::reset_for_testing();
	RecordSchema::register(
		'mf_velog_customer',
		array(
			'field_policies'  => array(
				'name'  => 'public',
				'phone' => 'contact',
				'email' => 'contact',
			),
			'fields'          => array(
				'name'  => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
				'phone' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
				'email' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
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
}

register_v3_test_schemas();

$actor = new WP_User( 1 );
$actor->add_cap( 'mf_velog_manage_customers' );
$actor->add_cap( 'mf_velog_manage_vehicles' );
$actor->add_cap( 'mf_velog_read_records' );
$actor->add_cap( 'mf_velog_read_customer_contacts' );

WriteCoordinator::ensure_lock_row();

// ─── V3-A: Seed records across types and settings ─────────────────────────────

update_option(
	'velog_settings',
	array(
		'currency' => 'VND',
		'unit'     => 'km',
	),
	false
);
update_option( 'velog_version', '0.2.0', false );

$req_cust = 'v3-cust-' . wp_generate_uuid4();
$cust_res = RecordRepository::create(
	'mf_velog_customer',
	array(
		'name'  => 'Retention Customer',
		'phone' => '0912345678',
		'email' => 'retention@example.com',
	),
	$actor,
	$req_cust
);
v3_assert( ! is_wp_error( $cust_res ), 'V3-A: created customer record', is_wp_error( $cust_res ) ? $cust_res->get_error_message() : '' );

$req_veh = 'v3-veh-' . wp_generate_uuid4();
$veh_res = RecordRepository::create(
	'mf_velog_vehicle',
	array( 'vin' => 'VIN-RETENTION-999' ),
	$actor,
	$req_veh
);
v3_assert( ! is_wp_error( $veh_res ), 'V3-A: created vehicle record', is_wp_error( $veh_res ) ? $veh_res->get_error_message() : '' );

$cust_id = ( ! ( $cust_res instanceof WP_Error ) ) ? (int) $cust_res['id'] : 0;
$veh_id  = ( ! ( $veh_res instanceof WP_Error ) ) ? (int) $veh_res['id'] : 0;

$idem_opt_cust = 'mf_velog_create_' . hash( 'sha256', $req_cust );

// ─── V3-B: Real deactivation via CLI subprocess ───────────────────────────────

$wp_root         = ABSPATH;
$deactivate_out  = array();
$deactivate_code = 0;
exec( "wp plugin deactivate velog --path=\"$wp_root\" --allow-root 2>&1", $deactivate_out, $deactivate_code );
v3_assert( 0 === $deactivate_code, 'V3-B: wp plugin deactivate exited 0' );

// ─── V3-C: Real uninstall execution in separate process ───────────────────────

$plugin_dir       = dirname( dirname( dirname( dirname( __DIR__ ) ) ) );
$uninstall_script = $plugin_dir . '/uninstall.php';

$uninstall_runner = sys_get_temp_dir() . '/velog_run_uninstall_' . getmypid() . '.php';
$runner_code      = sprintf(
	"<?php\ndefine('WP_UNINSTALL_PLUGIN', 'velog/velog.php');\nrequire_once '%s/wp-load.php';\nrequire_once '%s';\n",
	addslashes( $wp_root ),
	addslashes( $uninstall_script )
);
file_put_contents( $uninstall_runner, $runner_code );

$uninstall_out  = array();
$uninstall_exit = 0;
exec( "php \"$uninstall_runner\" 2>&1", $uninstall_out, $uninstall_exit );
v3_assert( 0 === $uninstall_exit, 'V3-C: uninstall process executed cleanly (exit 0)' );
if ( file_exists( $uninstall_runner ) ) {
	unlink( $uninstall_runner );
}

// ─── V3-D: Verify full database retention ─────────────────────────────────────

// Verify posts exist in DB directly.
$post_count_cust = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'private'", $cust_id )
);
v3_assert( 1 === $post_count_cust, 'V3-D: customer post row retained in wp_posts' );

$post_count_veh = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'private'", $veh_id )
);
v3_assert( 1 === $post_count_veh, 'V3-D: vehicle post row retained in wp_posts' );

// Verify authoritative metadata exists.
$meta_count_cust = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_mf_velog_record'", $cust_id )
);
v3_assert( 1 === $meta_count_cust, 'V3-D: customer authoritative meta row retained' );

// Verify projection exists.
$proj_count = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'vin' AND meta_value = 'VIN-RETENTION-999'", $veh_id )
);
v3_assert( 1 === $proj_count, 'V3-D: vehicle projection row retained' );

// Verify options retained.
$settings = get_option( 'velog_settings' );
v3_assert( is_array( $settings ) && 'VND' === ( $settings['currency'] ?? '' ), 'V3-D: velog_settings option retained' );

$write_lock = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = 'mf_velog_write_lock'" );
v3_assert( 1 === (int) $write_lock, 'V3-D: mf_velog_write_lock option retained' );

$idem_opt_exists = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $idem_opt_cust )
);
v3_assert( 1 === (int) $idem_opt_exists, 'V3-D: request idempotency option marker retained' );

// ─── V3-E: Reactivate plugin via CLI subprocess ───────────────────────────────

$activate_out  = array();
$activate_code = 0;
exec( "wp plugin activate velog --path=\"$wp_root\" --allow-root 2>&1", $activate_out, $activate_code );
v3_assert( 0 === $activate_code, 'V3-E: wp plugin activate exited 0' );

// ─── V3-F: Re-read records via repository API ─────────────────────────────────

register_v3_test_schemas();

$reread_cust = RecordRepository::get( 'mf_velog_customer', $cust_id, $actor );
v3_assert( ! is_wp_error( $reread_cust ), 'V3-F: re-read customer record succeeds', is_wp_error( $reread_cust ) ? $reread_cust->get_error_message() : '' );
if ( ! ( $reread_cust instanceof WP_Error ) ) {
	v3_assert( 'Retention Customer' === $reread_cust['fields']['name'], 'V3-F: customer name intact' );
	v3_assert( '0912345678' === $reread_cust['fields']['phone'], 'V3-F: customer phone intact' );
	v3_assert( 1 === $reread_cust['record_version'], 'V3-F: record_version is 1' );
}

$reread_veh = RecordRepository::get( 'mf_velog_vehicle', $veh_id, $actor );
v3_assert( ! is_wp_error( $reread_veh ), 'V3-F: re-read vehicle record succeeds', is_wp_error( $reread_veh ) ? $reread_veh->get_error_message() : '' );
if ( ! ( $reread_veh instanceof WP_Error ) ) {
	v3_assert( 'VIN-RETENTION-999' === $reread_veh['fields']['vin'], 'V3-F: vehicle VIN intact' );
}

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n=== V3 SUMMARY ===\n";
if ( empty( $GLOBALS['velog_failures'] ) ) {
	echo "ALL V3 ASSERTIONS PASS\n";
	exit( 0 );
} else {
	echo sprintf( "FAILURES: %d assertion(s) failed:\n", count( $GLOBALS['velog_failures'] ) );
	foreach ( $GLOBALS['velog_failures'] as $f ) {
		echo "  - $f\n";
	}
	exit( 1 );
}
