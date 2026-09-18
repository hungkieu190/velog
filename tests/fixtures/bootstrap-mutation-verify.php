<?php
/**
 * Baseline-mutation verification script.
 *
 * Reproduces the ORIGINAL BUG: uses an entry point that does NOT call run(),
 * mirroring HEAD f04d4fc's behaviour, then asserts that the mutation FAILS
 * to register load_plugin_textdomain after init.
 *
 * This script must exit 0 ONLY when the baseline bug is confirmed present
 * (i.e., load_plugin_textdomain was NOT called after init). If the mutation
 * somehow passes (textdomain IS registered), it exits 1 to alert that the
 * mutation test is no longer valid.
 *
 * Intended to be run as a subprocess from BootstrapEntryTest::
 * test_baseline_mutation_without_run_fails_to_register_callbacks().
 *
 * Exit 0 = baseline bug confirmed (mutation correctly fails — as expected).
 * Exit 1 = mutation unexpectedly passed (mutation test is invalid).
 * Exit 2 = unexpected PHP error.
 *
 * @package MF\VeLog\Tests
 * @author  Mamflow <https://mamflow.com>
 * @since   0.1.0
 */

declare( strict_types = 1 );

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, WordPress.Security.EscapeOutput.OutputNotEscaped, Generic.Files.LineLength.TooLong, Generic.CodeAnalysis.UnusedFunctionParameter.Found

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/velog-bootstrap-test/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/velog-test-plugins' );
}

/**
 * Captured textdomain calls.
 *
 * @var array<int, array<string, mixed>> $captured_textdomain_calls
 */
$captured_textdomain_calls = array();

/**
 * Registered hooks.
 *
 * @var array<string, array<int, callable>> $wp_hooks
 */
$wp_hooks = array();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub: records the hook/callback pair.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args count.
	 * @return bool
	 */
	function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): bool {
		global $wp_hooks;
		$wp_hooks[ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Stub: fires all registered callbacks for the hook.
	 *
	 * @param string $hook Hook name.
	 */
	function do_action( string $hook ): void {
		global $wp_hooks;
		foreach ( $wp_hooks[ $hook ] ?? array() as $callback ) {
			$callback();
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub: delegates to add_action for recording purposes.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args count.
	 * @return bool
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return add_action( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Stub: no-op.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Callback.
	 */
	function register_activation_hook( string $file, callable $callback ): void {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Stub: no-op.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Callback.
	 */
	function register_deactivation_hook( string $file, callable $callback ): void {}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Stub: returns trailing-slash directory of the given file.
	 *
	 * @param string $file Absolute path to a file.
	 * @return string
	 */
	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Stub: returns a placeholder URL.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/velog/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Stub: returns the plugin-relative path.
	 *
	 * @param string $file Absolute path to a plugin file.
	 * @return string
	 */
	function plugin_basename( string $file ): string {
		return 'velog/' . basename( $file );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Stub: returns a sufficient WP version.
	 *
	 * @param string $show Field name.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		if ( 'version' === $show ) {
			return '6.7';
		}
		return '';
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	/**
	 * Stub: captures call for assertion.
	 *
	 * @param string      $domain     Text domain.
	 * @param bool        $deprecated Deprecated argument.
	 * @param string|bool $path       Path relative to WP_PLUGIN_DIR.
	 * @return bool
	 */
	function load_plugin_textdomain( string $domain, bool $deprecated, string|bool $path = false ): bool {
		global $captured_textdomain_calls;
		$captured_textdomain_calls[] = array(
			'domain' => $domain,
			'path'   => $path,
		);
		return true;
	}
}

// -------------------------------------------------------------------------
// Load Composer autoloader so Plugin class is available.
// -------------------------------------------------------------------------

$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "ERROR: vendor/autoload.php not found.\n" );
	exit( 2 );
}
require $autoload;

// -------------------------------------------------------------------------
// Define plugin constants (mirroring velog.php) WITHOUT loading velog.php.
// This skips the add_action('plugins_loaded','mf_velog_bootstrap') registration,
// reproducing the baseline f04d4fc state where run() was never called.
// -------------------------------------------------------------------------

$plugin_file = dirname( __DIR__, 2 ) . '/velog.php';
define( 'VELOG_VERSION', '0.1.0' );
define( 'VELOG_MINIMUM_PHP_VERSION', '8.1' );
define( 'VELOG_MINIMUM_WP_VERSION', '6.4' );
define( 'VELOG_PLUGIN_FILE', $plugin_file );
define( 'VELOG_PLUGIN_DIR', plugin_dir_path( $plugin_file ) );
define( 'VELOG_PLUGIN_URL', 'https://example.com/wp-content/plugins/velog/' );
define( 'VELOG_PLUGIN_BASENAME', 'velog/velog.php' );
define( 'VELOG_TEXT_DOMAIN', 'velog' );

// Instantiate the singleton WITHOUT calling run() — the baseline bug.
\MF\VeLog\Core\Plugin::get_instance();

// -------------------------------------------------------------------------
// Fire plugins_loaded — nothing should happen (no mf_velog_bootstrap registered).
// -------------------------------------------------------------------------

do_action( 'plugins_loaded' );

// -------------------------------------------------------------------------
// Fire init — load_plugin_textdomain must NOT have been called.
// -------------------------------------------------------------------------

do_action( 'init' );

if ( ! empty( $captured_textdomain_calls ) ) {
	// The mutation unexpectedly passed — the test is no longer valid.
	fwrite( STDERR, "UNEXPECTED: load_plugin_textdomain() was called even with the baseline mutation. Mutation test invalid.\n" );
	exit( 1 );
}

echo "CONFIRMED: baseline mutation correctly reproduces the bug — load_plugin_textdomain() was NOT called after init when run() is not wired.\n";
exit( 0 );
