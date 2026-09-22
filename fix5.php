<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = str_replace("// 2024-03-11T04:30:00Z", "// Date is 2024-03-11T04:30:00Z.", $c);
file_put_contents($f, $c);
