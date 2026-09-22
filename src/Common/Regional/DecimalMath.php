<?php
/**
 * DecimalMath class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal arithmetic helper for exact decimal string operations.
 * Operations do not use float to avoid cumulative rounding errors.
 */
class DecimalMath {

	/**
	 * Normalize integer string by removing leading zeros.
	 *
	 * @param string $value The value to normalize.
	 * @return string
	 */
	public static function normalize_integer( string $value ): string {
		$value = ltrim( $value, '0' );
		return '' === $value ? '0' : $value;
	}

	/**
	 * Compare two normalized integer strings.
	 *
	 * @param string $a First integer string.
	 * @param string $b Second integer string.
	 * @return int
	 */
	public static function compare( string $a, string $b ): int {
		$a = self::normalize_integer( $a );
		$b = self::normalize_integer( $b );

		$len_a = strlen( $a );
		$len_b = strlen( $b );

		if ( $len_a !== $len_b ) {
			return $len_a <=> $len_b;
		}

		return strcmp( $a, $b );
	}

	/**
	 * Multiply a large integer string by a small integer.
	 *
	 * @param string $a The large integer string.
	 * @param int    $b The small integer multiplier.
	 * @return string
	 */
	public static function multiply_small( string $a, int $b ): string {
		if ( '0' === $a || 0 === $b ) {
			return '0';
		}

		$result = '';
		$carry  = 0;
		$len    = strlen( $a );

		for ( $i = $len - 1; $i >= 0; $i-- ) {
			$digit  = (int) $a[ $i ];
			$prod   = $digit * $b + $carry;
			$result = ( $prod % 10 ) . $result;
			$carry  = (int) ( $prod / 10 );
		}

		if ( $carry > 0 ) {
			$result = $carry . $result;
		}

		return $result;
	}

	/**
	 * Divide a large integer string by a small integer, returning quotient and remainder.
	 *
	 * @param string $a The dividend string.
	 * @param int    $b The divisor integer.
	 * @return array<int, string|int>
	 * @throws \InvalidArgumentException On division by zero.
	 */
	public static function divide_small_with_remainder( string $a, int $b ): array {
		if ( 0 === $b ) {
			throw new \InvalidArgumentException( 'Division by zero' );
		}

		$quotient  = '';
		$remainder = 0;
		$len       = strlen( $a );

		for ( $i = 0; $i < $len; $i++ ) {
			$digit   = (int) $a[ $i ];
			$current = $remainder * 10 + $digit;

			$q_digit   = (int) ( $current / $b );
			$quotient .= $q_digit;
			$remainder = $current % $b;
		}

		$quotient = self::normalize_integer( $quotient );

		return array( $quotient, $remainder );
	}

	/**
	 * Increment a large integer string by 1.
	 *
	 * @param string $a The integer string to increment.
	 * @return string
	 */
	public static function increment( string $a ): string {
		$result = '';
		$carry  = 1;
		$len    = strlen( $a );

		for ( $i = $len - 1; $i >= 0; $i-- ) {
			$digit = (int) $a[ $i ];
			$sum   = $digit + $carry;

			if ( $sum > 9 ) {
				$result = '0' . $result;
				$carry  = 1;
			} else {
				$result = $sum . $result;
				$carry  = 0;
			}
		}

		if ( $carry > 0 ) {
			$result = '1' . $result;
		}

		return $result;
	}

	/**
	 * Divide a large integer string by a small integer, rounding half up.
	 *
	 * @param string $a The dividend string.
	 * @param int    $b The divisor integer.
	 * @return string
	 * @throws \InvalidArgumentException On division by zero.
	 */
	public static function half_up_divide( string $a, int $b ): string {
		list( $quotient, $remainder ) = self::divide_small_with_remainder( $a, $b );
		$quotient                     = (string) $quotient;
		$remainder                    = (int) $remainder;

		if ( $remainder * 2 >= $b ) {
			$quotient = self::increment( $quotient );
		}

		return $quotient;
	}
}
