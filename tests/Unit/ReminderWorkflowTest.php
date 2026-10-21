<?php
/**
 * Reminder business boundaries independent of database query implementation.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Common\Reminder\ReminderPolicy;
use MF\VeLog\Common\Reminder\ReminderService;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Service\ServiceHistoryQuery;
use PHPUnit\Framework\TestCase;

/** Exact triggers and private presentation projections. */
final class ReminderWorkflowTest extends TestCase {
	/** Prepare registered schemas and translation doubles. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'Asia/Bangkok' ) );
		RecordSchema::reset_for_testing();
		RecordSchema::register_core_schemas();
	}

	/** Restore global registry and WordPress doubles. */
	protected function tearDown(): void {
		RecordSchema::reset_for_testing();
		Monkey\tearDown();
		parent::tearDown();
	}

	/** A single zero character is a legitimate nonempty title. */
	public function test_zero_title_is_not_unknown(): void {
		$this->assertSame( '0', ReminderService::title( '0' ) );
		$this->assertTrue( ReminderService::validate_record( $this->fields( array( 'title' => '0' ) ) ) );
	}

	/** Date and distance triggers are alternatives, with inclusive boundaries. */
	public function test_due_boundaries_and_unknown_distance(): void {
		$record = array(
			'state'  => 'active',
			'fields' => $this->fields(),
		);
		$this->assertSame( 'due', ReminderPolicy::effective( $record, 'active', null, '2026-10-21' ) );
		$record['fields']['due_date']  = '2026-10-22';
		$record['fields']['threshold'] = Distance::parse( '0', 'km', '.' );
		$this->assertSame( 'upcoming', ReminderPolicy::effective( $record, 'active', null, '2026-10-21' ) );
		$this->assertSame( 'due', ReminderPolicy::effective( $record, 'active', '0', '2026-10-21' ) );
		$record['fields']['threshold'] = Distance::parse( '1', 'mi', '.' );
		$this->assertSame( 'upcoming', ReminderPolicy::effective( $record, 'active', '1609343', '2026-10-21' ) );
		$this->assertSame( 'due', ReminderPolicy::effective( $record, 'active', '1609344', '2026-10-21' ) );
	}

	/** Snooze expiry and archive state never require a read-side update. */
	public function test_effective_state_precedence(): void {
		$record = array(
			'state'  => 'snoozed',
			'fields' => $this->fields( array( 'snooze_until' => '2026-10-22' ) ),
		);
		$this->assertSame( 'snoozed', ReminderPolicy::effective( $record, 'active', null, '2026-10-21' ) );
		$this->assertSame( 'due', ReminderPolicy::effective( $record, 'active', null, '2026-10-22' ) );
		$this->assertSame( 'suspended', ReminderPolicy::effective( $record, 'archived', null, '2026-10-21' ) );
		$record['state'] = 'completed';
		$this->assertSame( 'completed', ReminderPolicy::effective( $record, 'archived', null, '2026-10-21' ) );
	}

	/** Malformed dates, triggerless and forged tuples cannot enter storage. */
	public function test_validation_is_closed_and_exact(): void {
		$this->assertSame( '2028-02-29', ReminderPolicy::date( '2028-02-29' ) );
		$this->assertNull( ReminderPolicy::date( '2026-02-29' ) );
		$this->assertFalse( ReminderService::validate_record( $this->fields( array( 'due_date' => '' ) ) ) );
		$this->assertFalse( ReminderService::validate_record( $this->fields( array( 'unknown' => true ) ) ) );
		foreach ( array( '', "a\nb", '<b>x</b>', array(), str_repeat( 'x', 201 ) ) as $bad ) {
			$this->assertNull( ReminderService::title( $bad ) );
		}
		$this->assertSame( '2026-10-21T00:00:00Z', ReminderService::timestamp( '2026-10-21T00:00:00Z' ) );
		$this->assertNull( ReminderService::timestamp( '2026-02-30T00:00:00Z' ) );
	}

	/** History disclosure must exclude arbitrary contact fields in audit entries. */
	public function test_history_audit_is_contact_free(): void {
		$result = ServiceHistoryQuery::audit_projection(
			array(
				array(
					'actor_id' => 1,
					'at_utc'   => '2026-10-21T00:00:00Z',
					'reason'   => 'Correction',
					'before'   => array(
						'notes'   => 'Before',
						'__state' => 'finalized',
						'phone'   => 'PRIVATE',
					),
					'after'    => array(
						'notes'   => 'After',
						'__state' => 'finalized',
						'email'   => 'PRIVATE',
					),
				),
			)
		);
		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'phone', $result[0]['before'] );
		$this->assertArrayNotHasKey( 'email', $result[0]['after'] );
		$this->assertInstanceOf( \WP_Error::class, ServiceHistoryQuery::audit_projection( array( array() ) ) );
	}

	/**
	 * Construct complete canonical fields with explicit unknown distance.
	 *
	 * @param array<string, mixed> $changes Field overrides.
	 * @return array<string, mixed>
	 */
	private function fields( array $changes = array() ): array {
		return array_merge(
			array(
				'vehicle_id'       => 42,
				'title'            => 'Inspect brakes',
				'due_date'         => '2026-10-21',
				'threshold'        => array(),
				'snooze_until'     => '',
				'completed_by'     => 0,
				'completed_at_utc' => '',
			),
			$changes
		);
	}
}
