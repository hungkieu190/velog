<?php
/**
 * CurrencyCatalog class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class providing currency catalog methods.
 */
class CurrencyCatalog {

	/**
	 * Get all currencies.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return CurrencyCatalogData::get_catalog();
	}

	/**
	 * Get currency by code.
	 *
	 * @param string $code Currency code.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException On unknown currency code.
	 */
	public static function get( string $code ): array {
		$catalog = self::all();
		if ( ! isset( $catalog[ $code ] ) ) {
			throw new \InvalidArgumentException( 'unknown_currency' );
		}
		return $catalog[ $code ];
	}
}
