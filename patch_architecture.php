<?php
$f = 'ai-document/architecture.md';
$c = file_get_contents($f);
$old = <<<TEXT
## Regional Primitives (CORE-002)

The `MF\VeLog\Common\Regional` namespace provides pure PHP helpers for distance, currency, and date operations:
- `Distance::to_decimal( \$input, \$unit, \$places )` converts localized distances to canonical millimeter strings.
- `DecimalMath` provides exact half-up logic without binary floats.
- `Money::parse( \$input, \$currency_code, \$separator )` returns exact original strings, resolving scale from `CurrencyCatalogData`.
- `CalendarDate::parse()` and `CalendarDate::today()` handle strict date-only values avoiding timezone offsets.
- `DecimalInput` enforces rigid numeric ASCII character constraints (no NUL bytes), limiting values to 256 bytes.
- `CurrencyCatalogData` is statically generated from Unicode CLDR via `derive-catalog.php` (no runtime network/database dependencies) and includes 153 explicitly active currencies.
TEXT;

$new = <<<TEXT
## Regional Primitives (CORE-002)

The `MF\VeLog\Common\Regional` namespace provides pure PHP helpers for distance, currency, and date operations:
- `Distance::parse( \$input, \$unit, \$separator )` returns an array with `original_value`, `unit`, and `canonical_mm`.
- `Distance::to_decimal( \$canonical_mm, \$unit, \$places )` takes canonical_mm and returns a fixed-place display decimal.
- `DecimalMath` provides exact half-up logic without binary floats. All stored/full quantities are exact decimal strings, and arithmetic is handled as bounded intermediates.
- `Money::parse( \$input, \$currency_code, \$separator )` returns `original_value`, `minor_units`, `currency`, `scale`, and `catalog_version` using `CurrencyCatalog`.
- `CalendarDate::parse()` and `CalendarDate::today()` handle strict date-only values avoiding timezone offsets.
- `DecimalInput` enforces rigid numeric ASCII character constraints (no NUL bytes), limiting values to 256 bytes.
- `CurrencyCatalogData` is statically generated from Unicode CLDR via `derive-catalog.php` (no runtime network/database dependencies) and includes explicitly active currencies.
TEXT;

$c = str_replace(trim($old), trim($new), $c);
file_put_contents($f, $c);
