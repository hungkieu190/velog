<?php
$f = 'tests/Unit/RegionalPrimitivesTest.php';
$c = file_get_contents($f);
$c = str_replace(
    [ "\"\0123\", \"123\0\", \"12\03\",", "\"\n3\", \"123\0\", \"12\03\"," ],
    "\"\\0\" . \"123\", \"123\" . \"\\0\", \"12\" . \"\\0\" . \"3\",",
    $c
);
file_put_contents($f, $c);
