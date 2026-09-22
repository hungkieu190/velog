<?php
/**
 * DecimalInput class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for parsing decimal inputs.
 */
class DecimalInput {

	/**
	 * Parse a scalar UI string using an explicitly supplied locale separator.
	 *
	 * @param mixed  $input        The input value to parse.
	 * @param string $separator    The decimal separator.
	 * @param int    $max_fraction Maximum allowed fractional digits.
	 * @return string Canonical decimal string.
	 * @throws \InvalidArgumentException On invalid input, precision, or separator.
	 */
	public static function parse( mixed $input, string $separator, int $max_fraction ): string {
		if ( ! is_string( $input ) ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		if ( strlen( $input ) > 256 ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		if ( '.' !== $separator && ',' !== $separator ) {
			throw new \InvalidArgumentException( 'invalid_separator' );
		}

		if ( $max_fraction < 0 || $max_fraction > 4 ) {
			throw new \InvalidArgumentException( 'invalid_precision' );
		}

		$trimmed = trim( $input, " \t\n\r\x0B\x0C" );
		if ( '' === $trimmed ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		$sep_escaped = preg_quote( $separator, '/' );
		if ( ! preg_match( '/^[0-9]+(?:' . $sep_escaped . '[0-9]+)?\z/', $trimmed ) ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		$parts        = explode( $separator, $trimmed );
		$integer_part = ltrim( $parts[0], '0' );
		if ( '' === $integer_part ) {
			$integer_part = '0';
		}

		$fractional_part = isset( $parts[1] ) ? $parts[1] : '';
		if ( strlen( $fractional_part ) > $max_fraction ) {
			throw new \InvalidArgumentException( 'invalid_precision' );
		}

		if ( '' !== $fractional_part ) {
			return $integer_part . '.' . $fractional_part;
		}

		return $integer_part;
	}
}
