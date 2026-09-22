<?php
/**
 * Money class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling money values.
 */
class Money {

	/**
	 * Parse money input based on currency constraints.
	 *
	 * @param mixed  $input     Input value.
	 * @param string $currency  Currency code.
	 * @param string $separator Decimal separator.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException On out of range.
	 */
	public static function parse( mixed $input, string $currency, string $separator ): array {
		$currency_info   = CurrencyCatalog::get( $currency );
		$scale           = $currency_info['scale'];
		$catalog_version = $currency_info['catalog_version'];

		$decimal = DecimalInput::parse( $input, $separator, $scale );
		$parts   = explode( '.', $decimal );

		$integer_part    = $parts[0];
		$fractional_part = isset( $parts[1] )
			? str_pad( $parts[1], $scale, '0', STR_PAD_RIGHT )
			: str_repeat( '0', $scale );

		$minor_units = $integer_part . $fractional_part;
		$minor_units = DecimalMath::normalize_integer( $minor_units );

		if ( strlen( $minor_units ) > 15 ) {
			throw new \InvalidArgumentException( 'out_of_range' );
		}

		return array(
			'original_value'  => $decimal,
			'minor_units'     => $minor_units,
			'currency'        => $currency,
			'scale'           => $scale,
			'catalog_version' => $catalog_version,
		);
	}
}
