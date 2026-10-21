<?php
/**
 * VEH-001 isolated WordPress domain, storage and concurrency verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;
use MF\VeLog\Common\Vehicle\VehicleQuery;
use MF\VeLog\Common\Vehicle\VehicleService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}

/** Assert a domain condition without printing credentials. */
function mf_velog_vehicle_assert( bool $condition, string $label ): void {
	echo ( $condition ? 'PASS: ' : 'FAIL: ' ) . $label . "\n";
	if ( ! $condition ) {
		exit( 1 );
	}
}

/** Assert a specific safe error. */
function mf_velog_vehicle_error( mixed $result, string $code, string $label ): void {
	mf_velog_vehicle_assert( is_wp_error( $result ) && $code === $result->get_error_code(), $label );
}

/** Start two independent WordPress processes behind a shared barrier. */
function mf_velog_vehicle_race( array $modes, int $actor_id, int $target = 0 ): array {
	$barrier = ABSPATH . 'veh001-race-go';
	@unlink( $barrier );
	$workers = array();
	try {
		foreach ( $modes as $mode ) {
			$command = array( 'wp', 'eval-file', __DIR__ . '/veh-001-concurrent-worker.php', $mode, (string) $actor_id, (string) $target, '--path=' . ABSPATH );
			$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
			mf_velog_vehicle_assert( is_resource( $process ), 'Concurrent worker starts.' );
			fclose( $pipes[0] );
			$workers[] = array( $process, $pipes );
		}
		touch( $barrier );
		$results = array();
		foreach ( $workers as &$worker ) {
			$results[] = trim( stream_get_contents( $worker[1][1] ) );
			$stderr = stream_get_contents( $worker[1][2] );
			fclose( $worker[1][1] );
			fclose( $worker[1][2] );
			$exit = proc_close( $worker[0] );
			$worker[0] = null;
			mf_velog_vehicle_assert( 0 === $exit && '' === $stderr, 'Concurrent worker exits cleanly.' );
		}
		unset( $worker );
		return $results;
	} finally {
		foreach ( $workers as $worker ) {
			if ( is_resource( $worker[0] ) ) {
				proc_terminate( $worker[0] );
				proc_close( $worker[0] );
			}
		}
		@unlink( $barrier );
	}
}

if ( 'fixture-failure' === getenv( 'NEGATIVE_MODE' ) ) {
	echo "VELOG_CAUSE: fixture-failure\n";
	exit( 1 );
}

$manager_id = wp_insert_user( array( 'user_login' => 'veh_manager', 'user_pass' => wp_generate_password(), 'role' => 'mf_velog_manager' ) );
$tech_id    = wp_insert_user( array( 'user_login' => 'veh_technician', 'user_pass' => wp_generate_password(), 'role' => 'mf_velog_technician' ) );
$denied_id  = wp_insert_user( array( 'user_login' => 'veh_denied', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$manager   = new WP_User( $manager_id );
$tech      = new WP_User( $tech_id );
$denied    = new WP_User( $denied_id );
wp_set_current_user( $manager_id );
$customers = new CustomerService();
$owner     = $customers->create( array( 'name' => 'Vehicle Owner', 'phone' => 'PRIVATE-PHONE-VEH001', 'email' => 'private.vehicle@example.com' ), $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $owner ), 'Private customer fixture is created.' );
$service   = new VehicleService();
$request   = wp_generate_uuid4();
$fields    = array( 'jurisdiction' => ' th ', 'plate' => ' กข 123 ', 'vin' => 'veh001-base', 'current_customer_id' => $owner['id'], 'year' => 2020 );
$vehicle   = $service->create( $fields, $manager, $request );
mf_velog_vehicle_assert( ! is_wp_error( $vehicle ) && 1 === $vehicle['record_version'], 'Manager creates a Unicode vehicle with a private customer.' );
$id = $vehicle['id'];
$replay = $service->create( $fields, $manager, $request );
mf_velog_vehicle_assert( ! is_wp_error( $replay ) && $id === $replay['id'], 'Same create request returns the original identity.' );
mf_velog_vehicle_error( $service->create( array( 'vin' => 'VEH001-BASE' ), $manager, wp_generate_uuid4() ), 'conflict', 'Normalized duplicate VIN rejected.' );
mf_velog_vehicle_error( $service->create( array( 'jurisdiction' => 'TH', 'plate' => 'กข 123' ), $manager, wp_generate_uuid4() ), 'conflict', 'Normalized jurisdiction/plate tuple rejected.' );
foreach ( array( array( 'vin' => '0', 'year' => '' ), array( 'jurisdiction' => 'VN', 'plate' => 'กข 123' ), array( 'vin' => 'É-IDENTITY' ), array( 'vin' => 'E-IDENTITY' ) ) as $valid ) {
	mf_velog_vehicle_assert( ! is_wp_error( $service->create( $valid, $manager, wp_generate_uuid4() ) ), 'Optional identifiers and exact binary uniqueness do not create false conflicts.' );
}
foreach ( array( array(), array( 'vin' => 'X', 'plate' => 'NO-JURISDICTION' ), array( 'vin' => "bad\xFF" ), array( 'vin' => 'X', 'year' => 1885 ) ) as $invalid ) {
	mf_velog_vehicle_error( $service->create( $invalid, $manager, wp_generate_uuid4() ), 'invalid_input', 'Invalid complete vehicle rejected.' );
}
mf_velog_vehicle_error( $service->create( array( 'vin' => 'DENIED' ), $tech, wp_generate_uuid4() ), 'forbidden', 'Technician mutation denied.' );
mf_velog_vehicle_error( RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => 'WRONG-OWNER', 'current_customer_id' => $manager_id ), $manager, wp_generate_uuid4() ), 'invalid_customer', 'Direct repository rejects WordPress user as private owner.' );
mf_velog_vehicle_error( RecordRepository::create( 'mf_velog_vehicle', array( 'vin' => 'MISSING-OWNER', 'current_customer_id' => 999999 ), $manager, wp_generate_uuid4() ), 'invalid_customer', 'Direct repository rejects missing owner.' );
mf_velog_vehicle_error( $customers->archive( $owner['id'], 1, $manager, wp_generate_uuid4() ), 'active_vehicle_link', 'Customer archive respects vehicle write guard.' );

$updated = $service->update( $id, 1, array( 'make' => 'Same identifier edit' ), $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $updated ) && 2 === $updated['record_version'], 'Self edit retains unique identifiers and advances version.' );
mf_velog_vehicle_error( $service->update( $id, 1, array( 'make' => 'Stale' ), $manager, wp_generate_uuid4() ), 'stale_version', 'Stale update rejected.' );
$baseline = array( 'original_value' => '10', 'unit' => 'km', 'canonical_mm' => '10000000', 'date' => '2020-01-01' );
$updated = $service->update( $id, 2, array( 'baseline' => $baseline, 'preferred_odometer_unit' => 'mi' ), $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $updated ), 'Known baseline with independent preferred unit is accepted.' );
$zero = array( 'original_value' => '0', 'unit' => 'mi', 'canonical_mm' => '0', 'date' => '2020-01-02' );
mf_velog_vehicle_error( $service->update( $id, 3, array( 'baseline' => $zero ), $manager, wp_generate_uuid4() ), 'reason_required', 'Decrease without reason rejected.' );
$updated = $service->update( $id, 3, array( 'baseline' => $zero ), $manager, wp_generate_uuid4(), 'Correct initial reading' );
mf_velog_vehicle_assert( ! is_wp_error( $updated ) && '0' === $updated['fields']['baseline']['canonical_mm'], 'Reasoned decrease preserves real zero and audit.' );
mf_velog_vehicle_error( $service->update( $id, 4, array( 'baseline' => array() ), $manager, wp_generate_uuid4() ), 'reason_required', 'Clearing known zero still requires reason.' );
$forged = array_merge( $zero, array( 'canonical_mm' => '1' ) );
mf_velog_vehicle_error( RecordRepository::save( 'mf_velog_vehicle', $id, 4, array( 'baseline' => $forged ), $manager, wp_generate_uuid4() ), 'invalid_input', 'Direct repository rejects forged canonical baseline.' );
$archived = $service->transition( $id, 4, 'archived', $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $archived ), 'Vehicle archive preserves identifiers.' );
mf_velog_vehicle_error( $service->create( array( 'vin' => 'VEH001-BASE' ), $manager, wp_generate_uuid4() ), 'conflict', 'Archived vehicle still reserves VIN.' );
$archived_owner = $customers->archive( $owner['id'], 1, $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $archived_owner ), 'Customer archive succeeds after vehicle archive.' );
mf_velog_vehicle_error( $service->transition( $id, 5, 'active', $manager, wp_generate_uuid4() ), 'invalid_customer', 'Restore cannot reactivate an archived owner relation.' );
$unassigned = $service->update( $id, 5, array( 'current_customer_id' => '' ), $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $unassigned ) && 0 === $unassigned['fields']['current_customer_id'], 'Archived vehicle can explicitly clear its relation.' );
$restored = $service->transition( $id, 6, 'active', $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $restored ), 'Unassigned vehicle can be restored.' );

$query = new VehicleQuery();
$detail = $query->get( $id, $tech );
mf_velog_vehicle_assert( ! is_wp_error( $detail ) && ! isset( $detail['audit'] ), 'Technician reads operational detail without audit.' );
mf_velog_vehicle_error( $query->get( $id, $denied ), 'forbidden', 'Subscriber detail denied.' );
$list = $query->search( array( 'term' => 'VEH001-BASE' ), $tech );
mf_velog_vehicle_assert( ! is_wp_error( $list ) && 1 === $list['total'], 'Technician exact identifier search is bounded and authorized.' );
mf_velog_vehicle_assert( ! str_contains( wp_json_encode( $list ), 'PRIVATE-PHONE-VEH001' ) && ! str_contains( wp_json_encode( $list ), 'private.vehicle@example.com' ), 'Operational search never contains customer contacts.' );

for ( $i = 1; $i <= 51; ++$i ) {
	$result = $service->create( array( 'vin' => sprintf( 'VEH001-PAGE-%03d', $i ) ), $manager, wp_generate_uuid4() );
	mf_velog_vehicle_assert( ! is_wp_error( $result ), 'Pagination fixture ' . $i );
}
$page1 = $query->search( array( 'term' => 'VEH001-PAGE' ), $manager );
$page2 = $query->search( array( 'term' => 'VEH001-PAGE', 'page' => 2 ), $manager );
mf_velog_vehicle_assert( ! is_wp_error( $page1 ) && ! is_wp_error( $page2 ) && 51 === $page1['total'] && 50 === count( $page1['items'] ) && 1 === count( $page2['items'] ), 'Stable pagination returns 50 plus one row.' );

$rollback = WriteCoordinator::run( $manager, static function ( WriteUnit $unit ): WP_Error {
	$result = $unit->create( 'mf_velog_vehicle', array( 'vin' => 'ROLLBACK-VEH001' ), wp_generate_uuid4() );
	return is_wp_error( $result ) ? $result : new WP_Error( 'fixture_rollback', 'Rollback this fixture operation.' );
} );
mf_velog_vehicle_error( $rollback, 'fixture_rollback', 'Write unit reports deliberate rollback.' );
$rolled = $query->search( array( 'term' => 'ROLLBACK-VEH001' ), $manager );
mf_velog_vehicle_assert( ! is_wp_error( $rolled ) && 0 === $rolled['total'], 'Rollback leaves no vehicle or projections.' );
$failed_sql = WriteCoordinator::run( $manager, static function ( WriteUnit $unit ): WP_Error {
    $created = $unit->create( 'mf_velog_vehicle', array( 'vin' => 'SQLFAIL-VEH001' ), wp_generate_uuid4() );
    if ( is_wp_error( $created ) ) {
        return $created;
    }
    $rows = WriteCoordinator::query_direct( $unit->get_dbh(), 'SELECT missing_fixture_column FROM information_schema.tables LIMIT 1' );
    return false === $rows ? new WP_Error( 'storage_unavailable', 'Expected fixture SQL failure.' ) : new WP_Error( 'unexpected_sql', 'Negative control failed.' );
} );
mf_velog_vehicle_error( $failed_sql, 'storage_unavailable', 'Real SQL failure returns a safe storage error.' );
$failed_sql_rows = $query->search( array( 'term' => 'SQLFAIL-VEH001' ), $manager );
mf_velog_vehicle_assert( ! is_wp_error( $failed_sql_rows ) && 0 === $failed_sql_rows['total'], 'SQL failure rolls back prior vehicle writes.' );
$results = mf_velog_vehicle_race( array( 'duplicate', 'duplicate' ), $manager_id );
sort( $results );
mf_velog_vehicle_assert( array( 'conflict', 'ok' ) === $results, 'Concurrent identical VIN create has one winner.' );
$race_owner = $customers->create( array( 'name' => 'Race Owner' ), $manager, wp_generate_uuid4() );
mf_velog_vehicle_assert( ! is_wp_error( $race_owner ), 'Race owner fixture exists.' );
$results = mf_velog_vehicle_race( array( 'archive_customer', 'assign_customer' ), $manager_id, $race_owner['id'] );
mf_velog_vehicle_assert( ( 'ok' === $results[0] && 'invalid_customer' === $results[1] ) || ( 'active_vehicle_link' === $results[0] && 'ok' === $results[1] ), 'Customer archive and assignment race preserves the active relation invariant.' );

// A disposable finalized-service marker verifies the explicitly conservative chronology boundary.
global $wpdb;
$marker = wp_insert_post( array( 'post_type' => 'mf_velog_service', 'post_status' => 'private' ) );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $marker, 'meta_key' => '_mf_velog_state', 'meta_value' => 'finalized' ) );
mf_velog_vehicle_error( $service->update( $id, 7, array( 'baseline' => array() ), $manager, wp_generate_uuid4(), 'Clear reading' ), 'service_history_locked', 'Finalized service boundary refuses guessed baseline corrections.' );
wp_delete_post( $marker, true );
echo "VEH-001 domain verification complete.\n";
