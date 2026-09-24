<?php
// phpcs:ignoreFile

/**
 * Fixture: core-003-verify.php
 *
 * @package MF\VeLog\Tests\Fixtures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['velog_errors'] = 0;

/**
 * Assert a condition and log result.
 *
 * @param bool   $condition Condition to check.
 * @param string $message   Message to display.
 */
function velog_assert( $condition, $message ) {
	if ( ! $condition ) {
		echo esc_html( "FAIL: $message\n" ); // phpcs:ignore
		++$GLOBALS['velog_errors'];
	} else {
		echo esc_html( "PASS: $message\n" ); // phpcs:ignore
	}
}

$port     = isset( $args[0] ) ? $args[0] : 80;
$base_url = "http://localhost:$port";

// Seed private records.
$cpts     = array( 'mf_velog_customer', 'mf_velog_vehicle', 'mf_velog_service', 'mf_velog_reminder' );
$post_ids = array();
foreach ( $cpts as $cpt ) {
	$pid              = wp_insert_post(
		array(
			'post_title'  => "Test $cpt",
			'post_type'   => $cpt,
			'post_status' => 'private',
		)
	);
	$post_ids[ $cpt ] = $pid;
}
$public_pid = wp_insert_post(
	array(
		'post_title'  => 'Public Post',
		'post_type'   => 'post',
		'post_status' => 'publish',
	)
);

// Create users.
$roles = array(
	'anonymous'           => 0,
	'subscriber'          => 0,
	'editor'              => 0,
	'administrator'       => 0,
	'mf_velog_manager'    => 0,
	'mf_velog_technician' => 0,
);
foreach ( $roles as $current_role => &$uid_ref ) {
	if ( 'anonymous' === $current_role ) {
		continue;
	}
	$uid_ref = wp_insert_user(
		array(
			'user_login' => "user_$current_role",
			'user_pass'  => 'password',
			'role'       => $current_role,
		)
	);
}
unset( $uid_ref );

/**
 * Perform HTTP request with correct auth cookies.
 */
function do_request( $url, $uid = 0, $method = 'GET', $body = array() ) {
	$req_args = array(
		'method'      => $method,
		'redirection' => 0,
		'timeout'     => 15,
	);
	if ( ! empty( $body ) ) {
		$req_args['body'] = $body;
	}
	if ( $uid > 0 ) {
		$cookie_auth      = wp_generate_auth_cookie( $uid, time() + 3600, 'auth' );
		$cookie_secure    = wp_generate_auth_cookie( $uid, time() + 3600, 'secure_auth' );
		$cookie_logged_in = wp_generate_auth_cookie( $uid, time() + 3600, 'logged_in' );
		$req_args['cookies'] = array(
			new WP_Http_Cookie( array( 'name' => AUTH_COOKIE, 'value' => $cookie_auth ) ),
			new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => $cookie_secure ) ),
			new WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => $cookie_logged_in ) ),
		);
	}
	$res = wp_remote_request( $url, $req_args );
	if ( is_wp_error( $res ) ) {
		echo 'WP_Error: ' . esc_html( $res->get_error_message() ) . "\n";
	}
	return $res;
}

/**
 * Validate that a redirect destination is a login/auth page, not the resource itself.
 * Returns true if the redirect is to a safe deny destination (wp-login.php or admin-post.php).
 *
 * @param array|WP_Error $res HTTP response.
 * @param string         $label Context label for assertion.
 * @return bool Whether the redirect is a valid denial redirect.
 */
function velog_assert_deny_redirect( $res, $label ) {
	$code     = wp_remote_retrieve_response_code( $res );
	$location = wp_remote_retrieve_header( $res, 'location' );
	// 302 is acceptable ONLY if redirecting to wp-login.php (not the resource).
	if ( 302 === $code || 301 === $code ) {
		$to_login = ( false !== strpos( $location, 'wp-login.php' ) || false !== strpos( $location, 'admin-post' ) );
		velog_assert( $to_login, "$label redirect to login (location: $location, code: $code)" );
		return $to_login;
	}
	velog_assert( 403 === $code, "$label denied with 403 (code: $code)" );
	return 403 === $code;
}

// ======================================================================
// SECTION 1 — CPT flag checks (no HTTP, all actors).
// ======================================================================
echo "\n=== CPT flag checks ===\n";
foreach ( $cpts as $cpt ) {
	$obj = get_post_type_object( $cpt );
	velog_assert( null !== $obj, "Post type $cpt registered" );
	velog_assert( false === $obj->public, "$cpt public flag is false" );
	velog_assert( false === (bool) $obj->publicly_queryable, "$cpt publicly_queryable is false" );
	velog_assert( false === (bool) $obj->show_in_rest, "$cpt show_in_rest is false" );
	velog_assert( 'do_not_allow' === $obj->cap->edit_post, "$cpt edit_post is do_not_allow" );
}

// ======================================================================
// SECTION 2 — Authenticated positive controls.
// Establish that each authenticated actor's session cookie works.
// ======================================================================
echo "\n=== Authenticated positive controls ===\n";
// Editor and admin can edit a public post — proves authentication works.
foreach ( array( 'editor', 'administrator' ) as $auth_role ) {
	$res  = do_request( "$base_url/wp-admin/post.php?post=$public_pid&action=edit", $roles[ $auth_role ] );
	$code = wp_remote_retrieve_response_code( $res );
	velog_assert( 200 === $code, "$auth_role authenticated positive: can edit public post (code $code)" );
}
// Manager and technician: verify they can access wp-admin (200 or 302-to-dashboard, not 500).
foreach ( array( 'mf_velog_manager', 'mf_velog_technician' ) as $auth_role ) {
	$res  = do_request( "$base_url/wp-admin/index.php", $roles[ $auth_role ] );
	$code = wp_remote_retrieve_response_code( $res );
	velog_assert( in_array( $code, array( 200, 302 ), true ), "$auth_role authenticated positive: wp-admin accessible (code $code)" );
	if ( 302 === $code ) {
		$location = wp_remote_retrieve_header( $res, 'location' );
		// Must redirect within wp-admin, not to login.
		velog_assert( false === strpos( $location, 'wp-login.php' ), "$auth_role authenticated redirect goes within admin (location: $location)" );
	}
}

// ======================================================================
// SECTION 3 — Discovery denial matrix (six actors x four CPTs).
// ======================================================================
echo "\n=== Discovery denial matrix ===\n";
foreach ( $roles as $current_role => $uid ) {
	foreach ( $cpts as $cpt ) {
		$pid = $post_ids[ $cpt ];

		// Direct ID — must 404 (private post).
		$res  = do_request( "$base_url/?p=$pid", $uid );
		$code = wp_remote_retrieve_response_code( $res );
		velog_assert( 404 === $code, "$current_role direct ID $cpt returns 404 (code $code)" );

		// Search — title must not appear.
		$res  = do_request( "$base_url/?s=Test", $uid );
		$body = wp_remote_retrieve_body( $res );
		velog_assert( false === strpos( $body, "Test $cpt" ), "$current_role search does not leak $cpt" );

		// Feed — title must not appear.
		$res  = do_request( "$base_url/?feed=rss2&post_type=$cpt", $uid );
		$body = wp_remote_retrieve_body( $res );
		velog_assert( false === strpos( $body, "Test $cpt" ), "$current_role feed does not leak $cpt" );

		// Sitemap — CPT slug must not appear.
		$res  = do_request( "$base_url/?sitemap=1", $uid );
		$body = wp_remote_retrieve_body( $res );
		velog_assert( false === strpos( $body, $cpt ), "$current_role sitemap does not expose $cpt" );

		// REST — 404 (not registered in REST) or 403. Reject 301/302/5xx.
		$res  = do_request( rest_url( "wp/v2/$cpt" ), $uid );
		$code = wp_remote_retrieve_response_code( $res );
		velog_assert( in_array( $code, array( 404, 403 ), true ), "$current_role REST $cpt rejects with 404 or 403 (code $code)" );
	}
}

// ======================================================================
// SECTION 4 — Native admin denial matrix (authenticated actors only).
// ======================================================================
echo "\n=== Native admin denial matrix ===\n";
// Denied roles: subscriber, editor (no VeLog caps).
$denied_auth_roles = array( 'subscriber', 'editor' );

foreach ( $denied_auth_roles as $current_role ) {
	$uid = $roles[ $current_role ];
	foreach ( $cpts as $cpt ) {
		$pid = $post_ids[ $cpt ];

		// GET post.php — must 403 or redirect to wp-login.php, never 500.
		$res  = do_request( "$base_url/wp-admin/post.php?post=$pid&action=edit", $uid );
		$code = wp_remote_retrieve_response_code( $res );
		// 302 only if to wp-login.php.
		if ( 302 === $code ) {
			$location = wp_remote_retrieve_header( $res, 'location' );
			velog_assert( false !== strpos( $location, 'wp-login.php' ) || false !== strpos( $location, 'wp-admin' ), "$current_role edit $cpt GET: redirect to deny destination (location: $location)" );
		} else {
			velog_assert( 403 === $code, "$current_role edit $cpt GET denied with 403 (code $code)" );
		}

		// GET post-new.php — must 403 or redirect to wp-login.php, never 500.
		$res  = do_request( "$base_url/wp-admin/post-new.php?post_type=$cpt", $uid );
		$code = wp_remote_retrieve_response_code( $res );
		if ( 302 === $code ) {
			$location = wp_remote_retrieve_header( $res, 'location' );
			velog_assert( false !== strpos( $location, 'wp-login.php' ) || false !== strpos( $location, 'wp-admin' ), "$current_role new $cpt GET: redirect to deny destination (location: $location)" );
		} else {
			velog_assert( 403 === $code, "$current_role new $cpt GET denied with 403 (code $code)" );
		}

		// POST mutation with valid nonce — must deny and leave state unchanged.
		wp_set_current_user( $uid );
		$nonce    = wp_create_nonce( 'update-post_' . $pid );
		$req_body = array(
			'post_ID'    => $pid,
			'action'     => 'editpost',
			'_wpnonce'   => $nonce,
			'post_title' => 'Mutated Title',
		);
		$res      = do_request( "$base_url/wp-admin/post.php", $uid, 'POST', $req_body );
		$code     = wp_remote_retrieve_response_code( $res );
		if ( 302 === $code ) {
			$location = wp_remote_retrieve_header( $res, 'location' );
			velog_assert( false !== strpos( $location, 'wp-login.php' ), "$current_role POST mutation $cpt: redirect to login (location: $location)" );
		} else {
			velog_assert( 403 === $code, "$current_role POST mutation $cpt denied with 403 (code $code)" );
		}

		// State must be unchanged regardless of denial type.
		wp_set_current_user( 0 );
		$check_post = get_post( $pid );
		velog_assert( 'Mutated Title' !== $check_post->post_title, "$cpt state unchanged after mutation attempt by $current_role" );
	}
}

// Reset user.
wp_set_current_user( 0 );

// ======================================================================
// SECTION 5 — Capability grant matrix (current_user_can).
// ======================================================================
echo "\n=== Capability grant matrix ===\n";
$admin_caps = array(
	'mf_velog_read_records',
	'mf_velog_manage_settings',
	'mf_velog_manage_customers',
	'mf_velog_read_customer_contacts',
	'mf_velog_manage_vehicles',
	'mf_velog_create_services',
	'mf_velog_edit_own_service_drafts',
	'mf_velog_finalize_own_services',
	'mf_velog_correct_services',
	'mf_velog_manage_reminders',
);

wp_set_current_user( $roles['administrator'] );
foreach ( $admin_caps as $cap ) {
	velog_assert( current_user_can( $cap ), "administrator has $cap" );
}

wp_set_current_user( $roles['mf_velog_manager'] );
foreach ( $admin_caps as $cap ) {
	velog_assert( current_user_can( $cap ), "mf_velog_manager has $cap" );
}

wp_set_current_user( $roles['mf_velog_technician'] );
$tech_caps      = array( 'mf_velog_read_records', 'mf_velog_create_services', 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' );
$tech_deny_caps = array_values( array_diff( $admin_caps, $tech_caps ) );
foreach ( $tech_caps as $cap ) {
	velog_assert( current_user_can( $cap ), "mf_velog_technician has $cap" );
}
foreach ( $tech_deny_caps as $cap ) {
	velog_assert( ! current_user_can( $cap ), "mf_velog_technician does NOT have $cap" );
}

foreach ( array( 'subscriber', 'editor' ) as $denied_role ) {
	wp_set_current_user( $roles[ $denied_role ] );
	foreach ( $admin_caps as $cap ) {
		velog_assert( ! current_user_can( $cap ), "$denied_role does NOT have $cap" );
	}
}

wp_set_current_user( 0 );

// ======================================================================
// SECTION 6 — Lifecycle: repair, collision, rollback with before/after snapshots.
// ======================================================================
echo "\n=== Lifecycle tests ===\n";

/**
 * Snapshot current role/option state for before/after comparison.
 *
 * @return array{schema: mixed, ledger: mixed, technician_caps: array, manager_caps: array}
 */
function velog_snap_state() {
	global $wpdb;
	$schema_row    = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'mf_velog_capability_schema_version' ) );
	$ledger_row    = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'mf_velog_role_ledger' ) );
	$tech_obj      = get_role( 'mf_velog_technician' );
	$manager_obj   = get_role( 'mf_velog_manager' );
	return array(
		'schema_exists'    => null !== $schema_row,
		'schema_value'     => $schema_row ? $schema_row->option_value : null,
		'schema_autoload'  => $schema_row ? $schema_row->autoload : null,
		'ledger_exists'    => null !== $ledger_row,
		'ledger_value'     => $ledger_row ? $ledger_row->option_value : null,
		'ledger_autoload'  => $ledger_row ? $ledger_row->autoload : null,
		'tech_caps'        => $tech_obj ? $tech_obj->capabilities : array(),
		'manager_caps'     => $manager_obj ? $manager_obj->capabilities : array(),
	);
}

// 1. Repair test — remove a cap and verify install restores it.
$before_repair = velog_snap_state();
$technician    = get_role( 'mf_velog_technician' );
$technician->remove_cap( 'mf_velog_read_records' );
velog_assert( ! $technician->has_cap( 'mf_velog_read_records' ), 'Removed mf_velog_read_records for repair test' );

$success = \MF\VeLog\Core\Capabilities::install();
velog_assert( true === $success, 'Repair install returned true' );

$after_repair = velog_snap_state();
$technician   = get_role( 'mf_velog_technician' );
velog_assert( $technician->has_cap( 'mf_velog_read_records' ), 'Repair restored mf_velog_read_records' );
// Schema and ledger values must not change during repair.
velog_assert( $before_repair['schema_value'] === $after_repair['schema_value'], 'Repair: schema value unchanged' );
velog_assert( $before_repair['ledger_exists'] === $after_repair['ledger_exists'], 'Repair: ledger existence unchanged' );

// 2. Collision test — ledger entry removed, foreign role detected.
$before_collision = velog_snap_state();
$ledger           = get_option( 'mf_velog_role_ledger', array() );
$orig_ledger      = $ledger;
unset( $ledger['mf_velog_manager'] );
update_option( 'mf_velog_role_ledger', $ledger );

$success = \MF\VeLog\Core\Capabilities::install();
velog_assert( false === $success, 'Collision: install returned false' );

// State must be unchanged after collision detection (no mutation occurred).
$after_collision = velog_snap_state();
velog_assert( $before_collision['tech_caps'] === $after_collision['tech_caps'], 'Collision: technician caps unchanged' );

// Restore ledger.
update_option( 'mf_velog_role_ledger', $orig_ledger );

// 3. Rollback test — inject failure after real mutation, check exact state restoration.
global $velog_inject_ledger_failure;
$velog_inject_ledger_failure = false;

add_filter(
	'pre_update_option_mf_velog_role_ledger',
	function ( $value ) {
		global $velog_inject_ledger_failure;
		if ( $velog_inject_ledger_failure ) {
			throw new \Exception( 'VELOG_INJECTED: ledger-update-failure' );
		}
		return $value;
	}
);

// Remove a technician cap so install() actually mutates roles before hitting ledger.
$technician = get_role( 'mf_velog_technician' );
$technician->remove_cap( 'mf_velog_read_records' );

// Snapshot state immediately before install() begins its mutation.
$before_rollback = velog_snap_state();

$velog_inject_ledger_failure = true;
$success                     = \MF\VeLog\Core\Capabilities::install();
$velog_inject_ledger_failure = false;

velog_assert( false === $success, 'Rollback: install returned false after injected failure' );

// Verify exact state restoration after rollback.
$after_rollback = velog_snap_state();

velog_assert( $before_rollback['schema_exists'] === $after_rollback['schema_exists'], 'Rollback: schema existence matches pre-mutation snapshot' );
velog_assert( $before_rollback['schema_value'] === $after_rollback['schema_value'], 'Rollback: schema value matches pre-mutation snapshot' );
velog_assert( $before_rollback['schema_autoload'] === $after_rollback['schema_autoload'], 'Rollback: schema autoload matches pre-mutation snapshot' );
velog_assert( $before_rollback['ledger_exists'] === $after_rollback['ledger_exists'], 'Rollback: ledger existence matches pre-mutation snapshot' );
velog_assert( $before_rollback['ledger_value'] === $after_rollback['ledger_value'], 'Rollback: ledger value matches pre-mutation snapshot' );
velog_assert( $before_rollback['ledger_autoload'] === $after_rollback['ledger_autoload'], 'Rollback: ledger autoload matches pre-mutation snapshot' );

// In-memory role state must also be restored (wp_roles()->for_site() was called).
$technician_after = get_role( 'mf_velog_technician' );
velog_assert( $technician_after->has_cap( 'mf_velog_read_records' ) === isset( $before_rollback['tech_caps']['mf_velog_read_records'] ), 'Rollback: in-memory technician role state restored' );

// 4. Reactivation test — after rollback, re-run install must succeed.
// Re-add the cap that rollback restored so next install is idempotent.
$success = \MF\VeLog\Core\Capabilities::install();
velog_assert( true === $success, 'Reactivation after rollback succeeded' );
$technician_reactivated = get_role( 'mf_velog_technician' );
velog_assert( $technician_reactivated->has_cap( 'mf_velog_read_records' ), 'Reactivation restored mf_velog_read_records' );

// ======================================================================
// SECTION 7 — Negative mode gate.
// ======================================================================
if ( getenv( 'NEGATIVE_MODE' ) === 'fixture-failure' ) {
	velog_assert( false, 'VELOG_CAUSE: fixture-failure injected' );
}

if ( ! empty( $GLOBALS['velog_errors'] ) && $GLOBALS['velog_errors'] > 0 ) {
	echo esc_html( "{$GLOBALS['velog_errors']} errors found.\n" ); // phpcs:ignore
	exit( 1 );
}
echo esc_html( "Fixture tests passed.\n" ); // phpcs:ignore
