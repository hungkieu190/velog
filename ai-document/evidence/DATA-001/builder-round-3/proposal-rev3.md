# DATA-001 Builder Round 3 Implementation Proposal (Revision 3)

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 5 (Correction Blueprint)
- **Author**: Antigravity Builder
- **Reviewer**: Codex Architect
- **Date**: 2026-09-29
- **Status**: `PROPOSAL_PENDING_APPROVAL` (Preflight gate per AGENTS.md § 3 & Escalation Ledger)
- **Escalated Scope**: `D1-F-013`, `D1-F-014`, `D1-F-015`, `D1-F-016` (following Architect approach review 2)
- **Normal Builder Scope**: `D1-F-017`, `D1-F-018` (one implementation failure; normal correction path)

---

## 1. D1-F-013: Bounded Authorized Query and Exact Count

### 1.1 Public API & Parameter Constraints
- **Preserved Public Signature**:
  `RecordRepository::query( string $type, array $filters, int $page, \WP_User $actor ): array|\WP_Error`
- **Fixed Page Size**: Exactly 50 items (no `$per_page` parameter; preserves pinned contract).
- **Page Validation**: `$page = (int) $page; if ($page < 1) return new \WP_Error('invalid_page', 'Page must be >= 1.');`
- **Capability Check**: `$actor->has_cap('mf_velog_read_records')` checked immediately; returns `\WP_Error('unauthorized')` if lacking.
- **Allowlisted Filters**: Only registered, allowlisted query filters are accepted: `'state'` (string). Any unallowlisted key immediately returns `\WP_Error('invalid_filter', 'Unsupported query filter.')`.

### 1.2 Fixed Scalar Query Projections & SQL Predicates
To evaluate queries and calculate exact totals in SQL without loading large historical audit envelopes into PHP memory:
1. **Registered Core Projections**:
   Every record automatically projects its scalar lifecycle state into `wp_postmeta` under the fixed, protected key `_mf_velog_state` during the write unit.
   For `mf_velog_service`, the object policy read predicates (`state in ('draft', 'finalized')`, `post_author > 0`, and `_mf_velog_vehicle_visible = '1'`) are represented by:
   - `p.post_author > 0` (native column in `wp_posts`).
   - `_mf_velog_state` in `('draft', 'finalized')`.
   - `_mf_velog_vehicle_visible = '1'` (scalar projection derived from vehicle relationship during service write).
2. **Exact Authorized SQL Count Query**:
   ```sql
   SELECT COUNT(DISTINCT p.ID)
   FROM {$wpdb->posts} p
   INNER JOIN {$wpdb->postmeta} pm_state 
     ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
   -- For service only:
   -- INNER JOIN {$wpdb->postmeta} pm_vis 
   --   ON pm_vis.post_id = p.ID AND pm_vis.meta_key = '_mf_velog_vehicle_visible' AND pm_vis.meta_value = '1'
   WHERE p.post_type = %s
     AND p.post_status = 'private'
     AND (%s = '' OR pm_state.meta_value = %s)
   ```
   Executed using prepared statements on `$wpdb`. Returns exact `$total`.
3. **Bounded Candidate Page Query**:
   ```sql
   SELECT p.ID
   FROM {$wpdb->posts} p
   INNER JOIN {$wpdb->postmeta} pm_state 
     ON pm_state.post_id = p.ID AND pm_state.meta_key = '_mf_velog_state'
   -- For service only: INNER JOIN pm_vis ...
   WHERE p.post_type = %s
     AND p.post_status = 'private'
     AND (%s = '' OR pm_state.meta_value = %s)
   ORDER BY p.ID ASC
   LIMIT 50 OFFSET %d
   ```
   Selects at most 50 IDs for the requested page.

### 1.3 Bounded Batch Hydration & Integrity Verification
1. **Single-Batch Fetch**:
   Query all postmeta for the selected IDs:
   `SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN (...)`
2. **Strict Invariant Verification Per ID**:
   For each ID in the candidate list:
   - Must have **exactly 1** `_mf_velog_record` row. (If 0 or >1 -> return `\WP_Error('corrupted_record')`).
   - Must have **exactly 1** row for each required projection key (`_mf_velog_state`, etc.). (If 0 or >1 -> return `\WP_Error('corrupted_record')`).
   - Projection values must match envelope attributes exactly.
   - Execute `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )` on the decoded envelope.
   - If policy rejects the record or payload disagrees with SQL selection: return `\WP_Error('corrupted_record')`. NEVER silently drop rows to return a partial page.
3. **Structured Response**:
   ```php
   return array(
       'items'       => $hydrated_page, // array of 0..50 snapshots
       'total'       => (int) $total,
       'page'        => $page,
       'per_page'    => 50,
       'total_pages' => (int) ceil( $total / 50 ),
   );
   ```
4. **Transactional Projection Invariant Limit**:
   The exact count derived from projections holds true under the invariant that projection rows are written atomically with the authoritative envelope inside `WriteCoordinator`. This boundary is explicitly documented.

---

## 2. D1-F-014: Complete-Tuple Uniqueness and Projection Integrity

### 2.1 Prepared Statement Complete-Tuple Predicate
In `RecordRepository::check_unique_keys_under_lock( \mysqli $dbh, string $prefix, string $type, array $fields, ?int $current_id )`:
1. **Tuple Validation**:
   - Inspect registered `$schema['unique_keys']`. Each rule maps a name to an array of field names, e.g. `'unique_vehicle' => array('make', 'model')`.
   - Every attribute in the tuple must exist in `$fields` and be a valid scalar string. If missing, fail closed.
2. **Dynamic Parameterized SQL via Pinned Prepared Statement**:
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
3. **Safe Binding & Execution**:
   - Prepare statement via `$stmt = $dbh->prepare( $sql )`. If prepare fails -> return `\WP_Error('storage_unavailable')`.
   - Bind parameters using `bind_param()` with exact types (`s` for strings, `i` for current_id).
   - Execute `$stmt->execute()`. If execute or fetch fails -> return `\WP_Error('storage_unavailable')` and trigger transaction rollback.
   - Close statement. No interpolated user strings, no database error strings exposed in caller message.
4. **Collision Result**:
   - If a matching ID is found: return `new \WP_Error('conflict', "Unique constraint '{$rule_name}' violated.")`.
   - Distinct tuples sharing key A (e.g. `(make='Toyota', model='Camry')` vs `(make='Toyota', model='Corolla')`): `m0` matches but `m1` does not match; 0 rows returned -> Valid.
   - The same check runs on `create()` and on `save()` whenever any component of a composite unique rule changes.

### 2.2 Strict Projection Cardinality Under Lock
1. **Zero-Tolerance for Duplicate Projection Rows**:
   During `verify_write()` before commit:
   - For every registered projection key: query `SELECT COUNT(*) FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ?`.
   - If count !== 1 -> return `\WP_Error('corrupted_record', 'Duplicate or missing projection row detected.')`.
   - Authoritative envelope row `_mf_velog_record` must also have count === 1.

---

## 3. D1-F-015: Transaction Result & Read-Only Create/Save Reconciliation

### 3.1 Pinned Connection Identity & Strict Rollback Classification
In `WriteCoordinator::run()`:
1. **Thread Identity Tracking**:
   - Record `$saved_thread_id = $dbh->thread_id;` upon pinning.
   - On exception or failure:
     - Check `$current_thread_id = @mysqli_thread_id( $dbh );`
     - If `$current_thread_id !== $saved_thread_id` or `! @mysqli_ping( $dbh )`:
       Return `new \WP_Error('indeterminate', 'Database connection changed or disconnected during transaction.')`.
2. **Rollback Confirmation**:
   - Execute `ROLLBACK` on pinned handle.
   - If `ROLLBACK` returns false:
     Return `new \WP_Error('indeterminate', 'Transaction rollback failed.')`.
   - Lock timeout (1205) or deadlock (1213) returns retryable `\WP_Error('conflict')` **KHI VÀ CHỈ KHI** `ROLLBACK` succeeded and thread ID matches `$saved_thread_id`.

### 3.2 Read-Only Reconciliation Under Fresh Coordinator Lock
When a caller receives `indeterminate` (e.g. lost commit acknowledgement), it calls:
- `WriteCoordinator::reconcile_create( \WP_User $actor, string $type, string $request_id, array $payload )`
- `WriteCoordinator::reconcile_save( \WP_User $actor, string $type, int $id, int $expected_version, string $request_id, array $changes )`

Both execute **read-only** under a freshly acquired options-row write lock:
1. **Create Reconciliation**:
   - Check `wp_options` under lock for `_mf_velog_req_{sha256($request_id)}`.
   - **Case A (Marker Exists)**:
     - Decode marker JSON: verify `type === $type` and `payload_fingerprint === sha256(json_encode($payload))`.
     - Direct SQL query `wp_posts` and `wp_postmeta` for stored `post_id`.
     - Verify: `post_type === $type`, `post_status === 'private'`, exactly 1 envelope row, and matching projections.
     - Authorize actor via `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )`.
     - Return **committed record snapshot** (standard snapshot shape, exact same as normal `create()`).
   - **Case B (Marker Missing)**:
     - Under the fresh write lock, verify no post row exists in `wp_posts` with that request marker or unique key collision.
     - Return `new \WP_Error('not_found', 'Request was not committed; safe to retry.')`.
   - **Case C (Marker Mismatch / Corrupt)**:
     - Return `new \WP_Error('indeterminate', 'Corrupt or mismatched request marker.')`.
2. **Save Reconciliation**:
   - Direct query `wp_posts` and `_mf_velog_record` for `$id` under fresh lock.
   - Inspect entire append-only `audit` array for `$request_id`, `$actor->ID`, `$expected_version`, and change fingerprint:
   - **Case A (Audit Match Found)**:
     - Save committed successfully. Authorize actor and return **committed record snapshot**.
   - **Case B (No Audit Match & Version Unchanged)**:
     - `record_version === $expected_version` and no audit entry matches `$request_id`.
     - Return `new \WP_Error('not_found', 'Save was not committed; safe to retry.')`.
   - **Case C (No Audit Match & Version Advanced)**:
     - `record_version > $expected_version` and no audit entry matches `$request_id`.
     - Another mutation committed. Return `new \WP_Error('stale_version', 'Concurrent update committed by another actor.')`.
   - **Case D (Ambiguous State)**:
     - Return `new \WP_Error('indeterminate', 'Unable to determine save outcome.')`.
3. **Zero Mutation During Reconciliation**: Reconciliation performs `SELECT` queries only; no `INSERT`, `UPDATE`, or `DELETE`.

### 3.3 Cache Invalidation & Timeout Reset in `finally`
- In `finally`:
  - `wp_cache_delete( $post_id, 'posts' )` and `wp_cache_delete( $post_id, 'post_meta' )`. A return value of `false` is treated as a normal cache miss.
  - `clean_post_cache( $post_id )` is wrapped in try/catch. If an exception occurs that leaves cache dirty after a committed write, the operation returns `\WP_Error('indeterminate')` to prevent exposing stale data.
  - Restore `innodb_lock_wait_timeout` on pinned `$dbh` if thread ID is intact.
  - Zero debug logs (`var_dump`, `console.log`).

---

## 4. D1-F-016: Executable Failure & Fault Controls in Fixtures

### 4.1 Multi-Record Failure Injection After Each Step (V2)
In `tests/fixtures/wp-integration/data001/v2_concurrency.php`:
Define discrete write steps inside `WriteUnit`:
- Step 1: `wp_posts` row inserted.
- Step 2: Authoritative `_mf_velog_record` inserted in `wp_postmeta`.
- Step 3: Projections inserted in `wp_postmeta`.
- Step 4: Request marker inserted in `wp_options`.

Execute 4 distinct negative tests injecting a fault after each reachable step:
1. **Fault After Step 1**: Force exception after post row inserted. Assert: `ROLLBACK` executes, post row deleted from `wp_posts`, post count unchanged.
2. **Fault After Step 2**: Force exception after envelope inserted. Assert: `ROLLBACK` executes, post and meta rows deleted, post count unchanged.
3. **Fault After Step 3**: Force exception after projections inserted. Assert: `ROLLBACK` executes, all post and projection rows deleted.
4. **Fault After Step 4**: Force exception after request marker inserted before commit. Assert: `ROLLBACK` executes, request option, post row, and meta rows all absent.
- Compare raw `SELECT` counts in `wp_posts`, `wp_postmeta`, and `wp_options` before and after each failure on both WP 6.4.3 and 6.7.2.
- Fault injection mechanism: Controlled via a test-only unit callback wrapper, completely inaccessible from HTTP/request input.

### 4.2 Comprehensive Connection & Commit Fault Controls (V4)
In `tests/fixtures/wp-integration/data001/v4_cache_failure.php`:
1. **Disconnect Before COMMIT**:
   - Start transaction on pinned handle, perform writes, kill connection (`$dbh->kill($dbh->thread_id)` or `mysqli_close($dbh)`).
   - Assert: WriteCoordinator returns `\WP_Error('indeterminate')`, no rows committed in database.
2. **Failed COMMIT with Surviving Connection**:
   - Inject SQL failure during COMMIT (e.g. deferrable constraint violation or trigger error).
   - Assert: Returns `\WP_Error('indeterminate')`, rollback verified.
3. **Deadlock (1213) & Lock Timeout (1205) Handling**:
   - Worker 1 holds row lock; writer times out (1205).
   - Assert: Rollback verified, thread ID intact, returns retryable `\WP_Error('conflict')`, elapsed time ~5s.
4. **Reconcile Create Control**:
   - Case 1 (Committed): Create record, simulate lost response. Call `reconcile_create()`. Assert: returns hydrated record snapshot.
   - Case 2 (Uncommitted): Simulate uncommitted request ID. Call `reconcile_create()`. Assert: returns `\WP_Error('not_found')` confirming safe retry.
5. **Reconcile Save Control**:
   - Case 1 (Committed): Save record, simulate lost response. Call `reconcile_save()`. Assert: returns hydrated record snapshot.
   - Case 2 (Uncommitted): Call `reconcile_save()` with uncommitted request ID. Assert: returns `\WP_Error('not_found')` confirming safe retry.
   - Case 3 (Stale): Advance version with another request, call `reconcile_save()`. Assert: returns `\WP_Error('stale_version')`.

---

## 5. Normal Builder Corrections (D1-F-017 & D1-F-018)

*Implemented directly under standard Builder assignment without proposal gating.*

### 5.1 D1-F-017: Atomic Process Ownership & Graceful Termination
In `tests/fixtures/wp-integration/data001/run.php`:
1. **Atomic Directory Creation**:
   `$tmp_base = '/tmp/velog_' . bin2hex(random_bytes(16)); mkdir($tmp_base, 0700, true);`
2. **Process Identity Verification**:
   - Read PID from `$tmp_base/mysql.pid`.
   - Inspect `/proc/{$pid}/cmdline` to verify it contains `$tmp_base`. Record start time from `/proc/{$pid}/stat`.
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
   `RecordSchema::register( string $type, array $definition ): void`
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
