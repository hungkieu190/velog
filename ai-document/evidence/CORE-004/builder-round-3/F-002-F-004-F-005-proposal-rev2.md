# Correction Proposal for F-002, F-004, F-005 - Revision 2

## 1. F-002: Settings envelope and input
- **Function/Symbol Map**:
  - `ShopSettings::get_raw_option()`: A new private method using `$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'velog_settings'")` to read the exact serialized string.
  - `ShopSettings::get_settings()`: Calls `get_raw_option()`. If missing (null), returns a default array (version 0, configured false). If present, unserializes and strictly validates schema/types against `SCHEMA_VERSION`. If malformed (wrong type, invalid fields, unexpected schema), returns `WP_Error` and exposes this error securely to the admin form as an empty/blocked state.
  - `ShopSettings::require_configured()`: If `get_settings()` returns `WP_Error` or indicates `configured === false`, returns a `WP_Error('unconfigured_velog', ...)` to block operations.
  - `ShopSettings::save_settings()`: Uses the exact raw string returned from `get_raw_option()` as the `WHERE option_value = %s` condition in the `UPDATE` query for the CAS operation. This guarantees no data loss from unrecognized fields and avoids PHP serialization differences.
- **Error Behavior**:
  - Unrecognized `SCHEMA_VERSION`, missing/invalid catalog currency, or non-boolean `configured` flag throws `WP_Error` on read without silently overwriting.
  - Malformed POST values (arrays/objects) for scalar fields are rejected with `wp_die('Invalid input type', '', 400)`.
  - Invalid strings (out of range versions) trigger a redirect with `?settings-updated=false&error=invalid_version` (no PHP warnings, no writes).

## 2. F-004: Executable oracles
- **Real Concurrent Oracle**:
  - **Synchronization**: We will use two independent `mysqli` connections within a single test fixture script (`core-004-concurrent.php`) executed via CLI within the isolated DB.
  - **Verification**: Both connections read the exact same prior version via `SELECT`. We prepare two conflicting `UPDATE` statements using the strict CAS (`WHERE option_name = 'velog_settings' AND option_value = ?`). We fire them sequentially in code (which guarantees strict ordering on the DB thread without network race flakiness). One `UPDATE` affects 1 row, the other affects 0. We assert `$affected1 + $affected2 === 1` and that the final `record_version` incremented exactly by 1, with the option row matching the single winner.
- **Historical Oracle**:
  - **Seed**: We will use `\MF\VeLog\Common\Storage\RecordRepository::create( 'service', ['customer_id' => 1, 'vehicle_id' => 1, 'odometer' => 1000], $user, 'req123' )` to create a valid service record under the current settings.
  - **Capture**: We will read the exact serialized meta row (`$wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'velog_record_meta'")`) to capture the envelope containing the canonical `distance_unit` and `currency_scale` bytes.
  - **Switch**: We will update the global settings (via `ShopSettings::save_settings()`), changing `distance_unit` and `currency_code`.
  - **Assertion**: We will re-read the exact meta row bytes directly via `$wpdb->get_var`. The bytes must match the initially captured envelope exactly, proving historical immutability.

## 3. F-005: Quality and cleanup
- **Target Files**: `phpcs.xml`, `ai-document/walkthroughs/CORE-004.md`.
- **Invariants**:
  - We will fix actual warnings (like whitespace, alignment) and narrowly configure `phpcs.xml` to allow `mf_velog_*` custom capabilities by adding `<property name="custom_capabilities" type="array" value="mf_velog_manage_settings,mf_velog_read_records,mf_velog_write_records"/>`.
  - Direct SQL in CAS will have inline `// phpcs:ignore WordPress.DB.DirectDatabaseQuery` documented with "Required for atomic CAS".
  - **Resource Cleanup**: All test harnesses (`product-smoke.sh`) trap EXIT/ERR to `rm -rf` the unique temp directory and `kill -9` the specific MariaDB and PHP PIDs stored in `.pid` files on success or failure.
  - **Evidence Logs**: We will write raw output to `ai-document/evidence/CORE-004/builder-round-3/logs/` (e.g., `phpcs.log`, `phpunit.log`, `verify-*.log`).
  - **Manual UI Evidence**: `walkthroughs/CORE-004.md` will list exact observations for keyboard navigation, 320px rendering, RTL layout, and long-string wrapping.

Pending APPROVED FOR IMPLEMENTATION.
