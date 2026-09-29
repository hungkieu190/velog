<?php
/**
 * AuditEntryTest: unit tests for AuditEntry immutability and server-side generation.
 *
 * Covers verification matrix case V1 (AC1).
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Storage\AuditEntry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuditEntry: server-side generation, immutability, no forge vector.
 *
 * @covers \MF\VeLog\Common\Storage\AuditEntry
 */
class AuditEntryTest extends TestCase {

	// -------------------------------------------------------------------------
	// Server-side generation — no public constructor.
	// -------------------------------------------------------------------------

	/**
	 * For-create generates actor_id from WP_User.
	 */
	public function test_for_create_generates_actor_id_from_wp_user(): void {
		$actor     = new \WP_User();
		$actor->ID = 99;

		$entry = AuditEntry::for_create( $actor, 'request-uuid-1', array( 'name' => 'Alice' ) );
		$arr   = $entry->to_array();

		$this->assertSame( 99, $arr['actor_id'] );
	}

	/**
	 * For-create generates UTC timestamp.
	 */
	public function test_for_create_generates_utc_timestamp(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_create( $actor, 'request-uuid-1', array() );
		$arr       = $entry->to_array();

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$arr['at_utc']
		);
	}

	/**
	 * For-create op is 'create'.
	 */
	public function test_for_create_op_is_create(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_create( $actor, 'req-1', array() );
		$this->assertSame( 'create', $entry->get_op() );
	}

	/**
	 * For-create before snapshot is empty.
	 */
	public function test_for_create_before_is_empty(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_create( $actor, 'req-1', array( 'name' => 'Test' ) );
		$arr       = $entry->to_array();

		$this->assertSame( array(), $arr['before'] );
	}

	/**
	 * For-create after contains fields.
	 */
	public function test_for_create_after_contains_fields(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$fields    = array(
			'name'  => 'Alice',
			'score' => 10,
		);
		$entry     = AuditEntry::for_create( $actor, 'req-1', $fields );
		$arr       = $entry->to_array();

		$this->assertSame( $fields, $arr['after'] );
	}

	// -------------------------------------------------------------------------
	// V1: request_id stored as sha256 — raw UUID not in stored form.
	// -------------------------------------------------------------------------

	/**
	 * Request ID stored as SHA-256 not raw value.
	 */
	public function test_request_id_stored_as_sha256_not_raw(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$req_id    = 'my-secret-request-uuid';
		$entry     = AuditEntry::for_create( $actor, $req_id, array() );
		$arr       = $entry->to_array();

		$this->assertArrayNotHasKey( 'request_id', $arr );
		$this->assertArrayHasKey( 'request_id_sha256', $arr );
		$this->assertSame( hash( 'sha256', $req_id ), $arr['request_id_sha256'] );
	}

	// -------------------------------------------------------------------------
	// For-save audit entry.
	// -------------------------------------------------------------------------

	/**
	 * For-save op is 'save'.
	 */
	public function test_for_save_op_is_save(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_save(
			$actor,
			'req-2',
			'correction',
			array( 'name' => 'Old' ),
			array( 'name' => 'New' )
		);
		$this->assertSame( 'save', $entry->get_op() );
	}

	/**
	 * For-save captures before and after.
	 */
	public function test_for_save_captures_before_and_after(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$before    = array( 'name' => 'Old Name' );
		$after     = array( 'name' => 'New Name' );

		$entry = AuditEntry::for_save( $actor, 'req-2', '', $before, $after );
		$arr   = $entry->to_array();

		$this->assertSame( $before, $arr['before'] );
		$this->assertSame( $after, $arr['after'] );
	}

	/**
	 * For-save reason stored.
	 */
	public function test_for_save_reason_stored(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_save( $actor, 'req-2', 'typo correction', array(), array() );
		$arr       = $entry->to_array();

		$this->assertSame( 'typo correction', $arr['reason'] );
	}

	// -------------------------------------------------------------------------
	// V1: to_array() returns only expected keys — no internal state leak.
	// -------------------------------------------------------------------------

	/**
	 * To-array has expected keys only.
	 */
	public function test_to_array_has_expected_keys_only(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_create( $actor, 'req-3', array() );
		$arr       = $entry->to_array();

		$expected_keys = array(
			'actor_id',
			'at_utc',
			'request_id_sha256',
			'reason',
			'op',
			'before',
			'after',
		);

		$this->assertSame( $expected_keys, array_keys( $arr ) );
	}

	// -------------------------------------------------------------------------
	// Immutability: second call to to_array() returns same data.
	// -------------------------------------------------------------------------

	/**
	 * To-array is stable across multiple calls.
	 */
	public function test_to_array_is_stable(): void {
		$actor     = new \WP_User();
		$actor->ID = 1;
		$entry     = AuditEntry::for_create( $actor, 'req-4', array( 'x' => 1 ) );
		$first     = $entry->to_array();
		$second    = $entry->to_array();

		$this->assertSame( $first, $second );
	}
}
