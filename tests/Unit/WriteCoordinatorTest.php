<?php
/**
 * WriteCoordinatorTest: unit tests for WriteCoordinator logic.
 *
 * Covers verification matrix case V4 (AC4) — logic-level invariants.
 * Actual transaction/lock/cache behavior is proved in disposable WP integration fixtures.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;
use PHPUnit\Framework\TestCase;

/**
 * Logic-level tests for WriteCoordinator.
 *
 * @covers \MF\VeLog\Common\Storage\WriteCoordinator
 */
class WriteCoordinatorTest extends TestCase {

	/**
	 * Reset static state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		WriteCoordinator::reset_for_testing();
	}

	/**
	 * Reset static state after each test.
	 */
	protected function tearDown(): void {
		WriteCoordinator::reset_for_testing();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Environment check method exists.
	// -------------------------------------------------------------------------

	/**
	 * Check environment method exists.
	 */
	public function test_check_environment_method_exists(): void {
		$this->assertTrue( method_exists( WriteCoordinator::class, 'check_environment' ) );
	}

	// -------------------------------------------------------------------------
	// WriteUnit: touch and get_affected_post_ids deduplicates.
	// -------------------------------------------------------------------------

	/**
	 * WriteUnit tracks and deduplicates touched post IDs.
	 */
	public function test_write_unit_tracks_touched_post_ids(): void {
		if ( ! extension_loaded( 'mysqli' ) ) {
			$this->markTestSkipped( 'mysqli extension not available.' );
		}

		$unit = $this->build_write_unit();

		$unit->touch( 10 );
		$unit->touch( 20 );
		$unit->touch( 10 ); // Duplicate — must be deduplicated.

		$ids = $unit->get_affected_post_ids();
		sort( $ids );

		$this->assertSame( array( 10, 20 ), $ids );
	}

	// -------------------------------------------------------------------------
	// V4: run() and nested unit guard exist.
	// -------------------------------------------------------------------------

	/**
	 * Run method exists on WriteCoordinator.
	 */
	public function test_run_method_exists(): void {
		$this->assertTrue( method_exists( WriteCoordinator::class, 'run' ) );
	}

	// -------------------------------------------------------------------------
	// exec_direct / query_direct: typed interface exists.
	// -------------------------------------------------------------------------

	/**
	 * Methods exec_direct and query_direct exist.
	 */
	public function test_exec_direct_and_query_direct_exist(): void {
		$this->assertTrue( method_exists( WriteCoordinator::class, 'exec_direct' ) );
		$this->assertTrue( method_exists( WriteCoordinator::class, 'query_direct' ) );
	}

	// -------------------------------------------------------------------------
	// LOCK_TIMEOUT_SECONDS is 5 (contract candidate).
	// -------------------------------------------------------------------------

	/**
	 * LOCK_TIMEOUT_SECONDS private constant equals 5.
	 */
	public function test_lock_timeout_constant_is_five(): void {
		$ref       = new \ReflectionClass( WriteCoordinator::class );
		$constants = $ref->getConstants();

		$this->assertArrayHasKey( 'LOCK_TIMEOUT_SECONDS', $constants );
		$this->assertSame( 5, $constants['LOCK_TIMEOUT_SECONDS'] );
	}

	// -------------------------------------------------------------------------
	// Helper.
	// -------------------------------------------------------------------------

	/**
	 * Builds a WriteUnit with a dummy (disconnected) handle for accumulator tests.
	 *
	 * @return WriteUnit
	 */
	private function build_write_unit(): WriteUnit {
		$dbh = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init
		if ( false === $dbh ) {
			$this->markTestSkipped( 'mysqli_init() failed.' );
		}
		return new WriteUnit( $dbh, 'wp_' );
	}
}
