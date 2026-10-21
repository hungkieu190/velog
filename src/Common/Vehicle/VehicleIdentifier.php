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

	/**
	 * Validate an optional display jurisdiction without changing its case.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function validate_jurisdiction( mixed $value ): ?string {
		return self::validate_display_text( $value, 100, true );
	}

	/**
	 * Validate an optional display plate without changing its case.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function validate_plate( mixed $value ): ?string {
		return self::validate_display_text( $value, 64, true );
	}

	/**
	 * Validate an optional display VIN without changing its case.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function validate_vin( mixed $value ): ?string {
		return self::validate_display_text( $value, 64, false );
	}

	/**
	 * Normalise a required plate jurisdiction.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function normalize_jurisdiction( mixed $value ): ?string {
		return self::normalize_text( $value, 1, 100, true );
	}

	/**
	 * Normalise a required plate value.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function normalize_plate( mixed $value ): ?string {
		return self::normalize_text( $value, 1, 64, true );
	}

	/**
	 * Normalise an optional nonstandard VIN.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function normalize_vin( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return self::normalize_text( $value, 1, 64, false );
	}

	/**
	 * Build an unambiguous normalized plate uniqueness key.
	 *
	 * @param string $jurisdiction Normalized jurisdiction.
	 * @param string $plate        Normalized plate.
	 */
	public static function plate_key( string $jurisdiction, string $plate ): string {
		return strlen( $jurisdiction ) . ':' . $jurisdiction . strlen( $plate ) . ':' . $plate;
	}

	/**
	 * Validate the optional model year.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function validate_year( mixed $value ): ?int {
		if ( null === $value || '' === $value || 0 === $value ) {
			return 0;
		}
		if ( ! is_int( $value ) && ( ! is_string( $value ) || ! ctype_digit( $value ) ) ) {
			return null;
		}
		$year = (int) $value;
		return $year >= 1886 && $year <= ( (int) wp_date( 'Y' ) + 1 ) ? $year : null;
	}

	/**
	 * Enforce the plate/jurisdiction-or-VIN record invariant.
	 *
	 * @param array<string, mixed> $fields Vehicle fields.
	 */
	public static function validate_record( array $fields ): bool {
		$plate        = (string) ( $fields['plate'] ?? '' );
		$jurisdiction = (string) ( $fields['jurisdiction'] ?? '' );
		$vin          = (string) ( $fields['vin'] ?? '' );
		return ( '' !== $vin || ( '' !== $plate && '' !== $jurisdiction ) )
			&& ( ( '' === $plate ) === ( '' === $jurisdiction ) );
	}

	/**
	 * Build a normalized plate projection from stored display input.
	 *
	 * @param array<string, mixed> $fields Vehicle fields.
	 */
	public static function project_plate( array $fields ): ?string {
		return self::normalize_plate( $fields['plate'] ?? '' );
	}

	/**
	 * Build a normalized jurisdiction projection from stored display input.
	 *
	 * @param array<string, mixed> $fields Vehicle fields.
	 */
	public static function project_jurisdiction( array $fields ): ?string {
		return self::normalize_jurisdiction( $fields['jurisdiction'] ?? '' );
	}

	/**
	 * Build a normalized VIN projection from stored display input.
	 *
	 * @param array<string, mixed> $fields Vehicle fields.
	 */
	public static function project_vin( array $fields ): ?string {
		$vin = self::normalize_vin( $fields['vin'] ?? '' );
		return null !== $vin && '' !== $vin ? $vin : null;
	}

	/**
	 * Reject unsafe content, trim, and optionally collapse ASCII whitespace.
	 *
	 * @param mixed $value               Submitted text.
	 * @param int   $minimum             Minimum character count.
	 * @param int   $maximum             Maximum character count.
	 * @param bool  $collapse_whitespace Whether to collapse ASCII whitespace.
	 */
	private static function normalize_text(
		mixed $value,
		int $minimum,
		int $maximum,
		bool $collapse_whitespace
	): ?string {
		if (
			! is_string( $value )
			|| 0 !== preg_match( '/[\x00-\x08\x0A-\x1F\x7F<>]/u', $value )
		) {
			return null;
		}

		if ( ! $collapse_whitespace && str_contains( $value, "\t" ) ) {
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

	/**
	 * Validate optional display text while preserving user-facing casing.
	 *
	 * @param mixed $value               Submitted text.
	 * @param int   $maximum             Maximum character count.
	 * @param bool  $collapse_whitespace Whether to collapse ASCII whitespace.
	 */
	private static function validate_display_text( mixed $value, int $maximum, bool $collapse_whitespace ): ?string {
		if ( null === $value || '' === $value ) {
			return '';
		}
		if (
			! is_string( $value )
			|| 0 !== preg_match( '/[\x00-\x08\x0A-\x1F\x7F<>]/u', $value )
		) {
			return null;
		}
		if ( ! $collapse_whitespace && str_contains( $value, "\t" ) ) {
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
