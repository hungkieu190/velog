# Builder Round 7 Read-Only Delta Proposal Revision 5

## 1. ShopSettings::read_raw_option() Return Contract (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `read_raw_option()`
- **Mechanism**: Returns `?string|\WP_Error`.
  - **Missing row**: Returns `null` (when `$wpdb->get_var()` is `null` and `$wpdb->last_error` is `""`).
  - **Successful row**: Returns exact raw `string` bytes.
  - **SQL failure**: Returns `new \WP_Error('db_error', ...)` (when `$wpdb->last_error !== ""`).
- **Data Flow**: `get_settings()` calls `read_raw_option()`, handles the `\WP_Error` immediately, and otherwise deserializes the string/null into the validated array format. `save_settings()` calls `read_raw_option()` to fetch the exact raw `string` (or `null`) and uses it natively as the CAS comparison predicate `BINARY %s` without round-tripping through the array.
- **Verification**: `tests/Unit/ShopSettingsTest.php` running via `composer run test -- --filter test_read_raw_option`. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 2. Unconfigured Envelope Invariants (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `get_settings()`
- **Mechanism**: The unconfigured state has exact, rigid invariants: `schema_version` exactly `"1.0.0"`, `record_version` exactly `0`, `configured` exactly `false`, `distance_unit` exactly `""`, `currency_code` exactly `""`, `currency_scale` exactly `0`, `catalog_version` exactly `CurrencyCatalog::VERSION`, `region` exactly `""`. 
- **Rejection Cases**: Any loaded array lacking these exact keys, or having type mismatches, or having `record_version > 0` while `configured === false`, is explicitly rejected returning `\WP_Error('corrupt_settings')`.
- **Verification**: `tests/Unit/ShopSettingsTest.php` passing malformed missing states. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 3. Database Result Handling (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `save_settings()`
- **First-Insert Mechanism**: A conditional `INSERT` query using `SELECT ... WHERE NOT EXISTS (SELECT 1 FROM wp_options WHERE option_name = %s)`. 
- **Database Result Matrix**:
  - `$wpdb->query() === false`: Returns `\WP_Error('db_error')`.
  - `$affected_rows === 1`: Success.
  - `$affected_rows === 0`: Returns `\WP_Error('stale_version')`.
  - Any unexpected affected-row count (e.g., > 1): Returns `\WP_Error('db_error')`.
- **Verification**: `tests/Unit/ShopSettingsTest.php` testing CAS failure outcomes. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 4. Complete Option-Cache Handling (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `save_settings()`
- **Mechanism**: Following `$affected_rows === 1` for both insert and update:
  ```php
  wp_cache_delete(self::OPTION_NAME, 'options');
  wp_cache_delete('alloptions', 'options');
  $notoptions = wp_cache_get('notoptions', 'options');
  if (is_array($notoptions) && isset($notoptions[self::OPTION_NAME])) {
      unset($notoptions[self::OPTION_NAME]);
      wp_cache_set('notoptions', $notoptions, 'options');
  }
  ```
- **Verification**: A fresh-process assertion in bash runner `tests/workflow/core-004-cache.sh` running `wp eval 'print_r(MF\VeLog\Common\Regional\ShopSettings::get_settings());'` to prove the exact winner is visible immediately after a write. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 5. Concurrency Seam (F-004)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `save_settings()`
- **Mechanism**: The seam relies on an explicitly defined constant guard `MF_VELOG_TEST_CONCURRENT` that is undefined by default.
  ```php
  if (defined('MF_VELOG_TEST_CONCURRENT') && MF_VELOG_TEST_CONCURRENT === true) {
      do_action('mf_velog_test_concurrent_barrier');
  }
  ```
- **Location**: Executed inside `save_settings()` immediately after `read_raw_option()` and before `$wpdb->query()`.
- **Production Proof**: Because `MF_VELOG_TEST_CONCURRENT` is not defined in WordPress core or plugin bootstrap, evaluating `defined(...)` is strictly `false`. The action `do_action()` cannot be invoked, meaning no other plugin can hijack or block the production save path.
- **Verification**: `tests/workflow/core-004-concurrent.sh`. Asserts exactly 1 success, 1 conflict, exactly 1 version increment, exactly matched raw bytes, and cache-visible payload. Outer bash script exits nonzero (`exit 1`) on any timeout or mismatch. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 6. Historical Fixture Canonical Validators (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**: Registers the exact canonical schema:
  ```php
  RecordSchema::reset_for_testing();
  RecordSchema::register('mf_velog_service', [
      'capability' => 'mf_velog_create_services',
      'read_capability' => 'mf_velog_read_records',
      'states' => ['draft', 'finalized'],
      'fields' => [
          'odometer' => fn($v) => (is_int($v) && $v >= 0) ? $v : null,
          'distance_unit' => fn($v) => in_array($v, ['km', 'mi'], true) ? $v : null,
          'currency_code' => fn($v) => { try { CurrencyCatalog::get($v); return $v; } catch (\Exception $e) { return null; } },
          'currency_scale' => fn($v) => (is_int($v) && $v >= 0) ? $v : null,
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
- **Verification**: Valid capable actor runs `RecordRepository::create()`. Runner asserts ID and `_mf_velog_record` creation. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 7. Historical Switch Sequence (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**: Sequence executes `km/JPY` (initial) -> `mi/USD` (switch 1) -> `km/KWD` (switch 2) -> `ar` RTL locale change (switch 3).
- **Assertions**: After every change, the test queries raw `$wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} ...")` for `_mf_velog_record` and strictly asserts `===` identical authoritative bytes compared to the captured initial seed. Also queries `RecordRepository::read()` and strictly asserts `===` for parsed distance identity, currency code, scale, version, and audit envelope.
- **Verification**: `tests/workflow/core-004-verify.sh`. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 8. Actual HTTP Endpoint Isolated Runner (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh` and `RegionalSettingsPage::handle_save()`
- **Mechanism**: The runner starts a local PHP built-in web server (`php -S localhost:8080 -t $WP_DIR & PHP_SERVER_PID=$!`) providing a real HTTP interface.
- **Test Matrix via curl**: Uses `curl -s -o out.txt -w "%{http_code}" -d "action=velog_save_settings..." http://localhost:8080/wp-admin/admin-post.php` with various authenticated cookies (manager, subscriber) and nonces.
- **Failure Assertions**: Asserts HTTP `302` for valid inputs and HTTP `403` (or WP die) for denied/bad nonce cases. Reads `$wpdb->options` before and after denied/malformed POST cases to prove exact raw option bytes are completely unchanged. Any failed HTTP or byte assertion exits the outer script with `1`.
- **Verification**: Command `bash tests/workflow/core-004-endpoint.sh`. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 9. PHPCS Lint & 20 Line-Length Warnings (F-005)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` and `src/Admin/RegionalSettingsPage.php`
- **Mechanism**: Fix all 20 line-length warnings natively by reformatting arrays, string concatenation, and comments strictly below the 120-character limit. No PHPCS configuration changes or inline comments will be used to suppress or alter the line-length rule.
- **Verification**: `composer run lint -- --report=full`. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 10. WordPress.WP.Capabilities Sniff Configuration (F-005)
- **File/Symbol**: `phpcs.xml`
- **Mechanism**: Configures the exact installed `WordPress.WP.Capabilities` sniff using the `custom_capabilities` array property, precisely listing only the 6 specific VeLog capabilities registered in `src/Core/Capabilities.php`.
  ```xml
  <rule ref="WordPress.WP.Capabilities">
      <properties>
          <property name="custom_capabilities" type="array">
              <element value="mf_velog_manage_customers"/>
              <element value="mf_velog_manage_vehicles"/>
              <element value="mf_velog_create_services"/>
              <element value="mf_velog_read_records"/>
              <element value="mf_velog_manage_reminders"/>
              <element value="mf_velog_manage_settings"/>
          </property>
      </properties>
  </rule>
  ```
- **Verification**: `composer run lint -- --report=full`. Expected exit `0` (proving all 6 unknown-capability warnings are natively resolved). Future evidence: `builder-round-8/commands.log`.

## 11. Disposable WordPress/Database Cleanup Mechanism (F-005)
- **File/Symbol**: `tests/workflow/product-smoke.sh`
- **Mechanism**: Uses the project-supported `product-smoke.sh` which provisions a completely isolated WP directory and a fresh test database with a unique prefix (e.g., `velog_test_$$`). 
- **Temp Path & Process Ownership**:
  ```bash
  TEST_DB="velog_test_$$"
  TMP_DIR="/tmp/velog_test_env_$$"
  PID_A=""
  PID_B=""
  if [[ ! "$TMP_DIR" == /tmp/velog_test_env_* ]] || [ -z "$TMP_DIR" ]; then exit 1; fi
  ```
- **Idempotent Guaranteed Cleanup**:
  ```bash
  CLEANUP_DONE=0
  cleanup() {
      if [ $CLEANUP_DONE -eq 1 ]; then return; fi
      CLEANUP_DONE=1
      if [ -n "$PID_A" ]; then kill $PID_A 2>/dev/null || true; wait $PID_A 2>/dev/null || true; fi
      if [ -n "$PID_B" ]; then kill $PID_B 2>/dev/null || true; wait $PID_B 2>/dev/null || true; fi
      mysql -u root -e "DROP DATABASE IF EXISTS $TEST_DB"
      rm -rf "$TMP_DIR"
  }
  trap 'STATUS=$?; cleanup; exit $STATUS' EXIT INT TERM
  ```
- **Safety**: Cleanup guarantees it strictly waits for its own children, drops its unique test database, and recursively deletes its exact prefix directory without ever touching the live `velog_settings` option.
- **Verification**: Bash script exits. Expected exit `0`. Future evidence: `builder-round-8/cleanup-results.log`.

## 12. New Implementation Evidence Directory
- **Location**: `ai-document/evidence/CORE-004/builder-round-8/`
- **Files**: `commands.log`, `test-output.log`, `cleanup-results.log`, `manual-ui.md`. All future outputs, manual checklists, and NOT VERIFIED markers will reside here exclusively without polluting prior rounds.
