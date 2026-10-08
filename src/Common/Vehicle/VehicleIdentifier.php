<?php
/**
 * Vehicle identifier validation and canonicalisation.
 *
 * @package MF\VeLog\Common\Vehicle
 */

namespace MF\VeLog\Common\Vehicle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the approved G-04 identifier policy without locale-dependent folding.
 */
final class VehicleIdentifier {

	/** Validate an optional display jurisdiction without changing its case. */
	public static function validate_jurisdiction( mixed $value ): ?string {
		return self::validate_display_text( $value, 100, true );
	}

	/** Validate an optional display plate without changing its case. */
	public static function validate_plate( mixed $value ): ?string {
		return self::validate_display_text( $value, 64, true );
	}

	/** Validate an optional display VIN without changing its case. */
	public static function validate_vin( mixed $value ): ?string {
		return self::validate_display_text( $value, 64, false );
	}

	/** Normalise a required plate jurisdiction. */
	public static function normalize_jurisdiction( mixed $value ): ?string {
		return self::normalize_text( $value, 1, 100, true );
	}

	/** Normalise a required plate value. */
	public static function normalize_plate( mixed $value ): ?string {
		return self::normalize_text( $value, 1, 64, true );
	}

	/** Normalise an optional nonstandard VIN. */
	public static function normalize_vin( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return self::normalize_text( $value, 1, 64, false );
	}

	/**
	 * Build an unambiguous normalized plate uniqueness key.
	 */
	public static function plate_key( string $jurisdiction, string $plate ): string {
		return strlen( $jurisdiction ) . ':' . $jurisdiction . strlen( $plate ) . ':' . $plate;
	}

	/** Validate the optional model year. */
	public static function validate_year( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return 0;
		}
		if ( ! is_int( $value ) && ( ! is_string( $value ) || ! ctype_digit( $value ) ) ) {
			return null;
		}
		$year = (int) $value;
		return $year >= 1886 && $year <= ( (int) gmdate( 'Y' ) + 1 ) ? $year : null;
	}

	/** Enforce the plate/jurisdiction-or-VIN record invariant. */
	public static function validate_record( array $fields ): bool {
		$plate        = (string) ( $fields['plate'] ?? '' );
		$jurisdiction = (string) ( $fields['jurisdiction'] ?? '' );
		$vin          = (string) ( $fields['vin'] ?? '' );
		return ( '' !== $vin || ( '' !== $plate && '' !== $jurisdiction ) )
			&& ( '' !== $plate || '' === $jurisdiction );
	}

	/** Build a normalized plate projection from stored display input. */
	public static function project_plate( array $fields ): ?string {
		return self::normalize_plate( $fields['plate'] ?? '' );
	}

	/** Build a normalized jurisdiction projection from stored display input. */
	public static function project_jurisdiction( array $fields ): ?string {
		return self::normalize_jurisdiction( $fields['jurisdiction'] ?? '' );
	}

	/** Build a normalized VIN projection from stored display input. */
	public static function project_vin( array $fields ): ?string {
		return self::normalize_vin( $fields['vin'] ?? '' ) ?: null;
	}

	/**
	 * Reject unsafe content, trim, and optionally collapse ASCII whitespace.
	 */
	private static function normalize_text( mixed $value, int $minimum, int $maximum, bool $collapse_whitespace ): ?string {
		if ( ! is_string( $value ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', $value ) ) {
			return null;
		}

		$normalized = trim( $value );
		if ( $collapse_whitespace ) {
			$normalized = (string) preg_replace( '/[ \t]+/', ' ', $normalized );
		}
		$normalized = strtoupper( $normalized );
		$length     = function_exists( 'mb_strlen' ) ? mb_strlen( $normalized, 'UTF-8' ) : strlen( $normalized );
		return $length >= $minimum && $length <= $maximum ? $normalized : null;
	}

	/** Validate optional display text while preserving user-facing casing. */
	private static function validate_display_text( mixed $value, int $maximum, bool $collapse_whitespace ): ?string {
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( $collapse_whitespace ) {
			$value = (string) preg_replace( '/[ \t]+/', ' ', $value );
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		return $length > 0 && $length <= $maximum ? $value : null;
	}
}
