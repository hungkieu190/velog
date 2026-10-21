<?php
/**
 * Isolated concurrent service finalizer.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Service\ServiceService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) { exit( 1 ); }
$actor = new WP_User( (int) ( $args[0] ?? 0 ) );
$id = (int) ( $args[1] ?? 0 );
$until = microtime( true ) + 20;
while ( ! is_file( ABSPATH . 'serv001-race-go' ) ) {
	if ( microtime( true ) > $until ) { exit( 2 ); } usleep( 10000 );
}
$result = 'deactivate' === ( $args[2] ?? '' )
	? MF\VeLog\Common\Service\ServiceTypes::save( $id, 1, 'Race active type', 'inactive', $actor, wp_generate_uuid4() )
	: ( new ServiceService() )->change( $id, 1, array(), 'finalize', $actor, wp_generate_uuid4() );
echo is_wp_error( $result ) ? $result->get_error_code() : 'ok';
