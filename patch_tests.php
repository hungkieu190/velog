<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);

// 1. Update distance boundary test
$old_dist = "Distance::to_decimal( '1609344000000000', 'mi', 3 ); // Max+1.";
$new_dist = "\$this->assertSame( '999999999.999', Distance::to_decimal( '1609343999998391', 'mi', 3 ) );\n\t\t\tDistance::to_decimal( '1609343999998392', 'mi', 3 ); // Max+1.";
$c = str_replace($old_dist, $new_dist, $c);

// 2. Add arabic-indic and full-width to invalid_numbers
$old_invalid = "'+123',\n\t\t\t'-123',\n\t\t\tnull,";
$new_invalid = "'+123',\n\t\t\t'-123',\n\t\t\t'١٢٣',\n\t\t\t'１２３',\n\t\t\tnull,";
$c = str_replace($old_invalid, $new_invalid, $c);

// 3. Add money snapshot test
$old_usd = "\$usd = Money::parse( '12.34', 'USD', '.' );\n\t\t\$this->assertSame( '1234', \$usd['minor_units'] );\n\t\t\$this->assertSame( 2, \$usd['scale'] );";
$new_usd = <<<USD
\$usd = Money::parse( '1234.56', 'USD', '.' );
		\$this->assertSame( '1234.56', \$usd['original_value'] );
		\$this->assertSame( '123456', \$usd['minor_units'] );
		\$this->assertSame( 'USD', \$usd['currency'] );
		\$this->assertSame( 2, \$usd['scale'] );
		\$this->assertSame( '48.0.0-2026-09-22', \$usd['catalog_version'] );

		\$this->assertSame( '1,234.56', Formatter::decimal( \$usd['original_value'], 2, '.', ',' ) );
		\$this->assertSame( '1.234,56', Formatter::decimal( \$usd['original_value'], 2, ',', '.' ) );
		
		\$usd2 = Money::parse( '12.34', 'USD', '.' );
		\$this->assertSame( '1234', \$usd2['minor_units'] );
		\$this->assertSame( 2, \$usd2['scale'] );
USD;
$c = str_replace($old_usd, $new_usd, $c);

file_put_contents($f, $c);
