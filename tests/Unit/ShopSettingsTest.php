<?php
/**
 * Shop Settings Tests
 *
 * @package MF\VeLog\Tests\Unit
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Regional\ShopSettings;
use MF\VeLog\Common\Regional\CurrencyCatalog;
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
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb             = \Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';

		// Mock get_var to return null (missing).
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared_query' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );

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

		// Direct callers must not coerce a non-string region.
		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
				'region'        => 123,
			),
			0
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_region', $result->get_error_code() );

		// Valid configuration first insert success.
		$wpdb->shouldReceive( 'query' )->andReturn( 1 )->byDefault();
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

		// Test Errno 1062 Duplicate Race (F-002).
		$mysqli_mock        = new \stdClass();
		$mysqli_mock->errno = 1062;

		$wpdb_err             = \Mockery::mock();
		$wpdb_err->options    = 'wp_options';
		$wpdb_err->last_error = '';
		$wpdb_err->dbh        = clone $mysqli_mock;

		$wpdb_err->shouldReceive( 'prepare' )->andReturn( 'prepared_query' );
		$wpdb_err->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb_err->shouldReceive( 'query' )->andReturn( false );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb = $wpdb_err;

		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
			),
			0
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'stale_version', $result->get_error_code() );

		// Test Non-1062 DB Error (F-002).
		$mysqli_mock->errno = 1146;
		$wpdb_err->dbh      = clone $mysqli_mock;

		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
			),
			0
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'db_error', $result->get_error_code() );
	}

	/**
	 * Stored settings reject keys outside the closed envelope.
	 */
	public function test_stored_settings_reject_extra_keys(): void {
		global $wpdb;

		$stored = array(
			'schema_version'  => ShopSettings::SCHEMA_VERSION,
			'record_version'  => 1,
			'configured'      => true,
			'distance_unit'   => 'km',
			'currency_code'   => 'JPY',
			'currency_scale'  => 0,
			'region'          => '',
			'catalog_version' => CurrencyCatalog::VERSION,
			'forged_key'      => 'forged',
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb             = \Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'select_settings' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact WordPress option bytes fixture.
		$stored_bytes = serialize( $stored );
		$wpdb->shouldReceive( 'get_var' )->with( 'select_settings' )->once()->andReturn( $stored_bytes );

		$result = ShopSettings::get_settings();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'corrupt_settings', $result->get_error_code() );
	}

	/**
	 * Exact legacy settings are migrated with an exact-byte CAS.
	 */
	public function test_legacy_settings_are_migrated_atomically(): void {
		global $wpdb;

		$legacy = array(
			'schema_version' => ShopSettings::SCHEMA_VERSION,
			'record_version' => 1,
			'configured'     => true,
			'distance_unit'  => 'km',
			'currency_code'  => 'USD',
			'currency_scale' => 2,
			'region'         => '',
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact legacy option bytes fixture.
		$raw      = serialize( $legacy );
		$observed = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb             = \Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing(
				static function ( string $query, ...$arguments ) use ( &$observed ): string {
					if ( str_contains( $query, 'SELECT option_value' ) ) {
						return 'select_settings';
					}
					$observed = array(
						'query'     => $query,
						'arguments' => $arguments,
					);
					return 'migrate_settings';
				}
			);
		$wpdb->shouldReceive( 'get_var' )->with( 'select_settings' )->once()->andReturn( $raw );
		$wpdb->shouldReceive( 'query' )->with( 'migrate_settings' )->once()->andReturn( 1 );

		Functions\when( 'maybe_serialize' )->alias(
			static function ( mixed $value ): string {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Emulates maybe_serialize().
				return serialize( $value );
			}
		);
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$result = ShopSettings::get_settings();

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['record_version'] );
		$this->assertSame( CurrencyCatalog::VERSION, $result['catalog_version'] );
		$this->assertStringContainsString( 'BINARY option_value = BINARY %s', $observed['query'] );
		$this->assertSame( ShopSettings::OPTION_NAME, $observed['arguments'][1] );
		$this->assertSame( $raw, $observed['arguments'][2] );
	}

	/**
	 * A legacy-shaped row still rejects values that do not match the catalog.
	 */
	public function test_invalid_legacy_settings_are_not_migrated(): void {
		global $wpdb;

		$legacy = array(
			'schema_version' => ShopSettings::SCHEMA_VERSION,
			'record_version' => 1,
			'configured'     => true,
			'distance_unit'  => 'km',
			'currency_code'  => 'USD',
			'currency_scale' => 3,
			'region'         => '',
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb             = \Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'select_settings' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact invalid legacy fixture.
		$wpdb->shouldReceive( 'get_var' )->with( 'select_settings' )->once()->andReturn( serialize( $legacy ) );
		$wpdb->shouldNotReceive( 'query' );

		Functions\when( 'maybe_serialize' )->alias(
			static function ( mixed $value ): string {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Emulates maybe_serialize().
				return serialize( $value );
			}
		);

		$result = ShopSettings::get_settings();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'corrupt_settings', $result->get_error_code() );
	}

	/**
	 * Update CAS uses the same raw bytes that supplied the validated version.
	 */
	public function test_update_cas_uses_one_exact_raw_snapshot(): void {
		global $wpdb;

		$stored = array(
			'schema_version'  => ShopSettings::SCHEMA_VERSION,
			'record_version'  => 4,
			'configured'      => true,
			'distance_unit'   => 'mi',
			'currency_code'   => 'USD',
			'currency_scale'  => 2,
			'region'          => '',
			'catalog_version' => CurrencyCatalog::VERSION,
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact CAS byte oracle.
		$raw      = serialize( $stored );
		$observed = array();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb             = \Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'prepare' )
			->twice()
			->andReturnUsing(
				static function ( string $query, ...$arguments ) use ( &$observed ): string {
					if ( str_contains( $query, 'SELECT option_value' ) ) {
						return 'select_settings';
					}
					$observed = array(
						'query'     => $query,
						'arguments' => $arguments,
					);
					return 'update_settings';
				}
			);
		$wpdb->shouldReceive( 'get_var' )->with( 'select_settings' )->once()->andReturn( $raw );
		$wpdb->shouldReceive( 'query' )->with( 'update_settings' )->once()->andReturn( 1 );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'maybe_serialize' )->alias(
			static function ( mixed $value ): string {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Emulates maybe_serialize().
				return serialize( $value );
			}
		);
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );

		$result = ShopSettings::save_settings(
			array(
				'distance_unit' => 'km',
				'currency_code' => 'JPY',
			),
			4
		);

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'BINARY option_value = BINARY %s', $observed['query'] );
		$this->assertSame( ShopSettings::OPTION_NAME, $observed['arguments'][1] );
		$this->assertSame( $raw, $observed['arguments'][2] );
	}
}
