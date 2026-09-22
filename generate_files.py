import os

files = {
    'src/Common/Regional/DecimalMath.php': """<?php
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
            $digit  = (int) $a[$i];
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
     * @return array
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
            $digit   = (int) $a[$i];
            $current = $remainder * 10 + $digit;
            
            $q_digit   = (int) ( $current / $b );
            $quotient .= $q_digit;
            $remainder = $current % $b;
        }
        
        $quotient = self::normalize_integer( $quotient );
        
        return [ $quotient, $remainder ];
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
            $digit = (int) $a[$i];
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
     */
    public static function half_up_divide( string $a, int $b ): string {
        list( $quotient, $remainder ) = self::divide_small_with_remainder( $a, $b );
        
        if ( $remainder * 2 >= $b ) {
            $quotient = self::increment( $quotient );
        }
        
        return $quotient;
    }
}
""",

    'src/Common/Regional/DecimalInput.php': """<?php
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
            throw new \InvalidArgumentException( 'Input must be a string', 10 );
        }
        
        if ( strlen( $input ) > 256 ) {
            throw new \InvalidArgumentException( 'invalid_number', 11 );
        }
        
        if ( '.' !== $separator && ',' !== $separator ) {
            throw new \InvalidArgumentException( 'invalid_separator', 12 );
        }
        
        if ( $max_fraction < 0 || $max_fraction > 4 ) {
            throw new \InvalidArgumentException( 'invalid_precision', 13 );
        }
        
        $trimmed = trim( $input, " \t\n\r\0\x0B" );
        if ( '' === $trimmed ) {
            throw new \InvalidArgumentException( 'invalid_number', 14 );
        }
        
        $sep_escaped = preg_quote( $separator, '/' );
        if ( ! preg_match( '/^[0-9]+(?:' . $sep_escaped . '[0-9]+)?$/', $trimmed ) ) {
            throw new \InvalidArgumentException( 'invalid_number', 15 );
        }
        
        $parts        = explode( $separator, $trimmed );
        $integer_part = ltrim( $parts[0], '0' );
        if ( '' === $integer_part ) {
            $integer_part = '0';
        }
        
        $fractional_part = isset( $parts[1] ) ? $parts[1] : '';
        if ( strlen( $fractional_part ) > $max_fraction ) {
            throw new \InvalidArgumentException( 'invalid_precision', 16 );
        }
        
        if ( '' !== $fractional_part ) {
            return $integer_part . '.' . $fractional_part;
        }
        
        return $integer_part;
    }
}
""",

    'src/Common/Regional/CurrencyCatalog.php': """<?php
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
     * @return array
     */
    public static function all(): array {
        return CurrencyCatalogData::get_catalog();
    }

    /**
     * Get currency by code.
     *
     * @param string $code Currency code.
     * @return array
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
""",

    'src/Common/Regional/Distance.php': """<?php
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
     * @return array
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
        
        return [
            'original_value' => $decimal,
            'unit'           => $unit,
            'canonical_mm'   => $canonical_mm,
        ];
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
        
        if ( ! preg_match( '/^[0-9]+$/', $canonical_mm ) ) {
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
""",

    'src/Common/Regional/Money.php': """<?php
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
     * @return array
     * @throws \InvalidArgumentException On out of range.
     */
    public static function parse( mixed $input, string $currency, string $separator ): array {
        $currency_info   = CurrencyCatalog::get( $currency );
        $scale           = $currency_info['scale'];
        $catalog_version = $currency_info['catalog_version'];
        
        $decimal = DecimalInput::parse( $input, $separator, $scale );
        $parts   = explode( '.', $decimal );
        
        $integer_part    = $parts[0];
        $fractional_part = isset( $parts[1] ) ? str_pad( $parts[1], $scale, '0', STR_PAD_RIGHT ) : str_repeat( '0', $scale );
        
        $minor_units = $integer_part . $fractional_part;
        $minor_units = DecimalMath::normalize_integer( $minor_units );
        
        if ( strlen( $minor_units ) > 15 ) {
            throw new \InvalidArgumentException( 'out_of_range' );
        }
        
        return [
            'original_value'  => $decimal,
            'minor_units'     => $minor_units,
            'currency'        => $currency,
            'scale'           => $scale,
            'catalog_version' => $catalog_version,
        ];
    }
}
""",

    'src/Common/Regional/CalendarDate.php': """<?php
/**
 * CalendarDate class file.
 *
 * @package MF\VeLog
 */

namespace MF\VeLog\Common\Regional;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class for handling calendar dates.
 */
class CalendarDate {

    /**
     * Parse and validate a Gregorian date string.
     *
     * @param string $input Date string in YYYY-MM-DD format.
     * @return string
     * @throws \InvalidArgumentException On invalid date.
     */
    public static function parse( string $input ): string {
        if ( ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $input, $matches ) ) {
            throw new \InvalidArgumentException( 'invalid_date' );
        }
        
        $year  = (int) $matches[1];
        $month = (int) $matches[2];
        $day   = (int) $matches[3];
        
        if ( $year < 1 || $year > 9999 ) {
            throw new \InvalidArgumentException( 'invalid_date' );
        }
        
        if ( ! checkdate( $month, $day, $year ) ) {
            throw new \InvalidArgumentException( 'invalid_date' );
        }
        
        return $input;
    }

    /**
     * Get the current date in a specific timezone based on unix seconds.
     *
     * @param int           $unix_seconds Unix timestamp.
     * @param \DateTimeZone $zone         Timezone object.
     * @return string
     */
    public static function today( int $unix_seconds, \DateTimeZone $zone ): string {
        $dt = new \DateTimeImmutable( '@' . $unix_seconds );
        $dt = $dt->setTimezone( $zone );
        return $dt->format( 'Y-m-d' );
    }
}
""",

    'src/Common/Regional/Formatter.php': """<?php
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
        if ( ! preg_match( '/^[0-9]+(?:\.[0-9]+)?$/', $canonical ) ) {
            throw new \InvalidArgumentException( 'invalid_number' );
        }
        
        if ( '.' !== $separator && ',' !== $separator ) {
            throw new \InvalidArgumentException( 'invalid_separator' );
        }
        
        $allowed_group = [ '', ',', '.', ' ', "\xc2\xa0" ];
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
""",

    'tests/Unit/CurrencyCatalogTest.php': """<?php
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
""",

    'tests/Unit/RegionalPrimitivesTest.php': """<?php
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
        
        $invalid_cases = [
            [ '', '.', 2, 'invalid_number' ],
            [ ' ', '.', 2, 'invalid_number' ],
            [ '1,234.5', '.', 2, 'invalid_number' ],
            [ '1.234,5', ',', 2, 'invalid_number' ],
            [ '1e3', '.', 2, 'invalid_number' ],
            [ '-123', '.', 2, 'invalid_number' ],
            [ '123.456', '.', 2, 'invalid_precision' ],
            [ str_repeat( '9', 257 ), '.', 2, 'invalid_number' ],
        ];
        
        foreach ( $invalid_cases as $case ) {
            try {
                DecimalInput::parse( $case[0], $case[1], $case[2] );
                $this->fail( 'Expected exception for case ' . $case[0] );
            } catch ( \InvalidArgumentException $e ) {
                $this->assertSame( $case[3], $e->getMessage() );
            }
        }
        
        try {
            DecimalInput::parse( [], '.', 2 );
            $this->fail();
        } catch ( \InvalidArgumentException $e ) {
            $this->assertSame( 'Input must be a string', $e->getMessage() );
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
        
        $usd = Money::parse( '12.34', 'USD', '.' );
        $this->assertSame( '1234', $usd['minor_units'] );
        $this->assertSame( 2, $usd['scale'] );
        
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
        
        $invalid = [ '2025-02-29', '0000-01-01', '2024-1-1' ];
        foreach ( $invalid as $date ) {
            try {
                CalendarDate::parse( $date );
                $this->fail( "Expected failure for {$date}" );
            } catch ( \InvalidArgumentException $e ) {
                $this->assertSame( 'invalid_date', $e->getMessage() );
            }
        }
        
        $unix = 1704069000; // 2024-01-01T00:30:00Z
        
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
}
"""
}

for path, content in files.items():
    with open(path, 'w') as f:
        f.write(content)

