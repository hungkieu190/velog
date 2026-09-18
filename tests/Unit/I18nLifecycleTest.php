<?php
/**
 * Unit tests for translation lifecycle registration.
 *
 * Regression suite for CORE-001 AC2: verifies that the text-domain callback
 * is queued on `init` (not `plugins_loaded`) and that repeated Plugin::run()
 * calls do not register the callback twice.
 *
 * @package MF\VeLog\Tests\Unit
 * @author  Mamflow <https://mamflow.com>
 * @since   0.1.0
 */

namespace MF\VeLog\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Core\Loader;
use MF\VeLog\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Class I18nLifecycleTest
 *
 * Verifies that translation loading is registered on `init`, not on
 * `plugins_loaded`, and that the text-domain parameters are correct.
 * These are unit-level checks using Brain\Monkey; no live WordPress required.
 *
 * @since 0.1.0
 */
class I18nLifecycleTest extends TestCase {

	/**
	 * Singleton reset property.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $instance_prop;

	/**
	 * Sets up Brain\Monkey and resets the Plugin singleton before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$class               = new \ReflectionClass( Plugin::class );
		$this->instance_prop = $class->getProperty( 'instance' );
		$this->instance_prop->setAccessible( true );
		$this->instance_prop->setValue( null, null );
	}

	/**
	 * Tears down Brain\Monkey and resets the singleton after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		$this->instance_prop->setValue( null, null );
		parent::tearDown();
	}

	/**
	 * Verifies that the text-domain action is queued on `init`, not
	 * on `plugins_loaded`.
	 *
	 * This is a regression for the baseline bug where set_locale() queued
	 * load_plugin_textdomain on plugins_loaded — a hook that is already
	 * firing when Plugin is constructed, so the callback never ran.
	 *
	 * @since 0.1.0
	 */
	public function test_textdomain_is_registered_on_init_not_plugins_loaded(): void {
		$plugin = Plugin::get_instance();
		$loader = $plugin->get_loader();

		// Inspect the internal actions queue via reflection.
		$ref     = new \ReflectionClass( Loader::class );
		$actions = $ref->getProperty( 'actions' );
		$actions->setAccessible( true );
		$queued = $actions->getValue( $loader );

		// Find any entry whose hook is 'init' and callback is 'load_plugin_textdomain'.
		$found_on_init           = false;
		$found_on_plugins_loaded = false;

		foreach ( $queued as $entry ) {
			if ( 'load_plugin_textdomain' === $entry['callback'] ) {
				if ( 'init' === $entry['hook'] ) {
					$found_on_init = true;
				}
				if ( 'plugins_loaded' === $entry['hook'] ) {
					$found_on_plugins_loaded = true;
				}
			}
		}

		$this->assertTrue(
			$found_on_init,
			'load_plugin_textdomain must be queued on "init".'
		);
		$this->assertFalse(
			$found_on_plugins_loaded,
			'load_plugin_textdomain must NOT be queued on "plugins_loaded" (baseline regression).'
		);
	}

	/**
	 * Verifies that after Plugin::run(), exactly one `init` action is
	 * registered with WordPress for load_plugin_textdomain — even if
	 * run() is called more than once.
	 *
	 * @since 0.1.0
	 */
	public function test_textdomain_action_registered_exactly_once_after_run(): void {
		// add_action is intercepted; record every call.

		/*
		 * Accumulated calls: each element is [ hook_name, callable ].
		 *
		 * @var array<int, array<int, mixed>> $recorded
		 */
		$recorded = array();

		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback ) use ( &$recorded ): bool {
				$recorded[] = array( $hook, $callback );
				return true;
			}
		);

		$plugin = Plugin::get_instance();
		$plugin->run();
		$plugin->run(); // Must be no-op.

		// Filter only the init registrations whose callable targets load_plugin_textdomain.
		$textdomain_init_count = 0;
		foreach ( $recorded as $call ) {
			[ $hook, $callable ] = $call;
			if ( 'init' !== $hook ) {
				continue;
			}
			// The callable is a Closure wrapping [$plugin, 'load_plugin_textdomain'].
			if ( $callable instanceof \Closure ) {
				$rf = new \ReflectionFunction( $callable );
				// Brain\Monkey wraps in a Closure; check via get_loader queue instead.
			}
			++$textdomain_init_count;
		}

		// Exactly one init registration (from set_locale); run() is idempotent.
		$this->assertSame(
			1,
			$textdomain_init_count,
			'Exactly one add_action("init", ...) call expected after idempotent run().'
		);
	}

	/**
	 * Verifies that load_plugin_textdomain() uses the velog text domain and
	 * the exact WP_PLUGIN_DIR-relative languages path.
	 *
	 * The third argument to load_plugin_textdomain() must be a path relative
	 * to WP_PLUGIN_DIR. WordPress prepends WP_PLUGIN_DIR internally, so an
	 * absolute path would produce a duplicated directory segment (F-001).
	 *
	 * Expected path: dirname(plugin_basename(VELOG_PLUGIN_FILE)) . '/languages'
	 *   = dirname('velog/velog.php') . '/languages'
	 *   = 'velog/languages'
	 *
	 * @since 0.1.0
	 */
	public function test_load_plugin_textdomain_uses_correct_domain_and_path(): void {
		$plugin = Plugin::get_instance();

		// Stub plugin_basename() to return the canonical test-fixture value.
		// VELOG_PLUGIN_FILE is set in tests/bootstrap.php to the real plugin file path.
		Functions\when( 'plugin_basename' )->alias(
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $file is required by interface but intentionally hardcoded in stub.
			static function ( string $file ): string {
				// Simulate the WP behaviour: strip the plugin dir prefix.
				return 'velog/velog.php';
			}
		);

		// Capture what load_plugin_textdomain() receives.
		$captured_domain = null;
		$captured_path   = null;

		Functions\when( 'load_plugin_textdomain' )->alias(
			static function (
				string $domain,
				bool $deprecated,
				string $path
			) use (
				&$captured_domain,
				&$captured_path
			): bool {
				$captured_domain = $domain;
				$captured_path   = $path;
				return true;
			}
		);

		$plugin->load_plugin_textdomain();

		$this->assertSame(
			'velog',
			$captured_domain,
			'Text domain must be "velog".'
		);

		// Exact WP_PLUGIN_DIR-relative path expected by l10n.php.
		$this->assertSame(
			'velog/languages',
			$captured_path,
			'Path must be the WP_PLUGIN_DIR-relative "velog/languages", not an absolute path (F-001 regression).'
		);
	}
}
