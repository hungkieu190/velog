<?php
/**
 * CUST-001 disposable WordPress verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerQuery;
use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Storage\RecordRepository;

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
	array( 'current_customer_id' => $customer_id ),
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
