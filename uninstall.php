<?php
/**
 * VeLog Uninstall
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 *
 * DATA-001 retention contract: "Uninstall retains all four private CPTs,
 * metadata, request/lock options, settings including velog_settings, schema/
 * version markers, and operational taxonomy terms. Remove only proved
 * regenerable transients; no automatic purge or multisite migration."
 *
 * @package MF\VeLog
 * @license GPL-2.0-or-later
 */

// Security: only allow execution via WordPress uninstall mechanism.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Cleanup logic:
// - Only remove data created by THIS plugin that is REGENERABLE on next activation.
// - NEVER delete: velog_settings, velog_db_version, CPT posts/meta, lock options,
// idempotency markers (mf_velog_create_*), or any customer/vehicle/service/reminder data.
// - WordPress core and other plugins' data are never touched.

// Remove only the volatile version marker (regenerated on activation).
// velog_settings is RETAINED per DATA-001.
$velog_remove_options = array(
	'velog_version', // Regenerated on each activation; safe to remove.
);

foreach ( $velog_remove_options as $option ) {
	delete_option( $option );
}

// Delete proved-regenerable transients only.
// These are reconstructed on the next page load and contain no durable data.
$velog_transients = array(
	'velog_admin_notices',
);

foreach ( $velog_transients as $transient ) {
	delete_transient( $transient );
}

// Retained (never deleted here):
// - velog_settings       (business configuration — DATA-001 retention rule)
// - velog_db_version     (schema marker — retained for reinstall coherence)
// - mf_velog_write_lock  (InnoDB serialization row — retained per DATA-001)
// - mf_velog_create_*    (idempotency markers — retained per DATA-001)
// - wp_posts rows for mf_velog_{customer,vehicle,service,reminder}
// - wp_postmeta rows including _mf_velog_record envelopes.
