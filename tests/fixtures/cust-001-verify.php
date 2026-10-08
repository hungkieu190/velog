<?php
/**
 * CUST-001 disposable WordPress verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerQuery;
use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Assert a CUST-001 fixture condition. */
function cust001_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		echo "FAIL: {$message}\n";
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

if ( 'fixture-failure' === getenv( 'NEGATIVE_MODE' ) ) {
	echo "VELOG_CAUSE: fixture-failure\n";
	exit( 1 );
}

$manager_id = wp_create_user( 'cust_manager', wp_generate_password(), 'manager@example.com' );
$manager    = new WP_User( $manager_id );
$manager->add_cap( 'mf_velog_read_records' );
$manager->add_cap( 'mf_velog_manage_customers' );
$manager->add_cap( 'mf_velog_read_customer_contacts' );
$manager->add_cap( 'mf_velog_manage_vehicles' );

$technician_id = wp_create_user( 'cust_technician', wp_generate_password(), 'tech@example.com' );
$technician    = new WP_User( $technician_id );
$technician->add_cap( 'mf_velog_read_records' );

// Exercise transaction guards on the same live connection used for writes.
global $wpdb;
cust001_assert( true === WriteCoordinator::check_environment(), 'Idle database connection is accepted.' );
foreach ( array( 'START TRANSACTION', 'START TRANSACTION READ ONLY' ) as $statement ) {
	$wpdb->query( $statement );
	$guard = WriteCoordinator::check_environment();
	cust001_assert( is_wp_error( $guard ) && 'storage_unavailable' === $guard->get_error_code(), 'Active transaction is rejected: ' . $statement );
	$called = false;
	$guard = WriteCoordinator::run( $manager, static function () use ( &$called ): array {
		$called = true;
		return array();
	} );
	cust001_assert( is_wp_error( $guard ) && ! $called, 'Direct coordinator call cannot enter a caller transaction.' );
	$wpdb->query( 'ROLLBACK' );
}
$marker = 'velog_cust001_transaction_probe';
$wpdb->query( 'START TRANSACTION' );
$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $marker, 'pending' ) );
$guard = WriteCoordinator::check_environment();
cust001_assert( is_wp_error( $guard ), 'A transaction containing pending writes is rejected.' );
cust001_assert( 'pending' === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $marker ) ), 'Guard does not roll back caller writes.' );
$wpdb->query( 'ROLLBACK' );
cust001_assert( null === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $marker ) ), 'Guard does not commit caller writes.' );
$wpdb->query( 'SET autocommit = 0' );
$guard = WriteCoordinator::check_environment();
cust001_assert( is_wp_error( $guard ), 'Implicit transaction mode is rejected.' );
$wpdb->query( 'ROLLBACK' );
$wpdb->query( 'SET autocommit = 1' );
cust001_assert( true === WriteCoordinator::check_environment(), 'Connection is usable again after rollback and autocommit restoration.' );

wp_set_current_user( $manager_id );
foreach ( array( 'saved' => 'notice-success', 'storage_unavailable' => 'notice-error', 'forbidden' => 'notice-error' ) as $code => $class ) {
	$_GET['velog_notice'] = $code;
	ob_start();
	( new MF\VeLog\Admin\CustomerPage() )->render();
	$html = ob_get_clean();
	cust001_assert( str_contains( $html, $class ), 'Customer page renders the safe notice for ' . $code );
	cust001_assert( ! str_contains( $html, 'WriteCoordinator:' ), 'Customer notice does not expose database diagnostics.' );
}
unset( $_GET['velog_notice'] );

$service  = new CustomerService();
$created  = $service->create(
	array( 'name' => 'Nguyễn Văn An', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	wp_generate_uuid4()
);
cust001_assert( ! is_wp_error( $created ), 'Manager creates a Unicode customer.' );
$customer_id = (int) $created['id'];

$redacted = RecordRepository::get( 'mf_velog_customer', $customer_id, $technician );
cust001_assert( ! is_wp_error( $redacted ), 'Technician reads the customer summary.' );
cust001_assert( ! isset( $redacted['fields']['phone'], $redacted['fields']['email'] ), 'Technician contact fields are redacted.' );

$updated = $service->update(
	$customer_id,
	(int) $created['record_version'],
	array( 'name' => 'Nguyễn Văn An Updated', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	wp_generate_uuid4()
);
cust001_assert( ! is_wp_error( $updated ) && 2 === (int) $updated['record_version'], 'Manager update advances the exact version.' );

$stale = $service->update( $customer_id, 1, array( 'name' => 'Stale' ), $manager, wp_generate_uuid4() );
cust001_assert( is_wp_error( $stale ) && 'stale_version' === $stale->get_error_code(), 'Stale update is rejected.' );

$query = ( new CustomerQuery() )->search( array( 'term' => 'nguyễn', 'state' => 'active' ), $manager );
cust001_assert( ! is_wp_error( $query ) && 1 === (int) $query['total'], 'Manager search finds normalized Unicode customer data.' );

$vehicle = RecordRepository::create(
	'mf_velog_vehicle',
	array( 'current_customer_id' => $customer_id, 'vin' => 'CUST001-TEST-VIN' ),
	$manager,
	wp_generate_uuid4()
);
cust001_assert( ! is_wp_error( $vehicle ), 'Fixture creates an active linked vehicle through the repository.' );

$blocked = $service->archive( $customer_id, 2, $manager, wp_generate_uuid4() );
cust001_assert( is_wp_error( $blocked ) && 'active_vehicle_link' === $blocked->get_error_code(), 'Active vehicle blocks customer archive atomically.' );

$vehicle_archived = MF\VeLog\Common\Storage\WriteCoordinator::run(
	$manager,
	static function ( MF\VeLog\Common\Storage\WriteUnit $unit ) use ( $vehicle ): array|WP_Error {
		return $unit->save( 'mf_velog_vehicle', (int) $vehicle['id'], 1, array(), wp_generate_uuid4(), 'fixture_archive', 'archived' );
	}
);
cust001_assert( ! is_wp_error( $vehicle_archived ), 'Fixture archives the linked vehicle.' );

$archived = $service->archive( $customer_id, 2, $manager, wp_generate_uuid4() );
cust001_assert( ! is_wp_error( $archived ) && 'archived' === $archived['state'], 'Customer archive succeeds after vehicle archive.' );
$restored = $service->restore( $customer_id, 3, $manager, wp_generate_uuid4() );
cust001_assert( ! is_wp_error( $restored ) && 'active' === $restored['state'], 'Customer restore is versioned and audited.' );

$denied = $service->create( array( 'name' => 'Denied', 'phone' => '', 'email' => '' ), $technician, wp_generate_uuid4() );
cust001_assert( is_wp_error( $denied ) && 'forbidden' === $denied->get_error_code(), 'Technician customer mutation is denied.' );

echo "CUST-001 fixture passed.\n";
