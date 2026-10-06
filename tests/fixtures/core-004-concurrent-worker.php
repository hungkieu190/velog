<?php
/**
 * Independent WordPress worker for the CORE-004 settings CAS race.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

if ( 8 !== $argc ) {
	fwrite( STDERR, "Invalid worker arguments.\n" );
	exit( 1 );
}

list(
	$script,
	$wp_load,
	$barrier_dir,
	$worker_id,
	$user_id,
	$expected_version,
	$distance_unit,
	$currency_code
) = $argv;
unset( $script );

define( 'MF_VELOG_TEST_CONCURRENT', true );
require $wp_load;

add_action(
	'mf_velog_test_concurrent_barrier',
	static function () use ( $barrier_dir, $worker_id ): void {
		$ready_path = $barrier_dir . '/ready-' . $worker_id;
		if ( false === file_put_contents( $ready_path, 'ready', LOCK_EX ) ) {
			throw new RuntimeException( 'Unable to write barrier readiness file.' );
		}

		$deadline = microtime( true ) + 10;
		while ( ! ( file_exists( $barrier_dir . '/ready-one' ) && file_exists( $barrier_dir . '/ready-two' ) ) ) {
			if ( microtime( true ) >= $deadline ) {
				throw new RuntimeException( 'Concurrency barrier timed out.' );
			}
			usleep( 10000 );
		}
	}
);

wp_set_current_user( (int) $user_id );
$result = \MF\VeLog\Common\Regional\ShopSettings::save_settings(
	array( 'distance_unit' => $distance_unit, 'currency_code' => $currency_code ),
	(int) $expected_version
);

if ( true === $result ) {
	echo wp_json_encode( array( 'worker' => $worker_id, 'status' => 'ok' ) );
	exit( 0 );
}
if ( is_wp_error( $result ) && 'stale_version' === $result->get_error_code() ) {
	echo wp_json_encode( array( 'worker' => $worker_id, 'status' => 'stale_version' ) );
	exit( 3 );
}

$error_code = is_wp_error( $result ) ? $result->get_error_code() : 'unexpected_result';
fwrite( STDERR, wp_json_encode( array( 'worker' => $worker_id, 'status' => $error_code ) ) );
exit( 1 );
