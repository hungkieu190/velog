# Builder Round 8 Read-Only Delta Proposal Revision 6

## 1. `read_raw_option()` Return Declaration (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `read_raw_option()`
- **Valid PHP Mechanism**:
  ```php
  private static function read_raw_option(): string|null|\WP_Error {
  ```
  Returns `null` for a missing row, the exact raw `string` bytes for a successful read, and `new \WP_Error(...)` for an SQL failure.
- **Verification**: `php -l src/Common/Regional/ShopSettings.php`. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 2. Exact First-Insert Query & Duplicate Conflict (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `save_settings()`
- **Mechanism**:
  ```php
  $affected = $wpdb->query(
      $wpdb->prepare(
          "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
          self::OPTION_NAME,
          $new_serialized
      )
  );
  if ( $affected === false ) {
      if ( strpos( $wpdb->last_error, 'Duplicate entry' ) !== false ) {
          return new \WP_Error( 'stale_version' );
      }
      return new \WP_Error( 'db_error' );
  }
  if ( $affected !== 1 ) {
      return new \WP_Error( 'db_error' );
  }
  // Success path proceeds...
  ```
- **Verification**: `tests/Unit/ShopSettingsTest.php`. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 3. Valid PHP Syntax for Validators (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**:
  ```php
  'currency_code' => function($v) {
      if ( $v === 'JPY' ) {
          try {
              \MF\VeLog\Common\Regional\CurrencyCatalog::get( 'JPY' );
              return 'JPY';
          } catch ( \Exception $e ) {
              return null;
          }
      }
      return null;
  },
  ```
- **Verification**: `php -l tests/fixtures/core-004-verify.php`. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 4. Coherent Historical Validators (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**:
  ```php
  'odometer'       => function($v) { return ( is_int( $v ) && $v >= 0 ) ? $v : null; },
  'distance_unit'  => function($v) { return $v === 'km' ? 'km' : null; },
  'currency_scale' => function($v) { return $v === 0 ? 0 : null; },
  ```
- **USD/KWD Sequence**: The historical record is seeded initially while global settings are `km/JPY`. The global `ShopSettings::save_settings()` is then exercised to write `mi/USD` and `km/KWD` to `velog_settings`. After each global settings write, the previously created seed record is read from the database, proving that its envelope (`_mf_velog_record` raw database value) is completely unaltered by the global changes and remains identical to the original `km/JPY/0` seed state.
- **Verification**: `tests/workflow/core-004-verify.sh`. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 5. RecordRepository::get() Contract (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**:
  ```php
  $manager = get_user_by( 'login', 'manager1' );
  $record = \MF\VeLog\Common\Storage\RecordRepository::get( 'mf_velog_service', $record_id, clone $manager );
  
  assert( $record->get_distance()->get_unit() === 'km' );
  assert( $record->get_cost()->get_currency()->get_code() === 'JPY' );
  assert( $record->get_cost()->get_currency()->get_scale() === 0 );
  assert( $record->get_version() === 1 );
  assert( $record->get_created_by() === $manager->ID );
  ```
- **Verification**: `bash tests/workflow/product-smoke.sh --task=CORE-004`. Expected exit `0`. Future evidence: `builder-round-8/test-output.log`.

## 6. Integrated HTTP Endpoint Verification (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh` (Called by `tests/workflow/product-smoke.sh`)
- **Mechanism**: Reuses the random `$PORT` and isolated environment in `product-smoke.sh`.
  ```bash
  # Inside the test flow after WP install:
  wp user create manager1 mgr@example.com --role=administrator --user_pass=pass1
  wp user create sub1 sub@example.com --role=subscriber --user_pass=pass1
  
  # Obtain cookies
  curl -c cookie_mgr.txt -d "log=manager1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  curl -c cookie_sub.txt -d "log=sub1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  
  # Obtain nonces via WP-CLI
  NONCE_MGR=$(wp eval 'wp_set_current_user(get_user_by("login","manager1")->ID); echo wp_create_nonce("velog_settings_nonce");')
  NONCE_SUB=$(wp eval 'wp_set_current_user(get_user_by("login","sub1")->ID); echo wp_create_nonce("velog_settings_nonce");')
  
  # Submit request
  HTTP_CODE=$(curl -s -o out.txt -w "%{http_code}" -b cookie_mgr.txt -d "action=velog_save_settings&velog_settings_nonce=$NONCE_MGR&distance_unit=km&currency_code=JPY" "http://localhost:$PORT/wp-admin/admin-post.php")
  ```
- **Verification**: Executed within `product-smoke.sh`. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 7. Exact HTTP Outcomes and Row Assertions (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**:
  - **Manager with valid nonce**:
    Expected HTTP Status: `302`.
    Assertion: `wp option get velog_settings` validates row mutation success.
  - **Denied subscriber with valid nonce**:
    Expected HTTP Status: `403`.
    Assertion: Row remains strictly unchanged.
  - **Manager with invalid nonce**:
    Expected HTTP Status: `403`.
    Assertion: Row remains strictly unchanged.
  - **Malformed POST (manager, valid nonce, bad data)**:
    Expected HTTP Status: `302`.
    Assertion: Response URL contains `error=invalid_input`. Row remains strictly unchanged.
  - **Unchanged Row Check**:
    ```bash
    ROW_BEFORE=$(wp option get velog_settings --format=json 2>/dev/null || echo "")
    # ... curl request ...
    ROW_AFTER=$(wp option get velog_settings --format=json 2>/dev/null || echo "")
    if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then exit 1; fi
    ```
- **Verification**: Executed within `product-smoke.sh`. Outer runner exits `1` on mismatch. Expected exit `0`. Future evidence: `builder-round-8/commands.log`.

## 8. Removal of Unsafe DROP DATABASE (F-005)
- **File/Symbol**: `tests/workflow/product-smoke.sh`
- **Mechanism**: The previously proposed default-socket `DROP DATABASE` command is completely removed. Cleanup strictly reuses the existing ownership model native to `product-smoke.sh` by stopping the private MariaDB process (`$DB_PID`), stopping the private PHP process (`$PHP_PID`), and `rm -rf` deleting the owned `$TMP_DIR` (which safely encapsulates the `mysql.sock` and private datadir without affecting the real database).
- **Verification**: Manual code inspection of cleanup trap script. Expected exit `0`. Future evidence: `builder-round-8/cleanup-results.log`.

## 9. Established Signal Lifecycle (F-005)
- **File/Symbol**: `tests/workflow/product-smoke.sh`
- **Mechanism**: Modifies the signal traps in `product-smoke.sh` to explicitly preserve standardized exits:
  ```bash
  trap 'cleanup' EXIT
  trap 'exit 130' INT
  trap 'exit 143' TERM
  ```
  Since `cleanup()` already records the current exit code (`local exit_code=$?`) and ends with `exit $exit_code`, an `INT` (which triggers `exit 130`) will pass `130` sequentially into the `EXIT` trap, successfully performing cleanup and exiting `130`. 
- **Verification**: Manually sending SIGINT (`kill -INT <pid>`) to the runner. The outer process must receive an exit code of `130` and leave no orphaned PID. Future evidence: `builder-round-8/cleanup-results.log`.
