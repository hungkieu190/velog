<?php
/**
 * CORE-004 Verification Fixture
 *
 * Verifies V2 and V3 cases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function velog_core004_run() {
	echo "Running CORE-004 verification...\n";

	$plugin = \MF\VeLog\Core\Plugin::get_instance();
	$plugin->run();

	// Setup users
	$manager_id = wp_insert_user( [
		'user_login' => 'core004_manager',
		'user_pass'  => wp_generate_password(),
		'role'       => 'administrator',
	] );
	
	$tech_id = wp_insert_user( [
		'user_login' => 'core004_tech',
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber', // Without mf_velog_manage_settings
	] );

	// Setup nonce
	wp_set_current_user( $manager_id );
	$valid_nonce = wp_create_nonce( 'velog_save_settings' );

	echo "V2: Technician/subscriber with valid nonce (Actual POST handler)\n";
	wp_set_current_user( $tech_id );
	$_POST = [
		'velog_settings_nonce' => $valid_nonce,
		'record_version'       => 0,
		'distance_unit'        => 'km',
		'currency_code'        => 'JPY',
	];
	
	// Catch wp_die and wp_redirect
	add_filter( 'wp_die_handler', function() {
		return function( $message ) {
			throw new \Exception( "WP_DIE: " . strip_tags( (string) $message ) );
		};
	} );
	add_filter( 'wp_redirect', function( $url ) {
		throw new \Exception( "REDIRECT: $url" );
	} );

	$page = new \MF\VeLog\Admin\RegionalSettingsPage();

	try {
		$page->handle_save();
		echo "ERROR: Technician should have been denied\n";
		exit( 1 );
	} catch ( \Exception $e ) {
		if ( strpos( $e->getMessage(), 'Unauthorized' ) === false ) {
			echo "ERROR: Unexpected exception for Technician: " . $e->getMessage() . "\n";
			exit( 1 );
		}
	}
	echo "PASS: Technician denied\n";

	echo "V2: Manager bad nonce (Actual POST handler)\n";
	wp_set_current_user( $manager_id );
	$_POST['velog_settings_nonce'] = 'invalid_nonce';
	try {
		$page->handle_save();
		echo "ERROR: Bad nonce should have been denied\n";
		exit( 1 );
	} catch ( \Exception $e ) {
		if ( strpos( $e->getMessage(), 'Invalid nonce' ) === false ) {
			echo "ERROR: Unexpected exception for bad nonce: " . $e->getMessage() . "\n";
			exit( 1 );
		}
	}
	echo "PASS: Bad nonce denied\n";

	echo "V2: Stale settings version (Concurrent write)\n";
	wp_set_current_user( $manager_id );
	
	$current_settings = \MF\VeLog\Common\Regional\ShopSettings::get_settings();
	$v1 = $current_settings['record_version'];

	$_POST['velog_settings_nonce'] = wp_create_nonce( 'velog_save_settings' );
	$_POST['record_version']       = $v1;
	$_POST['distance_unit']        = 'km';
	$_POST['currency_code']        = 'JPY';
	try {
		$page->handle_save();
	} catch ( \Exception $e ) {
		if ( strpos( $e->getMessage(), 'REDIRECT' ) === false ) {
			echo "ERROR: Valid first save failed: " . $e->getMessage() . "\n";
			exit( 1 );
		}
	}
	// Verify success
	$notice = get_transient( "velog_settings_notice_{$manager_id}" );
	if ( empty( $notice ) || $notice['type'] !== 'success' ) {
		echo "ERROR: First save returned error notice: " . print_r($notice, true) . "\n";
		exit( 1 );
	}

	// Try stale save (concurrent write with old version)
	$_POST['distance_unit'] = 'mi';
	$_POST['currency_code'] = 'USD';
	try {
		$page->handle_save(); // should fail and redirect back with transient error
	} catch ( \Exception $e ) {
		if ( strpos( $e->getMessage(), 'REDIRECT' ) === false ) {
			echo "ERROR: Unexpected behavior on stale save: " . $e->getMessage() . "\n";
			exit( 1 );
		}
	}
	
	// Check transient for stale version error
	$notice = get_transient( "velog_settings_notice_{$manager_id}" );
	if ( empty( $notice ) || $notice['type'] !== 'error' || ( strpos( $notice['message'], 'Stale version' ) === false && strpos( $notice['message'], 'updated by another' ) === false && strpos( $notice['message'], 'created concurrently' ) === false ) ) {
		echo "ERROR: Stale version should be rejected with error notice. Got: " . print_r($notice, true) . "\n";
		exit( 1 );
	}
	echo "PASS: Stale version rejected\n";

	echo "V3: Seed snapshots then switch settings\n";
	
	// Seed DATA-001 record using WriteCoordinator to prove historical preservation.
	// Since ShopSettings doesn't modify records, we will create a dummy record in the DB directly
	// using the actual meta schema.
	$post_id_1 = wp_insert_post([
		'post_author' => $manager_id,
		'post_title'  => 'Historical Test Record',
		'post_type'   => 'mf_velog_service',
		'post_status' => 'private',
	]);
	if ( is_wp_error( $post_id_1 ) || empty( $post_id_1 ) ) {
		echo "ERROR: wp_insert_post 1 failed\n";
		exit( 1 );
	}

	$payload_1 = [
		'price' => [
			'amount'   => '1000',
			'currency' => 'JPY',
			'scale'    => 0,
		],
		'odometer' => [
			'value' => '50000.0',
			'unit'  => 'km',
		],
	];
	// Directly mimic how RecordRepository stores the data.
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
		$post_id_1,
		'_mf_velog_record',
		maybe_serialize( [ 'payload' => $payload_1 ] )
	) );

	$current_settings = \MF\VeLog\Common\Regional\ShopSettings::get_settings();
	$v2 = $current_settings['record_version']; 

	// Switch settings to mi/USD
	$_POST['velog_settings_nonce'] = wp_create_nonce( 'velog_save_settings' );
	$_POST['record_version']       = $v2;
	$_POST['distance_unit']        = 'mi';
	$_POST['currency_code']        = 'USD';
	try {
		$page->handle_save();
	} catch ( \Exception $e ) {}

	$notice = get_transient( "velog_settings_notice_{$manager_id}" );
	if ( empty( $notice ) || $notice['type'] !== 'success' ) {
		echo "ERROR: Valid second save failed. Got: " . print_r($notice, true) . "\n";
		exit( 1 );
	}

	// Read back directly to prove NO historical rewrite occurred
	$meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id_1, '_mf_velog_record' ) );
	$record_1_read = maybe_unserialize( $meta );

	if ( empty( $record_1_read['payload']['price']['currency'] ) || $record_1_read['payload']['price']['currency'] !== 'JPY' || $record_1_read['payload']['odometer']['unit'] !== 'km' ) {
		echo "ERROR: Historical payload 1 modified! Dump: " . print_r($record_1_read, true) . "\n";
		exit( 1 );
	}

	echo "PASS: Historical values preserved after settings switch\n";
	echo "All verifications passed.\n";
	exit( 0 );
}

velog_core004_run();
