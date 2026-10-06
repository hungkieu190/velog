# Correction Proposal for F-002, F-004, F-005 - Revision 2

## 1. F-002: Settings envelope and input boundary
- **Raw Option Read**: We will use a private method `ShopSettings::read_raw_option()` executing `$wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'velog_settings'" )` to fetch the exact serialized bytes from the database.
- **Distinguishing Missing vs Malformed**:
  - If the query returns `null`, the option is **missing**. `ShopSettings::get_settings()` returns a default unconfigured array structure.
  - If the query returns a string, we call `unserialize()`. If unserialize fails, or if the resulting array lacks the correct `schema_version` (1) or required scalar keys, it is **malformed**.
- **Admin Form Safety**: If `get_settings()` encounters malformed data, it returns a `WP_Error( 'corrupt_settings', ... )`. The admin page (`RegionalSettingsPage`) explicitly checks `is_wp_error()`. If true, it renders a secure error notice and disables the form; it does not silently replace invalid stored fields with defaults or attempt to render broken data.
- **CAS Raw Value Update**: The save mechanism will use the exact string returned from `read_raw_option()` in its WHERE clause to guarantee no data loss from unrecognized fields and avoid PHP serialization discrepancies: `UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'velog_settings' AND option_value = %s`.
- **Error Codes & Handler Behavior**:
  - **Unsupported Schema / Corrupt**: `WP_Error( 'corrupt_settings' )`.
  - **Invalid Version/Scale/Code (POST values)**: `WP_Error( 'invalid_configuration' )`.
  - **Malformed POST fields**: The POST handler checks `$_POST` scalars. If arrays/objects are submitted instead of scalars, the handler immediately aborts and redirects with `?page=velog-settings&settings-updated=false&error=invalid_input`. It does not emit PHP warnings and performs no writes.

## 2. F-004: Executable oracles
- **Real Concurrent Oracle**:
  - We will spawn two completely independent PHP CLI processes concurrently via a Bash test harness (`php test-concurrent.php processA & php test-concurrent.php processB & wait`).
  - **Synchronization Point**: Both processes will open their own `mysqli` connection and `SELECT` the same current raw option value. They will both write to a temporary synchronization file (or wait for a precise timestamp threshold) to guarantee they are both holding the same prior version before proceeding.
  - Both processes will then execute their `UPDATE` statements containing the conditional CAS (`WHERE option_name = 'velog_settings' AND option_value = <prior_raw_string>`).
  - **Asserted Outcome**: We will capture the affected rows of both scripts. Exactly one script must report `1` affected row (the winner) and the other must report `0` (the loser). The database `record_version` will be asserted to have incremented by exactly `1`.
- **Historical Oracle**:
  - **Seed**: We will use the exact contract: `\MF\VeLog\Common\Storage\RecordRepository::create( 'mf_velog_service', [ 'vehicle_id' => 1, 'customer_id' => 1, 'odometer' => 10000, 'type' => 'repair' ], $actor, wp_generate_uuid4() )`. There is no interface contradiction; this contract natively supports creating a service record.
  - **Capture**: Immediately after creation, we will read the exact meta row bytes: `$wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_mf_velog_record'", $post_id ) )`.
  - **Switch**: We will programmatically update the global shop settings (e.g., switching distance from `km` to `mi` and currency from `JPY` to `USD`) via a valid `ShopSettings::save_settings()` call.
  - **Assertion**: We will re-read the exact meta row bytes for the previously created service record. We will assert byte-for-byte equality with the captured envelope. This proves canonical distance and currency/scale identity are retained historically.

## 3. F-005: Quality and cleanup
- **Correct File**: We will update the actual `phpcs.xml` (not `.dist`).
- **Lint Warnings Fixes**: We will fix all actual indentation/whitespace warnings. We will narrowly configure the capabilities allowance by adding `<property name="custom_capabilities" type="array" value="mf_velog_manage_settings,mf_velog_read_records,mf_velog_write_records"/>` in the config, and use `// phpcs:ignore WordPress.DB.DirectDatabaseQuery` strictly inline only for the CAS update query.
- **Logging**: All commands, exits, PHP versions, and MariaDB versions will be recorded in `ai-document/evidence/CORE-004/builder-round-4/commands.log`.
- **Resource Cleanup**: The bash runner will use `trap 'rm -rf "$TMP_DIR"; kill $(jobs -p) 2>/dev/null || true' EXIT` to ensure the unique temporary directory, spawned concurrent processes, and any temporary fixtures are completely destroyed on both success and failure. No persistent tables outside the test schema will be touched.
- **Record of Observations**: Manual UI checks (keyboard navigation, 320px width, RTL layout, long strings) and any explicit `NOT VERIFIED` cases will be documented in `ai-document/evidence/CORE-004/builder-round-4/report.md`.

## Interface Contradiction Check
No interface contradictions exist. The `RecordRepository::create` signature required in the review (`string $type, array $fields, WP_User $actor, string $request_id`) perfectly matches the implementation in `src/Common/Storage/RecordRepository.php`.

Pending `APPROVED FOR IMPLEMENTATION`.
