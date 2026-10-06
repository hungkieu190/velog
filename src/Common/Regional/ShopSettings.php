<?php
/**
 * Regional Settings Service
 *
 * @package MF\VeLog\Common\Regional
 */

namespace MF\VeLog\Common\Regional;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates, reads, and saves versioned explicit setup in velog_settings.
 */
class ShopSettings {

	public const OPTION_NAME    = 'velog_settings';
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * Return the exact unconfigured settings shape.
	 *
	 * @return array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * }
	 */
	private static function defaults(): array {
		return array(
			'schema_version'  => self::SCHEMA_VERSION,
			'record_version'  => 0,
			'configured'      => false,
			'distance_unit'   => '',
			'currency_code'   => '',
			'currency_scale'  => 0,
			'region'          => '',
			'catalog_version' => CurrencyCatalog::VERSION,
		);
	}

	/**
	 * Read exact raw option bytes.
	 *
	 * @return string|null|\WP_Error
	 */
	private static function read_raw_option(): string|null|\WP_Error {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_option = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				self::OPTION_NAME
			)
		);

		if ( null === $raw_option && '' !== $wpdb->last_error ) {
			return new \WP_Error( 'db_error', __( 'Database error reading settings.', 'velog' ) );
		}

		return $raw_option; // Will be null if missing.
	}

	/**
	 * Decode and strictly validate one raw option snapshot.
	 *
	 * @param string|null $raw Exact option_value bytes, or null when absent.
	 * @return array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * }|\WP_Error
	 */
	private static function decode_settings( ?string $raw ): array|\WP_Error {
		if ( null === $raw ) {
			return self::defaults();
		}

		// Database content is untrusted. Object hydration is prohibited.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
		$settings = @unserialize( $raw, array( 'allowed_classes' => false ) );
		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'corrupt_settings', __( 'Settings are malformed.', 'velog' ) );
		}

		$expected_keys = array_keys( self::defaults() );
		$actual_keys   = array_keys( $settings );
		sort( $expected_keys );
		sort( $actual_keys );
		if ( $expected_keys !== $actual_keys ) {
			return new \WP_Error( 'corrupt_settings', __( 'Settings keys are invalid.', 'velog' ) );
		}

		if (
			self::SCHEMA_VERSION !== $settings['schema_version'] ||
			! is_int( $settings['record_version'] ) ||
			! is_bool( $settings['configured'] ) ||
			! is_string( $settings['distance_unit'] ) ||
			! is_string( $settings['currency_code'] ) ||
			! is_int( $settings['currency_scale'] ) ||
			! is_string( $settings['region'] ) ||
			! is_string( $settings['catalog_version'] )
		) {
			return new \WP_Error( 'corrupt_settings', __( 'Settings types are invalid.', 'velog' ) );
		}

		if ( $settings['configured'] ) {
			if ( $settings['record_version'] < 1 ) {
				return new \WP_Error(
					'corrupt_settings',
					__( 'Invalid record version for configured settings.', 'velog' )
				);
			}
			if ( 'km' !== $settings['distance_unit'] && 'mi' !== $settings['distance_unit'] ) {
				return new \WP_Error(
					'corrupt_settings',
					__( 'Invalid distance unit in stored settings.', 'velog' )
				);
			}
			try {
				$currency_data = CurrencyCatalog::get( $settings['currency_code'] );
				if ( $currency_data['scale'] !== $settings['currency_scale'] ) {
					return new \WP_Error(
						'corrupt_settings',
						__( 'Currency scale mismatch in stored settings.', 'velog' )
					);
				}
			} catch ( \InvalidArgumentException $exception ) {
				unset( $exception );
				return new \WP_Error( 'corrupt_settings', __( 'Unknown currency in stored settings.', 'velog' ) );
			}
		} elseif (
			0 !== $settings['record_version'] ||
			'' !== $settings['distance_unit'] ||
			'' !== $settings['currency_code'] ||
			0 !== $settings['currency_scale'] ||
			'' !== $settings['region']
		) {
			return new \WP_Error(
				'corrupt_settings',
				__( 'Unconfigured settings must have exact default values.', 'velog' )
			);
		}

		if ( CurrencyCatalog::VERSION !== $settings['catalog_version'] ) {
			return new \WP_Error( 'corrupt_settings', __( 'Catalog version mismatch.', 'velog' ) );
		}

		return array(
			'schema_version'  => $settings['schema_version'],
			'record_version'  => $settings['record_version'],
			'configured'      => $settings['configured'],
			'distance_unit'   => $settings['distance_unit'],
			'currency_code'   => $settings['currency_code'],
			'currency_scale'  => $settings['currency_scale'],
			'region'          => $settings['region'],
			'catalog_version' => $settings['catalog_version'],
		);
	}

	/**
	 * Decode the exact settings shape written before catalog_version was added.
	 *
	 * No unknown key, invalid type, unsupported value, or catalog mismatch is
	 * accepted by this compatibility path.
	 *
	 * @param string $raw Exact legacy option bytes.
	 * @return array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * }|\WP_Error
	 */
	private static function decode_legacy_settings( string $raw ): array|\WP_Error {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged
		$settings = @unserialize( $raw, array( 'allowed_classes' => false ) );
		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'corrupt_settings', __( 'Settings are malformed.', 'velog' ) );
		}

		$legacy_keys = array(
			'schema_version',
			'record_version',
			'configured',
			'distance_unit',
			'currency_code',
			'currency_scale',
			'region',
		);
		$actual_keys = array_keys( $settings );
		sort( $legacy_keys );
		sort( $actual_keys );
		if ( $legacy_keys !== $actual_keys ) {
			return new \WP_Error( 'corrupt_settings', __( 'Settings keys are invalid.', 'velog' ) );
		}

		$settings['catalog_version'] = CurrencyCatalog::VERSION;
		if ( true === $settings['configured'] && is_int( $settings['record_version'] ) ) {
			++$settings['record_version'];
		}

		return self::decode_settings( maybe_serialize( $settings ) );
	}

	/**
	 * Persist a validated legacy envelope with an exact-byte CAS.
	 *
	 * @param string               $raw_prior_option Exact legacy option bytes.
	 * @param array<string, mixed> $settings         Validated upgraded settings.
	 * @phpstan-param array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * } $settings
	 * @return array<string, mixed>|\WP_Error
	 * @phpstan-return array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * }|\WP_Error
	 */
	private static function migrate_legacy_settings( string $raw_prior_option, array $settings ): array|\WP_Error {
		global $wpdb;

		$new_serialized = maybe_serialize( $settings );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options}
				 SET option_value = %s
				 WHERE option_name = %s
				 AND BINARY option_value = BINARY %s",
				$new_serialized,
				self::OPTION_NAME,
				$raw_prior_option
			)
		);

		if ( false === $affected ) {
			return new \WP_Error( 'db_error', __( 'Database error migrating settings.', 'velog' ) );
		}
		if ( 1 !== $affected ) {
			return new \WP_Error(
				'stale_version',
				__( 'Settings changed while the legacy format was being migrated. Please refresh.', 'velog' )
			);
		}

		self::clear_cache();
		return $settings;
	}

	/**
	 * Read current settings.
	 *
	 * @return array{
	 *     schema_version:string,
	 *     record_version:int,
	 *     configured:bool,
	 *     distance_unit:string,
	 *     currency_code:string,
	 *     currency_scale:int,
	 *     region:string,
	 *     catalog_version:string
	 * }|\WP_Error
	 */
	public static function get_settings(): array|\WP_Error {
		$raw = self::read_raw_option();
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$settings = self::decode_settings( $raw );
		if ( ! is_wp_error( $settings ) || null === $raw || 'corrupt_settings' !== $settings->get_error_code() ) {
			return $settings;
		}

		$legacy_settings = self::decode_legacy_settings( $raw );
		if ( is_wp_error( $legacy_settings ) ) {
			return $settings;
		}

		return self::migrate_legacy_settings( $raw, $legacy_settings );
	}

	/**
	 * Get configured defaults.
	 *
	 * @return array{distance_unit:string, currency_code:string, currency_scale:int}|WP_Error
	 */
	public static function require_configured(): array|\WP_Error {
		$settings = self::get_settings();
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		if (
			empty( $settings['configured'] ) ||
			empty( $settings['distance_unit'] ) ||
			empty( $settings['currency_code'] )
		) {
			return new \WP_Error(
				'not_configured',
				__( 'Regional settings are not configured or invalid.', 'velog' )
			);
		}

		return array(
			'distance_unit'  => $settings['distance_unit'],
			'currency_code'  => $settings['currency_code'],
			'currency_scale' => $settings['currency_scale'],
		);
	}

	/**
	 * Save settings with atomic version check.
	 *
	 * @param array<string, mixed> $input Submitted data.
	 * @param int                  $expected_version Expected record version.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function save_settings( array $input, int $expected_version ) {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			return new \WP_Error( 'unauthorized', __( 'Unauthorized to save settings.', 'velog' ) );
		}

		if ( $expected_version < 0 ) {
			return new \WP_Error( 'invalid_version', __( 'Invalid settings version.', 'velog' ) );
		}

		$raw_prior_option = self::read_raw_option();
		if ( is_wp_error( $raw_prior_option ) ) {
			return $raw_prior_option;
		}

		$current = self::decode_settings( $raw_prior_option );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		if ( $current['record_version'] !== $expected_version ) {
			return new \WP_Error(
				'stale_version',
				__( 'Settings have been updated by another user. Please refresh.', 'velog' )
			);
		}

		if ( defined( 'MF_VELOG_TEST_CONCURRENT' ) && MF_VELOG_TEST_CONCURRENT === true ) {
			do_action( 'mf_velog_test_concurrent_barrier' );
		}

		$distance = isset( $input['distance_unit'] ) && is_string( $input['distance_unit'] )
			? $input['distance_unit']
			: '';
		if ( 'km' !== $distance && 'mi' !== $distance ) {
			return new \WP_Error( 'invalid_distance', __( 'Invalid distance unit.', 'velog' ) );
		}

		$currency_code = isset( $input['currency_code'] ) && is_string( $input['currency_code'] )
			? $input['currency_code']
			: '';
		try {
			$currency_data = CurrencyCatalog::get( $currency_code );
		} catch ( \InvalidArgumentException $exception ) {
			unset( $exception );
			return new \WP_Error( 'invalid_currency', __( 'Invalid currency code.', 'velog' ) );
		}
		if ( ! isset( $currency_data['scale'] ) || ! is_int( $currency_data['scale'] ) ) {
			return new \WP_Error( 'invalid_catalog', __( 'Currency catalog entry is invalid.', 'velog' ) );
		}
		$currency_scale = $currency_data['scale'];

		$region = '';
		if ( isset( $input['region'] ) ) {
			if ( ! is_string( $input['region'] ) ) {
				return new \WP_Error( 'invalid_region', __( 'Region must be a string.', 'velog' ) );
			}
			$region = sanitize_text_field( $input['region'] );
		}

		$new_settings = array(
			'schema_version'  => self::SCHEMA_VERSION,
			'record_version'  => $expected_version + 1,
			'configured'      => true,
			'distance_unit'   => $distance,
			'currency_code'   => $currency_code,
			'currency_scale'  => $currency_scale,
			'region'          => $region,
			'catalog_version' => CurrencyCatalog::VERSION,
		);

		global $wpdb;
		$new_serialized = maybe_serialize( $new_settings );

		if ( 0 === $expected_version ) {
			// Ensure first insert is unique (F-001).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$affected = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					self::OPTION_NAME,
					$new_serialized
				)
			);

			if ( false === $affected ) {
				if ( 1062 === self::last_database_errno() ) {
					return new \WP_Error(
						'stale_version',
						__( 'Settings have been updated by another user.', 'velog' )
					);
				}
				return new \WP_Error( 'db_error', __( 'Database error.', 'velog' ) );
			}
			if ( 1 !== $affected ) {
				return new \WP_Error( 'db_error', __( 'Failed to create settings.', 'velog' ) );
			}

			self::clear_cache();
		} else {
			// Atomic conditional update tied to exact prior serialized value (F-001).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$affected = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options}
					 SET option_value = %s
					 WHERE option_name = %s
					 AND BINARY option_value = BINARY %s",
					$new_serialized,
					self::OPTION_NAME,
					$raw_prior_option
				)
			);

			if ( false === $affected ) {
				return new \WP_Error( 'db_error', __( 'Database error.', 'velog' ) );
			}
			if ( 0 === $affected ) {
				return new \WP_Error( 'stale_version', __( 'Stale version conflict.', 'velog' ) );
			}

			self::clear_cache();
		}

		return true;
	}

	/**
	 * Return the native database error number for the last query.
	 */
	private static function last_database_errno(): int {
		global $wpdb;

		if ( is_object( $wpdb->dbh ) && isset( $wpdb->dbh->errno ) ) {
			return (int) $wpdb->dbh->errno;
		}

		return 0;
	}

	/**
	 * Clear options cache after a successful direct write.
	 */
	private static function clear_cache(): void {
		wp_cache_delete( self::OPTION_NAME, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION_NAME ] ) ) {
			unset( $notoptions[ self::OPTION_NAME ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}
}
