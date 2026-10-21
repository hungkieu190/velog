<?php
/**
 * Vehicle core and HTTP parsing boundary regressions.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Admin\VehiclePage;
use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Vehicle\OdometerPolicy;
use MF\VeLog\Common\Vehicle\VehicleQuery;
use MF\VeLog\Common\Vehicle\VehicleService;
use PHPUnit\Framework\TestCase;

/** Exercises security-relevant input shapes and exact baseline identity. */
final class VehicleCoreTest extends TestCase {

	/** Set independent WordPress locale/timezone functions. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'Asia/Bangkok' ) );
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_date' )->justReturn( '2026' );
		RecordSchema::reset_for_testing();
		RecordSchema::register_core_schemas();
	}

	/** Restore shared schema and WordPress stubs. */
	protected function tearDown(): void {
		RecordSchema::reset_for_testing();
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Unknown is not zero, and known tuples cannot forge canonical distance. */
	public function test_vehicle_baseline_identity_and_invalid_tuples(): void {
		$this->assertSame( array(), OdometerPolicy::validate_baseline( array() ) );
		$zero = array(
			'original_value' => '0',
			'unit'           => 'km',
			'canonical_mm'   => '0',
			'date'           => '2020-01-01',
		);
		$this->assertSame( $zero, OdometerPolicy::validate_baseline( $zero ) );
		$mile = array(
			'original_value' => '1',
			'unit'           => 'mi',
			'canonical_mm'   => '1609344',
			'date'           => '2020-01-01',
		);
		$this->assertSame( $mile, OdometerPolicy::validate_baseline( $mile ) );
		$this->assertNull( OdometerPolicy::validate_baseline( array_merge( $mile, array( 'canonical_mm' => '1' ) ) ) );
		$this->assertNull( OdometerPolicy::validate_baseline( array_merge( $mile, array( 'date' => '9999-01-01' ) ) ) );
		$this->assertNull( OdometerPolicy::validate_baseline( array_merge( $mile, array( 'date' => '2020-02-30' ) ) ) );
		$this->assertNull( OdometerPolicy::validate_baseline( array_merge( $mile, array( 'extra' => 'x' ) ) ) );
		$this->assertNull( OdometerPolicy::validate_baseline( array_merge( $mile, array( 'original_value' => 1 ) ) ) );
	}

	/** Corrections compare canonical values across original units. */
	public function test_vehicle_decrease_and_clear_require_a_reason(): void {
		$before = array( 'canonical_mm' => '1609344' );
		$this->assertTrue( OdometerPolicy::requires_reason( $before, array() ) );
		$this->assertTrue( OdometerPolicy::requires_reason( $before, array( 'canonical_mm' => '1000000' ) ) );
		$this->assertFalse( OdometerPolicy::requires_reason( $before, array( 'canonical_mm' => '1609344' ) ) );
		$this->assertFalse( OdometerPolicy::requires_reason( array(), array( 'canonical_mm' => '0' ) ) );
	}

	/** Query input never coerces arrays, page strings or unsupported contacts. */
	public function test_vehicle_query_rejects_forged_shapes(): void {
		foreach ( array(
			array( 'page' => '1' ),
			array( 'page' => 0 ),
			array( 'term' => array() ),
			array( 'phone' => '123' ),
			array( 'term' => "bad\xFF" ),
			array( 'state' => 'deleted' ),
		) as $input ) {
			$this->assertInstanceOf( \WP_Error::class, VehicleQuery::normalize_args( $input ) );
		}
		$this->assertSame(
			array(
				'term'  => 'กข',
				'state' => 'active',
				'page'  => 1,
			),
			VehicleQuery::normalize_args( array( 'term' => ' กข ' ) )
		);
	}

	/** Exact HTTP integer validation rejects leading zeros, overflow and signs. */
	public function test_vehicle_http_integer_shapes(): void {
		foreach ( array( '0', '01', '-1', '+1', ' 1', '1e2', '1x', array(), 1, str_repeat( '9', 20 ) ) as $input ) {
			$this->assertSame( 0, VehiclePage::positive_int( $input ) );
		}
		$this->assertSame( 42, VehiclePage::positive_int( '42' ) );
	}

	/** Localized form parsing is exact and rejects malformed optional relations. */
	public function test_vehicle_form_converts_localized_readings_without_float(): void {
		$input  = array(
			'vin'                     => 'FORM-TEST',
			'current_customer_id'     => '',
			'baseline_value'          => '1,5',
			'baseline_unit'           => 'km',
			'baseline_date'           => '2020-01-01',
			'preferred_odometer_unit' => 'mi',
		);
		$result = VehiclePage::parse_fields( $input, ',', 'km' );
		$this->assertIsArray( $result );
		$this->assertSame( '1500000', $result['baseline']['canonical_mm'] );
		$this->assertSame( '1.5', $result['baseline']['original_value'] );
		$this->assertInstanceOf(
			\WP_Error::class,
			VehiclePage::parse_fields( array_merge( $input, array( 'current_customer_id' => '1x' ) ), ',', 'km' )
		);
		$this->assertInstanceOf(
			\WP_Error::class,
			VehiclePage::parse_fields( array_merge( $input, array( 'baseline_value' => '' ) ), ',', 'km' )
		);
		$this->assertInstanceOf(
			\WP_Error::class,
			VehiclePage::parse_fields( array_merge( $input, array( 'reason' => array() ) ), ',', 'km' )
		);
	}

	/** Forged legacy unit fields cannot override trusted shop configuration. */
	public function test_system_unit_overrides_vehicle_preferences(): void {
		$input  = array(
			'baseline_value'          => '1',
			'baseline_date'           => '2020-01-01',
			'preferred_odometer_unit' => 'km',
			'baseline_unit'           => 'km',
		);
		$result = VehiclePage::parse_fields( $input, '.', 'mi' );
		$this->assertIsArray( $result );
		$this->assertSame( '1609344', $result['baseline']['canonical_mm'] );
		$this->assertSame( 'mi', $result['baseline']['unit'] );
		$this->assertArrayNotHasKey( 'preferred_odometer_unit', $result );
		$this->assertInstanceOf( \WP_Error::class, VehiclePage::parse_fields( $input, '.', '' ) );
	}

	/** Saving rounded conversions never changes the original physical distance. */
	public function test_unchanged_converted_reading_preserves_exact_baseline(): void {
		foreach ( array(
			array( '100', 'km', 'mi' ),
			array( '1', 'mi', 'km' ),
			array( '0', 'km', 'mi' ),
			array( '999999999.999', 'mi', 'km' ),
		) as [ $original, $source, $target ] ) {
			$before  = array_merge(
				\MF\VeLog\Common\Regional\Distance::parse( $original, $source, '.' ),
				array( 'date' => '2020-01-01' )
			);
			$display = OdometerPolicy::display_value( $before, $target );
			$input   = array(
				'baseline_value' => str_replace( '.', ',', $display ),
				'baseline_date'  => $before['date'],
			);
			$result  = VehiclePage::parse_fields( $input, ',', $target, $before );
			$this->assertIsArray( $result );
			$this->assertSame( $before, $result['baseline'] );
			$input['baseline_date'] = '2020-01-02';
			$changed_date           = VehiclePage::parse_fields( $input, ',', $target, $before );
			$this->assertIsArray( $changed_date );
			$this->assertSame( array_merge( $before, array( 'date' => '2020-01-02' ) ), $changed_date['baseline'] );
		}
	}

	/** Actual edits are parsed in system units, distinct from the retained tuple. */
	public function test_changed_reading_uses_system_unit_and_reason_comparison(): void {
		$before = array(
			'original_value' => '100',
			'unit'           => 'km',
			'canonical_mm'   => '100000000',
			'date'           => '2020-01-01',
		);
		$this->assertSame( '62.137', OdometerPolicy::display_value( $before, 'mi' ) );
		$result = VehiclePage::parse_fields(
			array(
				'baseline_value' => '60',
				'baseline_date'  => '2020-01-01',
			),
			'.',
			'mi',
			$before
		);
		$this->assertIsArray( $result );
		$this->assertSame( '96560640', $result['baseline']['canonical_mm'] );
		$this->assertSame( 'mi', $result['baseline']['unit'] );
		$this->assertTrue( OdometerPolicy::requires_reason( $before, $result['baseline'] ) );
		$this->assertSame( '100.000 km / 2020-01-01', VehiclePage::baseline_label( $before, 'km' ) );
		$this->assertStringNotContainsString( '100 km', VehiclePage::baseline_label( $before, '' ) );
	}

	/** Minimal old records stay valid without automatic field insertion. */
	public function test_vehicle_schema_preserves_legacy_and_enforces_new_fields(): void {
		$fields = RecordSchema::validate_fields( 'mf_velog_vehicle', array( 'vin' => 'LEGACY' ) );
		$this->assertSame( array( 'vin' => 'LEGACY' ), $fields );
		$this->assertTrue( RecordSchema::validate_record( 'mf_velog_vehicle', $fields ) );
		$this->assertInstanceOf(
			\WP_Error::class,
			RecordSchema::validate_fields( 'mf_velog_vehicle', array( 'baseline' => false ) )
		);
		$this->assertSame( 0, \MF\VeLog\Common\Customer\CustomerRelations::validate_customer_id( 0 ) );
		$this->assertNull( \MF\VeLog\Common\Customer\CustomerRelations::validate_customer_id( -1 ) );
		$this->assertNull( VehicleService::validate_text( '<b>make</b>' ) );
		$this->assertNull( VehicleService::validate_color( str_repeat( 'a', 65 ) ) );
	}
}
