<?php
/**
 * Vehicle identifier policy tests.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Vehicle\VehicleIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the approved vehicle identifier policy.
 *
 * @covers \MF\VeLog\Common\Vehicle\VehicleIdentifier
 */
final class VehicleIdentifierTest extends TestCase {
	/** Set the site-calendar year independently of host time. */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		\Brain\Monkey\Functions\when( 'wp_date' )->justReturn( '2026' );
	}

	/** Remove WordPress function doubles. */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/** Reject partial plate pairs even if a VIN is present. */
	public function test_complete_identifier_invariant_and_malformed_utf8(): void {
		$this->assertFalse(
			VehicleIdentifier::validate_record(
				array(
					'plate' => 'A',
					'vin'   => 'VIN',
				)
			)
		);
		$this->assertFalse(
			VehicleIdentifier::validate_record(
				array(
					'jurisdiction' => 'TH',
					'vin'          => 'VIN',
				)
			)
		);
		$this->assertTrue( VehicleIdentifier::validate_record( array( 'vin' => '0' ) ) );
		$this->assertSame( '0', VehicleIdentifier::project_vin( array( 'vin' => '0' ) ) );
		$this->assertNull( VehicleIdentifier::normalize_vin( "bad\xFF" ) );
		$this->assertNull( VehicleIdentifier::validate_plate( "bad\xFF" ) );
		$this->assertSame( 0, VehicleIdentifier::validate_year( '' ) );
		$this->assertSame( 0, VehicleIdentifier::validate_year( 0 ) );
		$this->assertSame( 2027, VehicleIdentifier::validate_year( '2027' ) );
		$this->assertNull( VehicleIdentifier::validate_year( '2028' ) );
	}

	/** Verify Unicode plate normalization and unambiguous tuple keys. */
	public function test_plate_policy_preserves_unicode_and_builds_unambiguous_key(): void {
		$this->assertSame( 'TH', VehicleIdentifier::normalize_jurisdiction( ' th ' ) );
		$this->assertSame( 'กข 123', VehicleIdentifier::normalize_plate( " กข\t123 " ) );
		$this->assertSame( '2:TH10:กข 123', VehicleIdentifier::plate_key( 'TH', 'กข 123' ) );
	}

	/** Verify optional VIN and bounded year validation. */
	public function test_vin_and_year_policy_reject_unsafe_or_out_of_range_input(): void {
		$this->assertSame( 'AB-123', VehicleIdentifier::normalize_vin( ' ab-123 ' ) );
		$this->assertSame( '', VehicleIdentifier::normalize_vin( '' ) );
		$this->assertNull( VehicleIdentifier::normalize_plate( '<script>' ) );
		$this->assertNull( VehicleIdentifier::validate_year( 1885 ) );
		$this->assertSame( 1886, VehicleIdentifier::validate_year( '1886' ) );
	}
}
