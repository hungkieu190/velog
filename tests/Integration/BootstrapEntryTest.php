<?php
/**
 * Bootstrap entry-path integration tests.
 *
 * Regression suite for CORE-001 F-002: verifies the actual velog.php entry
 * path through subprocess execution rather than direct Plugin::run() calls.
 * Each subprocess loads velog.php with minimal WP stubs, fires the
 * WordPress lifecycle hooks, and asserts observable behavior.
 *
 * These tests would FAIL against the baseline f04d4fc because run() was never
 * called from the plugins_loaded callback. The mutation test proves this by
 * confirming that omitting run() leaves load_plugin_textdomain unregistered.
 *
 * @package MF\VeLog\Tests\Integration
 * @author  Mamflow <https://mamflow.com>
 * @since   0.1.0
 */

namespace MF\VeLog\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Class BootstrapEntryTest
 *
 * Exercises the real velog.php bootstrap path in isolated subprocesses.
 * Subprocess exit codes and output are the evidence, not reflection into
 * production internals.
 *
 * @since 0.1.0
 */
class BootstrapEntryTest extends TestCase {

	/**
	 * Absolute path to the fixtures directory.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $fixtures_dir;

	/**
	 * Sets up the fixtures path before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->fixtures_dir = dirname( __DIR__ ) . '/fixtures';
	}

	/**
	 * Runs a fixture script in a subprocess and returns the result.
	 *
	 * @since 0.1.0
	 * @param string $script Filename relative to the fixtures directory.
	 * @return array{exit_code: int, stdout: string, stderr: string}
	 */
	private function run_fixture( string $script ): array {
		$path       = $this->fixtures_dir . '/' . $script;
		$descriptor = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $path ),
			$descriptor,
			$pipes
		);

		if ( ! is_resource( $process ) ) {
			$this->fail( "proc_open failed for fixture: $script" );
		}

		fclose( $pipes[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );

		fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$exit_code = proc_close( $process );

		return array(
			'exit_code' => $exit_code,
			'stdout'    => (string) $stdout,
			'stderr'    => (string) $stderr,
		);
	}

	/**
	 * Verifies the real entry path: loading velog.php, firing plugins_loaded
	 * (which calls mf_velog_bootstrap() → run()), then firing init, causes
	 * load_plugin_textdomain() to be called with domain='velog' and the exact
	 * WP_PLUGIN_DIR-relative path 'velog/languages'.
	 *
	 * This test would fail against baseline f04d4fc because the anonymous
	 * closure on plugins_loaded called mf_velog() without run(), leaving the
	 * Loader unexecuted and load_plugin_textdomain() never called.
	 *
	 * @since 0.1.0
	 */
	public function test_real_entry_path_registers_and_fires_init_callback(): void {
		$result = $this->run_fixture( 'bootstrap-entry-verify.php' );

		$this->assertSame(
			0,
			$result['exit_code'],
			'Real bootstrap entry path must exit 0. stderr: ' . $result['stderr']
		);
		$this->assertStringContainsString(
			'PASS',
			$result['stdout'],
			'Subprocess output must contain PASS. stdout: ' . $result['stdout']
		);
		$this->assertStringContainsString(
			"path='velog/languages'",
			$result['stdout'],
			'Output must confirm exact WP_PLUGIN_DIR-relative path velog/languages.'
		);
	}

	/**
	 * Proves the failing-before case: when run() is not wired from the
	 * entry point (the original f04d4fc bug), load_plugin_textdomain() is
	 * never called after init.
	 *
	 * This is the negative/regression half of the F-002 fix: the mutation
	 * script must exit 0 (bug reproduced), proving the positive test above
	 * genuinely detects the absence of the fix.
	 *
	 * @since 0.1.0
	 */
	public function test_baseline_mutation_without_run_fails_to_register_callbacks(): void {
		$result = $this->run_fixture( 'bootstrap-mutation-verify.php' );

		$this->assertSame(
			0,
			$result['exit_code'],
			'Baseline mutation must exit 0 (bug confirmed present). stderr: ' . $result['stderr']
		);
		$this->assertStringContainsString(
			'CONFIRMED',
			$result['stdout'],
			'Mutation subprocess must output CONFIRMED. stdout: ' . $result['stdout']
		);
	}

	/**
	 * Verifies that the mf_velog() global function returns the Plugin instance
	 * (public API preserved), exercised through the real velog.php entry path.
	 *
	 * Checked via the subprocess stdout which includes the PASS line only after
	 * confirming the singleton and its behavior.
	 *
	 * @since 0.1.0
	 */
	public function test_mf_velog_accessor_returns_plugin_instance_via_real_entry(): void {
		$result = $this->run_fixture( 'bootstrap-entry-verify.php' );

		// The verify script succeeds only if mf_velog()->run() worked,
		// which confirms mf_velog() returns a functional Plugin instance.
		$this->assertSame(
			0,
			$result['exit_code'],
			'mf_velog() must return a functional Plugin; subprocess must exit 0. stderr: ' . $result['stderr']
		);
	}
}
