<?php
/**
 * VeLog Test Bootstrap
 *
 * @package MF\VeLog\Tests
 * @author  Mamflow <https://mamflow.com>
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Brain Monkey setup.
use Brain\Monkey;

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'VELOG_VERSION' ) ) {
	define( 'VELOG_VERSION', '0.1.0' );
}
if ( ! defined( 'VELOG_MINIMUM_PHP_VERSION' ) ) {
	define( 'VELOG_MINIMUM_PHP_VERSION', '8.1' );
}
if ( ! defined( 'VELOG_MINIMUM_WP_VERSION' ) ) {
	define( 'VELOG_MINIMUM_WP_VERSION', '6.4' );
}
if ( ! defined( 'VELOG_PLUGIN_FILE' ) ) {
	define( 'VELOG_PLUGIN_FILE', dirname( __DIR__ ) . '/velog.php' );
}
if ( ! defined( 'VELOG_PLUGIN_DIR' ) ) {
	define( 'VELOG_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'VELOG_PLUGIN_URL' ) ) {
	define( 'VELOG_PLUGIN_URL', 'https://example.com/wp-content/plugins/velog/' );
}
if ( ! defined( 'VELOG_PLUGIN_BASENAME' ) ) {
	define( 'VELOG_PLUGIN_BASENAME', 'velog/velog.php' );
}
if ( ! defined( 'VELOG_TEXT_DOMAIN' ) ) {
	define( 'VELOG_TEXT_DOMAIN', 'velog' );
}

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * WP_User stub.
	 */
	class WP_User {
		/**
		 * ID.
		 *
		 * @var int
		 */
		public $ID;
		/**
		 * Checks capability.
		 *
		 * @param string $cap Capability.
		 * @return bool
		 */
		public function has_cap( $cap ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			return false;
		}
	}
}
