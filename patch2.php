<?php
$f = 'ai-document/evidence/CORE-002/round-1/derive-catalog.php';
$c = file_get_contents($f);

// JSON decode failures
$c = str_replace(
    "\$currency_data = json_decode( \$downloads['currencyData.json'], true );",
    "\$currency_data = json_decode( \$downloads['currencyData.json'], true );\nif ( json_last_error() !== JSON_ERROR_NONE ) { fwrite( STDERR, \"JSON decode failed for currencyData.json\\n\" ); exit( 1 ); }",
    $c
);
$c = str_replace(
    "\$currencies_en = json_decode( \$downloads['currencies.json'], true );",
    "\$currencies_en = json_decode( \$downloads['currencies.json'], true );\nif ( json_last_error() !== JSON_ERROR_NONE ) { fwrite( STDERR, \"JSON decode failed for currencies.json\\n\" ); exit( 1 ); }",
    $c
);

// file_put_contents verification
$c = str_replace(
    "file_put_contents( \$target_dir . '/CurrencyCatalogData.php', \$php_data );\n\necho \"Catalog generated successfully at src/Common/Regional/CurrencyCatalogData.php\\n\";",
    "\$written = file_put_contents( \$target_dir . '/CurrencyCatalogData.php', \$php_data );\nif ( \$written !== strlen( \$php_data ) ) {\n    fwrite( STDERR, \"Failed to write full catalog data\\n\" );\n    exit( 1 );\n}\n\necho \"Catalog generated successfully at src/Common/Regional/CurrencyCatalogData.php\\n\";",
    $c
);

file_put_contents($f, $c);
