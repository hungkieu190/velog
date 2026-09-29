# DATA-001 Builder Round 1 Implementation Report

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 3 (Correction Blueprint)
- **Role**: Antigravity Builder
- **Date**: 2026-09-29
- **Status**: `READY_FOR_REVIEW`
- **Previous Review**: `ai-document/evidence/DATA-001/architect-round-0/review.md` (Findings D1-F-001 through D1-F-006)

---

## 1. Summary of Changes & Findings Resolution

All 6 findings from Architect Round 0 review have been comprehensively resolved within the bounded storage change map:

### D1-F-001: Unauthorized snapshots & context binding
- **Schema Context**: Added `RecordSchema::build_context(array $payload): array` returning context attributes (e.g. `service_status`, `assigned_technician_id`) used for policy evaluations.
- **Sensitive Field Redaction**: Added `contact_fields` registration (`phone`, `email`, `address`, `emergency_contact`) to schemas. `RecordRepository::build_snapshot()` now accepts `$actor` and `$type`, redacting contact fields when `! $actor->has_cap('mf_velog_read_customer_contacts')`.
- **Query Authorization**: `RecordRepository::query()` performs per-record `self::authorize_read()` filtering. Records failing policy checks are omitted from `$items`, and `$total` is adjusted accordingly.
- **Unit Tests**: Added `RecordRepositoryTest::test_query_filters_unauthorized_records_and_redacts_contacts()` and `test_read_authorization_and_contact_redaction()` covering both technician redaction and service visibility denial.

### D1-F-002: Transaction prerequisites & failure states
- **Environment Verification**: `WriteCoordinator::check_environment()` queries `information_schema.TABLES` to ensure `wp_posts`, `wp_postmeta`, and `wp_options` are InnoDB. It also queries `information_schema.INNODB_TRX` to ensure no active transaction exists prior to execution.
- **Connection Pinning & State Integrity**: Pinned `$wpdb->dbh` connection ID, verified connection identity before critical operations, mapped MySQL lock wait timeout (error codes 1205/1213) to retryable `WP_Error('conflict')`, and implemented fail-closed handling to `WP_Error('indeterminate')` if rollback fails or connection drops.
- **Fixture Verification**: Tested with real subprocess holding lock in V4 fixture.

### D1-F-003: Schema uniqueness & projection integrity
- **Lock-Grounded Unique Check**: Implemented `RecordRepository::check_unique_keys_under_lock()` which checks `wp_postmeta` under the write lock before persisting records, returning `WP_Error('conflict')` on collisions.
- **Projection Row Integrity**: `RecordRepository::write_projections_direct()` detects duplicate projection rows (>1), performs updates or inserts, and validates execution status.
- **Pre-Commit Verification**: Implemented `RecordRepository::verify_write()` validating:
  1. Exactly 1 post row exists in `wp_posts` with expected type and `private` status.
  2. Exactly 1 metadata row exists in `wp_postmeta` matching the record payload.
  3. All projected keys exist with exact expected values in `wp_postmeta`.
  4. Idempotency marker is properly recorded (if supplied).

### D1-F-004: Idempotency marker binding
- **Type & Payload Fingerprint Binding**: `RecordRepository::check_idempotency_under_lock()` validates marker format, type binding (`$type`), and SHA-256 payload fingerprint.
- **Existence Verification**: Queries database directly to verify the referenced record ID actually exists with matching `post_type` and `post_status === 'private'`. Mismatched, corrupted, or cross-type markers return `WP_Error('conflict')`.

### D1-F-005: Real multi-process verification fixtures
- Replaced synthetic mocks with real multi-process CLI integration fixtures executed against isolated, disposable MySQL 8.0 and WordPress instances:
  - `v1_lifecycle.php`: Real storage CRUD, validation, optimistic locking, idempotent replay.
  - `v2_concurrency.php`: Real concurrent worker processes competing on unique key (`VIN-RACE-*`) with 1 commit / 1 conflict result, concurrent save version race, and multi-record atomic rollback.
  - `v3_retention.php`: Real WP-CLI deactivation (data preserved), direct execution of `uninstall.php` (complete purge of custom tables, post types, metadata, audit entries, options), and reactivation.
  - `v4_cache_failure.php`: Real lock-wait timeout injected via concurrent worker holding lock row for 7s against writer with 5s timeout, returning retryable `conflict`; rollback state verification.
  - `v5_benchmark.php`: EXPLAIN query plan verification showing index scan (`type_status_date`), micro-benchmark sample; full G-08 28k dataset benchmark explicitly labeled NOT VERIFIED.

### D1-F-006: Production schema registration
- `RecordSchema::register_core_schemas()` is hooked on `init` inside `PostTypes::register_post_types()`. This ensures schemas are registered in real production WordPress runtime without altering the `Plugin` instance hook assertions in `I18nLifecycleTest`.

---

## 2. Verification Evidence & Test Results

### 2.1 Static Analysis & Unit Tests
- **PHPCS**: 0 errors, 0 warnings (`phpcs --standard=phpcs.xml`)
- **PHPStan**: 0 errors (`phpstan analyse --configuration=phpstan.neon`)
- **PHPUnit**: 78 tests, 273 assertions — 100% passing (`phpunit --configuration=tests/phpunit.xml`)
- **Git diff check**: Clean, no whitespace errors.

### 2.2 Disposable WordPress Integration Matrix
Isolated disposable environments with real MySQL 8.0 daemon were executed and fully cleaned up:

| WP Version | Suite | Result | Raw Output Log |
|---|---|---|---|
| **6.7.2** | V1–V5 Full Matrix | **PASS** | `ai-document/evidence/DATA-001/builder-round-1/6.7.2/run.txt` |
| **6.4.3** | V1–V5 Full Matrix | **PASS** | `ai-document/evidence/DATA-001/builder-round-1/6.4.3/run.txt` |

### 2.3 Explicit Boundaries & Non-Verified Scope
- **Persistent-cache compatibility**: `NOT VERIFIED` (requires external Redis/Memcached infrastructure).
- **G-08 Performance Target (28,000 dataset)**: `NOT VERIFIED` (micro-benchmark p95 = 2.81ms on 50 records verified; full 28k dataset benchmark reserved for future performance cycle).
- **Active site / database**: Clean, untouched.

### 2.4 Preservation of Prior Evidence
- `ai-document/evidence/DATA-001/builder-round-0/` is preserved intact.
- Accepted `CORE-002` and `CORE-003` contracts remain untouched and passing.

---

## 3. Evidence Index

- Review addressed: `ai-document/evidence/DATA-001/architect-round-0/review.md`
- WP 6.7.2 raw log: `ai-document/evidence/DATA-001/builder-round-1/6.7.2/run.txt`
- WP 6.4.3 raw log: `ai-document/evidence/DATA-001/builder-round-1/6.4.3/run.txt`
- Runner script: `tests/fixtures/wp-integration/data001/run.php`
- Fixture test files: `tests/fixtures/wp-integration/data001/v1_lifecycle.php` through `v5_benchmark.php`
