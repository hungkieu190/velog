<?php
/**
 * Vehicle identifier policy tests.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Vehicle\VehicleIdentifier;
use PHPUnit\Framework\TestCase;

/** @covers \MF\VeLog\Common\Vehicle\VehicleIdentifier */
final class VehicleIdentifierTest extends TestCase {
	public function test_plate_policy_preserves_unicode_and_builds_unambiguous_key(): void {
		$this->assertSame( 'TH', VehicleIdentifier::normalize_jurisdiction( ' th ' ) );
		$this->assertSame( 'กข 123', VehicleIdentifier::normalize_plate( " กข\t123 " ) );
		$this->assertSame( '2:TH10:กข 123', VehicleIdentifier::plate_key( 'TH', 'กข 123' ) );
	}

	public function test_vin_and_year_policy_reject_unsafe_or_out_of_range_input(): void {
		$this->assertSame( 'AB-123', VehicleIdentifier::normalize_vin( ' ab-123 ' ) );
		$this->assertSame( '', VehicleIdentifier::normalize_vin( '' ) );
		$this->assertNull( VehicleIdentifier::normalize_plate( '<script>' ) );
		$this->assertNull( VehicleIdentifier::validate_year( 1885 ) );
		$this->assertSame( 1886, VehicleIdentifier::validate_year( '1886' ) );
	}
}
