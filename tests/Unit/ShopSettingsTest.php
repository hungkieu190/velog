<?php
/**
 * Shop Settings Tests
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Regional\ShopSettings;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_Error;

/**
 * Tests for ShopSettings.
 */
class ShopSettingsTest extends TestCase {

	/**
	 * Setup method.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	/**
	 * Teardown method.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test explicit setup logic.
	 */
	public function test_explicit_setup() {
		// Mock get_option to return false.
		Functions\when( 'get_option' )->justReturn( false );

		// Before configuration.
		$defaults = ShopSettings::get_settings();
		$this->assertFalse( $defaults['configured'] );
		$this->assertEquals( '', $defaults['distance_unit'] );

		$configured = ShopSettings::require_configured();
		$this->assertInstanceOf( WP_Error::class, $configured );
		$this->assertEquals( 'not_configured', $configured->get_error_code() );

		// Simulate save as unauthorized.
		Functions\when( 'current_user_can' )->justReturn( false );
		$result = ShopSettings::save_settings( array(), 0 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'unauthorized', $result->get_error_code() );

		// Simulate save as authorized.
		Functions\when( 'current_user_can' )->justReturn( true );

		// Invalid currency.
		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'INVALID',
			),
			0
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_currency', $result->get_error_code() );

		// Valid configuration.
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb          = \Mockery::mock();
		$wpdb->options = 'wp_options';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared_query' );
		$wpdb->shouldReceive( 'query' )->andReturn( 1 );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'maybe_serialize' )->returnArg();

		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
			),
			0
		);
		$this->assertTrue( $result );

		// Valid second configuration (stubs for update).
		Functions\when( 'get_option' )->justReturn(
			array(
				'schema_version' => '1.0.0',
				'record_version' => 1,
				'configured'     => true,
				'distance_unit'  => 'km',
				'currency_code'  => 'JPY',
				'currency_scale' => 0,
				'region'         => '',
			)
		);
		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'mi',
				'currency_code' => 'USD',
			),
			1
		);
		$this->assertTrue( $result );

		// Stale save (expected version 0 instead of 1).
		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
			),
			0
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'stale_version', $result->get_error_code() );
	}
}
