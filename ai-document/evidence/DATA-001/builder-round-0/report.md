# DATA-001 Builder Implementation Report — Round 0

**Date:** 2026-09-29  
**Actor:** Antigravity (Builder)  
**Task:** [DATA-001: Versioned private storage and retained data](../../tasks/DATA-001-record-storage.md)  
**Contract Authority:** [contract.md](../architect-spike-1/contract.md)  
**Status Returned:** READY_FOR_REVIEW  

---

## 1. Summary of Changes

The approved bounded implementation blueprint (DATA-001 revision 2) has been fully implemented according to the locked specifications in `contract.md`:

### 1.1 New Common Storage Classes
- [`src/Common/Storage/RecordSchema.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Common/Storage/RecordSchema.php):
  - Defines the authoritative meta key `_mf_velog_record` and write-lock option `_mf_velog_write_lock`.
  - Maintains a closed in-memory registry for allowed private CPTs (`velog_customer`, `velog_vehicle`, `velog_service`, `velog_reminder`).
  - Supports field validation callables and allowed projection key allowlists.
  - Enforces sealed registry invariant (`seal()` prevents any post-bootstrap registrations; unsealed operations fail closed).
- [`src/Common/Storage/AuditEntry.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Common/Storage/AuditEntry.php):
  - Immutable value object representing an audit record.
  - Automatically captures operation (`create` or `save`), actor ID, UTC timestamp (ISO 8601), and field deltas (`before`/`after`).
- [`src/Common/Storage/WriteUnit.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Common/Storage/WriteUnit.php):
  - Bounded write context object passed to trusted operation callables inside `WriteCoordinator::run()`.
  - Exposes only the pinned MySQLi handle, table prefix, and affected post ID tracker. Prevents arbitrary SQL execution from untrusted code.
- [`src/Common/Storage/WriteCoordinator.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Common/Storage/WriteCoordinator.php):
  - Enforces single-site, MySQLi handle, and InnoDB engine prerequisites on `wp_posts`, `wp_postmeta`, and `wp_options`.
  - Pins the database connection and sets `innodb_lock_wait_timeout = 5`.
  - Acquires row-level `FOR UPDATE` lock on the fixed options row `_mf_velog_write_lock`.
  - Executes operations transactionally: `START TRANSACTION`, pre-commit verification, `COMMIT` on success, `ROLLBACK` on error.
  - Guarantees `finally` cache invalidation via `clean_post_cache()` for all touched post IDs, restores timeout, and clears unit state.
- [`src/Common/Storage/RecordRepository.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Common/Storage/RecordRepository.php):
  - Public interface: `get()`, `query()`, `create()`, `save()`.
  - Authorization gated through CORE-003 capabilities before any data retrieval or lock acquisition.
  - Direct SQL on pinned handle (bypassing `wp_insert_post` and WordPress write hooks during transactions).
  - Serialized `_mf_velog_record` envelope with versioning, audit trail, and domain fields.
  - Transactional idempotency using request-ID markers stored in `wp_options` with SHA-256 payload fingerprints.
  - Authoritative reads directly query the database to bypass stale WordPress object caches.

### 1.2 Modified Existing Files
- [`src/Core/PostTypes.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/src/Core/PostTypes.php):
  - Registers `_mf_velog_record` meta key for all four private CPTs with `show_in_rest = false`, `single = true`, and a deny-by-default `auth_callback` returning `false`.
- [`uninstall.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/uninstall.php):
  - Updated to enforce data retention policy: retains all private CPT posts, `_mf_velog_record` postmeta, `velog_settings`, idempotency request options, and taxonomy terms. Only removes regenerable transients.
- [`phpcs.xml`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/phpcs.xml):
  - Added scoped exclusions for `src/Common/Storage/*` for direct DB sniffs and `tests/*` for test stub structures.
- [`tests/bootstrap.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/tests/bootstrap.php):
  - Added unit test stubs for `WP_Error` and `is_wp_error()`.
- [`tests/fixtures/bootstrap-entry-verify.php`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/tests/fixtures/bootstrap-entry-verify.php):
  - Added `register_post_meta` stub.

---

## 2. Verification Evidence

### 2.1 Static Analysis & Quality Gates
- **PHPCS (WordPress Coding Standards):** Clean pass — 0 errors, 0 warnings.
- **PHPStan (Level 8 / Strict):** Clean pass — `[OK] No errors`.
- **PHPUnit (Unit & Integration Tests):** Clean pass — `75 tests, 264 assertions, 0 errors, 0 failures`.

### 2.2 Disposable WordPress Fixture Runs (V1–V5)
Both runs executed in isolated environments (`/tmp/velog_data001_*`) with disposable MariaDB 10.11 instances, dedicated unix sockets, non-networked configuration, and verified complete cleanup.

| Case | Scenario | WordPress 6.4.3 | WordPress 6.7.2 |
|---|---|---|---|
| **V1** | Schema & field rejection (unknown field, object value, validator failure, forged actor/audit/version) | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **V2** | Concurrency, stale version rejection, request-ID idempotency replay & conflict rejection | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **V3** | Retention verification: uninstall/reinstall retains CPTs, meta envelopes, settings & idempotency markers | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **V4** | Cache invalidation on rollback/commit, cache bypass on get, and 5s lock timeout conflict error | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **V5** | G-08 benchmark generator and query plan measurement harness | **PASS** (Exit 0)* | **PASS** (Exit 0)* |
| **Lint** | Full repository `composer run lint` inside fixture | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **Tests** | Full repository `composer run test` inside fixture | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **Diff** | `git diff --check` and `git diff --cached --check` | **PASS** (Exit 0) | **PASS** (Exit 0) |
| **Cleanup** | MariaDB daemon termination & temp dir removal | **PASS** (Exit 0) | **PASS** (Exit 0) |

*\* Note: V5 benchmark exit is 0, but performance acceptance remains NOT VERIFIED per contract until domain queries exist in later tasks.*

Raw run logs:
- WordPress 6.7.2: [`ai-document/evidence/DATA-001/builder-round-0/6.7.2/run.txt`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/ai-document/evidence/DATA-001/builder-round-0/6.7.2/run.txt)
- WordPress 6.4.3: [`ai-document/evidence/DATA-001/builder-round-0/6.4.3/run.txt`](file:///home/ecommercelife/Local%20Sites/velog/app/public/wp-content/plugins/velog/ai-document/evidence/DATA-001/builder-round-0/6.4.3/run.txt)

---

## 3. Explicit Boundaries and Non-Claims
- **Application behavior:** The repository provides the storage foundation. Application UI and domain business rules are NOT VERIFIED and belong to subsequent domain tasks (CORE-004, CUST-001, VEH-001, etc.).
- **Persistent object cache:** Persistent Redis/Memcached drop-ins are NOT VERIFIED.
- **G-08 Performance Benchmark:** Benchmark dataset generator and query plan structures are operational; full product benchmark is deferred to MVP-001 when real operational queries exist.
- **Active site:** No active database or site data was modified. All integration tests ran on isolated temporary fixtures.
