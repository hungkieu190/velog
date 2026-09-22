<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = preg_replace("/\\\$this->fail\( 'Expected exception for case: ' \. var_export\(\\\$val, true\) \);/", "\$this->fail( 'Expected exception for case' );", $c);
file_put_contents($f, $c);
