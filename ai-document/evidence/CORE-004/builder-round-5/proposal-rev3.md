# Correction Proposal for F-002, F-004, F-005 - Revision 3

## 1. F-002: Settings envelope and exact CAS

### Storage Reading & Validation (`ShopSettings`)
- **`read_raw_option()`**: Will use `$wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_NAME ) )` to retrieve the exact stored byte string.
- **Differentiating Missing vs Error**: If `$wpdb->last_error` is non-empty, it returns a database error. If empty and result is `null`, it is genuinely missing (unconfigured).
- **Deserialization**: We will use `unserialize( $raw_string, ['allowed_classes' => false] )` to safely decode the value without object instantiation.
- **Strict Validation**: The parsed array will be checked against `self::SCHEMA_VERSION` (`"1.0.0"`). It must contain `catalog_version`, `configured` (bool), `record_version` (int), `distance_unit` ('km' or 'mi'), `currency_code` (string), and catalog-derived `currency_scale` (int). If any key is missing, or if unrecognized keys are present, or types are wrong, it is deemed corrupt to prevent silent data loss of unknown properties.
- **Error Propagation**: `get_settings()` signature will be updated to `get_settings(): array|\WP_Error`. `require_configured()` will return `\WP_Error` if `get_settings()` returns an error. `RegionalSettingsPage::render()` will check `is_wp_error($settings)`: if true, it renders an exact error notice (e.g., "Settings corrupted") and a disabled form, blocking writes.

### Atomic CAS Update (`save_settings()`)
- **CAS Predicate**: To perform a byte-exact CAS ignoring MySQL text collation, the query will use `BINARY`:
  `UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = BINARY %s`
  This proves case-different or spacing-different bytes cannot inadvertently match.
- **Outcomes**:
  - `$affected === 1`: Success. Cache invalidated via `wp_cache_delete(self::OPTION_NAME, 'options')`.
  - `$affected === 0` and `$wpdb->last_error === ''`: Stale version/conflict. Returns `WP_Error('stale_version')`.
  - `$wpdb->last_error !== ''`: Database error. Returns `WP_Error('db_error')`.

### Submitted Values (`RegionalSettingsPage::handle_save()`)
- **Bounded Parsing**: We will read `record_version` via `filter_var($_POST['record_version'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])`. If `false`, execution aborts immediately.
- **Type Checking**: Before applying `sanitize_text_field()` or WP string functions, we will strictly verify that `$_POST['distance_unit']`, `$_POST['currency_code']`, and `$_POST['region']` are scalar strings. Array/object POST payloads will be rejected outright.
- **Error Codes & Redirection**: We will distinguish corrupt stored data (`WP_Error('corrupt_settings')`) from invalid HTTP input. When `handle_save` detects invalid input, it will not log warnings or write to DB. Instead of exposing POST data in the URL, it sets a transient notice and redirects to `admin.php?page=velog-settings&error=invalid_input` (where `invalid_input` is a static allowlisted code).

## 2. F-004: Concurrency Oracle

- **Test Harness**: A Bash script `tests/workflow/core-004-concurrent.sh` will bootstrap two independent WordPress processes (`php concurrent-worker.php A & PID_A=$!`; `php concurrent-worker.php B & PID_B=$!`).
- **Two-Party Barrier**: Both workers call `ShopSettings::get_settings()` to read the same prior row version. They then write to a temporary file `ready_A` or `ready_B` and loop up to 5 seconds waiting for the other's ready file to establish a strict barrier at the service read/write boundary.
- **Execution & Assertions**: Once the barrier is crossed, both invoke `ShopSettings::save_settings()`.
  - Outer Runner uses `wait $PID_A; EXIT_A=$?` and `wait $PID_B; EXIT_B=$?`.
  - Assert exactly one worker exited with `0` (success) and one with `2` (conflict).
  - Assert `record_version` incremented exactly by `1` in the DB.
  - Assert `ShopSettings::get_settings()` (cache-visible state) reflects the winning worker's payload exactly.
  - The outer runner exits nonzero if any assertion fails.

## 3. F-004: Historical Oracle

- **Schema Registration**: The test fixture will unseal and register a valid isolated schema:
  ```php
  RecordSchema::reset_for_testing();
  RecordSchema::register('mf_velog_service', [
      'fields' => [
          'odometer' => fn($v) => is_numeric($v) ? (int)$v : null,
          'distance_unit' => fn($v) => is_string($v) ? $v : null,
          'currency_code' => fn($v) => is_string($v) ? $v : null,
          'currency_scale' => fn($v) => is_int($v) ? $v : null,
      ],
      'capability' => 'mf_velog_create_services'
  ]);
  RecordSchema::seal();
  ```
- **Historical Seed**: A valid actor will call `RecordRepository::create('mf_velog_service', ['odometer' => 10000, 'distance_unit' => 'km', 'currency_code' => 'JPY', 'currency_scale' => 0], $actor, wp_generate_uuid4())`. Canonical distance/currency identity is explicitly stored inside the record's `fields` array within the authoritative envelope.
- **Switch & Assert**: The fixture captures the exact byte string of `_mf_velog_record` via direct SQL. It modifies the shop settings (switching to `mi` and `USD`, changing locale). It re-queries the meta row and asserts it matches the originally captured byte string exactly, proving immutability of the interpreted historical values.
- **Negative Controls**: The verification matrix will execute POST requests for settings save without capabilities or valid nonces, asserting HTTP 403 / rejection. Uninstall/reinstall retention will be verified by running `uninstall.php` and asserting `velog_settings` option still exists in `$wpdb->options`.

## 4. F-005: Lint and Owned-Resource Cleanup

- **PHPCS Sniffs**:
  - The proposed `custom_capabilities` will be verified against the `WordPress.WP.Capabilities.RoleAndCapabilityCheck` sniff to ensure it resolves the warnings.
  - The CAS query will narrowly suppress `WordPress.DB.DirectDatabaseQuery.DirectQuery` and `WordPress.DB.DirectDatabaseQuery.NoCaching` using strictly inline `// phpcs:ignore` comments on the specific `$wpdb->query` lines.
- **Resource Cleanup**:
  - The bash runner establishes a unique temp directory `TMP_DIR=$(mktemp -d)` and stores it.
  - It tracks child processes (`PID_A`, `PID_B`).
  - A bash `trap` is registered for `EXIT`, `INT`, `TERM`: `trap 'kill $PID_A $PID_B 2>/dev/null; rm -rf "$TMP_DIR"; wp db query "DELETE FROM wp_options WHERE option_name = '\''velog_settings'\''"' EXIT`. This ensures both success and failure paths reap only its own children and clean up its exact DB footprint.
- **Evidence Documentation**: The results, including CLI output, WP/PHP versions, outer runner exit codes, manual UI observations (keyboard navigation, RTL layout), and any explicit `NOT VERIFIED` items, will be written to `ai-document/evidence/CORE-004/builder-round-5/report.md` and `commands.log`.
