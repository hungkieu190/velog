<?php
$f = 'src/Common/Regional/Money.php';
$c = file_get_contents($f);
$c = str_replace(
    "\$fractional_part = isset( \$parts[1] ) ? str_pad( \$parts[1], \$scale, '0', STR_PAD_RIGHT ) : str_repeat( '0', \$scale );",
    "\$fractional_part = isset( \$parts[1] )\n\t\t\t? str_pad( \$parts[1], \$scale, '0', STR_PAD_RIGHT )\n\t\t\t: str_repeat( '0', \$scale );",
    $c
);
file_put_contents($f, $c);
