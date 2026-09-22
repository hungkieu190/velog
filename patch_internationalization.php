<?php
$f = 'ai-document/internationalization.md';
$c = file_get_contents($f);

$old = <<<TEXT
### Currency Catalog Provenance

To support a strict list of 153 active currencies (as required by C2-F-003 and C2-F-004), a one-time development generator (`derive-catalog.php`) was built to download and parse Unicode CLDR data (including `currencyData.json` and `currencies.json`). The script validates source checksums, counts the active currencies that are legal tender within the selection date, and writes the hardcoded PHP catalog array to `src/Common/Regional/CurrencyCatalogData.php`. The catalog relies strictly on bundled PHP arrays and requires no runtime network or database requests.
TEXT;

$new = <<<TEXT
### Currency Catalog Provenance

To support a strict list of active currencies, a one-time development generator (`derive-catalog.php`) was built to download and parse Unicode CLDR 48.0.0 data (including `currencyData.json` and `currencies.json`) with a selection date of 2026-09-22. The script validates source checksums against the pinned manifest at [currency-source-verification.json](evidence/CORE-002/planning/currency-source-verification.json), counts the active currencies that are legal tender within the selection date, and writes the hardcoded PHP catalog array to `src/Common/Regional/CurrencyCatalogData.php`. 

The catalog metadata is a pinned snapshot, and its specific version (code, scale, and catalog_version) is retained explicitly in parsed money values. The catalog relies strictly on bundled PHP arrays and requires no runtime network or database requests.
TEXT;

$c = str_replace(trim($old), trim($new), $c);
file_put_contents($f, $c);
