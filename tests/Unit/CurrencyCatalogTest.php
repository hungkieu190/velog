<?php
/**
 * CurrencyCatalogTest file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Regional\CurrencyCatalog;
use PHPUnit\Framework\TestCase;

/**
 * CurrencyCatalogTest class.
 */
class CurrencyCatalogTest extends TestCase {

	/**
	 * Test catalog size and sorting.
	 *
	 * @return void
	 */
	public function test_catalog_size_and_codes() {
		$all = CurrencyCatalog::all();
		$this->assertCount( 153, $all );

		$keys        = array_keys( $all );
		$sorted_keys = $keys;
		sort( $sorted_keys );
		$this->assertSame( $sorted_keys, $keys, 'Catalog should be sorted by code' );
	}

	/**
	 * Test properties of specific currencies.
	 *
	 * @return void
	 */
	public function test_specific_currencies() {
		$vnd = CurrencyCatalog::get( 'VND' );
		$this->assertSame( 'VND', $vnd['code'] );
		$this->assertSame( 0, $vnd['scale'] );
		$this->assertArrayHasKey( 'catalog_version', $vnd );

		$jpy = CurrencyCatalog::get( 'JPY' );
		$this->assertSame( 0, $jpy['scale'] );

		$usd = CurrencyCatalog::get( 'USD' );
		$this->assertSame( 2, $usd['scale'] );

		$eur = CurrencyCatalog::get( 'EUR' );
		$this->assertSame( 2, $eur['scale'] );

		$kwd = CurrencyCatalog::get( 'KWD' );
		$this->assertSame( 3, $kwd['scale'] );
	}

	/**
	 * Test unknown currency code throws exception.
	 *
	 * @return void
	 */
	public function test_unknown_currency_throws() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'unknown_currency' );
		CurrencyCatalog::get( 'ZZZ' );
	}
}
