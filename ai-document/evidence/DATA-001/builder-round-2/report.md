# DATA-001 Builder Round 2 Implementation Report

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 4 (Correction Blueprint)
- **Role**: Antigravity Builder
- **Date**: 2026-09-29
- **Status**: `READY_FOR_REVIEW`
- **Previous Reviews**: 
  - `ai-document/evidence/DATA-001/architect-round-0/review.md` (Findings D1-F-001–D1-F-006)
  - `ai-document/evidence/DATA-001/architect-round-1/review.md` (Findings D1-F-007–D1-F-012)

---

## 1. Summary of Changes & Findings Resolution

All findings D1-F-007 through D1-F-012 from Architect Round 1 review, as well as prior findings D1-F-001 through D1-F-006, have been completely resolved and verified in real disposable environments:

### D1-F-007: Test Harness False Positives & Strict Assertion Detection
- **Harness Failure Detection**: Updated `tests/fixtures/wp-integration/data001/run.php` to scan fixture stdout/stderr for `FAIL:`, `WordPress database error`, `Fatal error`, or missing expected summary markers. Any occurrence immediately marks the fixture FAILED and sets runner exit code to 1 regardless of subprocess exit code.
- **Negative Control**: Created `tests/fixtures/wp-integration/data001/negative_control.php` with an intentional failure (`v_assert(false, "intentional negative control failure")`). The runner executes this negative control first and aborts if the harness fails to catch the error or if exit code is 0. Both WP 6.7.2 and 6.4.3 verified the runner correctly detects failures.
- **Failure Tracking Variable Scope**: Standardized failure tracking across all fixtures (`v1_rejection.php`, `v2_concurrency.php`, `v3_retention.php`, `v4_cache_failure.php`, `v5_benchmark.php`) using `$GLOBALS['velog_failures']` to ensure full visibility under WP-CLI `eval-file` execution scope. Fixtures print the full failure list and explicitly call `exit(1)` when failures exist.

### D1-F-008: Privilege-Safe Transaction Detection
- **No PROCESS Privilege Requirement**: Replaced queries to `information_schema.INNODB_TRX` in `WriteCoordinator::check_environment()` with standard session variable check: `SELECT @@in_transaction`.
- **Fail-Closed Availability**: If the query returns `null` or `$wpdb->last_error` is non-empty, `WriteCoordinator` immediately aborts with `storage_unavailable`.
- **Tested on Standard Restricted User**: Disposable database user `velog_test` has only standard `GRANT ALL ON velog_test.*` privileges (no `PROCESS` privilege). The check executes without permission errors on both WP 6.4.3 and 6.7.2.

### D1-F-009: Audit Snapshot Contact Redaction
- **Historical Snapshot Redaction**: Updated `RecordRepository::build_snapshot()` to inspect `$audit` entries. For each entry, both `'before'` and `'after'` payloads are filtered against registered `contact_fields` when the requesting actor lacks `mf_velog_read_customer_contacts`.
- **Unit Test Coverage**: Added tests in `tests/Unit/RecordRepositoryTest.php` asserting that technicians (lacking contact cap) receive redacted snapshots in both top-level `payload` and every entry of `audit['before']`/`audit['after']`, while managers receive intact contact data.

### D1-F-010: Query Authorization & Offset/Limit Pagination
- **Capability Check**: `RecordRepository::query()` checks `$schema['read_capability']` (`mf_velog_read_records`) and returns `WP_Error('unauthorized')` if actor lacks the capability.
- **Pre-Pagination Filtering**: Replaced naive database limit/offset with candidate retrieval joining `wp_posts` and `wp_postmeta`, filtering each record through `self::authorize_read()` and state checks, determining stable `$total = count($authorized_records)`, and then slicing the page array.
- **Stable Pagination**: Result contains exact gap-free pages and accurate `$total` matching only authorized records.

### D1-F-011: Generic Core Schemas & Domain Separation
- **Generic Production Schemas**: `RecordSchema::register_core_schemas()` now registers minimal, generic production schemas for `mf_velog_customer`, `mf_velog_vehicle`, `mf_velog_service`, and `mf_velog_reminder` without hardcoded domain validation rules (e.g. strict VIN regex) or hardcoded domain defaults.
- **Fail-Closed Context Builder**: Missing context attributes default to fail-closed (`vehicle_visible => false`, `service_status => ''`).
- **Fixture Domain Schemas**: All integration fixtures (`v1`–`v5`) register explicit schema definitions tailored to their test scenarios before creating records.

### D1-F-012: Multi-Record Operations via WriteUnit
- **WriteUnit CRUD**: Added `create()`, `save()`, and `get()` methods to `WriteUnit`, operating directly on the pinned `$dbh` handle with explicit actor authorization and schema validation.
- **Atomic Multi-Record Commit & Rollback**: `v2_concurrency.php` tests multi-record atomic rollback and commit using `$unit->create()` inside `WriteCoordinator::run()` without triggering nested unit guards.
- **Cache Cleanliness on Rollback**: `v4_cache_failure.php` uses `$unit->create()` followed by an intentional abort; verifies database post count remains unchanged, post row does not exist, and cache invalidation in `WriteCoordinator` finally block purges warmed cache items.

---

## 2. Verification Evidence & Test Results

### 2.1 Static Analysis & Unit Tests
- **PHPCS**: 0 errors, 0 warnings (`composer run lint` / `phpcs --standard=phpcs.xml`)
- **PHPStan**: 0 errors (`composer run lint` / `phpstan analyse --configuration=phpstan.neon`)
- **PHPUnit**: 80 tests, 283 assertions — 100% passing (`composer run test`)
- **Git diff check**: Clean (`git diff --check` exits 0)

### 2.2 Disposable WordPress Integration Matrix
Tested against real isolated MariaDB 10.11 + disposable WordPress environments with strict assertion detection, zero failed assertions, and automatic full cleanup:

| WP Version | Negative Control | Integration Suite | Result | Raw Output Log |
|---|---|---|---|---|
| **6.7.2** | PASS (exit != 0 caught) | V1–V5 Full Matrix | **PASS** (exit 0, 0 failed assertions) | `ai-document/evidence/DATA-001/builder-round-2/6.7.2/run.txt` |
| **6.4.3** | PASS (exit != 0 caught) | V1–V5 Full Matrix | **PASS** (exit 0, 0 failed assertions) | `ai-document/evidence/DATA-001/builder-round-2/6.4.3/run.txt` |

### 2.3 Detailed Assertion Breakdown (Both WP Versions)
- **Negative Control**: Correctly verified harness detection of intentional assertion failure.
- **V1 (Schema Rejection)**: ALL V1 ASSERTIONS PASS (unregistered schema rejection, missing capability rejection, unsealed schema rejection, duplicate schema rejection, validation failure rejection, sealed modification rejection).
- **V2 (Concurrency & Idempotency)**: ALL V2 ASSERTIONS PASS (optimistic concurrency stale version rejection, exact idempotent replay, conflicting payload rejection, cross-type request ID conflict, corrupted marker conflict, multi-record atomic rollback, multi-record atomic commit, real concurrent unique key race with 1 commit / 1 conflict, real concurrent save version race with 1 commit / 1 stale_version).
- **V3 (Retention)**: ALL V3 ASSERTIONS PASS (create customer/vehicle, deactivate plugin with data retained, direct execution of uninstall.php with all plugin posts/meta/options retained per explicit retention policy, reactivation with full entity and projection integrity verified).
- **V4 (Cache Invalidation & Failure Modes)**: ALL V4 ASSERTIONS PASS (clean cache invalidation on write, bypass-cache direct read, 5-second lock wait timeout returning retryable `conflict` error, pre-commit transaction rollback restoring state and purging cached post).
- **V5 (G-08 Benchmark Contract)**: ALL V5 ASSERTIONS PASS (50 representative records created across CPTs, EXPLAIN query plan confirms `type_status_date` index scan with `ref` access, p50 = 0.56ms, p95 = 0.85ms; full 28k dataset explicitly NOT VERIFIED).

### 2.4 Explicit Boundaries & Non-Verified Scope
- **Persistent-cache compatibility**: `NOT VERIFIED` (requires external Redis/Memcached infrastructure).
- **G-08 Performance Target (28,000 dataset)**: `NOT VERIFIED` (micro-benchmark sample verified; full 28,000-record dataset and domain search query benchmarks deferred to domain and performance tasks per contract).
- **Active site / database**: Clean, untouched.

### 2.5 Preservation of Prior Evidence
- `ai-document/evidence/DATA-001/builder-round-0/` is preserved intact.
- `ai-document/evidence/DATA-001/builder-round-1/` is preserved intact.
- Accepted `CORE-002` and `CORE-003` contracts remain untouched and passing.

---

## 3. Evidence Index

- Review addressed: `ai-document/evidence/DATA-001/architect-round-1/review.md`
- WP 6.7.2 raw log: `ai-document/evidence/DATA-001/builder-round-2/6.7.2/run.txt`
- WP 6.4.3 raw log: `ai-document/evidence/DATA-001/builder-round-2/6.4.3/run.txt`
- Runner script: `tests/fixtures/wp-integration/data001/run.php`
- Negative control: `tests/fixtures/wp-integration/data001/negative_control.php`
- Fixture test files: `tests/fixtures/wp-integration/data001/v1_rejection.php` through `v5_benchmark.php`
