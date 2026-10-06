# CORE-004 Architect takeover closure

## Decision

`SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER`: CORE4-F-002, CORE4-F-004, and CORE4-F-005 are closed on 2026-10-05 under the user-approved repeated-failure exception. This is explicitly non-independent implementation and acceptance by Codex Architect.

The incoming `implementation-round-3/report.md` was insufficient by itself: it contained no retained command output, and independent PHPCS/PHPStan checks initially failed. The Architect inspected the diff, found a two-read lost-update window in `ShopSettings::save_settings()`, corrected the implementation and oracles directly, and reran the complete takeover matrix.

## Architect corrections

- F-002: settings now use one raw option snapshot for strict decoding, record-version validation, and binary CAS. Stored envelopes reject missing/extra keys, wrong PHP types, unsupported schema/catalog versions, invalid km/mi values, unknown currencies, and incorrect catalog scales. Submitted nonce, version, unit, currency, and region shapes fail before unsafe casts or writes. Duplicate insert error 1062 maps to `stale_version`; other database errors map to `db_error`.
- F-004: the disposable fixture runs a strict two-process barrier inside `save_settings()` and proves exactly one success plus one stale writer. It proves exact JPY/km option bytes, USD/mi and KWD/km scale identity, a public `RecordRepository::create()` historical seed, audit/envelope shape, unchanged raw envelope bytes and SHA-256 after preference/locale changes, and exact retention through uninstall/reactivation. Real authenticated HTTP checks prove denied actor, invalid nonce, and malformed-array rejection without option mutation.
- F-005: line-length/Yoda and PHPStan findings were fixed, the custom-capability PHPCS configuration was narrowed to the installed VeLog capabilities, architecture/internationalization documentation was updated, production assets were rebuilt, and the browser walkthrough covered labels, keyboard order, 320px, long Arabic content, real RTL, and unrelated-screen asset exclusion.

## Retained evidence

- `composer-test.log`: exit 0; 83 tests, 302 assertions on PHP 8.3.6.
- `composer-lint.log`: exit 0; PHPCS and PHPStan pass. The PHPStan age notice is informational.
- `phpcs.log`: exit 0 with no warnings or errors.
- `product-smoke-wp-6.4.3.log`: exit 0; concurrency, exact bytes, historical record, retention, HTTP negative controls, and cleanup path pass.
- `product-smoke-wp-6.7.2.log`: exit 0 with the same matrix; the opposite race worker may win, while the one-winner/one-stale invariant remains exact.
- `npm-production.log`: exit 0; four production files built.
- `ai-document/walkthroughs/CORE-004.md`: actual disposable-browser observations.
- `git diff --check`: exit 0 after final changes.

WP-CLI emits its standard redirect diagnostic while the fixture intentionally intercepts `wp_safe_redirect()`; the outer assertions and both runners exit 0. No task-owned WordPress, PHP, MariaDB, barrier, browser, hold-script, or diagnostic resource remained after cleanup.

## Remaining scope

CORE4-F-001, CORE4-F-003, and CORE4-F-006 remain open on the normal Builder/independent Architect review path as directed. Evidence generated during takeover may support their next review, but this closure does not change their disposition.
