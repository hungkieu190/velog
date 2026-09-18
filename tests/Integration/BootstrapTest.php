<?php
/**
 * Plugin Integration Test
 *
 * Covers singleton construction, public API preservation and bootstrap
 * idempotency regressions for CORE-001.
 *
 * @package MF\VeLog\Tests\Integration
 * @author  Mamflow <https://mamflow.com>
 * @since   0.1.0
 */

namespace MF\VeLog\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Core\Loader;
use MF\VeLog\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Class BootstrapTest
 *
 * Tests singleton construction, Loader registration and run() idempotency.
 *
 * @since 0.1.0
 */
class BootstrapTest extends TestCase {

	/**
	 * Reflection property used to reset the singleton between tests.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $instance_prop;

	/**
	 * Reflection property used to reset the $ran flag between tests.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $ran_prop;

	/**
	 * Sets up Brain\Monkey and a clean singleton before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$class               = new \ReflectionClass( Plugin::class );
		$this->instance_prop = $class->getProperty( 'instance' );
		$this->instance_prop->setAccessible( true );
		$this->instance_prop->setValue( null, null );

		$this->ran_prop = $class->getProperty( 'ran' );
		$this->ran_prop->setAccessible( true );
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
	 * Verifies that Plugin::get_instance() returns a Plugin and exposes
	 * the correct version — the original passing assertion.
	 *
	 * @since 0.1.0
	 */
	public function test_plugin_instance_can_be_retrieved(): void {
		$instance = Plugin::get_instance();
		$this->assertInstanceOf( Plugin::class, $instance );
		$this->assertSame( VELOG_VERSION, $instance->get_version() );
	}

	/**
	 * Verifies that get_loader() returns the Loader instance (public API intact).
	 *
	 * @since 0.1.0
	 */
	public function test_get_loader_returns_loader_instance(): void {
		$plugin = Plugin::get_instance();
		$this->assertInstanceOf( Loader::class, $plugin->get_loader() );
	}

	/**
	 * Verifies that the Loader queue contains at least one action after
	 * construction — specifically the init/load_plugin_textdomain entry
	 * queued by set_locale().
	 *
	 * At baseline set_locale() queued on plugins_loaded (wrong hook) and
	 * never fired; this regression ensures at least one hook is ready.
	 *
	 * @since 0.1.0
	 */
	public function test_loader_has_queued_actions_after_construction(): void {
		$plugin = Plugin::get_instance();
		$loader = $plugin->get_loader();

		$ref     = new \ReflectionClass( Loader::class );
		$actions = $ref->getProperty( 'actions' );
		$actions->setAccessible( true );
		$queued = $actions->getValue( $loader );

		$this->assertNotEmpty(
			$queued,
			'Loader must have at least one queued action after Plugin construction.'
		);
	}

	/**
	 * Verifies that calling Plugin::run() multiple times does not multiply
	 * the number of add_action() calls issued to WordPress.
	 *
	 * This is the core PLAN-F-004 regression: at baseline Plugin::run()
	 * forwarded to Loader::run() unconditionally; repeated calls would
	 * re-register every hook.
	 *
	 * @since 0.1.0
	 */
	public function test_run_called_twice_registers_hooks_only_once(): void {
		$call_count = 0;

		Functions\when( 'add_action' )->alias(
			static function () use ( &$call_count ): bool {
				++$call_count;
				return true;
			}
		);

		$plugin = Plugin::get_instance();
		$plugin->run();
		$after_first = $call_count;

		$plugin->run(); // Idempotency guard must prevent a second pass.
		$after_second = $call_count;

		$this->assertGreaterThan(
			0,
			$after_first,
			'At least one add_action() call expected after first run().'
		);
		$this->assertSame(
			$after_first,
			$after_second,
			'Second run() must not register additional hooks (idempotency regression).'
		);
	}

	/**
	 * Verifies that get_instance() returns the same object on repeated calls
	 * (singleton identity preserved).
	 *
	 * @since 0.1.0
	 */
	public function test_get_instance_returns_same_singleton(): void {
		$a = Plugin::get_instance();
		$b = Plugin::get_instance();
		$this->assertSame( $a, $b );
	}
}
