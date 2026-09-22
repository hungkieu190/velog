<?php
$f = 'src/Common/Regional/DecimalMath.php';
$c = file_get_contents($f);
$c = str_replace(
    "list( \$quotient, \$remainder ) = self::divide_small_with_remainder( \$a, \$b );",
    "list( \$quotient, \$remainder ) = self::divide_small_with_remainder( \$a, \$b );\n\t\t\$quotient  = (string) \$quotient;\n\t\t\$remainder = (int) \$remainder;",
    $c
);
file_put_contents($f, $c);
