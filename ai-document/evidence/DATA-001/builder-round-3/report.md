# DATA-001 Builder Round 3 Implementation Report

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 5 (Correction Blueprint)
- **Role**: Antigravity Builder
- **Date**: 2026-09-29
- **Status**: `READY_FOR_REVIEW`
- **Previous Reviews**: 
  - `ai-document/evidence/DATA-001/architect-round-0/review.md` (Findings D1-F-001–D1-F-006)
  - `ai-document/evidence/DATA-001/architect-round-1/review.md` (Findings D1-F-007–D1-F-012)
  - `ai-document/evidence/DATA-001/architect-round-2/review.md` (Findings D1-F-013–D1-F-018)
  - `ai-document/evidence/DATA-001/architect-proposal-review-5/review.md` (Escalated defects D1-F-013–D1-F-016: APPROVED FOR IMPLEMENTATION)
- **Approved Blueprint**: `ai-document/evidence/DATA-001/builder-round-3/proposal-rev5.md`

---

## 1. Summary of Changes & Escalated Defect Resolutions

Implementation was executed strictly within the approved Correction Blueprint Revision 5 and adheres to all four binding constraints from Architect Proposal Review 5:

### D1-F-013: Query Invariants, Exact Predicate Sharing & Page Hydration
- **Single Shared SQL Predicate**: `RecordRepository::query()` shares the exact SQL predicate (`post_type`, `post_status`, and `_mf_velog_state`) between the count query and the page query. For service records (`mf_velog_service`), the predicate additionally enforces `post_author > 0` and `_mf_velog_vehicle_visible = '1'`.
- **Preflight Envelope & Projection Checks**: Hydration runs on the page IDs in a single batch query (`wp_postmeta WHERE post_id IN (...)`). In PHP, it preflights that each row has exactly 1 envelope row, exactly 1 state projection row, and (for services) exactly 1 visibility row. Duplicate or missing keys immediately abort with `WP_Error('storage_unavailable')`.
- **Envelope vs Post Alignment**: Strictly asserts `p.post_author === (int)($envelope['created_by'] ?? 0)` and projection state matches envelope state before constructing snapshots.
- **Fail-Closed Page Integrity**: Never returns a shortened or truncated page; fails closed on any contradiction.

### D1-F-014: Tuple Uniqueness & Exact Cardinality Validation
- **Prepared Multi-INNER JOIN Under Lock**: `RecordRepository::check_unique_keys_under_lock()` validates that every field in the unique rule is present in canonical input and scalar (missing fields fail closed with `invalid_input`). It generates a prepared `p.ID != current_post_id` query joining `wp_postmeta` once per unique field, executing `FOR UPDATE` on the pinned handle.
- **Candidate Row & Envelope Verification**: If a collision candidate is found, verifies that `p.post_type` and `p.post_status` match. It loads candidate postmeta, unserializes the candidate envelope, and derives expected projections using `RecordSchema::derive_projections()`.
- **Strict Absent Projection Cardinality**: For all registered projection keys, expected projections must have exactly 1 row and matching value; absent projections must have 0 rows in `wp_postmeta`. Any projection corruption returns `WP_Error('storage_unavailable')`. If candidate passes integrity, returns `WP_Error('conflict')`.

### D1-F-015: Ambiguous Write Reconciliation Under Fresh Coordinator Lock
- **`reconcile_create()`**: Reads `mf_velog_create_<sha256(request_id)>` option under a fresh coordinator write lock (`FOR UPDATE`).
  - If missing: proves create did not commit; returns `WP_Error('not_found')`.
  - If present: verifies stored type, payload fingerprint (`hash('sha256', serialize($canonical))`), candidate post row, and envelope. Returns committed snapshot or `WP_Error('conflict')` on mismatch.
- **`reconcile_save()`**: Queries target record under fresh coordinator write lock. Inspects audit history entries for `request_id_sha256 === hash('sha256', $request_id)`.
  - If matched: returns committed snapshot.
  - If unmatched and `record_version === expected_version`: save did not commit; returns `WP_Error('not_found')`.
  - If unmatched and `record_version > expected_version`: another save committed; returns `WP_Error('stale_version')`.
  - If audit history is corrupt: returns `WP_Error('indeterminate')`.

### D1-F-016: Inert Fault Injection Seam & Rollback Safety
- **Test-Only Seam**: `WriteCoordinator::configure_test_fault()` and `WriteCoordinator::maybe_trigger_test_fault()` are guarded by `defined('VELOG_TEST_FIXTURE_RUN') && true === constant('VELOG_TEST_FIXTURE_RUN')`. Inert in production.
- **Seam Locations on Real Pinned Handle**:
  1. `wp_posts_insert` (after post insertion)
  2. `envelope_meta_insert` (after envelope insertion)
  3. `custom_projection_insert` (after projection insertion)
  4. `create_marker_insert` (after idempotency marker insertion)
  5. `envelope_meta_update` (after envelope update)
  6. `verify_write` (during write verification)
  7. `pre_commit_disconnect` (before COMMIT)
  8. `post_commit_lost_ack` (after COMMIT)
- **Closed Handle Safety**: Added `WriteCoordinator::is_live_handle()` and wrapped `exec_direct` / `query_direct` in `try / catch (\Throwable)` to prevent `mysqli_sql_exception` property/ping crashes on closed connections in PHP 8.1+.
- **Conservative Rollback Classification**: If rollback cannot be confirmed on the live pinned handle, marks result `indeterminate`. Cleanup failures in `finally` mark result `indeterminate` without masking existing `indeterminate` errors.

### D1-F-017: Owned-Process & Test Harness Lifecycle Cleanup
- **Direct PID Tracking**: In `tests/fixtures/wp-integration/data001/run.php`, mysqld PID is read directly from `mysql.pid`.
- **Graceful Termination**: Sends `SIGTERM` via `posix_kill($pid, SIGTERM)` and loops with 0.5s checks up to 10 seconds. Only falls back to `SIGKILL` if process has not terminated.
- **Zero Pattern-Based Kills**: Completely eliminated all `pkill -f`.
- **Atomic Temp Directory**: Uses `bin2hex(random_bytes(8))` token in temp base path (`/tmp/velog_data001_<version>_<pid>_<token>`) to prevent collisions.

### D1-F-018: Sensitive-Field Policy & Schema Sealing
- **Mandatory Policy Declaration**: `RecordSchema::normalize_definition()` validates that every field in `fields` must have an explicit exposure policy declared in `field_policies` (`'public' | 'internal' | 'contact'`). Any omitted field throws `InvalidArgumentException`.
- **Auto-Populated Contact Fields**: Automatically derives `contact_fields` from all fields marked `'contact'` in `field_policies`.
- **All Schemas Sealed**: Core schemas registered via `RecordSchema::register_core_schemas()` and sealed at `plugins_loaded`. All test schemas in unit tests and fixtures declare explicit `field_policies`.

---

## 2. Verification Evidence & Test Results

### 2.1 Static Analysis & Unit Suite
- **PHPCS**: 0 errors, 0 warnings (`composer run lint` / `phpcs --standard=phpcs.xml`)
- **PHPStan**: 0 errors (`composer run lint` / `phpstan analyse --configuration=phpstan.neon`)
- **PHPUnit**: 80 tests, 283 assertions — 100% passing (`composer run test`)
- **Git diff check**: Clean, zero trailing whitespace or line errors (`git diff --check` and `git diff --cached --check` exit 0).

### 2.2 Disposable WordPress Integration Matrix
Tested against real isolated MariaDB 10.11 + disposable WordPress environments with strict assertion detection, zero failed assertions, and automatic graceful cleanup:

| WP Version | Negative Control | Integration Suite | Result | Raw Output Log |
|---|---|---|---|---|
| **6.7.2** | PASS (exit != 0 caught) | V1–V5 Full Matrix | **PASS** (exit 0, 0 failed assertions) | `ai-document/evidence/DATA-001/builder-round-3/6.7.2/run.txt` |
| **6.4.3** | PASS (exit != 0 caught) | V1–V5 Full Matrix | **PASS** (exit 0, 0 failed assertions) | `ai-document/evidence/DATA-001/builder-round-3/6.4.3/run.txt` |

### 2.3 Detailed Assertion Breakdown (Both WP Versions)
- **Negative Control**: Verified harness correctly catches intentional failure and exits nonzero.
- **V1 (Schema Rejection)**: ALL V1 ASSERTIONS PASS (unregistered schema rejection, missing capability rejection, contact field redaction for technicians, service visibility constraints).
- **V2 (Concurrency, Idempotency & Fault Injection)**: ALL V2 ASSERTIONS PASS:
  - Exact idempotent replay & payload conflict detection.
  - Multi-record atomic rollback and multi-record atomic commit.
  - Real concurrent unique key race (2 subprocesses compete on same VIN -> 1 win, 1 conflict).
  - Real concurrent save version race (2 subprocesses compete on same record -> 1 win, 1 stale_version).
  - **V2-G Fault Controls**: Tested `wp_posts_insert`, `envelope_meta_insert`, `custom_projection_insert`, `create_marker_insert`, `verify_write`, `envelope_meta_update`, and projection update. Proved each triggers rollback to exact prior post and postmeta counts.
- **V3 (Retention)**: ALL V3 ASSERTIONS PASS (deactivation, direct uninstall.php execution with all data preserved, reactivation with entity/projection integrity intact).
- **V4 (Cache Invalidation, Failure Modes & Reconciliation)**: ALL V4 ASSERTIONS PASS:
  - Clean cache invalidation on write; direct SQL postmeta verification.
  - 5-second bounded lock-wait timeout returning retryable `conflict` error.
  - Pre-commit rollback restoring database and clearing warmed object cache.
  - **V4-G Pre-commit disconnect**: Verified `pre_commit_disconnect` triggers `WP_Error('indeterminate')` and handles connection drop cleanly.
  - **V4-H Reconcile Create**: Verified `reconcile_create()` returns committed snapshot on match, `not_found` when uncommitted, and `conflict` on payload mismatch.
  - **V4-I Reconcile Save**: Verified `reconcile_save()` returns committed snapshot on match, `not_found` when uncommitted with current version, and `stale_version` when version advanced.
- **V5 (G-08 Benchmark Contract)**: ALL V5 ASSERTIONS PASS (50 representative records created, EXPLAIN query plan confirms index access, query latency p50 = 1.32ms, p95 = 4.34ms).

### 2.4 Explicit Truthful Boundaries & Non-Verified Scope
Per contract §Boundaries and Architect Proposal Review 5:
- **MariaDB Live-Handle Commit Failure**: `NOT VERIFIED` (cannot be stimulated on local unix socket without external network proxy).
- **Coordinator 1213 Deadlock**: `NOT VERIFIED` (lock acquisition follows total ordering on single option row; multi-statement transaction cycles cannot occur on single lock row).
- **Persistent-cache compatibility**: `NOT VERIFIED` (requires external Redis/Memcached infrastructure).
- **Full G-08 28,000 dataset product performance**: `NOT VERIFIED` (representative 50-record micro-benchmark verified; full 28,000 product benchmark deferred to MVP-001).
- **Active site / database**: Clean, untouched.

### 2.5 Preservation of Prior Evidence
- `ai-document/evidence/DATA-001/builder-round-0/` preserved.
- `ai-document/evidence/DATA-001/builder-round-1/` preserved.
- `ai-document/evidence/DATA-001/builder-round-2/` preserved.
- Accepted `CORE-002` and `CORE-003` contracts remain untouched and passing.

---

## 3. Evidence Index

- Approved approach: `ai-document/evidence/DATA-001/builder-round-3/proposal-rev5.md`
- Architect approval: `ai-document/evidence/DATA-001/architect-proposal-review-5/review.md`
- WP 6.7.2 raw log: `ai-document/evidence/DATA-001/builder-round-3/6.7.2/run.txt`
- WP 6.4.3 raw log: `ai-document/evidence/DATA-001/builder-round-3/6.4.3/run.txt`
- Runner script: `tests/fixtures/wp-integration/data001/run.php`
- Negative control: `tests/fixtures/wp-integration/data001/negative_control.php`
- Fixture test files: `tests/fixtures/wp-integration/data001/v1_rejection.php` through `v5_benchmark.php`
