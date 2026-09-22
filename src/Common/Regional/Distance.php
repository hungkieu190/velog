<?php
/**
 * Distance class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for parsing and formatting distances.
 */
class Distance {

	/**
	 * Parse input to distance canonical representation.
	 *
	 * @param mixed  $input     Input value.
	 * @param string $unit      Distance unit (km or mi).
	 * @param string $separator Decimal separator.
	 * @return array<string, string>
	 * @throws \InvalidArgumentException On invalid unit or out of range.
	 */
	public static function parse( mixed $input, string $unit, string $separator ): array {
		if ( 'km' !== $unit && 'mi' !== $unit ) {
			throw new \InvalidArgumentException( 'invalid_unit' );
		}

		$decimal = DecimalInput::parse( $input, $separator, 3 );
		$parts   = explode( '.', $decimal );

		$integer_part    = $parts[0];
		$fractional_part = isset( $parts[1] ) ? str_pad( $parts[1], 3, '0', STR_PAD_RIGHT ) : '000';

		$q = $integer_part . $fractional_part;
		$q = DecimalMath::normalize_integer( $q );

		if ( DecimalMath::compare( $q, '999999999999' ) > 0 ) {
			throw new \InvalidArgumentException( 'out_of_range' );
		}

		if ( 'km' === $unit ) {
			$canonical_mm = DecimalMath::multiply_small( $q, 1000 );
		} else {
			$prod         = DecimalMath::multiply_small( $q, 1609344 );
			$canonical_mm = DecimalMath::half_up_divide( $prod, 1000 );
		}

		return array(
			'original_value' => $decimal,
			'unit'           => $unit,
			'canonical_mm'   => $canonical_mm,
		);
	}

	/**
	 * Convert canonical representation to decimal format.
	 *
	 * @param string $canonical_mm Canonical mm value.
	 * @param string $unit         Target unit.
	 * @param int    $places       Decimal places.
	 * @return string
	 * @throws \InvalidArgumentException On invalid unit, precision or out of range.
	 */
	public static function to_decimal( string $canonical_mm, string $unit, int $places = 3 ): string {
		if ( 'km' !== $unit && 'mi' !== $unit ) {
			throw new \InvalidArgumentException( 'invalid_unit' );
		}

		if ( $places < 0 || $places > 3 ) {
			throw new \InvalidArgumentException( 'invalid_precision' );
		}

		if ( ! preg_match( '/^[0-9]+\z/', $canonical_mm ) ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		$canonical_mm = DecimalMath::normalize_integer( $canonical_mm );
		if ( DecimalMath::compare( $canonical_mm, '1609343999998391' ) > 0 ) {
			throw new \InvalidArgumentException( 'out_of_range' );
		}

		$multiplier = 1;
		for ( $i = 0; $i < $places; $i++ ) {
			$multiplier *= 10;
		}

		$scaled_mm = DecimalMath::multiply_small( $canonical_mm, $multiplier );

		if ( 'km' === $unit ) {
			$val = DecimalMath::half_up_divide( $scaled_mm, 1000000 );
		} else {
			$val = DecimalMath::half_up_divide( $scaled_mm, 1609344 );
		}

		if ( 0 === $places ) {
			return $val;
		}

		$val             = str_pad( $val, $places + 1, '0', STR_PAD_LEFT );
		$len             = strlen( $val );
		$integer_part    = substr( $val, 0, $len - $places );
		$fractional_part = substr( $val, $len - $places );

		return $integer_part . '.' . $fractional_part;
	}
}
