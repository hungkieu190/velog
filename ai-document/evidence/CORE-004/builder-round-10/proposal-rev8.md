# Builder Round 10 Read-Only Delta Proposal Revision 8

## 1. Test-Only Authenticated Nonce Endpoint (F-004)
- **File/Symbol**: `tests/workflow/product-smoke.sh`
- **Mechanism**: Inject an MU-plugin containing a test-only AJAX endpoint that generates the specific `velog_save_settings` nonce using the real authenticated session cookies. This MU-plugin is automatically removed when the disposable `$WP_DIR` is cleaned up.
  ```php
  # Inside product-smoke.sh:
  cat << 'EOF' > "$WP_DIR/wp-content/mu-plugins/test-nonce-endpoint.php"
  <?php
  add_action( 'wp_ajax_test_get_velog_nonce', function() {
      echo wp_create_nonce( 'velog_save_settings' );
      wp_die();
  } );
  EOF
  ```
- **Verification**: Invoked during the test runner execution.

## 2. Explicit Runner Context and Login Identity Proof (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**: The helper receives arguments explicitly and verifies the `wordpress_logged_in` cookie fragment to prove login success.
  ```bash
  PORT=$1
  WP_DIR=$2
  
  # Manager Login
  curl -s -c cookie_mgr.txt -d "log=manager1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  if ! grep -q "wordpress_logged_in" cookie_mgr.txt; then echo "Manager login failed"; exit 1; fi
  NONCE_MGR=$(curl -s -b cookie_mgr.txt "http://localhost:$PORT/wp-admin/admin-ajax.php?action=test_get_velog_nonce")
  
  # Subscriber Login
  curl -s -c cookie_sub.txt -d "log=sub1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  if ! grep -q "wordpress_logged_in" cookie_sub.txt; then echo "Subscriber login failed"; exit 1; fi
  NONCE_SUB=$(curl -s -b cookie_sub.txt "http://localhost:$PORT/wp-admin/admin-ajax.php?action=test_get_velog_nonce")
  ```

## 3. Base64-Encoded Raw Option Byte Oracle (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**: Use `wp eval` to directly query `$wpdb` for the raw `option_value` bytes, returning them as a base64 string to prevent serialization loss or shell formatting issues.
  ```bash
  get_raw_bytes() {
      wp eval 'echo base64_encode( (string) $GLOBALS["wpdb"]->get_var( "SELECT option_value FROM {$GLOBALS["wpdb"]->options} WHERE option_name = \"velog_settings\"" ) );' --path="$WP_DIR" 2>/dev/null
  }
  ROW_BEFORE=$(get_raw_bytes)
  # ... perform denied request ...
  ROW_AFTER=$(get_raw_bytes)
  if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then echo "Row mutated!"; exit 1; fi
  ```

## 4. Exact HTTP 302 Location Header Oracle (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**: Capture curl headers to a file using `-D` and explicitly grep the exact redirect destination error.
  ```bash
  HTTP_CODE=$(curl -s -o out.txt -D headers.txt -w "%{http_code}" -b cookie_mgr.txt -d "action=velog_save_settings&velog_settings_nonce=$NONCE_MGR&distance_unit[]=km&currency_code=JPY" "http://localhost:$PORT/wp-admin/admin-post.php")
  
  if [ "$HTTP_CODE" != "302" ]; then exit 1; fi
  if ! grep -q "Location: .*admin.php?page=velog-settings&error=invalid_input" headers.txt; then
      echo "Failed to redirect to invalid_input error"
      exit 1
  fi
  ```

## 5. Repository Audit Envelope Assertion (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**: Guard the cloned actor type and explicitly assert the existence and identity of the historical audit trail.
  ```php
  $manager = get_user_by( 'login', 'manager1' );
  if ( ! ( $manager instanceof \WP_User ) ) { echo "Fail: User not found\n"; exit( 1 ); }
  
  $record = \MF\VeLog\Common\Storage\RecordRepository::get( 'mf_velog_service', $record_id, clone $manager );
  if ( is_wp_error( $record ) ) { echo "Fail: get() returned error\n"; exit( 1 ); }
  
  if ( empty( $record['audit'] ) || ! is_array( $record['audit'] ) ) { echo "Fail: Empty audit envelope\n"; exit( 1 ); }
  if ( $record['audit'][0]['actor_id'] !== $manager->ID ) { echo "Fail: Audit identity mismatch\n"; exit( 1 ); }
  ```

## 6. Errno 1062 and Non-1062 Error Classification Oracle (F-002)
- **File/Symbol**: `tests/Unit/ShopSettingsTest.php`
- **Mechanism**: Define negative controls utilizing PHPUnit mocks on `$wpdb` to accurately inject the specific mysqli errno failures.
  - **Duplicate Race (1062)**: Mock `$wpdb->query` to return `false` and mock `$wpdb->dbh` as a mocked `mysqli` instance with `->errno = 1062`. Assert that `ShopSettings::save_settings()` returns a `WP_Error` with code `stale_version`.
  - **Unrelated Failure (non-1062)**: Mock `$wpdb->query` to return `false` and mock `$wpdb->dbh->errno = 1146` (Table doesn't exist) or similar. Assert that `save_settings()` returns a `WP_Error` with code `db_error`.

## 7. Implementation Evidence Directory (F-005)
- **File/Symbol**: Global paths and evidence structures.
- **Mechanism**: `ai-document/evidence/CORE-004/implementation-round-3/` will be strictly reserved and utilized for all forthcoming application code changes, test logs, lint exit statuses, manual checks, and cleanup proofs during the implementation phase. No further `builder-round-X` directories will be used to store application implementation evidence.

All resolved revision-6 and revision-7 decisions—including the PHP union syntax, the unconfigured `save_settings()` raw return type, and the `product-smoke.sh` 130/143 signal lifecycle with isolated DB cleanup—remain explicitly preserved.
