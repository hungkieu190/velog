<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);

// Remove the closing brace of the class
$c = preg_replace('/}\n$/', '', $c);

$additional_tests = <<<TESTS

    /**
     * Test R2-1: Absolute whole-string validation prevents LF/CRLF/NUL/junk.
     */
    public function test_absolute_grammar_rejection() {
        \$invalid_dates = [ "2024-02-29\\n", "2024-02-29\\r\\n", "2024-02-29\\0", "2024-02-29x", "x2024-02-29" ];
        foreach ( \$invalid_dates as \$date ) {
            try {
                CalendarDate::parse( \$date );
                \$this->fail( "Expected invalid_date for " . bin2hex(\$date) );
            } catch ( \InvalidArgumentException \$e ) {
                \$this->assertSame( 'invalid_date', \$e->getMessage() );
            }
        }

        \$invalid_decimals = [ "500\\n", "500\\r\\n", "500\\0", "500 ", " 500" ];
        foreach ( \$invalid_decimals as \$val ) {
            try {
                Distance::to_decimal( \$val, 'km', 3 );
                \$this->fail( "Expected invalid_number for " . bin2hex(\$val) );
            } catch ( \InvalidArgumentException \$e ) {
                \$this->assertSame( 'invalid_number', \$e->getMessage() );
            }
            try {
                Formatter::decimal( \$val, '.' );
                \$this->fail( "Expected invalid_number for " . bin2hex(\$val) );
            } catch ( \InvalidArgumentException \$e ) {
                \$this->assertSame( 'invalid_number', \$e->getMessage() );
            }
        }
        
        // Valid cases remain correct
        \$this->assertSame( '0.001', Distance::to_decimal( '500', 'km', 3 ) );
        \$this->assertSame( '2024-02-29', CalendarDate::parse( '2024-02-29' ) );
        \$this->assertSame( '1,234.50', Formatter::decimal( '1234.50', '.', ',' ) );
    }

    /**
     * Test R2-2: DecimalInput parse correctly handles NUL and whitespace limits.
     */
    public function test_decimal_input_whitespace_and_limits() {
        \$this->assertSame( '123', DecimalInput::parse( " \t\n\r\x0B\x0C123 \t\n\r\x0B\x0C", '.', 2 ) );
        
        \$invalid_numbers = [
            "\\0123", "123\\0", "12\\03",
            "1 23", "1,23", "1a23", "1e3", "+123", "-123",
            null, [], 123
        ];
        
        foreach ( \$invalid_numbers as \$val ) {
            try {
                DecimalInput::parse( \$val, '.', 2 );
                \$this->fail( "Expected exception for case" );
            } catch ( \InvalidArgumentException \$e ) {
                \$this->assertSame( 'invalid_number', \$e->getMessage() );
            }
        }

        try {
            DecimalInput::parse( '123', 'x', 2 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_separator', \$e->getMessage() );
        }

        try {
            DecimalInput::parse( '123', '.', -1 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_precision', \$e->getMessage() );
        }
        try {
            DecimalInput::parse( '123', '.', 5 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_precision', \$e->getMessage() );
        }

        \$valid_256 = str_repeat('9', 256);
        \$this->assertSame( \$valid_256, DecimalInput::parse( \$valid_256, '.', 0 ) );
        
        \$invalid_257 = str_repeat('9', 257);
        try {
            DecimalInput::parse( \$invalid_257, '.', 0 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_number', \$e->getMessage() );
        }
    }

    /**
     * Test R2-3: Distance and money validations.
     */
    public function test_distance_and_money_validations() {
        try {
            Distance::parse( '1', 'ft', '.' );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_unit', \$e->getMessage() );
        }

        try {
            Distance::to_decimal( '1', 'ft', 3 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_unit', \$e->getMessage() );
        }

        try {
            Distance::to_decimal( '1e3', 'km', 3 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_number', \$e->getMessage() );
        }

        try {
            Distance::to_decimal( '-1000', 'km', 3 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_number', \$e->getMessage() );
        }
        
        try {
            Distance::to_decimal( '1609344000000000', 'mi', 3 ); // Max+1
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'out_of_range', \$e->getMessage() );
        }

        try {
            Distance::to_decimal( '1000', 'km', -1 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_precision', \$e->getMessage() );
        }

        try {
            Distance::to_decimal( '1000', 'km', 4 );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_precision', \$e->getMessage() );
        }

        // Money
        \$vnd = Money::parse( '0', 'VND', '.' );
        \$this->assertSame( '0', \$vnd['minor_units'] );

        \$usd = Money::parse( '1234.56', 'USD', '.' );
        \$this->assertSame( '1234.56', \$usd['original_value'] );
        \$this->assertSame( 'USD', \$usd['currency'] );
        \$this->assertSame( 2, \$usd['scale'] );
        \$this->assertArrayHasKey( 'catalog_version', \$usd );

        try {
            Money::parse( '123', 'usd', '.' ); // lowercase
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'unknown_currency', \$e->getMessage() );
        }

        // minor digit boundary 15/16
        \$money15 = Money::parse( str_repeat('9', 15), 'VND', '.' );
        \$this->assertSame( str_repeat('9', 15), \$money15['minor_units'] );

        try {
            Money::parse( str_repeat('9', 16), 'VND', '.' );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'out_of_range', \$e->getMessage() );
        }
    }

    /**
     * Test R2-4: Calendar DST and formatter config.
     */
    public function test_calendar_dst_and_formatter() {
        \$unix = 1710131400; // 2024-03-11T04:30:00Z
        \$ny_today = CalendarDate::today( \$unix, new \DateTimeZone( 'America/New_York' ) );
        \$this->assertSame( '2024-03-11', \$ny_today );

        try {
            Formatter::decimal( '1234.50', '.', 'x' );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_separator', \$e->getMessage() );
        }

        try {
            Formatter::decimal( '12a4', '.' );
            \$this->fail();
        } catch ( \InvalidArgumentException \$e ) {
            \$this->assertSame( 'invalid_number', \$e->getMessage() );
        }

        // NBSP grouping retains fraction
        \$nbsp = "\xc2\xa0";
        \$formatted = Formatter::decimal( '1234.56', '.', \$nbsp );
        \$this->assertSame( "1{\$nbsp}234.56", \$formatted );
    }
}
TESTS;

file_put_contents($f, $c . $additional_tests);
