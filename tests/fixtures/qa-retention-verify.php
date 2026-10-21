<?php
/**
 * Verify exact private record retention after uninstalling a ZIP-only runtime.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! str_starts_with( ABSPATH, '/tmp/velog-product-smoke.' ) ) {
	exit( 1 );
}
global $wpdb;
$posts = $wpdb->get_results( "SELECT ID,post_type,post_status,post_author FROM {$wpdb->posts} WHERE post_type IN ('mf_velog_customer','mf_velog_vehicle','mf_velog_service','mf_velog_reminder') ORDER BY ID", ARRAY_A );
$meta = $wpdb->get_results( "SELECT m.post_id,m.meta_key,m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('mf_velog_customer','mf_velog_vehicle','mf_velog_service','mf_velog_reminder') ORDER BY m.post_id,m.meta_key,m.meta_id", ARRAY_A );
if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Retention snapshot SQL failed.' ); }
$state = array( 'posts' => count( $posts ), 'metadata' => count( $meta ), 'records_sha256' => hash( 'sha256', serialize( array( $posts, $meta ) ) ), 'settings' => get_option( 'velog_settings' ) );
$path = ABSPATH . '../qa-retention.json';
if ( 'snapshot' === ( $args[0] ?? '' ) ) {
	if ( false === file_put_contents( $path, wp_json_encode( $state ) ) ) { throw new RuntimeException( 'Cannot persist retention snapshot.' ); }
	echo "PASS: ZIP-only retention snapshot recorded.\n";
} elseif ( 'verify' === ( $args[0] ?? '' ) ) {
	$expected = json_decode( file_get_contents( $path ), true );
	if ( $expected !== $state || 0 === $state['posts'] ) { throw new RuntimeException( 'Uninstall/reinstall changed operational records or Settings.' ); }
	$manager = get_user_by( 'login', 'serv_manager' );
	if ( ! $manager instanceof WP_User || ! $manager->has_cap( 'mf_velog_manage_reminders' ) ) { throw new RuntimeException( 'Reinstall lost manager capabilities.' ); }
	echo "PASS: ZIP-only uninstall/reinstall retained exact records, audits, derived indexes, Settings and manager access.\n";
} else {
	exit( 1 );
}
