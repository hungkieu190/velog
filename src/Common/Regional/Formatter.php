<?php
/**
 * Formatter class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for numeric formatting.
 */
class Formatter {

	/**
	 * Format a canonical decimal string.
	 *
	 * @param string $canonical       Canonical decimal value.
	 * @param string $separator       Decimal separator.
	 * @param string $group_separator Grouping separator.
	 * @return string
	 * @throws \InvalidArgumentException On invalid number or separator.
	 */
	public static function decimal( string $canonical, string $separator, string $group_separator = '' ): string {
		if ( ! preg_match( '/^[0-9]+(?:\.[0-9]+)?\z/', $canonical ) ) {
			throw new \InvalidArgumentException( 'invalid_number' );
		}

		if ( '.' !== $separator && ',' !== $separator ) {
			throw new \InvalidArgumentException( 'invalid_separator' );
		}

		$allowed_group = array( '', ',', '.', ' ', "\xc2\xa0" );
		if ( ! in_array( $group_separator, $allowed_group, true ) ) {
			throw new \InvalidArgumentException( 'invalid_separator' );
		}

		if ( '' !== $group_separator && $separator === $group_separator ) {
			throw new \InvalidArgumentException( 'invalid_separator' );
		}

		$parts = explode( '.', $canonical );

		$integer_part    = $parts[0];
		$fractional_part = isset( $parts[1] ) ? $parts[1] : '';

		if ( '' !== $group_separator ) {
			$grouped_integer = '';
			$len             = strlen( $integer_part );
			for ( $i = 0; $i < $len; $i++ ) {
				if ( $i > 0 && 0 === $i % 3 ) {
					$grouped_integer = $group_separator . $grouped_integer;
				}
				$grouped_integer = $integer_part[ $len - 1 - $i ] . $grouped_integer;
			}
			$integer_part = $grouped_integer;
		}

		if ( '' !== $fractional_part ) {
			return $integer_part . $separator . $fractional_part;
		}

		return $integer_part;
	}
}
