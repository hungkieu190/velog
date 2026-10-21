<?php
/**
 * Explicit, CLI-only CUST-001 UAT provisioning; never loaded by plugin runtime.
 *
 * Usage: wp eval-file <file> plan|apply|verify <exact-home-url> <private-json-path> --user=admin.
 * Apply requires separate owner authorization. No cleanup or credential reset is provided.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Customer\CustomerQuery;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

// Fixture SQL is read-only inventory, except a connection-scoped advisory lock.
// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.PHP.DevelopmentFunctions
// Variable/array comparisons below deliberately compare expected values with actual values.
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable Squiz.Commenting.FunctionComment,Generic.Files.LineLength.TooLong

/**
 * Fail closed without including contact data, credentials or database errors.
 *
 * @throws RuntimeException When a preparation invariant is violated.
 */
function mf_velog_uat_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/** Assert repository success using only its safe error code. */
function mf_velog_uat_result( mixed $result ): array {
	mf_velog_uat_require( ! is_wp_error( $result ), 'Repository refused operation: ' . ( is_wp_error( $result ) ? $result->get_error_code() : '' ) );
	mf_velog_uat_require( is_array( $result ), 'Invalid repository result.' );
	return $result;
}

/** Capture non-owned user identities/password hashes and authoritative domain storage. */
function mf_velog_uat_baseline( array $excluded_users, array $excluded_records ): string {
	global $wpdb;
	$users = $wpdb->get_results( "SELECT ID, user_login, user_pass, user_email FROM {$wpdb->users} ORDER BY ID", ARRAY_A );
	mf_velog_uat_require( is_array( $users ) && '' === $wpdb->last_error, 'User inventory failed.' );
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_type, p.post_status, m.meta_id, m.meta_key, m.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type IN ('mf_velog_customer', 'mf_velog_vehicle') ORDER BY p.ID, m.meta_id", ARRAY_A );
	mf_velog_uat_require( is_array( $rows ) && '' === $wpdb->last_error, 'Domain inventory failed.' );
	$users = array_values( array_filter( $users, static fn( array $row ): bool => ! in_array( (int) $row['ID'], $excluded_users, true ) ) );
	foreach ( $users as &$user ) {
		$user['roles'] = get_user_meta( (int) $user['ID'], $wpdb->prefix . 'capabilities', true );
	}
	unset( $user );
	$rows = array_values( array_filter( $rows, static fn( array $row ): bool => ! in_array( (int) $row['ID'], $excluded_records, true ) ) );
	return hash( 'sha256', wp_json_encode( array( $users, $rows ) ) );
}

/** Read fixture candidate IDs; no field/contact values leave this helper. */
function mf_velog_uat_candidates(): array {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE (p.post_type='mf_velog_customer' AND m.meta_key='_mf_velog_customer_name' AND (m.meta_value LIKE %s OR m.meta_value=%s)) OR (p.post_type='mf_velog_vehicle' AND m.meta_key='_mf_velog_vehicle_vin' AND m.meta_value=%s)",
			$wpdb->esc_like( 'uat customer ' ) . '%',
			'uat linked customer',
			'VELOG-UAT-CUST001-20261015'
		)
	);
	mf_velog_uat_require( is_array( $ids ) && '' === $wpdb->last_error, 'Fixture collision inventory failed.' );
	return array_map( 'intval', $ids );
}

/** Perform a bounded provisioning pass. */
function mf_velog_uat_seed( array $arguments ): array {
	global $wpdb;
	mf_velog_uat_require( 3 === count( $arguments ), 'Expected mode, exact site URL and private credentials path.' );
	list( $mode, $target, $credential_path ) = $arguments;
	mf_velog_uat_require( in_array( $mode, array( 'plan', 'apply', 'verify' ), true ), 'Unsupported mode.' );
	$site = untrailingslashit( home_url() );
	mf_velog_uat_require( $target === $site && ! is_multisite(), 'Site mismatch or multisite is unsupported.' );
	$root      = realpath( ABSPATH );
	$live_root = '/home/ecommercelife/Local Sites/velog/app/public';
	$isolated  = 1 === preg_match( '#^/tmp/velog-product-smoke\.[A-Za-z0-9]+/wp$#', (string) $root ) && 1 === preg_match( '#^http://(?:localhost|127\.0\.0\.1):[0-9]+$#', $site );
	mf_velog_uat_require( ( $root === $live_root && 'https://velog.local' === $site ) || $isolated, 'Target is outside the approved LocalWP/disposable boundary.' );
	$actor = wp_get_current_user();
	foreach ( array( 'manage_options', 'create_users', 'mf_velog_manage_customers', 'mf_velog_read_customer_contacts', 'mf_velog_manage_vehicles' ) as $capability ) {
		mf_velog_uat_require( $actor->has_cap( $capability ), 'Administrative fixture actor required.' );
	}
	mf_velog_uat_require( defined( 'VELOG_VERSION' ) && true === WriteCoordinator::check_environment(), 'VeLog write environment is unavailable.' );
	// The VEH-001 snapshot is authorized only for disposable regression; live CUST-001 preparation stays pinned.
	$approved_asset = $isolated ? 'f773ea03bc1a3924cceb2afe0755783b2d4138460b944a304892a6a5fe09377d' : 'a651d52dd770433291508b939c5c1792c05953f2ebff253cb3a77bfcf267be89';
	mf_velog_uat_require( $approved_asset === hash_file( 'sha256', __DIR__ . '/../../assets/css/admin.css' ), 'Approved admin asset mismatch.' );
	$roles = array(
		'velog_uat_manager'    => 'mf_velog_manager',
		'velog_uat_technician' => 'mf_velog_technician',
	);
	foreach ( $roles as $role ) {
		mf_velog_uat_require( null !== get_role( $role ), 'Required staff role is unavailable.' );
	}
	$parent = realpath( dirname( $credential_path ) );
	mf_velog_uat_require( false !== $parent && $parent . '/' . basename( $credential_path ) === $credential_path && ! str_starts_with( $parent . '/', $root . '/' ) && ! is_link( $credential_path ), 'Credentials require a canonical path outside the public site.' );
	mf_velog_uat_require( 0700 === ( fileperms( $parent ) & 0777 ), 'Credentials directory must have mode 0700.' );
	$option   = 'mf_velog_uat_cust001_v1';
	$manifest = get_option( $option, null );
	$existing = null !== $manifest;
	if ( $existing ) {
		mf_velog_uat_require( is_array( $manifest ) && 1 === ( $manifest['schema'] ?? null ) && $site === ( $manifest['site'] ?? null ) && $credential_path === ( $manifest['credentials_path'] ?? null ) && isset( $manifest['batch'], $manifest['users'], $manifest['records'], $manifest['baseline'] ), 'Manifest conflict; manual reconciliation required.' );
		mf_velog_uat_require( is_file( $credential_path ) && 0600 === ( fileperms( $credential_path ) & 0777 ), 'Owned private credentials are missing or permissions differ.' );
		$credentials = json_decode( (string) file_get_contents( $credential_path ), true );
		mf_velog_uat_require( is_array( $credentials ) && $manifest['batch'] === ( $credentials['batch'] ?? null ) && $site === ( $credentials['site'] ?? null ), 'Private credential ownership mismatch.' );
	} else {
		mf_velog_uat_require( ! file_exists( $credential_path ), 'Unowned credentials file exists; refusing overwrite.' );
		$manifest    = array(
			'schema'           => 1,
			'site'             => $site,
			'credentials_path' => $credential_path,
			'batch'            => wp_generate_uuid4(),
			'actor'            => $actor->ID,
			'users'            => array(),
			'records'          => array(),
			'baseline'         => mf_velog_uat_baseline( array(), array() ),
		);
		$credentials = array(
			'site'     => $site,
			'batch'    => $manifest['batch'],
			'accounts' => array(),
		);
	}
	mf_velog_uat_require( array() === array_diff( mf_velog_uat_candidates(), array_values( $manifest['records'] ) ), 'Existing customer prefix or VIN is not manifest-owned; refusing adoption.' );
	foreach ( $roles as $login => $role ) {
		$user = get_user_by( 'login', $login );
		if ( $user ) {
			mf_velog_uat_require( isset( $manifest['users'][ $login ], $credentials['accounts'][ $login ] ) && $manifest['users'][ $login ] === $user->ID, 'Staff identity is not manifest-owned.' );
			mf_velog_uat_require( $manifest['batch'] === get_user_meta( $user->ID, '_mf_velog_uat_fixture', true ), 'Staff ownership marker differs.' );
			mf_velog_uat_require( array( $role ) === $user->roles && $login . '@example.com' === $user->user_email, 'Staff role/email drift; refusing reset.' );
			mf_velog_uat_require( wp_check_password( $credentials['accounts'][ $login ]['password'], $user->user_pass, $user->ID ), 'Saved staff password does not match; refusing reset.' );
		} else {
			mf_velog_uat_require( ! isset( $manifest['users'][ $login ] ) && ! email_exists( $login . '@example.com' ), 'Staff identity missing or email already in use.' );
		}
	}
	mf_velog_uat_require( $manifest['baseline'] === mf_velog_uat_baseline( array_values( $manifest['users'] ), array_values( $manifest['records'] ) ), 'Non-owned user/domain data changed; inspect before continuing.' );
	if ( 'plan' === $mode ) {
		return array(
			'mode'               => $mode,
			'site'               => $site,
			'existing_manifest'  => $existing,
			'new_staff_accounts' => 2 - count( $manifest['users'] ),
			'new_domain_records' => 53 - count( $manifest['records'] ),
			'read_only'          => true,
		);
	}
	mf_velog_uat_require( 'apply' === $mode || $existing, 'Apply must complete before verification.' );
	if ( 'apply' === $mode && ! $existing ) {
		foreach ( $roles as $login => $role ) {
			$credentials['accounts'][ $login ] = array(
				'role'     => $role,
				'password' => wp_generate_password( 32, true, false ),
			);
		}
		$previous_mask = umask( 0077 );
		$handle        = fopen( $credential_path, 'x' );
		umask( $previous_mask );
		mf_velog_uat_require( false !== $handle, 'Cannot exclusively create private credentials.' );
		$encoded = wp_json_encode( $credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$written = fwrite( $handle, $encoded );
		$flushed = fflush( $handle ) && fsync( $handle );
		fclose( $handle );
		mf_velog_uat_require( strlen( $encoded ) === $written && $flushed, 'Credentials could not be durably saved; no identities created.' );
		mf_velog_uat_require( add_option( $option, $manifest, '', false ), 'Cannot reserve fixture manifest; no identities created.' );
	}
	foreach ( $roles as $login => $role ) {
		if ( ! isset( $manifest['users'][ $login ] ) ) {
			mf_velog_uat_require( 'apply' === $mode, 'Staff provisioning is incomplete.' );
			$id = wp_insert_user(
				array(
					'user_login' => $login,
					'user_email' => $login . '@example.com',
					'user_pass'  => $credentials['accounts'][ $login ]['password'],
					'role'       => $role,
					'meta_input' => array( '_mf_velog_uat_fixture' => $manifest['batch'] ),
				)
			);
			mf_velog_uat_require( ! is_wp_error( $id ), 'Staff creation failed; preserve credentials and reconcile manifest.' );
			$manifest['users'][ $login ] = $id;
			mf_velog_uat_require( update_option( $option, $manifest, false ), 'Staff checkpoint failed; manual reconciliation required.' );
		}
	}
	$specifications = array();
	for ( $index = 1; $index <= 51; ++$index ) {
		$specifications[ 'customer_' . $index ] = array(
			'mf_velog_customer',
			array(
				'name'  => sprintf( 'UAT Customer %03d', $index ),
				'phone' => '',
				'email' => sprintf( 'uat%03d@example.com', $index ),
			),
		);
	}
	$specifications['linked_customer'] = array(
		'mf_velog_customer',
		array(
			'name'  => 'UAT Linked Customer',
			'phone' => '',
			'email' => '',
		),
	);
	$specifications['linked_vehicle']  = array( 'mf_velog_vehicle', array() );
	foreach ( $specifications as $label => $specification ) {
		list( $type, $fields ) = $specification;
		if ( 'linked_vehicle' === $label ) {
			$fields = array(
				'vin'                 => 'VELOG-UAT-CUST001-20261015',
				'current_customer_id' => $manifest['records']['linked_customer'],
			);
		}
		if ( ! isset( $manifest['records'][ $label ] ) ) {
			mf_velog_uat_require( 'apply' === $mode, 'Domain provisioning is incomplete.' );
			$hash                          = hash( 'sha256', $manifest['batch'] . ':' . $label );
			$request                       = substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-4' . substr( $hash, 13, 3 ) . '-a' . substr( $hash, 17, 3 ) . '-' . substr( $hash, 20, 12 );
			$created                       = mf_velog_uat_result( RecordRepository::create( $type, $fields, $actor, $request ) );
			$manifest['records'][ $label ] = (int) $created['id'];
			mf_velog_uat_require( update_option( $option, $manifest, false ), 'Domain checkpoint failed; manual reconciliation required.' );
		}
		$snapshot = mf_velog_uat_result( RecordRepository::get( $type, $manifest['records'][ $label ], $actor ) );
		mf_velog_uat_require( 1 === $snapshot['record_version'] && 'active' === $snapshot['state'], 'Fixture state/version drift; refusing reset.' );
		foreach ( $fields as $key => $value ) {
			mf_velog_uat_require( $value === ( $snapshot['fields'][ $key ] ?? null ), 'Fixture fields drifted; refusing reset.' );
		}
	}
	$manager    = new WP_User( $manifest['users']['velog_uat_manager'] );
	$technician = new WP_User( $manifest['users']['velog_uat_technician'] );
	$query      = new CustomerQuery();
	$page_one   = mf_velog_uat_result(
		$query->search(
			array(
				'term'  => 'UAT Customer ',
				'state' => 'active',
				'page'  => 1,
			),
			$manager
		)
	);
	$page_two   = mf_velog_uat_result(
		$query->search(
			array(
				'term'  => 'UAT Customer ',
				'state' => 'active',
				'page'  => 2,
			),
			$manager
		)
	);
	$ids        = array_column( array_merge( $page_one['items'], $page_two['items'] ), 'id' );
	$expected   = array_values( array_slice( $manifest['records'], 0, 51, true ) );
	sort( $ids );
	sort( $expected );
	mf_velog_uat_require( 51 === $page_one['total'] && 50 === count( $page_one['items'] ) && 1 === count( $page_two['items'] ) && $ids === $expected, 'Pagination fixture verification failed.' );
	mf_velog_uat_require( is_wp_error( $query->search( array(), $technician ) ) && ! $technician->has_cap( 'mf_velog_manage_customers' ) && ! $technician->has_cap( 'mf_velog_read_customer_contacts' ), 'Technician permission boundary differs.' );
	$link = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} c ON c.post_id=p.ID AND c.meta_key='_mf_velog_current_customer_id' INNER JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_mf_velog_state' WHERE p.ID=%d AND p.post_type='mf_velog_vehicle' AND p.post_status='private' AND c.meta_value=%s AND s.meta_value='active'", $manifest['records']['linked_vehicle'], (string) $manifest['records']['linked_customer'] ) );
	mf_velog_uat_require( '' === $wpdb->last_error && 1 === (int) $link, 'Active vehicle relation projection missing.' );
	mf_velog_uat_require( $manifest['baseline'] === mf_velog_uat_baseline( array_values( $manifest['users'] ), array_values( $manifest['records'] ) ), 'Non-owned user/domain data changed during provisioning.' );
	return array(
		'mode'                     => $mode,
		'site'                     => $site,
		'wordpress'                => get_bloginfo( 'version' ),
		'php_cli'                  => PHP_VERSION,
		'velog'                    => VELOG_VERSION,
		'users'                    => $manifest['users'],
		'customer_ids'             => array_slice( $manifest['records'], 0, 51, true ),
		'linked_customer'          => $manifest['records']['linked_customer'],
		'linked_vehicle'           => $manifest['records']['linked_vehicle'],
		'pagination'               => array( 50, 1 ),
		'non_owned_data_unchanged' => true,
		'credentials_path'         => $credential_path,
		'preparation_verified'     => true,
		'manual_acceptance'        => 'NOT_EXECUTED',
	);
}

$mf_velog_uat_lock       = 'velog-uat-' . substr( hash( 'sha256', DB_NAME . ':' . $GLOBALS['wpdb']->prefix ), 0, 40 );
$mf_velog_uat_mail       = 0;
$mf_velog_uat_block_mail = static function () use ( &$mf_velog_uat_mail ): bool {
	++$mf_velog_uat_mail;
	return false;
};
add_filter( 'pre_wp_mail', $mf_velog_uat_block_mail, PHP_INT_MAX );
$mf_velog_uat_error = null;
try {
	mf_velog_uat_require( '1' === (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT GET_LOCK(%s, 0)', $mf_velog_uat_lock ) ), 'Another fixture process holds the preparation lock.' );
	$mf_velog_uat_report                          = mf_velog_uat_seed( $args ?? array() );
	$mf_velog_uat_report['mail_attempts_blocked'] = $mf_velog_uat_mail;
} catch ( Throwable $mf_velog_uat_exception ) {
	$mf_velog_uat_error = $mf_velog_uat_exception instanceof RuntimeException ? $mf_velog_uat_exception->getMessage() : 'Unexpected preparation failure; inspect privately without exposing credentials.';
} finally {
	$GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT RELEASE_LOCK(%s)', $mf_velog_uat_lock ) );
	remove_filter( 'pre_wp_mail', $mf_velog_uat_block_mail, PHP_INT_MAX );
}
if ( null !== $mf_velog_uat_error ) {
	WP_CLI::error( $mf_velog_uat_error );
}
WP_CLI::line( wp_json_encode( $mf_velog_uat_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
