<?php
/**
 * SERV-001 real isolated WordPress workflow and rollback verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Service\ServiceQuery;
use MF\VeLog\Common\Service\ServiceService;
use MF\VeLog\Common\Service\ServiceTypes;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;
use MF\VeLog\Common\Vehicle\VehicleService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}

/** Assert without exposing credentials. */
function mf_velog_serv_assert( bool $condition, string $label ): void {
	echo ( $condition ? 'PASS: ' : 'FAIL: ' ) . $label . "\n";
	if ( ! $condition ) { exit( 1 ); }
}

/** Assert an exact rejection. */
function mf_velog_serv_error( mixed $result, string $code, string $label ): void {
	mf_velog_serv_assert( is_wp_error( $result ) && $code === $result->get_error_code(), $label . ( is_wp_error( $result ) ? ' [' . $result->get_error_code() . ']' : '' ) );
}

if ( 'fixture-failure' === getenv( 'NEGATIVE_MODE' ) ) { echo "VELOG_CAUSE: fixture-failure\n"; exit( 1 ); }
mf_velog_serv_assert( false === get_taxonomy( ServiceTypes::TAXONOMY )->public && false === get_taxonomy( ServiceTypes::TAXONOMY )->show_in_rest && false === get_taxonomy( ServiceTypes::TAXONOMY )->show_ui, 'Taxonomy has no public, REST or alternate native UI surface.' );
wp_set_current_user( 1 );
$settings = ShopSettings::save_settings( array( 'distance_unit' => 'km', 'currency_code' => 'USD', 'region' => 'US' ), 0 );
mf_velog_serv_assert( true === $settings, 'Shop Settings configured explicitly.' );
$ids = array();
foreach ( array( 'serv_manager' => 'mf_velog_manager', 'serv_tech' => 'mf_velog_technician', 'serv_other' => 'mf_velog_technician', 'serv_denied' => 'subscriber' ) as $login => $role ) {
	$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	mf_velog_serv_assert( is_int( $id ), 'Isolated staff/denied fixture exists.' );
	$ids[ $login ] = $id;
}
$manager = new WP_User( $ids['serv_manager'] );
$tech = new WP_User( $ids['serv_tech'] );
$other = new WP_User( $ids['serv_other'] );
$denied = new WP_User( $ids['serv_denied'] );
$customers = new CustomerService();
$owner = $customers->create( array( 'name' => 'Historical Owner กข', 'phone' => 'SERV-PRIVATE-CONTACT', 'email' => 'private.serv@example.com' ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $owner ), 'Private customer exists.' );
$vehicles = new VehicleService();
$vehicle = $vehicles->create( array( 'vin' => 'SERV001-VEHICLE', 'current_customer_id' => $owner['id'], 'baseline' => array_merge( Distance::parse( '100', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $vehicle ), 'Vehicle with known baseline exists.' );
$type_request = wp_generate_uuid4();
$type = ServiceTypes::save( 0, 0, 'Oil change กข', 'active', $manager, $type_request );
mf_velog_serv_assert( is_array( $type ), 'Private active service type created with audit.' );
$repeat = ServiceTypes::save( 0, 0, 'Oil change กข', 'active', $manager, $type_request );
mf_velog_serv_assert( is_array( $repeat ) && $repeat['id'] === $type['id'], 'Type create replay is idempotent.' );
mf_velog_serv_error( ServiceTypes::save( 0, 0, 'Denied', 'active', $tech, wp_generate_uuid4() ), 'forbidden', 'Technician cannot manage service types.' );
mf_velog_serv_error( ServiceTypes::save( 0, 0, 'oil change กข', 'active', $manager, wp_generate_uuid4() ), 'conflict', 'Duplicate service type refused.' );
$service = new ServiceService();
$query = new ServiceQuery();
$fields = array( 'vehicle_id' => $vehicle['id'], 'service_date' => '2020-01-03', 'reading' => Distance::parse( '150', 'km', '.' ), 'service_type_id' => $type['id'], 'notes' => 'Original notes', 'cost' => Money::parse( '12.30', 'USD', '.' ) );
$draft = $service->create( $fields, $tech, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $draft ) && 'draft' === $draft['state'], 'Technician creates own incomplete-capable draft.' );
$id = $draft['id'];
mf_velog_serv_error( $query->get( $id, $other ), 'forbidden', 'Other technician cannot read private draft by ID.' );
$list = $query->search( $other );
mf_velog_serv_assert( is_array( $list ) && 0 === $list['total'], 'Other technician query excludes draft.' );
mf_velog_serv_error( RecordRepository::save( 'mf_velog_service', $id, 1, array( 'notes' => 'Bypass' ), $other, wp_generate_uuid4() ), 'forbidden', 'Direct repository cannot bypass draft ownership.' );
$changed = $service->change( $id, 1, array( 'notes' => 'Manager edit' ), 'save', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $changed ) && $changed['created_by'] === $tech->ID, 'Manager edits foreign draft without impersonating creator.' );
$final = $service->change( $id, 2, array(), 'finalize', $tech, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $final ) && 'finalized' === $final['state'] && 'Historical Owner กข' === $final['fields']['customer_snapshot']['name'], 'Own finalization captures customer and service type.' );
mf_velog_serv_assert( is_array( $query->get( $id, $other ) ), 'Finalized record visible to operational staff.' );
mf_velog_serv_assert( ! str_contains( wp_json_encode( $query->get( $id, $other ) ), 'SERV-PRIVATE-CONTACT' ) && ! str_contains( wp_json_encode( $query->get( $id, $other ) ), 'private.serv@example.com' ), 'Service snapshot/audit do not leak contact details.' );
mf_velog_serv_error( $service->change( $id, 3, array( 'notes' => 'Denied' ), 'correct', $tech, wp_generate_uuid4(), 'A reason' ), 'reason_required', 'Technician correction denied even with a reason.' );
mf_velog_serv_error( RecordRepository::save( 'mf_velog_service', $id, 3, array( 'notes' => 'Bypass' ), $tech, wp_generate_uuid4() ), 'reason_required', 'Direct repository finalized edit denied.' );
mf_velog_serv_error( $service->change( $id, 3, array( 'notes' => 'Missing reason' ), 'correct', $manager, wp_generate_uuid4() ), 'reason_required', 'Manager correction requires reason.' );
$corrected = $service->change( $id, 3, array( 'notes' => 'Corrected' ), 'correct', $manager, wp_generate_uuid4(), 'Correct typo' );
mf_velog_serv_assert( is_array( $corrected ) && 4 === count( $corrected['audit'] ), 'Manager correction appends full audit.' );
$renamed = ServiceTypes::save( $type['id'], 1, 'Renamed oil type', 'inactive', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $renamed ), 'Referenced type renamed/deactivated without deleting history.' );
$owner_changed = $customers->update( $owner['id'], 1, array( 'name' => 'Renamed current owner' ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $owner_changed ), 'Current customer name changed separately.' );
$stored = $query->get( $id, $manager );
mf_velog_serv_assert( 'Oil change กข' === $stored['fields']['type_snapshot']['name'] && 'Historical Owner กข' === $stored['fields']['customer_snapshot']['name'] && 'USD' === $stored['fields']['cost']['currency'], 'Historical type/customer/currency remain frozen.' );
$incomplete = $service->create( array( 'vehicle_id' => $vehicle['id'] ), $tech, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $incomplete ), 'Incomplete draft saved without fake zero/date/type.' );
mf_velog_serv_error( $service->change( $incomplete['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4() ), 'invalid_type', 'Incomplete finalization refused.' );
$inactive_draft = $service->create( $fields, $tech, wp_generate_uuid4() );
mf_velog_serv_error( $service->change( $inactive_draft['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4() ), 'invalid_type', 'Inactive referenced type blocks new finalization.' );
mf_velog_serv_error( $service->change( $id, 4, array( 'customer_snapshot' => array( 'id' => $owner['id'], 'name' => 'Forged history' ) ), 'correct', $manager, wp_generate_uuid4(), 'Cannot rewrite owner' ), 'invalid_input', 'Finalized customer snapshot cannot be forged by correction.' );
$unowned = $vehicles->create( array( 'vin' => 'SERV001-NO-OWNER' ), $manager, wp_generate_uuid4() );
$type = ServiceTypes::save( $type['id'], 2, 'Active oil', 'active', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $type ), 'Type reactivated.' );
$unowned_draft = $service->create( array_merge( $fields, array( 'vehicle_id' => $unowned['id'] ) ), $tech, wp_generate_uuid4() );
mf_velog_serv_error( $service->change( $unowned_draft['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4() ), 'invalid_customer', 'Finalization requires active private customer relation.' );
$direct = WriteCoordinator::run( $tech, static fn ( WriteUnit $unit ): array|WP_Error => $unit->save( 'mf_velog_service', $incomplete['id'], 1, $fields, wp_generate_uuid4(), '', 'finalized' ) );
mf_velog_serv_error( $direct, 'incomplete_service', 'Direct repository transition cannot omit authoritative snapshots.' );
// Backdated insertion fits both neighbours but cannot become the current reading.
$back = $service->create( array_merge( $fields, array( 'service_date' => '2020-01-02', 'reading' => Distance::parse( '120', 'km', '.' ) ) ), $tech, wp_generate_uuid4() );
$back_final = $service->change( $back['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $back_final ), 'Backdated reading between baseline and later service accepted.' );
$current = $query->current( $vehicle['id'], $tech );
mf_velog_serv_assert( is_array( $current ) && $current['service_id'] === $id && '150000000' === $current['reading']['canonical_mm'], 'Backdated record does not replace later current reading.' );
$bad = $service->create( array_merge( $fields, array( 'service_date' => '2020-01-02', 'reading' => Distance::parse( '160', 'km', '.' ) ) ), $tech, wp_generate_uuid4() );
mf_velog_serv_error( $service->change( $bad['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4(), 'Technician cannot override' ), 'chronology_conflict', 'Reading above newer neighbour cannot be finalized by technician.' );
$exception = $service->change( $bad['id'], 1, array(), 'finalize', $manager, wp_generate_uuid4(), 'Documented historical discrepancy' );
mf_velog_serv_assert( is_array( $exception ), 'Manager reasoned chronology exception audited.' );
$back_current = $service->change( $id, 4, array( 'service_date' => '2019-12-31', 'reading' => Distance::parse( '90', 'km', '.' ) ), 'correct', $manager, wp_generate_uuid4(), 'Correct original service date' );
mf_velog_serv_assert( is_array( $back_current ), 'Manager moves latest service earlier with reason.' );
$current = $query->current( $vehicle['id'], $tech );
mf_velog_serv_assert( is_array( $current ) && $current['service_id'] === $bad['id'], 'Current reading recomputed from remaining finalized records.' );
$baseline_change = $vehicles->update( $vehicle['id'], 1, array( 'baseline' => array_merge( Distance::parse( '200', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
mf_velog_serv_error( $baseline_change, 'chronology_conflict', 'Baseline edit checks actual service neighbours, not a global lock.' );
$baseline_good = $vehicles->update( $vehicle['id'], 1, array( 'baseline' => array_merge( Distance::parse( '110', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $baseline_good ), 'Compatible baseline edit allowed despite finalized services.' );
$unrelated = $vehicles->create( array( 'vin' => 'SERV001-UNRELATED' ), $manager, wp_generate_uuid4() );
$unrelated_changed = $vehicles->update( $unrelated['id'], 1, array( 'baseline' => array_merge( Distance::parse( '1', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $unrelated_changed ), 'Other vehicle history does not block unrelated baseline.' );
// Transaction rollback must erase an inserted draft and its projections.
$rollback = WriteCoordinator::run( $tech, static function ( WriteUnit $unit ) use ( $vehicle ): WP_Error {
	$r = $unit->create( 'mf_velog_service', array( 'vehicle_id' => $vehicle['id'], 'vehicle_visible' => true, 'notes' => 'ROLLBACK-SERV001' ), wp_generate_uuid4() );
	if ( is_wp_error( $r ) ) { return $r; }
	WriteCoordinator::exec_direct( $unit->get_dbh(), 'INSERT INTO velog_fixture_missing_table (id) VALUES (1)' );
	return new WP_Error( 'fixture_sql_failure' );
} );
mf_velog_serv_error( $rollback, 'fixture_sql_failure', 'Injected SQL failure rolls back write unit.' );
global $wpdb;
$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_mf_velog_record' AND meta_value LIKE '%ROLLBACK-SERV001%'" );
mf_velog_serv_assert( '0' === $count, 'Failed write leaves no inserted service/audit/projections.' );
// Archive blocks draft work but preserves finalized history and manager correction.
$archived = $vehicles->transition( $vehicle['id'], 2, 'archived', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $archived ), 'Vehicle archived with history retained.' );
mf_velog_serv_error( $service->change( $incomplete['id'], 1, array( 'notes' => 'Blocked' ), 'save', $tech, wp_generate_uuid4() ), 'invalid_vehicle', 'Archived vehicle blocks draft edit.' );
$historical = $service->change( $id, 5, array( 'notes' => 'Archived correction' ), 'correct', $manager, wp_generate_uuid4(), 'Correct retained notes' );
mf_velog_serv_assert( is_array( $historical ), 'Archived vehicle history permits reasoned manager correction.' );
$restored = $vehicles->transition( $vehicle['id'], 3, 'active', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $restored ), 'Test vehicle restored for HTTP.' );
// Real concurrent finalization: exactly one transition and no duplicated audit.
$race = $service->create( array_merge( $fields, array( 'service_date' => '2020-01-04', 'reading' => Distance::parse( '200', 'km', '.' ) ) ), $tech, wp_generate_uuid4() );
$barrier = ABSPATH . 'serv001-race-go';
@unlink( $barrier );
$workers = array();
try {
	for ( $i = 0; $i < 2; ++$i ) {
		$proc = proc_open( array( 'wp', 'eval-file', __DIR__ . '/serv-001-concurrent-worker.php', (string) $tech->ID, (string) $race['id'], '--path=' . ABSPATH ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		mf_velog_serv_assert( is_resource( $proc ), 'Concurrent finalizer starts.' ); fclose( $pipes[0] ); $workers[] = array( $proc, $pipes );
	}
	touch( $barrier ); $results = array();
	foreach ( $workers as &$worker ) {
		$results[] = trim( stream_get_contents( $worker[1][1] ) ); $err = stream_get_contents( $worker[1][2] ); fclose( $worker[1][1] ); fclose( $worker[1][2] );
		$exit = proc_close( $worker[0] ); $worker[0] = null; mf_velog_serv_assert( 0 === $exit && '' === $err, 'Concurrent worker exits cleanly.' );
	} unset( $worker ); sort( $results );
	mf_velog_serv_assert( array( 'invalid_state', 'ok' ) === $results || array( 'ok', 'stale_version' ) === $results, 'Exactly one concurrent finalization succeeds.' );
} finally {
	foreach ( $workers as $worker ) { if ( is_resource( $worker[0] ) ) { proc_terminate( $worker[0] ); proc_close( $worker[0] ); } } @unlink( $barrier );
}
$race_stored = $query->get( $race['id'], $manager );
mf_velog_serv_assert( 2 === count( $race_stored['audit'] ), 'Concurrent finalize has one transition audit.' );
// Concurrent type deactivation and finalization serialize on the same domain lock.
$race_type = ServiceTypes::save( 0, 0, 'Race active type', 'active', $manager, wp_generate_uuid4() );
$type_draft = $service->create( array_merge( $fields, array( 'service_type_id' => $race_type['id'], 'service_date' => '2020-01-06', 'reading' => Distance::parse( '300', 'km', '.' ) ) ), $tech, wp_generate_uuid4() );
@unlink( $barrier ); $workers = array();
try {
	foreach ( array( array( $tech->ID, $type_draft['id'], 'finalize' ), array( $manager->ID, $race_type['id'], 'deactivate' ) ) as $job ) {
		$proc = proc_open( array( 'wp', 'eval-file', __DIR__ . '/serv-001-concurrent-worker.php', (string) $job[0], (string) $job[1], $job[2], '--path=' . ABSPATH ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		mf_velog_serv_assert( is_resource( $proc ), 'Type/finalize race starts.' ); fclose( $pipes[0] ); $workers[] = array( $proc, $pipes );
	}
	touch( $barrier ); $results = array();
	foreach ( $workers as &$worker ) {
		$results[] = trim( stream_get_contents( $worker[1][1] ) ); $err = stream_get_contents( $worker[1][2] ); fclose( $worker[1][1] ); fclose( $worker[1][2] );
		$exit = proc_close( $worker[0] ); $worker[0] = null; mf_velog_serv_assert( 0 === $exit && '' === $err, 'Type/finalize race exits cleanly.' );
	} unset( $worker );
	mf_velog_serv_assert( array( 'ok', 'ok' ) === $results || array( 'invalid_type', 'ok' ) === $results, 'Type deactivation/finalization race cannot finalize an already inactive type.' );
} finally {
	foreach ( $workers as $worker ) { if ( is_resource( $worker[0] ) ) { proc_terminate( $worker[0] ); proc_close( $worker[0] ); } } @unlink( $barrier );
}
// Missing projections must not make finalized history silently disappear from the read model.
$projection_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_mf_velog_service_vehicle_id'", $id ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_id' => (int) $projection_id ) );
mf_velog_serv_error( $query->current( $vehicle['id'], $manager ), 'storage_unavailable', 'Current reading fails closed on a missing service projection.' );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => '_mf_velog_service_vehicle_id', 'meta_value' => (string) $vehicle['id'] ) );
// Private type metadata must retain its complete audit chain.
$type_meta = get_term_meta( $type['id'], '_mf_velog_service_type', true );
$corrupt_type = $type_meta; $corrupt_type['audit'][0]['after']['name'] = 'Forged type audit';
update_term_meta( $type['id'], '_mf_velog_service_type', $corrupt_type );
mf_velog_serv_error( ServiceTypes::all( $manager ), 'storage_unavailable', 'Corrupt taxonomy audit chain rejected.' );
update_term_meta( $type['id'], '_mf_velog_service_type', $type_meta );
// Canonical stored fields are revalidated on reads, not merely trusted after unserializing.
$stored_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_mf_velog_record'", $id ) );
$payload = unserialize( $stored_raw, array( 'allowed_classes' => false ) );
$payload['fields']['reading']['canonical_mm'] = '1';
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => serialize( $payload ) ), array( 'post_id' => $id, 'meta_key' => '_mf_velog_record' ) );
mf_velog_serv_error( $query->get( $id, $manager ), 'forbidden', 'Malformed canonical stored reading is not exposed.' );
mf_velog_serv_error( $query->current( $vehicle['id'], $manager ), 'storage_unavailable', 'Corrupt canonical history cannot produce a current reading.' );
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $stored_raw ), array( 'post_id' => $id, 'meta_key' => '_mf_velog_record' ) );
// Fifty-one drafts exercise the real service page's bounded pagination.
for ( $i = 0; $i < 51; ++$i ) {
	$r = $service->create( array( 'vehicle_id' => $vehicle['id'], 'notes' => 'SERV-PAGE-' . $i ), $other, wp_generate_uuid4() );
	mf_velog_serv_assert( is_array( $r ), 'Pagination draft fixture ' . $i );
}
$page1 = $query->search( $other, 'draft' ); $page2 = $query->search( $other, 'draft', 0, 2 );
mf_velog_serv_assert( 51 === $page1['total'] && 50 === count( $page1['items'] ) && 1 === count( $page2['items'] ), 'Own-draft pagination returns 50 plus one, excluding foreign drafts.' );
file_put_contents( ABSPATH . '../serv001-fixture.json', wp_json_encode( array_merge( $ids, array( 'vehicle' => $vehicle['id'], 'type' => $type['id'], 'finalized' => $id, 'foreign_draft' => $incomplete['id'] ) ) ) );
echo "SERV-001 domain verification complete.\n";
