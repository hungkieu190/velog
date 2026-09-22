<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = str_replace("\r", "", $c);
$c = str_replace("// minor digit boundary 15/16.", "// Minor digit boundary at 15 versus 16.", $c);
file_put_contents($f, $c);
