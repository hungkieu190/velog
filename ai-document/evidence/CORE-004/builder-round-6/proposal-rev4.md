# Builder Round 6 Read-Only Proposal Revision 4 for F-002, F-004, F-005

## 1. ShopSettings Storage Reading & Validation (F-002)
- **`read_raw_option()`**: Will use `$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_NAME))` and return the raw string.
- **Missing vs SQL Error**: If the result is `null` and `$wpdb->last_error` is non-empty, return `WP_Error('db_error')`. If `$wpdb->last_error` is empty, return an unconfigured default array.
- **Deserialization & Validation**: The raw bytes are kept separate. We use `unserialize($raw_option, ['allowed_classes' => false])`. If decoding fails (returns `false` and `$raw_option` is not `serialize(false)`), is an object, has an unsupported `schema_version` (not `"1.0.0"`), or has an unknown key, it fails closed returning `WP_Error('corrupt_settings')`.
- **Allowed Keys**: Exactly `schema_version`, `record_version`, `configured`, `distance_unit`, `currency_code`, `currency_scale`, `catalog_version`, `region`.
- **State-Specific Invariants**:
  - Unconfigured (`configured === false`): `distance_unit`, `currency_code`, `catalog_version`, `region` may be empty strings; `currency_scale` may be `0`.
  - Configured (`configured === true`): `record_version > 0`, `distance_unit` must be `'km'` or `'mi'`, `currency_code` must be a known key in `CurrencyCatalog`, `currency_scale` must exactly match the catalog's scale, `catalog_version` must equal `CurrencyCatalog::VERSION`, `region` must be a string.
- **Error Propagation**: `get_settings()` returns `array|WP_Error`. Callers (e.g., `require_configured()`, `RegionalSettingsPage::render()`, `save_settings()`) check `is_wp_error()` before array access and propagate the exact error (preventing fatal errors or partial writes).

## 2. ShopSettings Atomic CAS Update (F-002)
- **Prepared Conditional Update**:
  `$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = BINARY %s", $new_serialized, self::OPTION_NAME, $raw_prior_option))`
- **Distinguishing Failures**:
  - `$affected === 1`: Success.
  - `$affected === 0` and `$wpdb->last_error === ''`: Returns `WP_Error('stale_version')`.
  - `$affected === 0` and `$wpdb->last_error !== ''`: Returns `WP_Error('db_error')`.
- **First-Insert Path**: Uses `INSERT IGNORE` or unique-key violation check. If it fails due to duplicate key, returns `WP_Error('stale_version')`. If `$wpdb->last_error` is non-empty for other reasons, returns `WP_Error('db_error')`.
- **Cache Invalidation**: On successful write, call `wp_cache_delete(self::OPTION_NAME, 'options')`.
- **Data Integrity**: Malformed/unknown stored data is never silently overwritten (a corrupt read prevents the save from starting). We prove case-different bytes cannot match the `BINARY` condition in testing.

## 3. Submitted-Input Validation (F-002)
- **`RegionalSettingsPage::handle_save()`**:
  - Checks if `$_POST['velog_settings_nonce']` is a string before calling `wp_unslash()`, `sanitize_key()`, or `wp_verify_nonce()`.
  - `record_version` is validated as a canonical decimal string matching `/^(0|[1-9][0-9]*)$/` before cast to integer, bounded to `PHP_INT_MAX`.
  - `distance_unit`, `currency_code`, and `region` are strictly type-checked with `is_string()` before any sanitizers. Arrays/objects result in instant rejection without warnings or writes.
- **Error Codes**:
  - `corrupt_settings`: Stored data is malformed.
  - `invalid_input`: Payload has invalid types or unrecognized values.
  - `stale_version`: Conflict during CAS.
  - `db_error`: MySQL query failure.
- **Redirection**: On failure, transient stores fixed error code and redirects to `admin.php?page=velog-settings&error=invalid_input` (no POST data in URL).

## 4. Concurrency Oracle (F-004)
- **Seam**: A test-only WordPress action `do_action('mf_velog_test_concurrent_barrier', 'velog_settings')` is placed *inside* `ShopSettings::save_settings()` immediately after `read_raw_option()` and before the `$wpdb->query` CAS execution.
- **Production Safety**: This hook is only triggered. In production, no callbacks are registered, making it a no-op.
- **Test Setup**: Bash script `tests/workflow/core-004-concurrent.sh` launches two separate WordPress PHP processes (`php tests/fixtures/worker.php A &` and `B &`) with separate DB connections.
- **Synchronization**: The worker script registers a callback on `mf_velog_test_concurrent_barrier` that creates a file `ready_{WORKER_ID}` and polls (timeout 5s) for the other worker's ready file. Once both read the same prior option bytes, they proceed simultaneously to write.
- **Assertions & Exit Codes**: Each worker outputs to `outcome_A.txt` and `outcome_B.txt`. The outer Bash runner waits on both PIDs. It asserts:
  - Exactly one worker exited `0` (success).
  - Exactly one worker exited `2` (conflict/`stale_version`).
  - DB `record_version` incremented exactly by 1.
  - `get_settings()` from a fresh third process matches the winning worker's payload exactly.
  - The raw option bytes exactly match the serialized winner.
  - Any timeout or mismatch results in the bash script exiting nonzero (`exit 1`).

## 5. Historical Oracle & Post Validation (F-004)
- **Isolated Service Schema**:
  ```php
  RecordSchema::reset_for_testing();
  RecordSchema::register('mf_velog_service', [
      'capability' => 'mf_velog_create_services',
      'states' => ['draft', 'finalized'],
      'fields' => [
          'odometer' => fn($v) => is_numeric($v) ? (int)$v : null,
          'distance_unit' => fn($v) => is_string($v) ? $v : null,
          'currency_code' => fn($v) => is_string($v) ? $v : null,
          'currency_scale' => fn($v) => is_int($v) ? $v : null,
      ],
      'field_policies' => [
          'odometer' => 'public',
          'distance_unit' => 'public',
          'currency_code' => 'public',
          'currency_scale' => 'public'
      ]
  ]);
  RecordSchema::seal();
  ```
- **Execution & Identity**: Run in a disposable WP site. Call `RecordRepository::create()` with a capable actor. Assert returned ID. Capture `_mf_velog_record` raw bytes and parsed payload.
- **Switch & Compare**: Change global settings via `ShopSettings::save_settings()` (e.g. km to mi, JPY to USD, locale change). Retrieve `_mf_velog_record` again. Assert raw bytes are strictly identical and interpreted identity is unchanged.
- **Negative Post Controls**: Test the actual `handle_save` endpoint with denied capabilities, malformed `$_POST`, and invalid nonces. Compare raw DB option row before and after to ensure it was unmodified.
- **Uninstall/Reinstall**: Explicitly marked **NOT VERIFIED**. We cannot guarantee a full WordPress reinstall lifecycle in a pure bash unit test without side-effects.

## 6. PHPCS Plan (F-005)
- **Capabilities Sniff**: Uses the installed sniff:
  ```xml
  <rule ref="WordPress.WP.Capabilities">
      <properties>
          <property name="custom_capabilities" type="array">
              <element value="mf_velog_manage_settings"/>
              <element value="mf_velog_manage_customers"/>
              <element value="mf_velog_manage_vehicles"/>
              <element value="mf_velog_create_services"/>
              <element value="mf_velog_read_records"/>
              <element value="mf_velog_manage_reminders"/>
          </property>
      </properties>
  </rule>
  ```
- **Direct Query Suppression**: In `ShopSettings.php`, immediately preceding the `$wpdb->query` statements for CAS:
  `// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching`
  This suppresses only the exact reported sniffs on the affected statements, without blanket disabling.

## 7. Resource Cleanup (F-005)
- **Disposable Environment**: Test runner provisions a fully isolated WP/DB instance (e.g. using `wp-env` or an isolated test DB) so pre-existing `velog_settings` are never touched.
- **Temp Path & Process Tracking**: Creates `TMP_DIR=$(mktemp -d)`. Validates `[ -n "$TMP_DIR" ]`. Tracks only spawned child PIDs (`PID_A`, `PID_B`).
- **Idempotent Cleanup**: Defines a bash function `cleanup()` that kills only those specific PIDs and removes `$TMP_DIR`.
  ```bash
  cleanup_done=0
  cleanup() {
      if [ $cleanup_done -eq 1 ]; then return; fi
      cleanup_done=1
      kill $PID_A $PID_B 2>/dev/null || true
      wait $PID_A $PID_B 2>/dev/null || true
      rm -rf "$TMP_DIR"
  }
  trap cleanup EXIT INT TERM
  ```
- **Results**: Execution logs, versions, exits, observations, and `NOT VERIFIED` instances will be written to `ai-document/evidence/CORE-004/builder-round-6/` (new round).
