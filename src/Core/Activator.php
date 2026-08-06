<?php
/**
 * VeLog Plugin Activator
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
 * Handles plugin activation.
 *
 * Runs once when the plugin is activated via WordPress admin.
 * Responsible for:
 * - Creating database tables.
 * - Setting default options.
 * - Scheduling cron jobs.
 * - Flushing rewrite rules.
 *
 * @since 0.1.0
 */
class Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * @since 0.1.0
	 */
	public static function activate(): void {
		self::check_requirements();
		self::create_tables();
		self::set_default_options();
		self::schedule_events();

		// Store the installed version.
		update_option( 'velog_version', VELOG_VERSION, false );

		// Flush rewrite rules after registering custom post types.
		flush_rewrite_rules();
	}

	/**
	 * Verifies that server requirements are met before activating.
	 *
	 * @since 0.1.0
	 */
	private static function check_requirements(): void {
		if ( version_compare( PHP_VERSION, VELOG_MINIMUM_PHP_VERSION, '<' ) ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version. */
						__( 'VeLog requires PHP %1$s or higher. Your server is running PHP %2$s.', 'velog' ),
						VELOG_MINIMUM_PHP_VERSION,
						PHP_VERSION
					)
				),
				esc_html__( 'Plugin Activation Error', 'velog' ),
				array( 'back_link' => true )
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), VELOG_MINIMUM_WP_VERSION, '<' ) ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required WP version, 2: Current WP version. */
						__( 'VeLog requires WordPress %1$s or higher. Your site is running %2$s.', 'velog' ),
						VELOG_MINIMUM_WP_VERSION,
						get_bloginfo( 'version' )
					)
				),
				esc_html__( 'Plugin Activation Error', 'velog' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Creates required database tables.
	 *
	 * @since 0.1.0
	 */
	private static function create_tables(): void {
		// TODO: Define and create custom tables here using dbDelta().
	}

	/**
	 * Sets default plugin options.
	 *
	 * @since 0.1.0
	 */
	private static function set_default_options(): void {
		// TODO: add_option() calls for default settings.
	}

	/**
	 * Schedules recurring cron events.
	 *
	 * @since 0.1.0
	 */
	private static function schedule_events(): void {
		// TODO: wp_schedule_event() calls.
	}
}
