<?php
/**
 * Real-bootstrap entry-path verification script.
 *
 * Exercises velog.php directly: defines minimal WordPress stubs, loads the
 * real entry point, fires plugins_loaded (invoking mf_velog_bootstrap()), then
 * fires init and asserts that load_plugin_textdomain() was called with the
 * correct arguments.
 *
 * Intended to be run as a subprocess from BootstrapEntryTest::
 * test_real_entry_path_registers_and_fires_init_callback().
 *
 * Exit 0 = all assertions pass.
 * Exit 1 = assertion failure (message on stderr).
 * Exit 2 = unexpected PHP error.
 *
 * @package MF\VeLog\Tests
 * @author  Mamflow <https://mamflow.com>
 * @since   0.1.0
 */

declare( strict_types = 1 );

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, WordPress.Security.EscapeOutput.OutputNotEscaped, Generic.Files.LineLength.TooLong, Generic.CodeAnalysis.UnusedFunctionParameter.Found

// -------------------------------------------------------------------------
// Minimal WordPress function stubs — no site bootstrap, no database.
// -------------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/velog-bootstrap-test/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/velog-test-plugins' );
}

/**
 * Captured textdomain registration calls.
 *
 * @var array<int, array<string, mixed>> $captured_textdomain_calls
 */
$captured_textdomain_calls = array();

/**
 * Registered WordPress hooks (simplified flat storage).
 *
 * @var array<string, array<int, callable>> $wp_hooks
 */
$wp_hooks = array();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub: records the hook/callback pair without priority ordering.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority (recorded but not sorted).
	 * @param int      $args     Accepted args count (recorded but unused).
	 * @return true
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
	 * @return true
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return add_action( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Stub: no-op (not tested in this script).
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
	 * Stub: returns the plugin-relative path (plugin-dir/plugin-file.php).
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
	 * Stub: returns a version string sufficient to pass the WP version check.
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
	 * Stub: captures arguments for post-execution assertion.
	 *
	 * @param string      $domain   Text domain.
	 * @param bool        $deprecated Deprecated argument (always false).
	 * @param string|bool $path     Path relative to WP_PLUGIN_DIR.
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
// Load the real plugin entry point.
// -------------------------------------------------------------------------

$plugin_file = dirname( __DIR__, 2 ) . '/velog.php';
if ( ! file_exists( $plugin_file ) ) {
	fwrite( STDERR, "ERROR: velog.php not found at $plugin_file\n" );
	exit( 2 );
}

require $plugin_file;

// -------------------------------------------------------------------------
// Assert: mf_velog_bootstrap() is registered on plugins_loaded.
// -------------------------------------------------------------------------

$plugins_loaded_callbacks = $wp_hooks['plugins_loaded'] ?? array();
$bootstrap_registered     = false;

foreach ( $plugins_loaded_callbacks as $cb ) {
	if ( is_string( $cb ) && 'mf_velog_bootstrap' === $cb ) {
		$bootstrap_registered = true;
		break;
	}
}

if ( ! $bootstrap_registered ) {
	fwrite( STDERR, "FAIL: mf_velog_bootstrap is not registered on plugins_loaded.\n" );
	exit( 1 );
}

// -------------------------------------------------------------------------
// Fire plugins_loaded — this must call mf_velog()->run().
// -------------------------------------------------------------------------

do_action( 'plugins_loaded' );

// -------------------------------------------------------------------------
// Assert: load_plugin_textdomain callback is now on init hook.
// -------------------------------------------------------------------------

$init_callbacks = $wp_hooks['init'] ?? array();
if ( empty( $init_callbacks ) ) {
	fwrite( STDERR, "FAIL: No callbacks registered on init after plugins_loaded.\n" );
	exit( 1 );
}

// -------------------------------------------------------------------------
// Fire init — this must invoke load_plugin_textdomain().
// -------------------------------------------------------------------------

do_action( 'init' );

if ( empty( $captured_textdomain_calls ) ) {
	fwrite( STDERR, "FAIL: load_plugin_textdomain() was not called after init.\n" );
	exit( 1 );
}

$call = $captured_textdomain_calls[0];

if ( 'velog' !== $call['domain'] ) {
	fwrite( STDERR, "FAIL: Expected domain 'velog', got '" . $call['domain'] . "'.\n" );
	exit( 1 );
}

$actual_path   = (string) $call['path'];
$expected_path = 'velog/languages';

if ( $expected_path !== $actual_path ) {
	fwrite( STDERR, "FAIL: Expected path '$expected_path', got '$actual_path'.\n" );
	exit( 1 );
}

// -------------------------------------------------------------------------
// Assert: repeated plugins_loaded/run() does not multiply init callbacks.
// -------------------------------------------------------------------------

$init_count_before_second_run = count( $wp_hooks['init'] ?? array() );
do_action( 'plugins_loaded' ); // idempotency: second plugins_loaded re-fires bootstrap.
$init_count_after_second_run = count( $wp_hooks['init'] ?? array() );

if ( $init_count_before_second_run !== $init_count_after_second_run ) {
	fwrite( STDERR, 'FAIL: Repeated bootstrap added ' . ( $init_count_after_second_run - $init_count_before_second_run ) . " extra init callback(s) (idempotency regression).\n" );
	exit( 1 );
}

echo "PASS: bootstrap entry path wired load_plugin_textdomain on init with domain='velog' path='$actual_path'; idempotency verified.\n";
exit( 0 );
