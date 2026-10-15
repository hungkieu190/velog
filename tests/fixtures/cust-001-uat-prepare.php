<?php
/**
 * Read-only prerequisite inventory for CUST-001 manual UAT.
 *
 * Run with WP-CLI eval-file; this helper never provisions data or accounts.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$counts = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT p.ID) AS customer_count,
		 COUNT(DISTINCT CASE WHEN name_meta.meta_value LIKE %s THEN p.ID END) AS pagination_fixture_count
		 FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} name_meta ON name_meta.post_id = p.ID
		 AND name_meta.meta_key = '_mf_velog_customer_name'
		 WHERE p.post_type = %s AND p.post_status = %s",
		$wpdb->esc_like( 'uat customer ' ) . '%',
		'mf_velog_customer',
		'private'
	),
	ARRAY_A
);
if ( ! is_array( $counts ) || ! empty( $wpdb->last_error ) ) {
	WP_CLI::error( 'Customer prerequisite count could not be read.' );
}
$staff = array();
foreach ( array( 'mf_velog_manager', 'mf_velog_technician' ) as $role_slug ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$staff[ $role_slug ] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
			 WHERE meta_key = %s AND meta_value LIKE %s",
			$wpdb->prefix . 'capabilities',
			'%' . $wpdb->esc_like( '"' . $role_slug . '";b:1;' ) . '%'
		)
	);
	if ( ! empty( $wpdb->last_error ) ) {
		WP_CLI::error( 'Staff prerequisite count could not be read.' );
	}
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$active_links = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} owner_meta ON owner_meta.post_id = p.ID
		  AND owner_meta.meta_key = %s AND owner_meta.meta_value REGEXP '^[1-9][0-9]*$'
		 INNER JOIN {$wpdb->postmeta} state_meta ON state_meta.post_id = p.ID
		  AND state_meta.meta_key = %s AND state_meta.meta_value = %s
		 WHERE p.post_type = %s AND p.post_status = %s",
		'_mf_velog_current_customer_id',
		'_mf_velog_state',
		'active',
		'mf_velog_vehicle',
		'private'
	)
);
if ( ! empty( $wpdb->last_error ) ) {
	WP_CLI::error( 'Vehicle prerequisite count could not be read.' );
}
$report = array(
	'mode'                             => 'read_only_inventory',
	'site_url'                         => home_url(),
	'wordpress'                        => get_bloginfo( 'version' ),
	'php_cli'                          => PHP_VERSION,
	'velog_active'                     => defined( 'VELOG_VERSION' ),
	'customer_count'                   => (int) $counts['customer_count'],
	'pagination_fixture_count'         => (int) $counts['pagination_fixture_count'],
	'staff_account_counts'             => $staff,
	'active_vehicle_link_candidates'   => $active_links,
	'manager_fixture_present'          => $staff['mf_velog_manager'] > 0,
	'technician_fixture_present'       => $staff['mf_velog_technician'] > 0,
	'pagination_fixture_count_reached' => (int) $counts['pagination_fixture_count'] >= 51,
	'limitations'                      => 'Counts are preparation candidates, not authentication, schema validation or manual test results.',
);
WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
