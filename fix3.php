<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = preg_replace('/\r\n/', "\n", $c);
$c = preg_replace('/\/\/ Valid cases remain correct/', '// Valid cases remain correct.', $c);
$c = preg_replace('/\/\/ Money/', '// Money.', $c);
$c = preg_replace('/\/\/ lowercase/', '// lowercase.', $c);
$c = preg_replace('/\/\/ minor digit boundary 15\/16/', '// minor digit boundary 15/16.', $c);
$c = preg_replace('/\/\/ Max\+1/', '// Max+1.', $c);
$c = preg_replace('/\/\/ NBSP grouping retains fraction/', '// NBSP grouping retains fraction.', $c);
file_put_contents($f, $c);
