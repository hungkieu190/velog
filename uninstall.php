<?php
/**
 * VeLog Uninstall
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 *
 * @package MF\VeLog
 * @license GPL-2.0-or-later
 */

// Security: only allow execution via WordPress uninstall mechanism.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Cleanup logic:
// - Only remove data created by THIS plugin.
// - Never drop tables or delete options created by WordPress core or other plugins.
// current_user_can() is not needed here because WordPress core ensures
// the uninstall hook is triggered only by an admin.

// Delete plugin options.
$velog_options = array(
	'velog_version',
	'velog_settings',
	'velog_db_version',
);

foreach ( $velog_options as $option ) {
	delete_option( $option );
}

// Delete transients.
$velog_transients = array(
	'velog_admin_notices',
);

foreach ( $velog_transients as $transient ) {
	delete_transient( $transient );
}

// TODO: Drop custom database tables here once they are defined.
