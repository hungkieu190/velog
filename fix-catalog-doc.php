<?php
$f = 'src/Common/Regional/CurrencyCatalogData.php';
$c = file_get_contents($f);
$c = str_replace('@return array', '@return array<string, array<string, mixed>>', $c);
file_put_contents($f, $c);
