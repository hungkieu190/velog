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

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WP_Error stub for unit tests.
	 */
	class WP_Error {
		/**
		 * Error codes and messages.
		 *
		 * @var array<string, array<int, string>>
		 */
		protected $errors = array();

		/**
		 * Error data.
		 *
		 * @var array<string, mixed>
		 */
		protected $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = (string) $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		/**
		 * Retrieve the first error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return empty( $codes ) ? '' : (string) $codes[0];
		}

		/**
		 * Retrieve the first error message.
		 *
		 * @param string $code Optional error code.
		 * @return string
		 */
		public function get_error_message( string $code = '' ): string {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			$messages = $this->errors[ $code ] ?? array();
			return empty( $messages ) ? '' : (string) $messages[0];
		}

		/**
		 * Retrieve error data.
		 *
		 * @param string $code Optional error code.
		 * @return mixed
		 */
		public function get_error_data( string $code = '' ): mixed {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Checks whether the given variable is a WordPress Error.
	 *
	 * @param mixed $thing Variable to check.
	 * @return bool
	 */
	function is_wp_error( mixed $thing ): bool {
		return ( $thing instanceof WP_Error );
	}
}
