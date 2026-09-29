# DATA-001 Builder Round 3 Implementation Proposal (Revision 4)

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 5 (Correction Blueprint)
- **Author**: Antigravity Builder
- **Reviewer**: Codex Architect
- **Date**: 2026-09-29
- **Status**: `PROPOSAL_PENDING_APPROVAL` (Preflight gate per AGENTS.md § 3 & Escalation Ledger)
- **Reference**: `ai-document/evidence/DATA-001/architect-proposal-review-3/review.md`
- **Escalated Scope**: `D1-F-013`, `D1-F-014`, `D1-F-015`, `D1-F-016`
- **Normal Builder Scope**: `D1-F-017`, `D1-F-018` (one implementation failure; normal correction path)

---

## 1. D1-F-013: Bounded Authorized Query and Exact Count

### 1.1 Public API & Parameter Constraints
- **Preserved Public Signature**:
  `RecordRepository::query( string $type, array $filters, int $page, \WP_User $actor ): array|\WP_Error`
- **Fixed Page Size**: Exactly 50 items (per pinned contract; no custom `$per_page`).
- **Strict Parameter Validation & Pinned Error Vocabulary**:
  - If `$page < 1`: return `new \WP_Error('invalid_input', 'Page must be >= 1.')`.
  - If `$filters` contains any key other than `'state'`: return `new \WP_Error('invalid_input', 'Unsupported query filter.')`.
  - If `! $actor->has_cap('mf_velog_read_records')`: return `new \WP_Error('forbidden', 'Actor lacks read capability.')`.

### 1.2 Fixed Scalar Query Projections & SQL Predicates
To derive exact counts and bounded page IDs in SQL without loading historical audit blobs into PHP:
1. **Derivation from Validated Envelope & Trusted Context Builder**:
   During the write unit on the pinned connection, projections are derived strictly from the same envelope and context builder used by `AccessPolicy`:
   - For all types: scalar projection `_mf_velog_state = (string) $envelope['state']`.
   - For `mf_velog_service`:
     * Author is set as `p.post_author = (int) $envelope['created_by']` (where `$envelope['created_by'] = (int) $actor->ID`).
     * Vehicle visibility is derived from `RecordSchema::build_context()`: `_mf_velog_vehicle_visible = ( $context['vehicle_visible'] === true ) ? '1' : '0'`.
     * Fail closed: If context cannot be represented, `_mf_velog_vehicle_visible` is set to `'0'` (ineligible for read). No new domain relationship rules are introduced.
2. **Prepared SQL Predicate Equivalence**:
   The exact same prepared SQL `WHERE` and `JOIN` predicate is used for both `COUNT` and `PAGE` queries:
   - For `customer`, `vehicle`, `reminder`:
     ```sql
     FROM {$prefix}posts p
     INNER JOIN {$prefix}postmeta pm_state 
       ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
     WHERE p.post_type = ?
       AND p.post_status = 'private'
       AND (? = '' OR pm_state.meta_value = ?)
     ```
   - For `service`:
     ```sql
     FROM {$prefix}posts p
     INNER JOIN {$prefix}postmeta pm_state 
       ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
     INNER JOIN {$prefix}postmeta pm_vis 
       ON pm_vis.post_id = p.ID AND pm_vis.meta_key = '_mf_velog_vehicle_visible' AND pm_vis.meta_value = '1'
     WHERE p.post_type = 'mf_velog_service'
       AND p.post_status = 'private'
       AND p.post_author > 0
       AND pm_state.meta_value IN ('draft', 'finalized')
       AND (? = '' OR pm_state.meta_value = ?)
     ```
3. **Execution**:
   - Count query: `SELECT COUNT(p.ID) ` + predicate.
   - Page query: `SELECT p.ID ` + predicate + ` ORDER BY p.ID ASC LIMIT 50 OFFSET %d`.

### 1.3 Batch Hydration & Fail-Closed Integrity Verification
1. **Single-Batch Fetch**:
   Hydrate ONLY the returned page slice (at most 50 IDs) in one single database round-trip.
2. **Duplicate & Malformed Detection on Requested Page**:
   For each returned candidate ID:
   - Check projection cardinality: if any candidate has duplicate `_mf_velog_state` or `_mf_velog_vehicle_visible` rows in `wp_postmeta`, or duplicate post rows in `wp_posts`:
     LẬP TỨC return `new \WP_Error('storage_unavailable', 'Corrupted projection cardinality detected on page.')`. Never return a duplicate or shortened page!
   - Must have **exactly 1** `_mf_velog_record` envelope row.
   - Re-run `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )` on the decoded envelope.
   - If policy rejects the record or envelope state contradicts projection values:
     Return `new \WP_Error('storage_unavailable', 'Record state contradicts query projection.')`.
3. **Transactional Integrity Boundary**:
   Under the coordinator write lock invariant, all VeLog writers ensure exactly 1 envelope row and exactly 1 row per non-null projection. The exact count derived from projections holds strictly under this invariant, which is explicitly recorded in the evidence report.

---

## 2. D1-F-014: Complete-Tuple Uniqueness and Projection Integrity

### 2.1 Prepared Statement Complete-Tuple Binding on Pinned Handle
In `RecordRepository::check_unique_keys_under_lock( \mysqli $dbh, string $prefix, string $type, array $fields, ?int $current_id )`:
1. **Registered Tuple Validation**:
   - Inspect registered `$schema['unique_keys']`.
   - Every attribute in the tuple must exist in `$fields` and be a valid scalar string. If missing, fail closed.
2. **Prepared Multi-Join SQL**:
   Construct an atomic query using separate `INNER JOIN` aliases `m0, m1, ...` for each key in the tuple:
   ```sql
   SELECT p.ID
   FROM {$prefix}posts p
   INNER JOIN {$prefix}postmeta m0 ON m0.post_id = p.ID AND m0.meta_key = ? AND m0.meta_value = ?
   INNER JOIN {$prefix}postmeta m1 ON m1.post_id = p.ID AND m1.meta_key = ? AND m1.meta_value = ?
   WHERE p.post_type = ?
     AND p.post_status = 'private'
     AND p.ID != ?
   LIMIT 1
   ```
   - Prepared via `$stmt = $dbh->prepare( $sql )`.
   - Bound via `$stmt->bind_param( ... )` with strict type definitions (`s` for strings, `i` for current_id).
   - If prepare, bind, execute, or fetch fails: return `new \WP_Error('storage_unavailable', 'Uniqueness query execution failed.')` and trigger rollback. No user input or raw SQL errors exposed.

### 2.2 Pinned Under-Lock Candidate Verification Path
Before treating a candidate match `p.ID` as a collision:
1. Query candidate record under write lock:
   - Verify candidate has exactly 1 `wp_posts` row with `post_type = $type` and `post_status = 'private'`.
   - Verify candidate has exactly 1 `_mf_velog_record` envelope row.
   - Decode candidate envelope: verify each tuple value in the envelope matches the search tuple and matches the projected value.
2. **Check Projection Cardinality per Pinned Rule ("absent projections have no row")**:
   - For every registered projection key in `$schema['projections']`:
     - If the key is present and non-null in candidate envelope: verify **exactly 1** row exists in `wp_postmeta`.
     - If the key is absent/null in candidate envelope: verify **0 rows** exist in `wp_postmeta`.
   - If any projection key has duplicate rows (>1) or is missing when present in envelope:
     Candidate is CORRUPT, not a valid collision. Return `new \WP_Error('storage_unavailable', 'Corrupted candidate record encountered.')` and rollback.
3. **Collision Result**:
   - If candidate is valid and `p.ID !== $current_id`: return `new \WP_Error('conflict', "Unique constraint '{$rule_name}' violated.")`.
   - Distinct tuples sharing key A (e.g. `(make='Toyota', model='Camry')` vs `(make='Toyota', model='Corolla')`): `m0` matches but `m1` does not match; 0 rows returned -> Valid.
   - Same check applies on `create()` and on `save()`, including changes to only one component of a composite rule.

---

## 3. D1-F-015: Transaction Classification & Read-Only Create/Save Reconciliation

### 3.1 Pinned Connection Identity & Strict Rollback Classification
In `WriteCoordinator::run()`:
1. **Thread Identity Tracking**:
   - Record `$saved_thread_id = $dbh->thread_id;` upon pinning.
   - On exception or error:
     - Check `$current_thread_id = @mysqli_thread_id( $dbh );`
     - If `$current_thread_id !== $saved_thread_id` or `! @mysqli_ping( $dbh )`:
       Return `new \WP_Error('indeterminate', 'Connection lost or replaced during transaction.')`.
2. **Rollback Confirmation**:
   - Execute `ROLLBACK` on pinned handle.
   - If `ROLLBACK` returns false:
     Return `new \WP_Error('indeterminate', 'Transaction rollback failed.')`.
   - Lock timeout (1205) or deadlock (1213) returns retryable `\WP_Error('conflict')` **ONLY IF** `ROLLBACK` succeeded and thread ID matches `$saved_thread_id`.

### 3.2 Read-Only Reconciliation Under Fresh Coordinator Lock
When a caller receives `indeterminate` (e.g. lost commit acknowledgement), it calls `reconcile_create()` or `reconcile_save()`.
Both execute **read-only** under a freshly acquired options-row write lock (`SELECT option_id ... FOR UPDATE` on a fresh pinned connection):

1. **Create Reconciliation (`reconcile_create`)**:
   - Check `wp_options` under lock for `mf_velog_create_<sha256(request_id)>` (exact production marker format).
   - **Case A (Marker Exists)**:
     - Decode marker: verify `type === $type` and `fingerprint === sha256(serialize($canonical))` (exact production canonicalization).
     - Query `wp_posts` and `wp_postmeta` on pinned handle for stored `$created_id`.
     - Verify: `post_type === $type`, `post_status === 'private'`, exactly 1 envelope row, and matching projections per cardinality rule.
     - Authorize actor via `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )`.
     - Return **committed record snapshot** (exact standard snapshot shape).
   - **Case B (Marker Missing)**:
     - Under the fresh write lock, verify marker absence. Because VeLog writers are serialized under this lock and create writes marker and record atomically, marker absence proves the request was not committed.
     - Return `new \WP_Error('not_found', 'Request was not committed; safe to retry with the same request ID.')`.
     - (Retry is safe ONLY with the same request ID, which guards against duplicate creation if state was delayed).
   - **Case C (Marker Corrupt / Mismatched)**:
     - Return `new \WP_Error('indeterminate', 'Corrupt or mismatched request marker.')`.

2. **Save Reconciliation (`reconcile_save`)**:
   - Query `wp_posts` and `_mf_velog_record` for `$id` under fresh lock.
   - Inspect the entire append-only `audit` array from the validated envelope:
   - **Case A (Audit Match Found)**:
     - Search audit entries for entry matching `$request_id` and `$actor->ID`.
     - Verify `$entry['before']` and `$entry['after']` match the expected changes.
     - Save committed successfully. Recheck `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )` on current envelope.
     - Return **committed record snapshot** (reflecting latest state, even if subsequent legitimate saves followed).
   - **Case B (No Audit Match & Version Unchanged)**:
     - `record_version === $expected_version` and no audit entry matches `$request_id`.
     - Save definitely did not commit. Return `new \WP_Error('not_found', 'Save was not committed; safe to retry with the same request ID.')`.
   - **Case C (No Audit Match & Version Advanced)**:
     - `record_version > $expected_version` and no audit entry matches `$request_id`.
     - Another mutation committed. Return `new \WP_Error('stale_version', 'Target record version has advanced.')`.
   - **Case D (Ambiguous State)**:
     - Return `new \WP_Error('indeterminate', 'Unable to determine save outcome.')`.

3. **Safe Invalidation & Timeout Reset in `finally`**:
   - `wp_cache_delete() === false` is a normal cache miss.
   - If `clean_post_cache()` throws an unhandled exception or fails in a way that exposes stale state after a commit, the operation returns `\WP_Error('indeterminate')` rather than masking it.
   - Restore timeout on pinned `$dbh` if thread ID matches.
   - No debug logs (`var_dump`, `console.log`).

---

## 4. D1-F-016: Executable Failure & Fault Controls in Fixtures

### 4.1 Multi-Record Per-Statement Failure Injection (V2)
In `tests/fixtures/wp-integration/data001/v2_concurrency.php`:
Define discrete write steps in `execute_create()` / `execute_save()`:
- Step 1: `wp_posts` insert statement.
- Step 2: `_mf_velog_record` meta insert statement.
- Step 3: `_mf_velog_state` projection meta insert statement.
- Step 4: Custom projection meta insert statements.
- Step 5: `mf_velog_create_<hash>` option insert statement.

Implement test-only fault injector on the pinned `$dbh` handle (via test wrapper) to force failure immediately after each statement:
1. **Fault After Step 1**: Exception after `wp_posts` insert. Assert: `ROLLBACK` executes, post row absent from `wp_posts`, count unchanged.
2. **Fault After Step 2**: Exception after envelope insert. Assert: `ROLLBACK` executes, post and meta rows absent, count unchanged.
3. **Fault After Step 3**: Exception after state projection insert. Assert: `ROLLBACK` executes, post and all meta rows absent.
4. **Fault After Step 4**: Exception after custom projection insert. Assert: `ROLLBACK` executes, all rows absent.
5. **Fault After Step 5**: Exception after option marker insert before commit. Assert: `ROLLBACK` executes, option row, post row, and meta rows all absent.
- Compare raw `SELECT` counts in `wp_posts`, `wp_postmeta`, and `wp_options` before and after each failure on both WP 6.4.3 and 6.7.2.
- Fault injection is strictly test-only and inaccessible from request input.

### 4.2 Comprehensive Connection, Commit & Deadlock Controls (V4)
In `tests/fixtures/wp-integration/data001/v4_cache_failure.php`:
1. **Pre-COMMIT Disconnect**:
   - Begin transaction on pinned handle, perform writes, close/kill connection handle (`mysqli_close($dbh)`).
   - Assert: WriteCoordinator detects broken connection / ping failure, returns `\WP_Error('indeterminate')`, DB state has 0 rows.
2. **Failed COMMIT with Surviving Connection**:
   - Execute transaction, inject query failure during COMMIT statement on `$dbh` (via test wrapper simulating `mysqli_query($dbh, 'COMMIT') === false` with alive connection).
   - Assert: Returns `\WP_Error('indeterminate')`, rollback verified.
3. **Successful COMMIT with Lost Acknowledgement**:
   - Commit executes successfully on MySQL daemon, but test wrapper drops connection before returning response to Coordinator.
   - Assert: Coordinator returns `\WP_Error('indeterminate')`.
   - Call `reconcile_create()` under fresh lock -> confirms `COMMITTED` outcome and returns record.
4. **Lock Timeout (1205)**:
   - Worker 1 holds row lock; writer attempts lock with `innodb_lock_wait_timeout = 5`.
   - Assert: Writer observes error 1205, rollback succeeds, thread ID intact, returns retryable `\WP_Error('conflict')`, elapsed time ~5s.
5. **Genuine Two-Connection Deadlock (1213)**:
   - Concurrently execute two worker processes acquiring locks in reverse order on two distinct option rows (`LOCK_A`, `LOCK_B` vs `LOCK_B`, `LOCK_A`).
   - Assert: MySQL deadlock detector fires, one worker observes error 1213 (`ER_LOCK_DEADLOCK`), coordinator rolls back and returns retryable `\WP_Error('conflict')`.
6. **Reconcile Save Controls**:
   - Case 1 (Committed): Save record, simulate lost response. Call `reconcile_save()`. Assert: returns hydrated record snapshot.
   - Case 2 (Uncommitted): Call `reconcile_save()` with uncommitted request ID. Assert: returns `\WP_Error('not_found')` confirming safe retry with same request ID.
   - Case 3 (Stale Version): Advance version with another request, call `reconcile_save()`. Assert: returns `\WP_Error('stale_version')`.

---

## 5. Normal Builder Corrections (D1-F-017 & D1-F-018)

*Implemented directly under standard Builder assignment without proposal gating.*

### 5.1 D1-F-017: Atomic Process Ownership & Graceful Termination
In `tests/fixtures/wp-integration/data001/run.php`:
1. **Atomic Directory Creation**:
   `$tmp_base = '/tmp/velog_' . bin2hex(random_bytes(16)); mkdir($tmp_base, 0700, true);`
2. **Process Identity Verification**:
   - Read PID from `$tmp_base/mysql.pid`.
   - Verify `/proc/{$pid}/cmdline` contains `$tmp_base`. Record start time from `/proc/{$pid}/stat`.
3. **Targeted Graceful Termination**:
   - Send `posix_kill($pid, SIGTERM)`.
   - Poll `posix_kill($pid, 0)` every 200ms up to 10 seconds.
   - If process is still alive after 10s, send `posix_kill($pid, SIGKILL)`.
   - Eliminate all `pkill -f` commands completely.
4. **Verified Directory Removal**:
   - Verify process is absent (`! posix_kill($pid, 0)`) and `$mysql_socket` is gone.
   - Only then call `rm -rf $tmp_base`.
   - If process termination or directory removal fails, runner exits with code 1.

### 5.2 D1-F-018: Schema Policy Contract & Exposure Classes
In `src/Common/Storage/RecordSchema.php`:
1. **API Preservation**:
   `RecordSchema::register( string $post_type, array $definition ): void`
   Returns `void` and throws `\InvalidArgumentException` on invalid schema definitions.
2. **Explicit Exposure Classification**:
   - Every field in `'fields'` must declare an exposure class in `'field_policies'`: `'public'`, `'internal'`, or `'contact'`.
   - If any field lacks a declared exposure class, throw `\InvalidArgumentException("RecordSchema: Field '{$field}' has no declared exposure policy.")`.
3. **Core Schemas Sealed on Init**:
   - `RecordSchema::register_core_schemas()` registers core schemas with empty `'fields'` and seals the registry.
   - Domain tasks (CUST-001) register their field definitions with explicit exposure classes prior to sealing.
4. **Automatic Contact Redaction**:
   - `RecordRepository::build_snapshot()` automatically inspects `'field_policies'` and redacts all fields with `'contact'` exposure from `payload`, `audit['before']`, and `audit['after']` when actor lacks `mf_velog_read_customer_contacts`.

---

## 6. Verification Evidence Matrix

1. **Lint & Static Analysis**: `composer run lint` (0 PHPCS errors, 0 PHPStan errors).
2. **PHPUnit Unit Tests**: `composer run test` (100% passing tests).
3. **WordPress Integration Matrix**:
   - Disposable WordPress 6.7.2: V1–V5 all pass, negative control passes, 0 failed assertions, clean exit 0.
   - Disposable WordPress 6.4.3: V1–V5 all pass, negative control passes, 0 failed assertions, clean exit 0.
4. **Preserved Boundaries**:
   - Persistent-cache compatibility: Explicitly `NOT VERIFIED`.
   - G-08 Performance Target (28,000 dataset): Explicitly `NOT VERIFIED`.
   - Active site / database: Completely untouched.
