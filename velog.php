<?php
/**
 * VeLog — Digital Vehicle Passport
 *
 * @package           MF\VeLog
 * @author            Mamflow
 * @copyright         2024 Mamflow
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       VeLog — Digital Vehicle Passport
 * Plugin URI:        https://mamflow.com/velog
 * Description:       Building the digital identity of every vehicle. The digital passport for every vehicle.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Mamflow
 * Author URI:        https://mamflow.com
 * Text Domain:       velog
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://mamflow.com/velog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------

define( 'VELOG_VERSION', '0.1.0' );
define( 'VELOG_MINIMUM_PHP_VERSION', '8.1' );
define( 'VELOG_MINIMUM_WP_VERSION', '6.4' );
define( 'VELOG_PLUGIN_FILE', __FILE__ );
define( 'VELOG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VELOG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VELOG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'VELOG_TEXT_DOMAIN', 'velog' );

// -------------------------------------------------------------------------
// Environment check
// -------------------------------------------------------------------------

/**
 * Displays an admin notice when the PHP version requirement is not met.
 */
function mf_velog_php_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version. */
				__( '%1$s requires PHP %2$s or higher. Your server is running PHP %3$s.', 'velog' ),
				'VeLog',
				VELOG_MINIMUM_PHP_VERSION,
				PHP_VERSION
			)
		)
	);
}

/**
 * Displays an admin notice when the WordPress version requirement is not met.
 */
function mf_velog_wp_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: Plugin name, 2: Required WP version, 3: Current WP version. */
				__( '%1$s requires WordPress %2$s or higher. Your site is running WordPress %3$s.', 'velog' ),
				'VeLog',
				VELOG_MINIMUM_WP_VERSION,
				get_bloginfo( 'version' )
			)
		)
	);
}

if ( version_compare( PHP_VERSION, VELOG_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'mf_velog_php_version_notice' );
	return;
}

if ( version_compare( get_bloginfo( 'version' ), VELOG_MINIMUM_WP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'mf_velog_wp_version_notice' );
	return;
}

// -------------------------------------------------------------------------
// Autoloader
// -------------------------------------------------------------------------

if ( file_exists( VELOG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once VELOG_PLUGIN_DIR . 'vendor/autoload.php';
}

// -------------------------------------------------------------------------
// Activation / Deactivation / Uninstall
// -------------------------------------------------------------------------

register_activation_hook( VELOG_PLUGIN_FILE, array( 'MF\\VeLog\\Core\\Activator', 'activate' ) );
register_deactivation_hook( VELOG_PLUGIN_FILE, array( 'MF\\VeLog\\Core\\Deactivator', 'deactivate' ) );

// -------------------------------------------------------------------------
// Bootstrap
// -------------------------------------------------------------------------

/**
 * Returns the main plugin instance.
 *
 * @since  0.1.0
 * @return MF\VeLog\Core\Plugin
 */
function mf_velog(): \MF\VeLog\Core\Plugin {
	return \MF\VeLog\Core\Plugin::get_instance();
}

/**
 * Bootstraps the plugin by running the Loader on plugins_loaded.
 *
 * Instantiates the singleton (which queues all callbacks) and then calls
 * Plugin::run() so that the Loader registers every queued hook with
 * WordPress exactly once.
 *
 * @since 0.1.0
 * @hook  plugins_loaded
 */
function mf_velog_bootstrap(): void {
	mf_velog()->run();
}

add_action( 'plugins_loaded', 'mf_velog_bootstrap' );
