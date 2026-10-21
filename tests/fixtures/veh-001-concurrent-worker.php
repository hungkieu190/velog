<?php
/**
 * VEH-001 subprocess worker, restricted to disposable product-smoke sites.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Vehicle\VehicleService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}

$mode    = $args[0] ?? '';
$actor   = new WP_User( (int) ( $args[1] ?? 0 ) );
$target  = (int) ( $args[2] ?? 0 );
$barrier = ABSPATH . 'veh001-race-go';
$end     = microtime( true ) + 20;
while ( ! is_file( $barrier ) ) {
	if ( microtime( true ) > $end ) {
		exit( 2 );
	}
	usleep( 10000 );
}
$service = new VehicleService();
if ( 'duplicate' === $mode ) {
	$result = $service->create( array( 'vin' => 'CONCURRENT-VEH001' ), $actor, wp_generate_uuid4() );
} elseif ( 'archive_customer' === $mode ) {
	$result = ( new CustomerService() )->archive( $target, 1, $actor, wp_generate_uuid4() );
} elseif ( 'assign_customer' === $mode ) {
	$result = $service->create( array( 'vin' => 'RACE-OWNER-VEH001', 'current_customer_id' => $target ), $actor, wp_generate_uuid4() );
} else {
	exit( 3 );
}
echo is_wp_error( $result ) ? $result->get_error_code() : 'ok';
