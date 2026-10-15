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
$create_request = wp_generate_uuid4();
$created  = $service->create(
	array( 'name' => 'Nguyễn Văn An', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	$create_request
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

$reconciled_history = RecordRepository::reconcile_create(
	'mf_velog_customer',
	array( 'name' => 'Nguyễn Văn An', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	$create_request
);
cust001_assert( ! is_wp_error( $reconciled_history ) && $customer_id === $reconciled_history['id'], 'Create reconciliation preserves identity across field edits and archive/restore history.' );

$original_history = get_post_meta( $customer_id, '_mf_velog_record', true );
$corrupt_history = $original_history;
$last_audit_index = count( $corrupt_history['audit'] ) - 1;
$corrupt_history['audit'][ $last_audit_index ]['after']['__state'] = 'archived';
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $corrupt_history ) ), array( 'post_id' => $customer_id, 'meta_key' => '_mf_velog_record' ) );
$corrupt_recovery = RecordRepository::reconcile_create(
	'mf_velog_customer',
	array( 'name' => 'Nguyễn Văn An', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	$create_request
);
cust001_assert( is_wp_error( $corrupt_recovery ), 'Reconciliation rejects audit state that contradicts the current envelope.' );
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $original_history ) ), array( 'post_id' => $customer_id, 'meta_key' => '_mf_velog_record' ) );



$denied = $service->create( array( 'name' => 'Denied', 'phone' => '', 'email' => '' ), $technician, wp_generate_uuid4() );
cust001_assert( is_wp_error( $denied ) && 'forbidden' === $denied->get_error_code(), 'Technician customer mutation is denied.' );

// Customer records remain independent of native login accounts.
$native_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" );
$optional = $service->create( array( 'name' => 'Name only', 'phone' => '', 'email' => '' ), $manager, wp_generate_uuid4() );
cust001_assert( ! is_wp_error( $optional ), 'Customer requires no username or contact fields.' );
$duplicate = $service->create(
	array( 'name' => 'Nguyễn Văn An', 'phone' => '+66 81 234 5678', 'email' => 'owner@example.com' ),
	$manager,
	wp_generate_uuid4()
);
cust001_assert( ! is_wp_error( $duplicate ) && (int) $duplicate['id'] !== $customer_id, 'Duplicate contacts create an independent customer record.' );
cust001_assert( $native_count === (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" ), 'Customer creation does not create WordPress users.' );

// A full page uses the same bounded reads as a single-row result.
for ( $index = 1; $index <= 51; ++$index ) {
	$seeded = $service->create(
		array( 'name' => sprintf( 'Batch Fixture %03d', $index ), 'phone' => '', 'email' => '' ),
		$manager,
		wp_generate_uuid4()
	);
	cust001_assert( ! is_wp_error( $seeded ), 'Create pagination fixture ' . $index );
}
$query_service = new CustomerQuery();
$before = $wpdb->num_queries;
$first = $query_service->search( array( 'term' => 'Batch Fixture', 'sort' => 'id' ), $manager );
$full_reads = $wpdb->num_queries - $before;
cust001_assert( ! is_wp_error( $first ) && 51 === $first['total'] && 50 === count( $first['items'] ), 'First page contains exactly 50 of 51 customers.' );
$before = $wpdb->num_queries;
$second = $query_service->search( array( 'term' => 'Batch Fixture', 'sort' => 'id', 'page' => 2 ), $manager );
$small_reads = $wpdb->num_queries - $before;
cust001_assert( ! is_wp_error( $second ) && 1 === count( $second['items'] ), 'Second page contains the remaining customer.' );
cust001_assert( $full_reads <= 5 && $full_reads === $small_reads, 'Batch hydration has constant query count for one and 50 rows.' );
$first_ids = array_column( $first['items'], 'id' );
cust001_assert( ! in_array( $second['items'][0]['id'], $first_ids, true ), 'Stable pagination does not repeat IDs.' );
$batch_summary = RecordRepository::get_many( 'mf_velog_customer', array( $customer_id ), $technician );
cust001_assert( ! is_wp_error( $batch_summary ) && ! isset( $batch_summary[0]['fields']['phone'], $batch_summary[0]['fields']['email'] ), 'Batch hydration preserves technician contact redaction.' );
$bad_batch = RecordRepository::get_many( 'mf_velog_customer', array( $customer_id, $customer_id ), $manager );
cust001_assert( is_wp_error( $bad_batch ), 'Batch reader rejects duplicate IDs.' );
$bad_batch = RecordRepository::get_many( 'mf_velog_customer', range( 1, 51 ), $manager );
cust001_assert( is_wp_error( $bad_batch ), 'Batch reader rejects an unbounded page.' );
$bad_batch = RecordRepository::get_many( 'mf_velog_customer', array( $manager_id ), $manager );
cust001_assert( is_wp_error( $bad_batch ), 'Native user IDs do not resolve to customer records by fallback.' );

// A duplicate authoritative row is rejected rather than silently selected.
$raw_envelope = get_post_meta( $customer_id, '_mf_velog_record', true );
$duplicate_meta_id = add_post_meta( $customer_id, '_mf_velog_record', $raw_envelope );
cust001_assert( false !== $duplicate_meta_id, 'Duplicate envelope fault is installed in the disposable site.' );
$corrupt_batch = RecordRepository::get_many( 'mf_velog_customer', array( $customer_id ), $manager );
cust001_assert( is_wp_error( $corrupt_batch ), 'Batch hydration fails closed on duplicate authoritative rows.' );
delete_metadata_by_mid( 'post', (int) $duplicate_meta_id );
$recovered_batch = RecordRepository::get_many( 'mf_velog_customer', array( $customer_id ), $manager );
cust001_assert( ! is_wp_error( $recovered_batch ), 'Batch read recovers after removing only the injected row.' );

echo "CUST-001 fixture passed.\n";
