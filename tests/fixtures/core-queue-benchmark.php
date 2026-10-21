<?php
/**
 * QA-001 exact-size synthetic read benchmark on a disposable database only.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerQuery;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Reminder\ReminderQuery;
use MF\VeLog\Common\Service\ServiceHistoryQuery;
use MF\VeLog\Common\Service\ServiceQuery;
use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Vehicle\VehicleQuery;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}
// Copy valid canonical shapes produced by the domain fixture before replacing synthetic data.
global $wpdb;
$types = array( 'mf_velog_customer' => 1000, 'mf_velog_vehicle' => 2000, 'mf_velog_service' => 20000, 'mf_velog_reminder' => 5000 );
$templates = array();
foreach ( $types as $type => $count ) {
	$state = 'mf_velog_service' === $type ? 'finalized' : 'active';
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_mf_velog_state' WHERE p.post_type=%s AND s.meta_value=%s LIMIT 1", $type, $state ) );
	$templates[ $type ] = get_post_meta( (int) $id, '_mf_velog_record', true );
	if ( ! is_array( $templates[ $type ] ) ) { throw new RuntimeException( 'Missing benchmark template.' ); }
}
$manager = get_user_by( 'login', 'serv_manager' );
if ( ! $manager instanceof WP_User ) { throw new RuntimeException( 'Missing benchmark manager.' ); }
$wpdb->query( "DELETE m FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('mf_velog_customer','mf_velog_vehicle','mf_velog_service','mf_velog_reminder')" );
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ('mf_velog_customer','mf_velog_vehicle','mf_velog_service','mf_velog_reminder')" );
$next = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0)+1 FROM {$wpdb->posts}" );
$bases = array();
foreach ( $types as $type => $count ) {
	$bases[ $type ] = $next;
	for ( $offset = 0; $offset < $count; $offset += 100 ) {
		$posts = array(); $post_values = array(); $metas = array(); $meta_values = array();
		for ( $i = $offset; $i < min( $offset + 100, $count ); ++$i ) {
			$id = $bases[ $type ] + $i;
			$env = $templates[ $type ];
			$f = $env['fields'];
			if ( 'mf_velog_customer' === $type ) {
				$f['name'] = 'Benchmark customer ' . $i;
			} elseif ( 'mf_velog_vehicle' === $type ) {
				$f['vin'] = 'BENCH-VIN-' . $i;
				$f['current_customer_id'] = $bases['mf_velog_customer'] + $i % 1000;
				$f['baseline'] = array_merge( Distance::parse( '100', 'km', '.' ), array( 'date' => '2020-01-01' ) );
			} elseif ( 'mf_velog_service' === $type ) {
				$f['vehicle_id'] = $bases['mf_velog_vehicle'] + intdiv( $i, 10 );
				$f['service_date'] = '2020-01-02';
				$f['reading'] = Distance::parse( '151', 'km', '.' );
				$f['notes'] = 'Benchmark service ' . $i;
				$f['customer_snapshot'] = array( 'id' => $bases['mf_velog_customer'] + intdiv( $i, 10 ) % 1000, 'name' => 'Benchmark snapshot' );
			} else {
				$f['vehicle_id'] = $bases['mf_velog_vehicle'] + $i % 2000;
				$f['title'] = 'Benchmark reminder ' . $i;
				$f['due_date'] = $i % 2 ? '2099-01-01' : '2020-01-01';
				$f['threshold'] = Distance::parse( '1000', 'km', '.' );
				$f['snooze_until'] = ''; $f['completed_by'] = 0; $f['completed_at_utc'] = '';
			}
			$env['fields'] = $f;
			// Synthetic read fixture uses canonical complete snapshots; it does not claim write throughput.
			foreach ( $env['audit'] as &$entry ) {
				foreach ( array( 'before', 'after' ) as $side ) {
					foreach ( $f as $field => $value ) {
						if ( array_key_exists( $field, $entry[ $side ] ) ) { $entry[ $side ][ $field ] = $value; }
					}
				}
			}
			unset( $entry );
			$valid = RecordSchema::validate_record( $type, $f );
			if ( is_wp_error( $valid ) ) { throw new RuntimeException( 'Invalid canonical benchmark fields.' ); }
			$posts[] = '(%d,%d,%s,%s,%s,%s,%s)';
			array_push( $post_values, $id, $env['created_by'], $type, 'private', gmdate( 'Y-m-d H:i:s', strtotime( $env['created_at_utc'] ) ), gmdate( 'Y-m-d H:i:s', strtotime( $env['updated_at_utc'] ) ), 'Benchmark' );
			$projection = RecordSchema::derive_projections( $type, $env );
			$projection['_mf_velog_record'] = serialize( $env );
			if ( 'mf_velog_vehicle' === $type ) {
				$projection['_mf_velog_current_mm'] = '151000000';
				$projection['_mf_velog_current_date'] = '2020-01-02';
				$projection['_mf_velog_current_service_id'] = (string) ( $bases['mf_velog_vehicle'] + 2000 + $i * 10 + 9 );
			}
			foreach ( $projection as $key => $value ) {
				$metas[] = '(%d,%s,%s)'; array_push( $meta_values, $id, $key, $value );
			}
		}
		$ok = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->posts} (ID,post_author,post_type,post_status,post_date_gmt,post_modified_gmt,post_title) VALUES " . implode( ',', $posts ), ...$post_values ) );
		if ( false === $ok ) { throw new RuntimeException( 'Benchmark post insert failed.' ); }
		$ok = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $metas ), ...$meta_values ) );
		if ( false === $ok ) { throw new RuntimeException( 'Benchmark meta insert failed.' ); }
	}
	$next += $count;
	echo 'BENCH_DATA: ' . $type . '=' . $count . "\n";
}
// Bulk loading requires refreshed optimizer statistics before warm query sampling.
$wpdb->query( "ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}" );
if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Benchmark statistics refresh failed.' ); }
wp_cache_flush();
$cases = array(
	'customers_search' => array( static fn () => ( new CustomerQuery() )->search( array( 'term' => 'Benchmark' ), $manager ), 1000 ),
	'vehicles_search' => array( static fn () => ( new VehicleQuery() )->search( array( 'term' => 'BENCH-VIN' ), $manager ), 2000 ),
	'services_finalized' => array( static fn () => ( new ServiceQuery() )->search( $manager, 'finalized' ), 20000 ),
	'history_filtered' => array( static fn () => ( new ServiceHistoryQuery() )->search( $bases['mf_velog_vehicle'], array( 'term' => 'Benchmark' ), $manager ), 10 ),
	'reminders_due' => array( static fn () => ( new ReminderQuery() )->search( $manager, 'due' ), 2500 ),
);
$failed = false;
foreach ( $cases as $name => [ $query, $expected ] ) {
	$samples = array();
	for ( $i = 0; $i < 35; ++$i ) {
		$start = microtime( true ); $r = $query(); $elapsed = microtime( true ) - $start;
		if ( is_wp_error( $r ) || $expected !== $r['total'] || min( 50, $expected ) !== count( $r['items'] ) ) {
			throw new RuntimeException( 'Benchmark query correctness failed: ' . $name . ( is_wp_error( $r ) ? ' ' . $r->get_error_code() : '' ) );
		}
		if ( $i >= 5 ) { $samples[] = $elapsed; }
	}
	sort( $samples ); $p95 = $samples[28]; $pass = $p95 <= 2.0; $failed = $failed || ! $pass;
	echo 'BENCH_RESULT: ' . wp_json_encode( array( 'query' => $name, 'samples' => 30, 'warmups' => 5, 'p95_seconds' => $p95, 'max_seconds' => max( $samples ), 'expected_total' => $expected, 'result' => $pass ? 'PASS' : 'FAIL' ) ) . "\n";
}
if ( $failed ) { exit( 1 ); }
