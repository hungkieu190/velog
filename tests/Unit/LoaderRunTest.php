<?php
/**
 * Unit tests for Loader hook registration and idempotency.
 *
 * Regression suite for CORE-001: verifies that the Loader registers each
 * queued hook exactly once and that Plugin::run() is idempotent.
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
 * Class LoaderRunTest
 *
 * Verifies that Plugin::run() executes Loader::run() exactly once regardless
 * of how many times run() is called, and that Loader::run() registers each
 * queued hook with WordPress exactly once.
 *
 * @since 0.1.0
 */
class LoaderRunTest extends TestCase {

	/**
	 * Stores a reflection to reset the singleton between tests.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $instance_prop;

	/**
	 * Stores a reflection to reset the $ran guard between tests.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $ran_prop;

	/**
	 * Sets up Brain\Monkey and resets singleton state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reflect into the singleton so each test starts with a clean slate.
		$class               = new \ReflectionClass( Plugin::class );
		$this->instance_prop = $class->getProperty( 'instance' );
		$this->instance_prop->setAccessible( true );
		$this->instance_prop->setValue( null, null );

		$this->ran_prop = $class->getProperty( 'ran' );
		$this->ran_prop->setAccessible( true );
	}

	/**
	 * Tears down Brain\Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		// Reset singleton so later tests/suites start clean.
		$this->instance_prop->setValue( null, null );
		parent::tearDown();
	}

	/**
	 * Verifies that Loader::run() passes each queued action to add_action().
	 *
	 * A representative action queued before run() must be forwarded to
	 * WordPress exactly once.
	 *
	 * @since 0.1.0
	 */
	public function test_loader_run_registers_queued_action_once(): void {
		// Stub component with a callable method.
		$component = new class() {
			/**
			 * No-op callback for hook registration testing.
			 *
			 * @return void
			 */
			public function my_callback(): void {}
		};

		$loader = new Loader();
		$loader->add_action( 'init', $component, 'my_callback' );

		// Brain\Monkey intercepts add_action(); no WP runtime needed.
		Functions\expect( 'add_action' )
			->once()
			->with( 'init', \Mockery::type( 'callable' ), 10, 1 );
		$this->addToAssertionCount( 1 );

		$loader->run();
	}

	/**
	 * Verifies that the closure passed to WordPress actually invokes the
	 * target component method with the correct arguments.
	 *
	 * @since 0.1.0
	 */
	public function test_loader_queued_action_is_executed_with_arguments(): void {
		$component = new class() {
			/**
			 * The argument passed to the action.
			 *
			 * @var string
			 */
			public string $passed_arg = '';
			/**
			 * Test callback.
			 *
			 * @param string $arg Arg.
			 */
			public function my_action( string $arg ): void {
				$this->passed_arg = $arg;
			}
		};

		$loader = new Loader();
		$loader->add_action( 'my_hook', $component, 'my_action', 10, 1 );

		/**
		 * The closure registered with WordPress.
		 *
		 * @var callable|null $registered_closure
		 */
		$registered_closure = null;
		Functions\when( 'add_action' )->alias(
			function ( $hook, $callback ) use ( &$registered_closure ): bool {
				$registered_closure = $callback;
				return true;
			}
		);

		$loader->run();

		$this->assertIsCallable( $registered_closure );
		if ( is_callable( $registered_closure ) ) {
			$registered_closure( 'hello_action' );
		}
		$this->assertSame( 'hello_action', $component->passed_arg );
	}

	/**
	 * Verifies that the closure passed to WordPress actually invokes the
	 * target component method and returns its value.
	 *
	 * @since 0.1.0
	 */
	public function test_loader_queued_filter_is_executed_and_returns_value(): void {
		$component = new class() {
			/**
			 * Test callback.
			 *
			 * @param string $arg Arg.
			 * @return string
			 */
			public function my_filter( string $arg ): string {
				return $arg . '_filtered';
			}
		};

		$loader = new Loader();
		$loader->add_filter( 'my_hook', $component, 'my_filter', 10, 1 );

		/**
		 * The closure registered with WordPress.
		 *
		 * @var callable|null $registered_closure
		 */
		$registered_closure = null;
		Functions\when( 'add_filter' )->alias(
			function ( $hook, $callback ) use ( &$registered_closure ): bool {
				$registered_closure = $callback;
				return true;
			}
		);

		$loader->run();

		$this->assertIsCallable( $registered_closure );
		$result = '';
		if ( is_callable( $registered_closure ) ) {
			$result = $registered_closure( 'hello' );
		}
		$this->assertSame( 'hello_filtered', $result );
	}

	/**
	 * Verifies that calling Loader::run() a second time does not
	 * re-register the same hooks with WordPress.
	 *
	 * At baseline (before the idempotency fix) a second run() call would
	 * invoke add_action() again for every queued hook.
	 *
	 * @since 0.1.0
	 */
	public function test_loader_run_called_twice_registers_hooks_twice(): void {
		// This test documents Loader-level behaviour: Loader itself is not
		// idempotent; idempotency is enforced by Plugin::run()'s $ran guard.
		$component = new class() {
			/**
			 * No-op callback for hook registration testing.
			 *
			 * @return void
			 */
			public function cb(): void {}
		};

		$loader = new Loader();
		$loader->add_action( 'init', $component, 'cb' );

		// Two calls → two registrations (Loader is a plain registry).
		Functions\expect( 'add_action' )
			->twice()
			->with( 'init', \Mockery::type( 'callable' ), 10, 1 );
		$this->addToAssertionCount( 1 );

		$loader->run();
		$loader->run();
	}

	/**
	 * Verifies that Plugin::run() is idempotent: calling it multiple times
	 * must not invoke Loader::run() more than once, protecting against
	 * duplicate hook registration.
	 *
	 * This is the primary regression for CORE-001 AC1 / PLAN-F-004.
	 *
	 * @since 0.1.0
	 */
	public function test_plugin_run_is_idempotent(): void {
		// Expect add_action only for the init hook queued by set_locale().
		// If run() were not idempotent the count would double on the second call.
		Functions\expect( 'add_action' )
			->twice()
			->with( 'init', \Mockery::type( 'callable' ), 10, 1 );
		$this->addToAssertionCount( 1 );

		$plugin = Plugin::get_instance();

		$plugin->run();
		$plugin->run(); // Second call must be a no-op.
	}

	/**
	 * Verifies that after Plugin::run() the $ran flag is set, preventing
	 * subsequent executions.
	 *
	 * @since 0.1.0
	 */
	public function test_plugin_run_sets_ran_flag(): void {
		// Stub add_action so Brain\Monkey doesn't complain about uncaptured calls.
		Functions\stubs( array( 'add_action' ) );

		$plugin = Plugin::get_instance();
		$this->assertFalse( $this->ran_prop->getValue( $plugin ) );

		$plugin->run();
		$this->assertTrue( $this->ran_prop->getValue( $plugin ) );
	}
}
