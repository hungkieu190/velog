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
	 * Read current settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = array(
			'schema_version' => self::SCHEMA_VERSION,
			'record_version' => 0,
			'configured'     => false,
			'distance_unit'  => '',
			'currency_code'  => '',
			'currency_scale' => 0,
			'region'         => '',
		);

		$settings = get_option( self::OPTION_NAME, $defaults );
		if ( ! is_array( $settings ) ) {
			return $defaults;
		}

		// Strict validation of stored values (F-002).
		$merged = array(
			'schema_version'  => isset( $settings['schema_version'] ) && is_string( $settings['schema_version'] ) ? $settings['schema_version'] : self::SCHEMA_VERSION,
			'record_version'  => isset( $settings['record_version'] ) ? (int) $settings['record_version'] : 0,
			'configured'      => ! empty( $settings['configured'] ),
			'distance_unit'   => isset( $settings['distance_unit'] ) && in_array( $settings['distance_unit'], array( 'km', 'mi' ), true ) ? $settings['distance_unit'] : '',
			'currency_code'   => isset( $settings['currency_code'] ) && is_string( $settings['currency_code'] ) ? $settings['currency_code'] : '',
			'currency_scale'  => isset( $settings['currency_scale'] ) ? (int) $settings['currency_scale'] : 0,
			'region'          => isset( $settings['region'] ) && is_string( $settings['region'] ) ? $settings['region'] : '',
			'catalog_version' => isset( $settings['catalog_version'] ) && is_string( $settings['catalog_version'] ) ? $settings['catalog_version'] : CurrencyCatalog::VERSION,
		);

		return $merged;
	}

	/**
	 * Get configured defaults.
	 *
	 * @return array|WP_Error Array with defaults or WP_Error if not configured.
	 */
	public static function require_configured() {
		$settings = self::get_settings();
		if ( empty( $settings['configured'] ) || empty( $settings['distance_unit'] ) || empty( $settings['currency_code'] ) ) {
			return new WP_Error( 'not_configured', __( 'Regional settings are not configured or invalid.', 'velog' ) );
		}

		try {
			// Validate currency against catalog (F-002).
			CurrencyCatalog::get( $settings['currency_code'] );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_currency', __( 'Configured currency is invalid.', 'velog' ) );
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
	 * @param array $input            Submitted data.
	 * @param int   $expected_version Expected record version.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function save_settings( array $input, int $expected_version ) {
		if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
			return new WP_Error( 'unauthorized', __( 'Unauthorized to save settings.', 'velog' ) );
		}

		$current = self::get_settings();
		if ( $current['record_version'] !== $expected_version ) {
			return new WP_Error( 'stale_version', __( 'Settings have been updated by another user. Please refresh and try again.', 'velog' ) );
		}

		$distance = isset( $input['distance_unit'] ) ? (string) $input['distance_unit'] : '';
		if ( 'km' !== $distance && 'mi' !== $distance ) {
			return new WP_Error( 'invalid_distance', __( 'Invalid distance unit.', 'velog' ) );
		}

		$currency_code = isset( $input['currency_code'] ) ? (string) $input['currency_code'] : '';
		try {
			$currency_data  = CurrencyCatalog::get( $currency_code );
			$currency_scale = $currency_data['scale'] ?? 2;
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_currency', __( 'Invalid currency code.', 'velog' ) );
		}

		$region = '';
		if ( isset( $input['region'] ) ) {
			if ( ! is_scalar( $input['region'] ) ) {
				return new WP_Error( 'invalid_region', __( 'Region must be a string.', 'velog' ) );
			}
			$region = sanitize_text_field( (string) $input['region'] );
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

		if ( 0 === $expected_version ) {
			// Ensure first insert is unique (F-001).
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					self::OPTION_NAME,
					maybe_serialize( $new_settings )
				)
			);

			if ( ! $inserted ) {
				return new WP_Error( 'save_failed', __( 'Failed to create settings. They may have been created concurrently.', 'velog' ) );
			}
			wp_cache_delete( self::OPTION_NAME, 'options' );
			$notoptions = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION_NAME ] ) ) {
				unset( $notoptions[ self::OPTION_NAME ] );
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}
			wp_cache_delete( 'alloptions', 'options' );
		} else {
			// Atomic conditional update tied to exact prior serialized value (F-001).
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					maybe_serialize( $new_settings ),
					self::OPTION_NAME,
					maybe_serialize( $current )
				)
			);

			if ( ! $updated ) {
				return new WP_Error( 'save_failed', __( 'Failed to update settings. Stale version or database error.', 'velog' ) );
			}
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}

		return true;
	}
}
