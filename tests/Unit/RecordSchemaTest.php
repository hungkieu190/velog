<?php
/**
 * RecordSchemaTest: unit tests for RecordSchema registry.
 *
 * Covers verification matrix case V1 (AC1).
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Storage\RecordSchema;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RecordSchema registry: registration, sealing, field validation.
 *
 * @covers \MF\VeLog\Common\Storage\RecordSchema
 */
class RecordSchemaTest extends TestCase {

	/**
	 * Reset registry before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		RecordSchema::reset_for_testing();
	}

	/**
	 * Reset registry after each test.
	 */
	protected function tearDown(): void {
		RecordSchema::reset_for_testing();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Registration and sealing.
	// -------------------------------------------------------------------------

	/**
	 * Register and get returns definition.
	 */
	public function test_register_and_get_returns_definition(): void {
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'name' => 'public',
				),
				'fields'         => array(
					'name' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
				),
				'states'         => array( 'active', 'archived' ),
				'capability'     => 'mf_velog_manage_customers',
			)
		);

		$def = RecordSchema::get( 'mf_velog_customer' );
		$this->assertIsArray( $def );
		$this->assertArrayHasKey( 'fields', $def );
	}

	/**
	 * Unregistered type throws.
	 */
	public function test_unregistered_type_fails_closed(): void {
		RecordSchema::seal();
		$this->expectException( \InvalidArgumentException::class );
		RecordSchema::get( 'mf_velog_customer' );
	}

	/**
	 * Register after seal throws LogicException.
	 */
	public function test_register_after_seal_throws(): void {
		RecordSchema::seal();
		$this->expectException( \LogicException::class );
		RecordSchema::register( 'mf_velog_customer', array() );
	}

	/**
	 * Disallowed post type rejected.
	 */
	public function test_disallowed_post_type_rejected(): void {
		$this->expectException( \LogicException::class );
		RecordSchema::register( 'unknown_type', array() );
	}

	/**
	 * Is-registered false before registration.
	 */
	public function test_is_registered_false_before_registration(): void {
		$this->assertFalse( RecordSchema::is_registered( 'mf_velog_customer' ) );
	}

	/**
	 * Is-registered true after registration.
	 */
	public function test_is_registered_true_after_registration(): void {
		RecordSchema::register( 'mf_velog_vehicle', array() );
		$this->assertTrue( RecordSchema::is_registered( 'mf_velog_vehicle' ) );
	}

	/**
	 * Seal is idempotent.
	 */
	public function test_seal_is_idempotent(): void {
		RecordSchema::seal();
		RecordSchema::seal(); // Must not throw.
		$this->assertTrue( RecordSchema::is_sealed() );
	}

	// -------------------------------------------------------------------------
	// V1: Unknown field rejected — no mutation.
	// -------------------------------------------------------------------------

	/**
	 * Unknown field returns WP_Error.
	 */
	public function test_unknown_field_rejected(): void {
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'name' => 'public',
				),
				'fields'         => array(
					'name' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
				),
			)
		);

		$result = RecordSchema::validate_fields(
			'mf_velog_customer',
			array(
				'name'          => 'Alice',
				'_forged_field' => 'injected',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// V1: PHP object value rejected.
	// -------------------------------------------------------------------------

	/**
	 * Object value returns WP_Error.
	 */
	public function test_php_object_value_rejected(): void {
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'name' => 'public',
				),
				'fields'         => array(
					'name' => static fn ( mixed $v ) => is_string( $v ) ? $v : null,
				),
			)
		);

		$result = RecordSchema::validate_fields(
			'mf_velog_customer',
			array(
				'name' => new \stdClass(),
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	// -------------------------------------------------------------------------
	// V1: Validator null return → rejection.
	// -------------------------------------------------------------------------

	/**
	 * Failing validator returns WP_Error.
	 */
	public function test_field_failing_validator_rejected(): void {
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'age' => 'internal',
				),
				'fields'         => array(
					'age' => static fn ( mixed $v ) => ( is_int( $v ) && $v >= 0 ) ? $v : null,
				),
			)
		);

		$result = RecordSchema::validate_fields(
			'mf_velog_customer',
			array( 'age' => -5 )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// V1: Valid fields pass and canonical value preserved.
	// -------------------------------------------------------------------------

	/**
	 * Valid fields returned as canonical values.
	 */
	public function test_valid_fields_returned_as_canonical(): void {
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'name'  => 'public',
					'score' => 'internal',
				),
				'fields'         => array(
					'name'  => static fn ( mixed $v ) => is_string( $v ) ? trim( $v ) : null,
					'score' => static fn ( mixed $v ) => is_int( $v ) ? $v : null,
				),
			)
		);

		$result = RecordSchema::validate_fields(
			'mf_velog_customer',
			array(
				'name'  => '  Alice  ',
				'score' => 42,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'Alice', $result['name'] );
		$this->assertSame( 42, $result['score'] );
	}

	// -------------------------------------------------------------------------
	// V1: Invalid validator definition rejected at registration.
	// -------------------------------------------------------------------------

	/**
	 * Non-callable validator rejected at registration.
	 */
	public function test_non_callable_validator_rejected_at_registration(): void {
		$this->expectException( \InvalidArgumentException::class );
		RecordSchema::register(
			'mf_velog_customer',
			array(
				'field_policies' => array(
					'name' => 'public',
				),
				'fields'         => array(
					'name' => 'not_a_callable',
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// META_KEY constant is the authoritative value.
	// -------------------------------------------------------------------------

	/**
	 * META_KEY constant has correct value.
	 */
	public function test_meta_key_constant_value(): void {
		$this->assertSame( '_mf_velog_record', RecordSchema::META_KEY );
	}

	// -------------------------------------------------------------------------
	// ALLOWED_TYPES matches CORE-003 CPT slugs.
	// -------------------------------------------------------------------------

	/**
	 * ALLOWED_TYPES matches CORE-003 slugs.
	 */
	public function test_allowed_types_match_core003_slugs(): void {
		$expected = array(
			'mf_velog_customer',
			'mf_velog_vehicle',
			'mf_velog_service',
			'mf_velog_reminder',
		);
		$this->assertSame( $expected, RecordSchema::ALLOWED_TYPES );
	}
}
