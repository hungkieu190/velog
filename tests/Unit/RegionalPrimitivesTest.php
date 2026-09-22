<?php
/**
 * RegionalPrimitivesTest file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Tests\Unit;

use MF\VeLog\Common\Regional\DecimalInput;
use MF\VeLog\Common\Regional\Distance;
use MF\VeLog\Common\Regional\Money;
use MF\VeLog\Common\Regional\CalendarDate;
use MF\VeLog\Common\Regional\Formatter;
use PHPUnit\Framework\TestCase;

/**
 * RegionalPrimitivesTest class.
 */
class RegionalPrimitivesTest extends TestCase {

	/**
	 * Test parsing localized decimal inputs.
	 *
	 * @return void
	 */
	public function test_localized_input() {
		$this->assertSame( '1234.5', DecimalInput::parse( '1234.5', '.', 2 ) );
		$this->assertSame( '1234.5', DecimalInput::parse( '1234,5', ',', 2 ) );
		$this->assertSame( '0', DecimalInput::parse( '0', '.', 2 ) );
		$this->assertSame( '0', DecimalInput::parse( '0000', '.', 2 ) );
		$this->assertSame( '0.12', DecimalInput::parse( '0.12', '.', 2 ) );

		$invalid_cases = array(
			array( '', '.', 2, 'invalid_number' ),
			array( ' ', '.', 2, 'invalid_number' ),
			array( '1,234.5', '.', 2, 'invalid_number' ),
			array( '1.234,5', ',', 2, 'invalid_number' ),
			array( '1e3', '.', 2, 'invalid_number' ),
			array( '-123', '.', 2, 'invalid_number' ),
			array( '123.456', '.', 2, 'invalid_precision' ),
			array( str_repeat( '9', 257 ), '.', 2, 'invalid_number' ),
		);

		foreach ( $invalid_cases as $case ) {
			try {
				DecimalInput::parse( $case[0], $case[1], $case[2] );
				$this->fail( 'Expected exception for case ' . $case[0] );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( $case[3], $e->getMessage() );
			}
		}

		try {
			DecimalInput::parse( array(), '.', 2 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_number', $e->getMessage() );
		}
	}

	/**
	 * Test distance parse and to_decimal.
	 *
	 * @return void
	 */
	public function test_exact_distance() {
		$mi1 = Distance::parse( '1', 'mi', '.' );
		$this->assertSame( '1609344', $mi1['canonical_mm'] );
		$this->assertSame( '1', $mi1['original_value'] );

		$mi_tiny = Distance::parse( '0.001', 'mi', '.' );
		$this->assertSame( '1609', $mi_tiny['canonical_mm'] );

		$mi_max = Distance::parse( '999999999.999', 'mi', '.' );
		$this->assertSame( '1609343999998391', $mi_max['canonical_mm'] );

		try {
			Distance::parse( '1000000000', 'mi', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'out_of_range', $e->getMessage() );
		}

		$km0 = Distance::parse( '0', 'km', '.' );
		$this->assertSame( '0', $km0['canonical_mm'] );

		$this->assertSame( '0.001', Distance::to_decimal( '500', 'km', 3 ) );
		$this->assertSame( '0.000', Distance::to_decimal( '499', 'km', 3 ) );

		$this->assertSame( '1.000', Distance::to_decimal( '1609344', 'mi', 3 ) );
		$this->assertSame( '0.001', Distance::to_decimal( '1609', 'mi', 3 ) );
	}

	/**
	 * Test money parse.
	 *
	 * @return void
	 */
	public function test_money_snapshot() {
		$vnd = Money::parse( '123', 'VND', '.' );
		$this->assertSame( '123', $vnd['minor_units'] );
		$this->assertSame( 0, $vnd['scale'] );

		$usd      = Money::parse( '1234.56', 'USD', '.' );
		$expected = array(
			'original_value'  => '1234.56',
			'minor_units'     => '123456',
			'currency'        => 'USD',
			'scale'           => 2,
			'catalog_version' => '48.0.0-2026-09-22',
		);
		$this->assertSame( $expected, $usd );

		$this->assertSame( '1,234.56', Formatter::decimal( $usd['original_value'], '.', ',' ) );
		$this->assertSame( '1.234,56', Formatter::decimal( $usd['original_value'], ',', '.' ) );

		$this->assertSame( $expected, $usd );

		$usd2 = Money::parse( '12.34', 'USD', '.' );
		$this->assertSame( '1234', $usd2['minor_units'] );
		$this->assertSame( 2, $usd2['scale'] );

		$kwd = Money::parse( '1.234', 'KWD', '.' );
		$this->assertSame( '1234', $kwd['minor_units'] );
		$this->assertSame( 3, $kwd['scale'] );

		try {
			Money::parse( '12.345', 'USD', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_precision', $e->getMessage() );
		}

		try {
			Money::parse( '123', 'ZZZ', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'unknown_currency', $e->getMessage() );
		}

		$large = Money::parse( str_repeat( '9', 15 ), 'VND', '.' );
		$this->assertSame( str_repeat( '9', 15 ), $large['minor_units'] );

		try {
			Money::parse( str_repeat( '9', 16 ), 'VND', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'out_of_range', $e->getMessage() );
		}
	}

	/**
	 * Test calendar date parsing and today's date logic.
	 *
	 * @return void
	 */
	public function test_calendar_boundaries() {
		$this->assertSame( '2024-02-29', CalendarDate::parse( '2024-02-29' ) );

		$invalid = array( '2025-02-29', '0000-01-01', '2024-1-1' );
		foreach ( $invalid as $date ) {
			try {
				CalendarDate::parse( $date );
				$this->fail( "Expected failure for {$date}" );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'invalid_date', $e->getMessage() );
			}
		}

		$unix = 1704069000;

		$ny_today = CalendarDate::today( $unix, new \DateTimeZone( 'America/New_York' ) );
		$this->assertSame( '2023-12-31', $ny_today );

		$berlin_today = CalendarDate::today( $unix, new \DateTimeZone( 'Europe/Berlin' ) );
		$this->assertSame( '2024-01-01', $berlin_today );
	}

	/**
	 * Test numeric formatting.
	 *
	 * @return void
	 */
	public function test_formatter() {
		$this->assertSame( '1,234.50', Formatter::decimal( '1234.50', '.', ',' ) );
		$this->assertSame( '1.234,50', Formatter::decimal( '1234.50', ',', '.' ) );
		$this->assertSame( '1234.50', Formatter::decimal( '1234.50', '.' ) );

		try {
			Formatter::decimal( '1234.50', '.', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_separator', $e->getMessage() );
		}
	}

	/**
	 * Test R2-1: Absolute whole-string validation prevents LF/CRLF/NUL/junk.
	 */
	public function test_absolute_grammar_rejection() {
		$invalid_dates = array( "2024-02-29\n", "2024-02-29\r\n", "2024-02-29\0", '2024-02-29x', 'x2024-02-29' );
		foreach ( $invalid_dates as $date ) {
			try {
				CalendarDate::parse( $date );
				$this->fail( 'Expected invalid_date for ' . bin2hex( $date ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'invalid_date', $e->getMessage() );
			}
		}

		$invalid_decimals = array( "500\n", "500\r\n", "500\0", '500 ', ' 500' );
		foreach ( $invalid_decimals as $val ) {
			try {
				Distance::to_decimal( $val, 'km', 3 );
				$this->fail( 'Expected invalid_number for ' . bin2hex( $val ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'invalid_number', $e->getMessage() );
			}
			try {
				Formatter::decimal( $val, '.' );
				$this->fail( 'Expected invalid_number for ' . bin2hex( $val ) );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'invalid_number', $e->getMessage() );
			}
		}

		// Valid cases remain correct.
		$this->assertSame( '0.001', Distance::to_decimal( '500', 'km', 3 ) );
		$this->assertSame( '2024-02-29', CalendarDate::parse( '2024-02-29' ) );
		$this->assertSame( '1,234.50', Formatter::decimal( '1234.50', '.', ',' ) );
	}

	/**
	 * Test R2-2: DecimalInput parse correctly handles NUL and whitespace limits.
	 */
	public function test_decimal_input_whitespace_and_limits() {
		$this->assertSame(
			'123',
			DecimalInput::parse(
				' 	
123 	
',
				'.',
				2
			)
		);

		$invalid_numbers = array(
			"\0" . '123',
			'123' . "\0",
			'12' . "\0" . '3',
			'1 23',
			'1,23',
			'1a23',
			'1e3',
			'+123',
			'-123',
			'١٢٣',
			'１２３',
			null,
			array(),
			123,
		);

		foreach ( $invalid_numbers as $val ) {
			try {
				DecimalInput::parse( $val, '.', 2 );
				$this->fail( 'Expected exception for case' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'invalid_number', $e->getMessage() );
			}
		}

		try {
			DecimalInput::parse( '123', 'x', 2 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_separator', $e->getMessage() );
		}

		try {
			DecimalInput::parse( '123', '.', -1 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_precision', $e->getMessage() );
		}
		try {
			DecimalInput::parse( '123', '.', 5 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_precision', $e->getMessage() );
		}

		$valid_256 = str_repeat( '9', 256 );
		$this->assertSame( $valid_256, DecimalInput::parse( $valid_256, '.', 0 ) );

		$invalid_257 = str_repeat( '9', 257 );
		try {
			DecimalInput::parse( $invalid_257, '.', 0 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_number', $e->getMessage() );
		}
	}

	/**
	 * Test R2-3: Distance and money validations.
	 */
	public function test_distance_and_money_validations() {
		try {
			Distance::parse( '1', 'ft', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_unit', $e->getMessage() );
		}

		try {
			Distance::to_decimal( '1', 'ft', 3 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_unit', $e->getMessage() );
		}

		try {
			Distance::to_decimal( '1e3', 'km', 3 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_number', $e->getMessage() );
		}

		try {
			Distance::to_decimal( '-1000', 'km', 3 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_number', $e->getMessage() );
		}

		$this->assertSame( '999999999.999', Distance::to_decimal( '1609343999998391', 'mi', 3 ) );

		try {
			Distance::to_decimal( '1609343999998392', 'mi', 3 ); // Max+1.
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'out_of_range', $e->getMessage() );
		}

		try {
			Distance::to_decimal( '1000', 'km', -1 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_precision', $e->getMessage() );
		}

		try {
			Distance::to_decimal( '1000', 'km', 4 );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_precision', $e->getMessage() );
		}

		// Money.
		$vnd = Money::parse( '0', 'VND', '.' );
		$this->assertSame( '0', $vnd['minor_units'] );

		$usd = Money::parse( '1234.56', 'USD', '.' );
		$this->assertSame( '1234.56', $usd['original_value'] );
		$this->assertSame( 'USD', $usd['currency'] );
		$this->assertSame( 2, $usd['scale'] );
		$this->assertArrayHasKey( 'catalog_version', $usd );

		try {
			Money::parse( '123', 'usd', '.' ); // lowercase.
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'unknown_currency', $e->getMessage() );
		}

		// Minor digit boundary at 15 versus 16.
		$money15 = Money::parse( str_repeat( '9', 15 ), 'VND', '.' );
		$this->assertSame( str_repeat( '9', 15 ), $money15['minor_units'] );

		try {
			Money::parse( str_repeat( '9', 16 ), 'VND', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'out_of_range', $e->getMessage() );
		}
	}

	/**
	 * Test R2-4: Calendar DST and formatter config.
	 */
	public function test_calendar_dst_and_formatter() {
		$unix     = 1710131400; // Date is 2024-03-11T04:30:00Z.
		$ny_today = CalendarDate::today( $unix, new \DateTimeZone( 'America/New_York' ) );
		$this->assertSame( '2024-03-11', $ny_today );

		try {
			Formatter::decimal( '1234.50', '.', 'x' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_separator', $e->getMessage() );
		}

		try {
			Formatter::decimal( '12a4', '.' );
			$this->fail();
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'invalid_number', $e->getMessage() );
		}

		// NBSP grouping retains fraction.
		$nbsp      = ' ';
		$formatted = Formatter::decimal( '1234.56', '.', $nbsp );
		$this->assertSame( "1{$nbsp}234.56", $formatted );
	}
}
