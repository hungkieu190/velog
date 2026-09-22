<?php
/**
 * Fixture: core-003-verify.php
 * Verify C3-V2 and C3-V3
 *
 * @package MF\VeLog\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$velog_errors = 0;

/**
 * Assert fixture.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 */
function assert_fixture( $condition, $message ) {
	global $velog_errors;
	if ( ! $condition ) {
		echo esc_html( "FAIL: $message\n" ); // phpcs:ignore WordPress.Security.EscapeOutput
		++$errors;
	} else {
		echo esc_html( "PASS: $message\n" ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

// 1. Verify exact assignments from activation.
$admin = get_role( 'administrator' );
assert_fixture( $admin->has_cap( 'mf_velog_manage_customers' ), 'Admin has mf_velog_manage_customers' );

$manager = get_role( 'mf_velog_manager' );
assert_fixture( null !== $manager, 'Manager role created' );
assert_fixture( $manager->has_cap( 'mf_velog_manage_customers' ), 'Manager has mf_velog_manage_customers' );

$tech = get_role( 'mf_velog_technician' );
assert_fixture( null !== $tech, 'Technician role created' );
assert_fixture( ! $tech->has_cap( 'mf_velog_manage_customers' ), 'Technician lacks mf_velog_manage_customers' );
assert_fixture( $tech->has_cap( 'mf_velog_create_services' ), 'Technician has mf_velog_create_services' );

// 2. Verify CPT registration
$cpts = array( 'mf_velog_customer', 'mf_velog_vehicle', 'mf_velog_service', 'mf_velog_reminder' );
foreach ( $cpts as $cpt ) {
	$obj = get_post_type_object( $cpt );
	assert_fixture( null !== $obj, "Post type $cpt registered" );
	assert_fixture( false === $obj->public, "$cpt is not public" );
	assert_fixture( 'do_not_allow' === $obj->cap->edit_post, "$cpt edit_post is do_not_allow" );
}

// 3. Idempotency (Deactivate/Reactivate)
MF\VeLog\Core\Capabilities::install();
$manager = get_role( 'mf_velog_manager' );
assert_fixture( null !== $manager, 'Manager role still exists after reinstall' );

if ( $velog_errors > 0 ) {
	echo esc_html( "{$velog_errors} errors found.\n" ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit( 1 );
}
echo esc_html( "Fixture tests passed.\n" ); // phpcs:ignore WordPress.Security.EscapeOutput
