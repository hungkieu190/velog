<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = str_replace(
    "\$this->assertSame( '1,234.56', Formatter::decimal( \$usd['original_value'], 2, '.', ',' ) );\n\t\t\$this->assertSame( '1.234,56', Formatter::decimal( \$usd['original_value'], 2, ',', '.' ) );",
    "\$this->assertSame( '1,234.56', Formatter::decimal( \$usd['original_value'], '.', ',' ) );\n\t\t\$this->assertSame( '1.234,56', Formatter::decimal( \$usd['original_value'], ',', '.' ) );",
    $c
);
file_put_contents($f, $c);
