<?php
/**
 * Service validation, privacy and chronology regressions.
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

// phpcs:disable Generic.Files.LineLength.TooLong -- Exact tuple/role regression assertions stay together.

use Brain\Monkey;
use Brain\Monkey\Functions;
use MF\VeLog\Admin\ServicePage;
use MF\VeLog\Common\AccessPolicy;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Service\ServiceChronology;
use MF\VeLog\Common\Service\ServiceFields;
use MF\VeLog\Common\Service\ServiceService;
use MF\VeLog\Common\Storage\RecordSchema;
use PHPUnit\Framework\TestCase;

/** Exact stored values and source-independent role checks. */
final class ServiceWorkflowTest extends TestCase {

	/** Install independent site-local clock/translation stubs. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_timezone' )->justReturn( new \DateTimeZone( 'Asia/Bangkok' ) );
		Functions\when( '__' )->returnArg();
	}

	/** Restore shared WordPress doubles. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Names/text/date/IDs reject forged types and markup. */
	public function test_canonical_service_fields(): void {
		$this->assertSame( "Unicode กข\nNotes", ServiceFields::text( "Unicode กข\nNotes" ) );
		foreach ( array( '<b>test</b>', "bad\0", "bad\xFF", array(), str_repeat( 'a', 10001 ) ) as $bad ) {
			$this->assertNull( ServiceFields::text( $bad ) );
		}
		$this->assertSame( '', ServiceFields::date( '' ) );
		$this->assertSame( '2020-02-29', ServiceFields::date( '2020-02-29' ) );
		$this->assertNull( ServiceFields::date( '2020-02-30' ) );
		$this->assertNull( ServiceFields::date( '9999-01-01' ) );
		$this->assertSame( 0, ServiceFields::id( 0 ) );
		$this->assertNull( ServiceFields::id( '1' ) );
		$this->assertNull( ServiceFields::id( -1 ) );
	}

	/** Stored fields are revalidated before reads and after partial write merges. */
	public function test_complete_service_fields_fail_closed(): void {
		RecordSchema::reset_for_testing();
		RecordSchema::register_core_schemas();
		try {
			$valid = array(
				'vehicle_id'      => 42,
				'vehicle_visible' => true,
			);
			$this->assertTrue( ServiceService::validate_record( $valid ) );
			$this->assertFalse( ServiceService::validate_record( array( 'vehicle_id' => 42 ) ) );
			$this->assertFalse( ServiceService::validate_record( array_merge( $valid, array( 'vehicle_id' => '42' ) ) ) );
			$this->assertFalse( ServiceService::validate_record( array_merge( $valid, array( 'secret' => 'injected' ) ) ) );
			$reading = array_merge( Distance::parse( '1', 'km', '.' ), array( 'canonical_mm' => '1' ) );
			$this->assertFalse( ServiceService::validate_record( array_merge( $valid, array( 'reading' => $reading ) ) ) );
		} finally {
			RecordSchema::reset_for_testing();
		}
	}

	/** Exact cost tuples preserve zero-, two- and three-decimal currencies. */
	public function test_cost_tuples_are_closed_and_type_exact(): void {
		$this->assertSame( array(), ServiceFields::cost( array() ) );
		foreach ( array( array( 'JPY', '100' ), array( 'USD', '12.30' ), array( 'KWD', '1.234' ), array( 'USD', '0' ) ) as [ $code, $value ] ) {
			$cost = Money::parse( $value, $code, '.' );
			$this->assertSame( $cost, ServiceFields::cost( $cost ) );
			$this->assertNull( ServiceFields::cost( array_merge( $cost, array( 'minor_units' => '999999' ) ) ) );
			$this->assertNull( ServiceFields::cost( array_merge( $cost, array( 'scale' => (string) $cost['scale'] ) ) ) );
			$this->assertNull( ServiceFields::cost( array_merge( $cost, array( 'extra' => 'x' ) ) ) );
		}
	}

	/** Known zero remains distinct from unknown and cannot forge millimetres. */
	public function test_reading_and_snapshot_tuples(): void {
		$this->assertSame( array(), ServiceFields::reading( array() ) );
		$zero = Distance::parse( '0', 'mi', '.' );
		$this->assertSame( $zero, ServiceFields::reading( $zero ) );
		$this->assertNull( ServiceFields::reading( array_merge( $zero, array( 'canonical_mm' => '1' ) ) ) );
		$this->assertNull( ServiceFields::reading( array_merge( $zero, array( 'date' => '2020-01-01' ) ) ) );
		$snapshot = array(
			'id'   => 42,
			'name' => 'Historical customer',
		);
		$this->assertSame( $snapshot, ServiceFields::snapshot( $snapshot ) );
		$this->assertNull( ServiceFields::snapshot( array_merge( $snapshot, array( 'email' => 'private@example.com' ) ) ) );
		$this->assertNull(
			ServiceFields::snapshot(
				array(
					'id'   => 0,
					'name' => 'X',
				)
			)
		);
	}

	/** Private draft reads and edits are restricted even with common read capability. */
	public function test_draft_privacy_and_manager_ownership_exception(): void {
		$tech     = $this->createMock( \WP_User::class );
		$tech->ID = 5;
		$tech->method( 'has_cap' )->willReturnCallback( static fn ( string $cap ): bool => 'mf_velog_correct_services' !== $cap );
		$manager     = $this->createMock( \WP_User::class );
		$manager->ID = 9;
		$manager->method( 'has_cap' )->willReturn( true );
		$context = array(
			'type'            => 'mf_velog_service',
			'state'           => 'draft',
			'author'          => 7,
			'vehicle_visible' => true,
		);
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $context ) );
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_edit_own_service_drafts', $context ) );
		$this->assertTrue( AccessPolicy::authorize( $manager, 'mf_velog_read_records', $context ) );
		$this->assertTrue( AccessPolicy::authorize( $manager, 'mf_velog_edit_own_service_drafts', $context ) );
		$context['state'] = 'finalized';
		$this->assertTrue( AccessPolicy::authorize( $tech, 'mf_velog_read_records', $context ) );
		$this->assertFalse( AccessPolicy::authorize( $tech, 'mf_velog_correct_services', $context ) );
	}

	/** Equal-day finalized records use stable descending IDs, baseline last. */
	public function test_current_reading_order_is_deterministic(): void {
		$rows = array(
			array(
				'service_id' => 0,
				'date'       => '2020-01-01',
			),
			array(
				'service_id' => 2,
				'date'       => '2020-01-02',
			),
			array(
				'service_id' => 7,
				'date'       => '2020-01-02',
			),
			array(
				'service_id' => 8,
				'date'       => '2020-01-01',
			),
		);
		$this->assertSame( array( 7, 2, 8, 0 ), array_column( ServiceChronology::ordered( $rows ), 'service_id' ) );
	}

	/** Global distance display round trips retain exact reading and historical currency. */
	public function test_form_keeps_original_reading_and_currency(): void {
		$before = array(
			'service_date' => '2020-01-01',
			'reading'      => Distance::parse( '1', 'mi', '.' ),
			'cost'         => Money::parse( '10', 'JPY', '.' ),
		);
		$input  = array(
			'vehicle_id'      => '42',
			'service_date'    => '2020-01-02',
			'reading'         => '1,609',
			'service_type_id' => '3',
			'notes'           => 'Edit',
			'cost'            => '20',
		);
		$result = ServicePage::parse_fields(
			$input,
			array(
				'distance_unit' => 'km',
				'currency_code' => 'USD',
			),
			$before,
			','
		);
		$this->assertIsArray( $result );
		$this->assertSame( $before['reading'], $result['reading'] );
		$this->assertSame( 'JPY', $result['cost']['currency'] );
		$this->assertSame( '20', $result['cost']['minor_units'] );
		$this->assertInstanceOf( \WP_Error::class, ServicePage::parse_fields( array_merge( $input, array( 'vehicle_id' => '01' ) ), array( 'distance_unit' => 'km' ), $before, ',' ) );
	}
}
