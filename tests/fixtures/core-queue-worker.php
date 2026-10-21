<?php
/**
 * QA-001 bounded reminder race worker restricted to disposable sites.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Reminder\ReminderService;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}
$actor = new WP_User( (int) ( $args[0] ?? 0 ) );
$id = (int) ( $args[1] ?? 0 );
$barrier = ABSPATH . 'queue-race-go';
$deadline = microtime( true ) + 20;
while ( ! is_file( $barrier ) ) {
	if ( microtime( true ) > $deadline ) {
		exit( 2 );
	}
	usleep( 10000 );
}
$result = ( new ReminderService() )->change( $id, 1, 'complete', array(), $actor, wp_generate_uuid4() );
echo is_wp_error( $result ) ? $result->get_error_code() : 'ok';
