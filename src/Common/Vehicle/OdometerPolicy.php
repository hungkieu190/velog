<?php
/**
 * Exact vehicle baseline validation.
 *
 * @package MF\VeLog\Common\Vehicle
 */

namespace MF\VeLog\Common\Vehicle;

use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Regional\DecimalMath;
use MF\VeLog\Common\Regional\DecimalInput;
use MF\VeLog\Common\Regional\Distance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates unknown or known baseline tuples without binary floats. */
final class OdometerPolicy {

	/**
	 * Validate an optional unit; legacy records may omit it.
	 *
	 * @param mixed $value Submitted unit.
	 */
	public static function validate_unit( mixed $value ): ?string {
		return is_string( $value ) && in_array( $value, array( 'km', 'mi' ), true ) ? $value : null;
	}

	/**
	 * Validate an exact closed baseline tuple. Empty means unknown, never zero.
	 *
	 * @param mixed $value Submitted tuple.
	 * @return array<string, string>|null
	 */
	public static function validate_baseline( mixed $value ): ?array {
		if ( array() === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || count( $value ) !== 4 ) {
			return null;
		}
		foreach ( array( 'original_value', 'unit', 'canonical_mm', 'date' ) as $key ) {
			if ( ! isset( $value[ $key ] ) || ! is_string( $value[ $key ] ) ) {
				return null;
			}
		}
		try {
			$parsed = Distance::parse( $value['original_value'], $value['unit'], '.' );
			$date   = CalendarDate::parse( $value['date'] );
		} catch ( \InvalidArgumentException $exception ) {
			return null;
		}
		$today = CalendarDate::today( time(), wp_timezone() );
		if ( $parsed['canonical_mm'] !== $value['canonical_mm'] || $date > $today ) {
			return null;
		}
		return array_merge( $parsed, array( 'date' => $date ) );
	}

	/**
	 * Project a baseline into the shop unit without mutating its original tuple.
	 *
	 * @param array<string, string> $baseline Stored baseline.
	 * @param string                $unit     Trusted system unit.
	 */
	public static function display_value( array $baseline, string $unit ): string {
		return empty( $baseline ) ? '' : Distance::to_decimal( $baseline['canonical_mm'], $unit );
	}

	/**
	 * Parse a system-unit reading, retaining exact history on unchanged display.
	 *
	 * @param string                $value     Submitted reading.
	 * @param string                $date      Submitted date.
	 * @param string                $unit      Trusted system unit.
	 * @param string                $separator Locale decimal separator.
	 * @param array<string, string> $before    Authorized stored baseline.
	 * @return array<string, string>
	 * @throws \InvalidArgumentException Invalid reading or precision.
	 */
	public static function parse_reading(
		string $value,
		string $date,
		string $unit,
		string $separator,
		array $before
	): array {
		$decimal = DecimalInput::parse( $value, $separator, 3 );
		if ( ! empty( $before ) ) {
			$display = self::display_value( $before, $unit );
			$parts   = explode( '.', $decimal );
			$key     = $parts[0] . '.' . str_pad( $parts[1] ?? '', 3, '0' );
			if ( $key === $display ) {
				return array_merge( $before, array( 'date' => $date ) );
			}
		}
		return array_merge( Distance::parse( $value, $unit, $separator ), array( 'date' => $date ) );
	}

	/**
	 * Determine whether a decrease or clearing requires a correction reason.
	 *
	 * @param array<string, string> $before Previous baseline.
	 * @param array<string, string> $after  Candidate baseline.
	 */
	public static function requires_reason( array $before, array $after ): bool {
		if ( empty( $before ) ) {
			return false;
		}
		return empty( $after ) || DecimalMath::compare( $after['canonical_mm'], $before['canonical_mm'] ) < 0;
	}
}
