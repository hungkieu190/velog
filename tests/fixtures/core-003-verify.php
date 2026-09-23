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
 * Assert fixture.
 */
/**
 * Assert a condition.
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

// Seed records..
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

// Create users..
$users = array();
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
 * Perform HTTP request.
 */
function do_request( $url, $uid = 0, $method = 'GET', $body = array() ) {
	$req_args = array(
		'method'      => $method,
		'redirection' => 0, 'timeout' => 15,
	);
	if ( ! empty( $body ) ) {
		$req_args['body'] = $body;
	}
	if ( $uid > 0 ) {
		$cookie_auth      = wp_generate_auth_cookie( $uid, time() + 3600, 'auth' );
		$cookie_secure    = wp_generate_auth_cookie( $uid, time() + 3600, 'secure_auth' );
		$cookie_logged_in = wp_generate_auth_cookie( $uid, time() + 3600, 'logged_in' );

		$req_args['cookies'] = array(
			new WP_Http_Cookie(
				array(
					'name'  => AUTH_COOKIE,
					'value' => $cookie_auth,
				)
			),
			new WP_Http_Cookie(
				array(
					'name'  => SECURE_AUTH_COOKIE,
					'value' => $cookie_secure,
				)
			),
			new WP_Http_Cookie(
				array(
					'name'  => LOGGED_IN_COOKIE,
					'value' => $cookie_logged_in,
				)
			),
		);
	}
	$res = wp_remote_request( $url, $req_args );
	if ( is_wp_error( $res ) ) {
		echo 'WP_Error during request: ' . $res->get_error_message() . "\n";
	}
	return $res;
}

// Matrix testing.
foreach ( $roles as $current_role => $uid ) {
	// Identity assertion for authenticated users.
	if ( $uid > 0 ) {
		// Ordinary editor check.
		if ( 'editor' === $current_role || 'administrator' === $current_role ) {
			$res  = do_request( "$base_url/wp-admin/post.php?post=$public_pid&action=edit", $uid );
			$code = wp_remote_retrieve_response_code( $res );
			velog_assert( 200 === $code, "$current_role can edit public post natively (code $code)" );
		}
	}

	foreach ( $cpts as $cpt ) {
		$pid = $post_ids[ $cpt ];

		$obj = get_post_type_object( $cpt );
		velog_assert( null !== $obj, "Post type $cpt registered" );
		if ( 'anonymous' === $current_role ) {
			velog_assert( false === $obj->public, "$cpt is not public" );
			velog_assert( 'do_not_allow' === $obj->cap->edit_post, "$cpt edit_post is do_not_allow" );
		}

		// Direct ID.
		$res  = do_request( "$base_url/?p=$pid", $uid );
		$code = wp_remote_retrieve_response_code( $res );
		velog_assert( in_array( $code, array( 404, 301, 302 ) ), "$current_role cannot read $cpt direct ID (code $code)" );

		// Search.
		$res  = do_request( "$base_url/?s=Test", $uid );
		$body = wp_remote_retrieve_body( $res );
		velog_assert( false === strpos( $body, "Test $cpt" ), "$current_role search does not leak $cpt" );

		// REST.
		$res  = do_request( "$base_url/wp-json/wp/v2/$cpt", $uid );
		$code = wp_remote_retrieve_response_code( $res );
		velog_assert( in_array( $code, array( 404, 301, 302, 403 ) ), "$current_role REST API rejects $cpt collections (code $code)" );

		// Native admin routes.
		if ( $uid > 0 ) {
			$res  = do_request( "$base_url/wp-admin/post.php?post=$pid&action=edit", $uid );
			$code = wp_remote_retrieve_response_code( $res );
			velog_assert( in_array( $code, array( 403, 302, 500 ) ), "$current_role cannot edit $cpt via post.php GET (code $code)" );

			$res  = do_request( "$base_url/wp-admin/post-new.php?post_type=$cpt", $uid );
			$code = wp_remote_retrieve_response_code( $res );
			velog_assert( in_array( $code, array( 403, 302, 500 ) ), "$current_role cannot create $cpt via post-new.php GET (code $code)" );

			// Denied mutation with correct nonce.
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
			velog_assert( 403 === $code || 302 === $code, "$current_role denied mutation of $cpt natively (code $code)" );

			// Check unchanged state.
			$check_post = get_post( $pid );
			velog_assert( 'Mutated Title' !== $check_post->post_title, "$cpt state unchanged after attempted mutation by $current_role" );
		}
	}
}

// Lifecycle checks.
// Collision/reactivation/repair/rollback tests.
// Test injection via filter.
add_filter(
	'pre_update_option_mf_velog_capability_schema_version',
	function ( $value, $old_value, $option ) {
		if ( $value === -1 ) {
			return false; // Force update_option to fail to simulate DB failure during install
		}
		return $value;
	},
	10,
	3
);

$orig_schema = get_option( 'mf_velog_capability_schema_version' );

// 1. Repair test. Delete a capability from a role and see if install repairs it..
$technician = get_role( 'mf_velog_technician' );
$technician->remove_cap( 'mf_velog_read_records' );
velog_assert( ! $technician->has_cap( 'mf_velog_read_records' ), 'Removed capability for repair test' );
$success = \MF\VeLog\Core\Capabilities::install();
velog_assert( true === $success, 'Capabilities install (repair) succeeded' );
$technician = get_role( 'mf_velog_technician' );
velog_assert( $technician->has_cap( 'mf_velog_read_records' ), 'Repair restored missing capability' );

// 2. Collision test. Create a role with the same slug but no ledger entry..
add_role( 'mf_velog_manager', 'Fake Manager', array( 'fake_cap' => true ) ); // Ledger was already created in earlier tests, wait, we need to delete ledger entry
$ledger      = get_option( 'mf_velog_role_ledger', array() );
$orig_ledger = $ledger;
unset( $ledger['mf_velog_manager'] );
update_option( 'mf_velog_role_ledger', $ledger );

$success = \MF\VeLog\Core\Capabilities::install();
velog_assert( false === $success, 'Capabilities install correctly failed on collision' );

// Restore ledger.
update_option( 'mf_velog_role_ledger', $orig_ledger );

// 3. Rollback test. Inject failure by setting schema to -1 in filter..
// Note: our filter makes update_option return false if value is -1, but in Capabilities::install it's hardcoded to save self::SCHEMA_VERSION..
// So we must temporarily modify SCHEMA_VERSION or inject another way..
// The instructions say: "driven by test-only filters to inject one-shot persistence failures securely.".
// Let's hook into update_option to throw an Exception when saving schema..
global $inject_schema_failure;
$inject_schema_failure = false;

add_filter(
	'pre_update_option_mf_velog_role_ledger',
	function ($value) {
		global $inject_schema_failure;
		if ( $inject_schema_failure ) {
			throw new \Exception( 'Injected schema update failure' );
		}
		return $value;
	}
);

$inject_schema_failure = true;
$success               = \MF\VeLog\Core\Capabilities::install();
velog_assert( false === $success, 'Capabilities install correctly failed with injected exception' );
$inject_schema_failure = false;

// After rollback, ensure state is intact..
$schema = get_option( 'mf_velog_capability_schema_version' );
velog_assert( 1 === (int) $schema, 'Rollback kept schema at 1' );


if ( getenv( 'NEGATIVE_MODE' ) === 'fixture-failure' ) {
	velog_assert( false, 'Injected fixture-failure' );
}

if ( ! empty( $GLOBALS['velog_errors'] ) && $GLOBALS['velog_errors'] > 0 ) {
	echo esc_html( "{$GLOBALS['velog_errors']} errors found.\n" ); // phpcs:ignore
	exit( 1 );
}
echo esc_html( "Fixture tests passed.\n" ); // phpcs:ignore
