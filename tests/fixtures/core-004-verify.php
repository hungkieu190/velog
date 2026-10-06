<?php
/**
 * CORE-004 WordPress integration verification.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Admin\RegionalSettingsPage;
use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\RecordSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['core004_barrier_dir'] = '';

/** Remove the task-owned process barrier directory. */
function core004_cleanup_barrier(): void {
	$directory = $GLOBALS['core004_barrier_dir'];
	if ( ! is_string( $directory ) || '' === $directory || ! is_dir( $directory ) ) {
		return;
	}
	foreach ( glob( $directory . '/*' ) ?: array() as $path ) {
		if ( is_file( $path ) ) {
			unlink( $path );
		}
	}
	rmdir( $directory );
}
register_shutdown_function( 'core004_cleanup_barrier' );

/**
 * Fail the fixture with a stable message.
 *
 * @param string $message Failure details.
 */
function core004_fail( string $message ): never {
	echo "FAIL: {$message}\n";
	exit( 1 );
}

/**
 * Assert a fixture invariant.
 *
 * @param bool   $condition Required condition.
 * @param string $message   Assertion label.
 */
function core004_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		core004_fail( $message );
	}
	echo "PASS: {$message}\n";
}

/**
 * Submit the real admin handler and capture its redirect or wp_die marker.
 *
 * @param RegionalSettingsPage $page    Page handler.
 * @param int                  $user_id WordPress user ID.
 * @param array<string, mixed> $post    Request body.
 * @return string
 */
function core004_submit( RegionalSettingsPage $page, int $user_id, array $post ): string {
	wp_set_current_user( $user_id );
	$_POST = $post;
	try {
		$page->handle_save();
	} catch ( RuntimeException $exception ) {
		return $exception->getMessage();
	}
	core004_fail( 'The admin handler returned without redirecting or terminating.' );
}

/** Read the exact settings option bytes. */
function core004_raw_settings(): ?string {
	global $wpdb;
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			ShopSettings::OPTION_NAME
		)
	);
}

/**
 * Run two independent WordPress processes through the internal CAS barrier.
 *
 * @param int $manager_id Manager user ID.
 */
function core004_verify_concurrent_update( int $manager_id ): void {
	$barrier_dir = sys_get_temp_dir() . '/velog-core004-' . wp_generate_uuid4();
	if ( ! mkdir( $barrier_dir, 0700 ) && ! is_dir( $barrier_dir ) ) {
		core004_fail( 'Unable to create concurrency barrier directory.' );
	}
	$GLOBALS['core004_barrier_dir'] = $barrier_dir;

	$worker    = __DIR__ . '/core-004-concurrent-worker.php';
	$workers   = array( array( 'one', 'mi', 'USD' ), array( 'two', 'km', 'KWD' ) );
	$processes = array();
	foreach ( $workers as $worker_args ) {
		$command = array(
			PHP_BINARY,
			$worker,
			ABSPATH . 'wp-load.php',
			$barrier_dir,
			$worker_args[0],
			(string) $manager_id,
			'1',
			$worker_args[1],
			$worker_args[2],
		);
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process = proc_open( $command, $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			core004_fail( 'Unable to start a concurrency worker.' );
		}
		fclose( $pipes[0] );
		$processes[] = array( $process, $pipes );
	}

	$exits   = array();
	$outputs = array();
	foreach ( $processes as $process_data ) {
		list( $process, $pipes ) = $process_data;
		$outputs[] = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exits[] = proc_close( $process );
	}
	sort( $exits );
	core004_assert(
		array( 0, 3 ) === $exits,
		'Two process CAS race has exactly one success and one stale_version: ' . implode( ' | ', $outputs )
	);

	$settings = ShopSettings::get_settings();
	core004_assert( ! is_wp_error( $settings ), 'Settings remain readable after the CAS race.' );
	core004_assert( 2 === $settings['record_version'], 'CAS race increments the record version exactly once.' );
	core004_cleanup_barrier();
	$GLOBALS['core004_barrier_dir'] = '';
}

echo "Running CORE-004 verification...\n";
$plugin = \MF\VeLog\Core\Plugin::get_instance();
$plugin->run();

$manager_id = wp_insert_user(
	array(
		'user_login' => 'manager1',
		'user_pass'  => 'pass1',
		'role'       => 'administrator',
	)
);
$subscriber_id = wp_insert_user(
	array(
		'user_login' => 'sub1',
		'user_pass'  => 'pass1',
		'role'       => 'subscriber',
	)
);
core004_assert( is_int( $manager_id ), 'Manager user created.' );
core004_assert( is_int( $subscriber_id ), 'Subscriber user created.' );
$manager = get_user_by( 'id', $manager_id );
core004_assert( $manager instanceof WP_User, 'Manager identity is available.' );

add_filter(
	'wp_die_handler',
	static function (): callable {
		return static function ( $message, $title = '', $args = array() ): void {
			unset( $title, $args );
			throw new RuntimeException( 'WP_DIE: ' . wp_strip_all_tags( (string) $message ) );
		};
	}
);
add_filter(
	'wp_redirect',
	static function ( string $location ): never {
		throw new RuntimeException( 'REDIRECT: ' . $location );
	}
);

$page = new RegionalSettingsPage();
wp_set_current_user( $manager_id );
$manager_nonce = wp_create_nonce( 'velog_save_settings' );
$denied        = core004_submit(
	$page,
	$subscriber_id,
	array(
		'velog_settings_nonce' => $manager_nonce,
		'record_version'       => '0',
		'distance_unit'        => 'km',
		'currency_code'        => 'JPY',
	)
);
core004_assert( str_contains( $denied, 'Unauthorized' ), 'Subscriber is denied by the real handler.' );

$bad_nonce = core004_submit(
	$page,
	$manager_id,
	array(
		'velog_settings_nonce' => 'invalid_nonce',
		'record_version'       => '0',
		'distance_unit'        => 'km',
		'currency_code'        => 'JPY',
	)
);
core004_assert( str_contains( $bad_nonce, 'Invalid nonce' ), 'Invalid nonce is denied by the real handler.' );

wp_set_current_user( $manager_id );
$first_save = core004_submit(
	$page,
	$manager_id,
	array(
		'velog_settings_nonce' => wp_create_nonce( 'velog_save_settings' ),
		'record_version'       => '0',
		'distance_unit'        => 'km',
		'currency_code'        => 'JPY',
	)
);
core004_assert( str_starts_with( $first_save, 'REDIRECT:' ), 'First JPY/km handler save redirects.' );
$notice = get_transient( "velog_settings_notice_{$manager_id}" );
core004_assert( is_array( $notice ) && 'success' === $notice['type'], 'First JPY/km save succeeds.' );

$stale_save = core004_submit(
	$page,
	$manager_id,
	array(
		'velog_settings_nonce' => wp_create_nonce( 'velog_save_settings' ),
		'record_version'       => '0',
		'distance_unit'        => 'mi',
		'currency_code'        => 'USD',
	)
);
core004_assert( str_starts_with( $stale_save, 'REDIRECT:' ), 'Stale handler save redirects.' );
$notice = get_transient( "velog_settings_notice_{$manager_id}" );
core004_assert( is_array( $notice ) && 'error' === $notice['type'], 'Sequential stale version is rejected.' );

core004_verify_concurrent_update( $manager_id );

wp_set_current_user( $manager_id );
$settings = ShopSettings::get_settings();
core004_assert( ! is_wp_error( $settings ), 'Settings are readable before the exact JPY/km save.' );
$jpy_result = ShopSettings::save_settings(
	array( 'distance_unit' => 'km', 'currency_code' => 'JPY' ),
	$settings['record_version']
);
core004_assert( true === $jpy_result, 'Exact JPY/km settings save succeeds.' );
$settings = ShopSettings::get_settings();
core004_assert( ! is_wp_error( $settings ), 'Exact JPY/km settings are readable.' );
core004_assert(
	maybe_serialize( $settings ) === core004_raw_settings(),
	'Database option bytes exactly match the validated JPY/km envelope.'
);
core004_assert(
	'JPY' === $settings['currency_code'] && 0 === $settings['currency_scale'] && 'km' === $settings['distance_unit'],
	'JPY/km identity and zero currency scale are exact.'
);

RecordSchema::reset_for_testing();
RecordSchema::register(
	'mf_velog_service',
	array(
		'capability'      => 'mf_velog_create_services',
		'read_capability' => 'mf_velog_read_records',
		'states'          => array( 'draft', 'finalized' ),
		'field_policies'  => array(
			'distance_value'  => 'public',
			'distance_unit'   => 'public',
			'amount_minor'    => 'public',
			'currency_code'   => 'public',
			'currency_scale'  => 'public',
			'vehicle_visible' => 'public',
		),
		'fields'          => array(
			'distance_value'  => static fn ( mixed $value ) => is_string( $value ) ? $value : null,
			'distance_unit'   => static fn ( mixed $value ) => in_array( $value, array( 'km', 'mi' ), true ) ? $value : null,
			'amount_minor'    => static fn ( mixed $value ) => is_string( $value ) ? $value : null,
			'currency_code'   => static fn ( mixed $value ) => is_string( $value ) && 1 === preg_match( '/^[A-Z]{3}$/', $value ) ? $value : null,
			'currency_scale'  => static fn ( mixed $value ) => is_int( $value ) && $value >= 0 && $value <= 3 ? $value : null,
			'vehicle_visible' => static fn ( mixed $value ) => is_bool( $value ) ? $value : null,
		),
		'context_builder' => static function ( array $envelope, WP_User $actor ): array {
			unset( $actor );
			return array(
				'type'            => 'mf_velog_service',
				'state'           => $envelope['state'] ?? 'draft',
				'author'          => (int) ( $envelope['created_by'] ?? 0 ),
				'vehicle_visible' => (bool) ( $envelope['fields']['vehicle_visible'] ?? false ),
			);
		},
	)
);
RecordSchema::seal();
$manager->add_cap( 'mf_velog_create_services' );
$manager->add_cap( 'mf_velog_read_records' );
wp_set_current_user( $manager_id );

$historical_fields = array(
	'distance_value'  => '10000.000',
	'distance_unit'   => 'km',
	'amount_minor'    => '125000',
	'currency_code'   => 'JPY',
	'currency_scale'  => 0,
	'vehicle_visible' => true,
);
$record_result = RecordRepository::create(
	'mf_velog_service',
	$historical_fields,
	clone $manager,
	wp_generate_uuid4()
);
core004_assert( ! is_wp_error( $record_result ), 'RecordRepository::create historical seed succeeds.' );
core004_assert( $historical_fields === $record_result['fields'], 'Created snapshot fields are exact.' );
core004_assert( 1 === $record_result['record_version'], 'Created envelope has record version 1.' );
core004_assert( 1 === $record_result['schema_version'], 'Created envelope has schema version 1.' );
core004_assert( 1 === count( $record_result['audit'] ), 'Created envelope has one audit entry.' );
core004_assert( $manager_id === $record_result['audit'][0]['actor_id'], 'Audit entry is bound to the manager.' );
core004_assert( 'create' === $record_result['audit'][0]['op'], 'Audit entry records create.' );
core004_assert( $historical_fields === $record_result['audit'][0]['after'], 'Audit after snapshot is exact.' );

$record_id = $record_result['id'];
global $wpdb;
$meta_before = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$record_id,
		RecordSchema::META_KEY
	)
);
core004_assert( is_string( $meta_before ) && '' !== $meta_before, 'Historical raw envelope exists.' );
$signature_before = hash( 'sha256', $meta_before );

foreach ( array( array( 'mi', 'USD', 2 ), array( 'km', 'KWD', 3 ) ) as $preference ) {
	$current = ShopSettings::get_settings();
	core004_assert( ! is_wp_error( $current ), 'Settings remain readable before preference switch.' );
	$result = ShopSettings::save_settings(
		array( 'distance_unit' => $preference[0], 'currency_code' => $preference[1] ),
		$current['record_version']
	);
	core004_assert( true === $result, "{$preference[1]}/{$preference[0]} settings switch succeeds." );
	$current = ShopSettings::get_settings();
	core004_assert(
		$preference[1] === $current['currency_code'] &&
		$preference[0] === $current['distance_unit'] &&
		$preference[2] === $current['currency_scale'],
		"{$preference[1]}/{$preference[0]} settings identity is exact."
	);
}

$original_language = (string) get_option( 'WPLANG', '' );
update_option( 'WPLANG', 'ja' );
$meta_after = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$record_id,
		RecordSchema::META_KEY
	)
);
update_option( 'WPLANG', $original_language );
core004_assert( $meta_before === $meta_after, 'Preference and locale changes preserve exact envelope bytes.' );
core004_assert( $signature_before === hash( 'sha256', $meta_after ), 'Historical envelope signature is unchanged.' );

$record = RecordRepository::get( 'mf_velog_service', $record_id, clone $manager );
core004_assert( ! is_wp_error( $record ), 'RecordRepository::get reads the historical seed.' );
core004_assert( $historical_fields === $record['fields'], 'Historical repository snapshot preserves exact fields.' );
core004_assert( $manager_id === $record['audit'][0]['actor_id'], 'Historical audit identity remains intact.' );

$assets = new \MF\VeLog\Admin\Assets( VELOG_VERSION );
$GLOBALS['wp_styles'] = new \WP_Styles();
$assets->enqueue_styles( 'edit.php' );
core004_assert( ! wp_style_is( 'velog-admin', 'enqueued' ), 'Unrelated admin hook has no VeLog asset.' );
$assets->enqueue_styles( 'velog_page_velog-settings' );
core004_assert( wp_style_is( 'velog-admin', 'enqueued' ), 'Settings hook enqueues VeLog asset.' );
$asset_url = $GLOBALS['wp_styles']->registered['velog-admin']->src;
core004_assert(
	plugin_dir_url( VELOG_PLUGIN_FILE ) . 'assets/css/admin.css' === $asset_url &&
	is_file( VELOG_PLUGIN_DIR . 'assets/css/admin.css' ),
	'Enqueued URL resolves to the generated plugin-root CSS.'
);

if ( ! function_exists( 'add_menu_page' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$admin_menu = new \MF\VeLog\Admin\AdminMenu( $page );
$admin_menu->add_menu_pages();
global $menu, $submenu;
$root = array_values( array_filter( $menu, static fn ( $item ) => 'velog' === $item[2] ) );
$settings_menu = array_values( array_filter( $submenu['velog'] ?? array(), static fn ( $item ) => 'velog-settings' === $item[2] ) );
core004_assert( 1 === count( $root ) && 'mf_velog_read_records' === $root[0][1], 'Root menu uses the granted read capability.' );
core004_assert( 1 === count( $settings_menu ) && 'mf_velog_manage_settings' === $settings_menu[0][1], 'Settings menu uses manager capability.' );
core004_assert( is_callable( array( $admin_menu, 'render_dashboard' ) ), 'Root menu has a callable landing page.' );
foreach ( array( 'administrator', 'mf_velog_manager', 'mf_velog_technician' ) as $role_name ) {
	core004_assert( get_role( $role_name )->has_cap( 'mf_velog_read_records' ), "{$role_name} can access the root menu." );
}
core004_assert( ! get_role( 'subscriber' )->has_cap( 'mf_velog_read_records' ), 'Subscriber cannot access the root menu.' );
core004_assert( get_role( 'mf_velog_manager' )->has_cap( 'mf_velog_manage_settings' ), 'Manager can access settings.' );
core004_assert( ! get_role( 'mf_velog_technician' )->has_cap( 'mf_velog_manage_settings' ), 'Technician cannot access settings.' );

$settings_before_uninstall = core004_raw_settings();
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'velog/velog.php' );
}
require VELOG_PLUGIN_DIR . 'uninstall.php';
core004_assert( $settings_before_uninstall === core004_raw_settings(), 'Uninstall retains exact settings bytes.' );
$meta_after_uninstall = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$record_id,
		RecordSchema::META_KEY
	)
);
core004_assert( $meta_before === $meta_after_uninstall, 'Uninstall retains the exact historical envelope.' );

\MF\VeLog\Core\Activator::activate();
core004_assert( $settings_before_uninstall === core004_raw_settings(), 'Reactivation preserves exact settings bytes.' );
$meta_after_reactivation = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$record_id,
		RecordSchema::META_KEY
	)
);
core004_assert( $meta_before === $meta_after_reactivation, 'Reactivation preserves the exact historical envelope.' );

echo "All CORE-004 takeover verifications passed.\n";
exit( 0 );
