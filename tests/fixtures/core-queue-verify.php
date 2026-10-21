<?php
/**
 * QA-001 isolated history, reminder and transactional index verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Reminder\ReminderQuery;
use MF\VeLog\Common\Reminder\ReminderService;
use MF\VeLog\Common\Service\ServiceHistoryQuery;
use MF\VeLog\Common\Service\ServiceService;
use MF\VeLog\Common\Service\ServiceTypes;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;
use MF\VeLog\Common\Vehicle\VehicleService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}
require __DIR__ . '/serv-001-verify.php';
// Reuse genuine service lifecycle/permission regressions and isolated staff setup.
$manager = new WP_User( $ids['serv_manager'] );
$tech = new WP_User( $ids['serv_tech'] );
$denied = new WP_User( $ids['serv_denied'] );
$today = CalendarDate::today( time(), wp_timezone() );
$future = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+2 days' )->format( 'Y-m-d' );
$vservice = new VehicleService();
$customer = ( new CustomerService() )->create( array( 'name' => 'Queue owner', 'phone' => 'QUEUE-SECRET' ), $manager, wp_generate_uuid4() );
$v = $vservice->create( array( 'vin' => 'QUEUE-KNOWN', 'current_customer_id' => $customer['id'], 'baseline' => array_merge( Distance::parse( '100', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
$unknown = $vservice->create( array( 'vin' => 'QUEUE-UNKNOWN' ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $v ) && is_array( $unknown ), 'Queue fixtures use independent known/unknown vehicles.' );
$reminders = new ReminderService();
$queue = new ReminderQuery();
$history = new ServiceHistoryQuery();
$input = array( 'vehicle_id' => $v['id'], 'title' => 'Brake inspection', 'due_date' => $future, 'threshold' => Distance::parse( '101', 'km', '.' ) );
mf_velog_serv_error( $reminders->create( $input, $tech, wp_generate_uuid4() ), 'forbidden', 'Technician cannot create manager reminders.' );
mf_velog_serv_error( $queue->search( $tech ), 'forbidden', 'Technician cannot query manager reminders.' );
mf_velog_serv_error( $history->search( $v['id'], array(), $denied ), 'forbidden', 'Subscriber cannot query private history.' );
$request = wp_generate_uuid4();
$r = $reminders->create( $input, $manager, $request );
mf_velog_serv_assert( is_array( $r ), 'Reminder create initializes baseline index.' );
$replay = $reminders->create( $input, $manager, $request );
mf_velog_serv_assert( is_array( $replay ) && $replay['id'] === $r['id'], 'Reminder create replay is idempotent.' );
mf_velog_serv_assert( '100000000' === get_post_meta( $v['id'], '_mf_velog_current_mm', true ), 'Initial current index uses canonical baseline.' );
$initial = $queue->search( $manager, 'upcoming', $v['id'] );
mf_velog_serv_assert( is_array( $initial ) && 1 === $initial['total'], 'Queue correctly filters future date and unreached distance.' );
mf_velog_serv_error( RecordRepository::get( 'mf_velog_reminder', $r['id'], $tech ), 'forbidden', 'Direct reminder detail denies technician.' );
$un = $reminders->create( array_merge( $input, array( 'vehicle_id' => $unknown['id'], 'threshold' => Distance::parse( '0', 'mi', '.' ) ) ), $manager, wp_generate_uuid4() );
$unknown_queue = $queue->search( $manager, 'upcoming', $unknown['id'] );
mf_velog_serv_assert( is_array( $un ) && 1 === $unknown_queue['total'] && null === $unknown_queue['items'][0]['current_mm'], 'Unknown reading does not satisfy a zero threshold.' );
$zero = $vservice->update( $unknown['id'], 1, array( 'baseline' => array_merge( Distance::parse( '0', 'km', '.' ), array( 'date' => '2020-01-01' ) ) ), $manager, wp_generate_uuid4() );
$zero_queue = $queue->search( $manager, 'due', $unknown['id'] );
mf_velog_serv_assert( is_array( $zero ) && 1 === $zero_queue['total'], 'Baseline write refreshes index and known zero satisfies zero threshold.' );
$type = ServiceTypes::save( 0, 0, 'Queue service', 'active', $manager, wp_generate_uuid4() );
$services = new ServiceService();
$first = 0;
for ( $i = 0; $i < 51; ++$i ) {
	$d = $services->create( array( 'vehicle_id' => $v['id'], 'service_date' => '2020-01-02', 'reading' => Distance::parse( (string) ( 101 + $i ), 'km', '.' ), 'service_type_id' => $type['id'], 'notes' => 'Queue history ' . $i, 'cost' => Money::parse( '1.234', 'KWD', '.' ) ), $tech, wp_generate_uuid4() );
	$f = $services->change( $d['id'], 1, array(), 'finalize', $tech, wp_generate_uuid4() );
	mf_velog_serv_assert( is_array( $f ), 'Finalized history fixture ' . $i );
	$first = 0 === $i ? $f['id'] : $first;
}
$last = $f['id'];
$queries_before = $wpdb->num_queries;
$h1 = $history->search( $v['id'], array(), $tech );
mf_velog_serv_assert( $wpdb->num_queries - $queries_before < 25, 'Fifty-row history has bounded batch query count rather than per-service lookups.' );
$h2 = $history->search( $v['id'], array( 'page' => 2 ), $tech );
mf_velog_serv_assert( is_array( $h1 ) && 51 === $h1['total'] && 50 === count( $h1['items'] ) && $last === $h1['items'][0]['id'] && 1 === count( $h2['items'] ), 'History count/order/pagination is 50 plus one; drafts excluded.' );
mf_velog_serv_assert( $last === $h1['current']['service_id'] && '151000000' === get_post_meta( $v['id'], '_mf_velog_current_mm', true ), 'Same-day finalized writes refresh current index by descending ID.' );
$filter = $history->search( $v['id'], array( 'from' => '2020-01-02', 'to' => '2020-01-02', 'type' => $type['id'], 'term' => 'history 50' ), $tech );
mf_velog_serv_assert( is_array( $filter ) && 1 === $filter['total'] && $last === $filter['items'][0]['id'], 'Date/type/text filters apply inclusively before count/page.' );
$none = $history->search( $v['id'], array( 'from' => '2020-01-03' ), $tech );
mf_velog_serv_assert( 0 === $none['total'] && $last === $none['current']['service_id'], 'History filters never change current reading provenance.' );
mf_velog_serv_error( $history->search( $v['id'], array( 'from' => '2020-02-30' ), $tech ), 'invalid_input', 'History rejects malformed calendar date.' );
$correction = $services->change( $last, 2, array( 'notes' => 'Corrected queue note' ), 'correct', $manager, wp_generate_uuid4(), 'Fix recorded note' );
$correct_history = $history->search( $v['id'], array( 'term' => 'Corrected queue note' ), $tech );
mf_velog_serv_assert( is_array( $correction ) && 'Fix recorded note' === $correct_history['items'][0]['audit'][2]['reason'] && ! str_contains( wp_json_encode( $correct_history ), 'QUEUE-SECRET' ), 'Correction disclosure includes reason and excludes private contacts.' );
$due = $queue->search( $manager, 'due', $v['id'] );
mf_velog_serv_assert( 1 === $due['total'], 'Distance alone makes a future-date reminder due.' );
$snoozed = $reminders->change( $r['id'], 1, 'snooze', array( 'snooze_until' => $future ), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $snoozed ) && 1 === $queue->search( $manager, 'snoozed', $v['id'] )['total'] && 0 === $queue->search( $manager, 'due', $v['id'] )['total'], 'Future snooze suppresses already reached threshold.' );
mf_velog_serv_error( $reminders->change( $r['id'], 1, 'resume', array(), $manager, wp_generate_uuid4() ), 'stale_version', 'Reminder rejects stale mutation.' );
$resumed = $reminders->change( $r['id'], 2, 'resume', array(), $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $resumed ) && 1 === $queue->search( $manager, 'due', $v['id'] )['total'], 'Resume restores due queue without creating a service.' );
$archived = $vservice->transition( $v['id'], 1, 'archived', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $archived ) && 1 === $queue->search( $manager, 'suspended', $v['id'] )['total'], 'Vehicle archive suspends effective reminder.' );
$restored = $vservice->transition( $v['id'], 2, 'active', $manager, wp_generate_uuid4() );
mf_velog_serv_assert( is_array( $restored ) && 1 === $queue->search( $manager, 'due', $v['id'] )['total'], 'Vehicle restore restores threshold evaluation.' );
$complete_request = wp_generate_uuid4();
$complete = $reminders->change( $r['id'], 3, 'complete', array(), $manager, $complete_request );
mf_velog_serv_assert( is_array( $complete ) && $manager->ID === $complete['fields']['completed_by'] && 1 === $queue->search( $manager, 'completed', $v['id'] )['total'], 'Completion stores server actor/time and terminal queue state.' );
$again = $reminders->change( $r['id'], 3, 'complete', array(), $manager, $complete_request );
mf_velog_serv_assert( is_array( $again ) && $again['record_version'] === $complete['record_version'], 'Completion replay after lost response is idempotent.' );
mf_velog_serv_error( $reminders->change( $r['id'], 4, 'complete', array(), $manager, $complete_request ), 'invalid_input', 'Reused request with a different base version is rejected.' );
mf_velog_serv_error( $reminders->change( $r['id'], 3, 'resume', array(), $manager, $complete_request ), 'invalid_input', 'Reused request cannot change operation.' );
mf_velog_serv_error( $reminders->change( $r['id'], 4, 'resume', array(), $manager, wp_generate_uuid4() ), 'invalid_state', 'Completed reminder cannot reopen.' );
$before_mm = get_post_meta( $v['id'], '_mf_velog_current_mm', true );
$rollback = WriteCoordinator::run( $manager, static function ( WriteUnit $unit ) use ( $last ): WP_Error {
	$saved = $unit->save( 'mf_velog_service', $last, 3, array( 'reading' => Distance::parse( '152', 'km', '.' ) ), wp_generate_uuid4(), 'Rollback probe' );
	mf_velog_serv_assert( is_array( $saved ), 'Failure probe reaches service after-write projection.' );
	return new WP_Error( 'injected_failure' );
} );
mf_velog_serv_error( $rollback, 'injected_failure', 'Caller failure rolls back complete write unit.' );
mf_velog_serv_assert( $before_mm === get_post_meta( $v['id'], '_mf_velog_current_mm', true ) && 3 === RecordRepository::get( 'mf_velog_service', $last, $manager )['record_version'], 'Rollback restores service version and derived current index together.' );
for ( $i = 0; $i < 51; ++$i ) {
	$p = $reminders->create( array_merge( $input, array( 'title' => 'Queue-page-' . $i ) ), $manager, wp_generate_uuid4() );
	mf_velog_serv_assert( is_array( $p ), 'Reminder pagination fixture ' . $i );
}
$queries_before = $wpdb->num_queries;
$q1 = $queue->search( $manager, 'due', $v['id'] );
mf_velog_serv_assert( $wpdb->num_queries - $queries_before < 25, 'Fifty-row reminder page hydrates relations in bounded batches.' );
$q2 = $queue->search( $manager, 'due', $v['id'], 2 );
mf_velog_serv_assert( 51 === $q1['total'] && 50 === count( $q1['items'] ) && 1 === count( $q2['items'] ), 'SQL effective filtering/count precedes reminder 50 plus one pagination.' );
// Competing complete requests must serialize to one terminal audit.
$race = $reminders->create( $input, $manager, wp_generate_uuid4() );
$workers = array();
$barrier = ABSPATH . 'queue-race-go';
try {
	for ( $i = 0; $i < 2; ++$i ) {
		$proc = proc_open( array( 'wp', 'eval-file', __DIR__ . '/core-queue-worker.php', (string) $manager->ID, (string) $race['id'], '--path=' . ABSPATH ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		mf_velog_serv_assert( is_resource( $proc ), 'Reminder race worker starts.' );
		fclose( $pipes[0] ); $workers[] = array( $proc, $pipes );
	}
	touch( $barrier ); $results = array();
	foreach ( $workers as &$worker ) {
		$results[] = trim( stream_get_contents( $worker[1][1] ) );
		$err = stream_get_contents( $worker[1][2] );
		fclose( $worker[1][1] ); fclose( $worker[1][2] );
		$exit = proc_close( $worker[0] ); $worker[0] = null;
		mf_velog_serv_assert( 0 === $exit && '' === $err, 'Reminder race worker exits cleanly.' );
	}
	unset( $worker ); sort( $results );
	mf_velog_serv_assert( array( 'invalid_state', 'ok' ) === $results, 'Concurrent reminder completion commits exactly once.' );
} finally {
	foreach ( $workers as $worker ) {
		if ( is_resource( $worker[0] ) ) { proc_terminate( $worker[0] ); proc_close( $worker[0] ); }
	}
	@unlink( $barrier );
}
mf_velog_serv_assert( 2 === count( RecordRepository::get( 'mf_velog_reminder', $race['id'], $manager )['audit'] ), 'Concurrent completion retains exactly one transition audit.' );
// Detect missing/duplicate/corrupt indexes, then restore exact values.
$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $v['id'], 'meta_key' => '_mf_velog_current_mm' ) );
mf_velog_serv_error( $queue->search( $manager ), 'storage_unavailable', 'Missing current projection cannot silently become upcoming.' );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $v['id'], 'meta_key' => '_mf_velog_current_mm', 'meta_value' => $before_mm ) );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $v['id'], 'meta_key' => '_mf_velog_current_mm', 'meta_value' => $before_mm ) );
mf_velog_serv_error( $queue->search( $manager ), 'storage_unavailable', 'Duplicate current projection cannot inflate queue totals.' );
$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $v['id'], 'meta_key' => '_mf_velog_current_mm' ) );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $v['id'], 'meta_key' => '_mf_velog_current_mm', 'meta_value' => $before_mm ) );
$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $first, 'meta_key' => '_mf_velog_service_vehicle_id' ) );
mf_velog_serv_error( $history->search( $v['id'], array(), $tech ), 'storage_unavailable', 'Missing history relation fails closed rather than dropping finalized service.' );
$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $first, 'meta_key' => '_mf_velog_service_vehicle_id', 'meta_value' => (string) $v['id'] ) );
file_put_contents( ABSPATH . '../queue-fixture.json', wp_json_encode( array( 'vehicle' => $v['id'], 'reminder' => $p['id'], 'future' => $future ) ) );
echo "QA-001 history/reminder domain verification complete.\n";
