# Builder Round 9 Read-Only Delta Proposal Revision 7

## 1. Duplicate Classification (F-002)
- **File/Symbol**: `src/Common/Regional/ShopSettings.php` / `save_settings()`
- **Mechanism**: Classify conflict by strictly checking the `mysqli` driver's exact errno `1062` instead of a substring match.
  ```php
  $affected = $wpdb->query(
      $wpdb->prepare(
          "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
          self::OPTION_NAME,
          $new_serialized
      )
  );
  if ( $affected === false ) {
      if ( $wpdb->dbh instanceof \mysqli && $wpdb->dbh->errno === 1062 ) {
          return new \WP_Error( 'stale_version' );
      }
      return new \WP_Error( 'db_error' );
  }
  if ( $affected !== 1 ) {
      return new \WP_Error( 'db_error' );
  }
  ```
- **Verification**: `tests/Unit/ShopSettingsTest.php`. Expected exit `0`. Future evidence: `builder-round-9/test-output.log`.

## 2. RecordRepository::get() Array Shape & Fixed Fields Assertions (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**:
  ```php
  $manager = get_user_by( 'login', 'manager1' );
  $record = \MF\VeLog\Common\Storage\RecordRepository::get( 'mf_velog_service', $record_id, clone $manager );
  
  if ( is_wp_error( $record ) ) {
      echo "VELOG_CAUSE: failed to get record\n";
      exit( 1 );
  }
  ```
- **Verification**: Executed within `product-smoke.sh`. Expected exit `0`.

## 3. Replaced Disabled Assertions (F-004)
- **File/Symbol**: `tests/fixtures/core-004-verify.php`
- **Mechanism**: Replace `assert()` with explicit `if` statements that forcefully exit with nonzero.
  ```php
  if ( $record['fields']['distance_unit'] !== 'km' ) {
      echo "Fail: wrong distance_unit\n"; exit( 1 );
  }
  if ( $record['fields']['currency_code'] !== 'JPY' ) {
      echo "Fail: wrong currency_code\n"; exit( 1 );
  }
  if ( $record['fields']['currency_scale'] !== 0 ) {
      echo "Fail: wrong currency_scale\n"; exit( 1 );
  }
  if ( $record['record_version'] !== 1 ) {
      echo "Fail: wrong version\n"; exit( 1 );
  }
  if ( $record['created_by'] !== $manager->ID ) {
      echo "Fail: wrong author\n"; exit( 1 );
  }
  ```
- **Verification**: Executed within `product-smoke.sh`. Expected exit `0`.

## 4. Authenticated curl Nonce Contract (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**: Log in and request the HTML settings page to parse the actual HTML nonce for `velog_save_settings`.
  ```bash
  # As manager
  curl -s -c cookie_mgr.txt -d "log=manager1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  curl -s -b cookie_mgr.txt "http://localhost:$PORT/wp-admin/admin.php?page=velog-settings" > mgr_page.html
  NONCE_MGR=$(grep -o 'name="velog_settings_nonce" value="[^"]*"' mgr_page.html | cut -d'"' -f4)
  
  # As subscriber
  curl -s -c cookie_sub.txt -d "log=sub1&pwd=pass1&wp-submit=Log In" "http://localhost:$PORT/wp-login.php"
  curl -s -b cookie_sub.txt "http://localhost:$PORT/wp-admin/admin.php?page=velog-settings" > sub_page.html
  NONCE_SUB=$(grep -o 'name="velog_settings_nonce" value="[^"]*"' sub_page.html | cut -d'"' -f4)
  ```
- **Verification**: Executed within `product-smoke.sh`.

## 5. Exact HTTP 403 Outcomes and Immutable Bytes (F-004)
- **File/Symbol**: `src/Admin/RegionalSettingsPage.php` and `tests/workflow/core-004-endpoint.sh`
- **Mechanism**:
  Modify application to return an explicit 403:
  ```php
  if ( ! current_user_can( 'mf_velog_manage_settings' ) ) {
      wp_die( 'Unauthorized', '', [ 'response' => 403 ] );
  }
  if ( ! isset( $_POST['velog_settings_nonce'] ) || ! wp_verify_nonce( $_POST['velog_settings_nonce'], 'velog_save_settings' ) ) {
      wp_die( 'Invalid nonce', '', [ 'response' => 403 ] );
  }
  ```
  In test:
  ```bash
  ROW_BEFORE=$(wp option get velog_settings --format=json --path="$WP_DIR" 2>/dev/null || echo "MISSING")
  
  # Denied subscriber valid nonce
  HTTP_CODE=$(curl -s -o out.txt -w "%{http_code}" -b cookie_sub.txt -d "action=velog_save_settings&velog_settings_nonce=$NONCE_SUB&distance_unit=km&currency_code=JPY" "http://localhost:$PORT/wp-admin/admin-post.php")
  if [ "$HTTP_CODE" != "403" ]; then exit 1; fi
  
  # Manager with invalid nonce
  HTTP_CODE=$(curl -s -o out.txt -w "%{http_code}" -b cookie_mgr.txt -d "action=velog_save_settings&velog_settings_nonce=INVALID&distance_unit=km&currency_code=JPY" "http://localhost:$PORT/wp-admin/admin-post.php")
  if [ "$HTTP_CODE" != "403" ]; then exit 1; fi
  
  # Malformed array input
  HTTP_CODE=$(curl -s -o out.txt -w "%{http_code}" -b cookie_mgr.txt -d "action=velog_save_settings&velog_settings_nonce=$NONCE_MGR&distance_unit[]=km&currency_code=JPY" "http://localhost:$PORT/wp-admin/admin-post.php")
  if [ "$HTTP_CODE" != "302" ]; then exit 1; fi
  if ! grep -q "error=invalid_input" out.txt; then exit 1; fi
  
  ROW_AFTER=$(wp option get velog_settings --format=json --path="$WP_DIR" 2>/dev/null || echo "MISSING")
  if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then exit 1; fi
  ```
- **Verification**: Executed within `product-smoke.sh`. Outer runner exits `1` on mismatch.

## 6. Runner Integration and `$WP_DIR` Path (F-004)
- **File/Symbol**: `tests/workflow/core-004-endpoint.sh`
- **Mechanism**: The helper strictly operates on the existing `$PORT` and `$WP_DIR` provided by `product-smoke.sh`, appending `--path="$WP_DIR"` to all WP-CLI commands:
  ```bash
  wp user create manager1 mgr@example.com --role=administrator --user_pass=pass1 --path="$WP_DIR"
  wp user create sub1 sub@example.com --role=subscriber --user_pass=pass1 --path="$WP_DIR"
  ```
  It does not create its own server, port, or database instance.
- **Verification**: Executed within `product-smoke.sh`.

## 7. Implementation Evidence Directory (F-005)
- **File/Symbol**: Evidence paths
- **Mechanism**: All upcoming implementation activities (code changes, tests, lint runs, cleanup evidence, and manual UI walkthrougs) will direct output into `ai-document/evidence/CORE-004/builder-round-9/`.
- **Verification**: File existence during implementation handoff.

All resolved revision-6 decisions regarding the valid PHP signature, immutable historical seed without catalog dependency, 130/143 traps, and safe native runner cleanup remain preserved exactly as approved.
