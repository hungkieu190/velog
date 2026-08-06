<?php
/**
 * VeLog Plugin Deactivator
 *
 * @package MF\VeLog\Core
 * @author  Mamflow <https://mamflow.com>
 * @license GPL-2.0-or-later
 */

namespace MF\VeLog\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 *
 * Runs when the plugin is deactivated via WordPress admin.
 * Responsible for:
 * - Clearing scheduled cron events.
 * - Flushing rewrite rules.
 *
 * Note: Does NOT delete data — that is handled by uninstall.php.
 *
 * @since 0.1.0
 */
class Deactivator {

	/**
	 * Runs on plugin deactivation.
	 *
	 * @since 0.1.0
	 */
	public static function deactivate(): void {
		self::clear_scheduled_events();

		// Flush rewrite rules after deregistering custom post types.
		flush_rewrite_rules();
	}

	/**
	 * Removes all scheduled cron events registered by this plugin.
	 *
	 * @since 0.1.0
	 */
	private static function clear_scheduled_events(): void {
		// TODO: wp_clear_scheduled_hook() calls for any scheduled events.
	}
}
