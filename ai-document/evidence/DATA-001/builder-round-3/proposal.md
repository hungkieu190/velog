# DATA-001 Builder Round 3 Implementation Proposal (Revision 5)

- **Task**: `DATA-001` (Core Record & Audit Storage)
- **Revision**: 5 (Correction Blueprint)
- **Author**: Antigravity Builder
- **Reviewer**: Codex Architect
- **Date**: 2026-09-29
- **Status**: `PROPOSAL_PENDING_APPROVAL` (Preflight gate per AGENTS.md § 3 & Escalation Ledger)
- **Reference**: `ai-document/evidence/DATA-001/architect-proposal-review-4/review.md`
- **Escalated Scope**: `D1-F-013`, `D1-F-014`, `D1-F-015`, `D1-F-016`
- **Normal Builder Scope**: `D1-F-017`, `D1-F-018` (one implementation failure; normal correction path)

---

## 1. D1-F-013: Query Authorization, Projection Equivalence & Duplicate-Page Handling

### 1.1 Public Signature & Pinned Error Vocabulary
- **Preserved Public API**:
  `RecordRepository::query( string $type, array $filters, int $page, \WP_User $actor ): array|\WP_Error`
- **Fixed Page Size**: Exactly 50 items (preserves contract §Public Common API).
- **Pinned Error Codes**:
  - `$page < 1`: return `new \WP_Error('invalid_input', 'Page must be >= 1.')`.
  - `$filters` with unallowlisted keys (anything other than `'state'`): return `new \WP_Error('invalid_input', 'Unsupported query filter.')`.
  - `! $actor->has_cap('mf_velog_read_records')`: return `new \WP_Error('forbidden', 'Actor lacks read capability.')`.
  - On corruption or database failure: return `new \WP_Error('storage_unavailable', ...)`.

### 1.2 Projection Derivation & Equivalence with AccessPolicy
1. **Author & Service Visibility Equivalence**:
   - `post_author`: `RecordRepository::create()` sets `p.post_author = $actor->ID`, matching envelope `created_by = (int) $actor->ID`. Contract line 15 guarantees: "No later record save mutates the post row." Both values are invariant and equal. For services, `p.post_author > 0` directly satisfies `AccessPolicy` context requirement `author > 0`.
   - `_mf_velog_vehicle_visible`: Derived strictly from the trusted context builder `RecordSchema::build_context()`. When `fields.vehicle_visible` is absent or not explicitly `true`, it defaults to `false` (fail-closed). The projection `_mf_velog_vehicle_visible` is written as `'1'` if and only if `$context['vehicle_visible'] === true`, otherwise `'0'`. No relationship rules are added.
   - `_mf_velog_state`: Written as `(string) $envelope['state']`.
2. **Identical Prepared SQL Predicates for Count and Page**:
   Count and page queries share the exact same `JOIN` and `WHERE` predicates:
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
   - Count: `SELECT COUNT(DISTINCT p.ID) ` + predicate.
   - Page: `SELECT p.ID ` + predicate + ` GROUP BY p.ID ORDER BY p.ID ASC LIMIT 50 OFFSET %d`.

### 1.3 Preflight Duplicate Detection & Batch Hydration
1. **Preflight Cardinality Query on Page IDs**:
   Before hydrating, query candidate IDs for duplicate projection rows:
   ```sql
   SELECT post_id, meta_key, COUNT(*) as cnt
   FROM {$prefix}postmeta
   WHERE post_id IN (...)
     AND meta_key IN ('_mf_velog_state', '_mf_velog_vehicle_visible', '_mf_velog_record')
   GROUP BY post_id, meta_key
   HAVING cnt > 1
   ```
   If any count > 1 is found, immediately return `new \WP_Error('storage_unavailable', 'Duplicate projection row detected on query page.')`. Total and total_pages are not returned as exact for that corrupt request.
2. **Single-Batch Page Hydration**:
   Fetch `_mf_velog_record` for the page IDs in one query. For each ID:
   - Must have exactly 1 envelope row.
   - Re-run `AccessPolicy::authorize( $actor, 'mf_velog_read_records', $context )` on the decoded envelope. If policy rejects or state contradicts SQL projection, return `new \WP_Error('storage_unavailable', 'Envelope state contradicts projection.')`. Never return a shortened page.
3. **Transactional Boundary Limit**:
   The exact count derived from projections holds strictly under the coordinator's atomic write invariant (all VeLog writers maintain exactly one row per projection).

---

## 2. D1-F-014: Complete-Tuple Uniqueness & Projection Integrity

### 2.1 Prepared Statement Multi-Join on Pinned Handle
In `RecordRepository::check_unique_keys_under_lock( \mysqli $dbh, string $prefix, string $type, array $fields, ?int $current_id )`:
1. Every attribute in registered unique rule must exist in `$fields` and be a valid scalar string; missing fields fail closed.
2. Prepare atomic query with separate `INNER JOIN` aliases for each key:
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
   Bound using `$stmt->bind_param()` with exact types. Failed prepare, bind, execute, or fetch returns `\WP_Error('storage_unavailable')` and triggers rollback.

### 2.2 Pinned Under-Lock Candidate and Target Integrity
When a candidate ID `C` is returned by the tuple query:
1. Verify candidate has exactly 1 `wp_posts` row with `post_type = $type`, `post_status = 'private'`.
2. Verify candidate has exactly 1 `_mf_velog_record` envelope row.
3. **Projection Derivation and Exact Cardinality per Pinned Rule ("absent projections have no row")**:
   - Compute expected projections using trusted schema rule: `RecordSchema::derive_projections( $type, $candidate_envelope['fields'] )`.
   - Query all projection rows for candidate `C`: `SELECT meta_key, meta_value, COUNT(*) as cnt FROM {$prefix}postmeta WHERE post_id = ? GROUP BY meta_key`.
   - For every registered projection key in schema:
     - If key is present in expected projections with non-null value `$v`: verify `cnt === 1` and `meta_value === (string) $v`.
     - If key is absent/null in expected projections: verify `cnt === 0` (absent projections have no row).
4. If candidate `C` has duplicate rows, missing rows, or value mismatch: candidate is CORRUPTED data. Return `new \WP_Error('storage_unavailable', 'Corrupted candidate record encountered.')`.
5. If candidate `C` is valid and `C !== $current_id`: return `new \WP_Error('conflict', "Unique constraint '{$rule_name}' violated.")`.
6. Same verification runs on the write target before commit during `verify_write()`.

---

## 3. D1-F-015: Transaction Classification & Create/Save Reconciliation

### 3.1 Pinned Connection Identity & Strict Rollback Classification
1. Capture `$saved_thread_id = $dbh->thread_id`.
2. On failure: verify `$current_thread_id === $saved_thread_id` and `@mysqli_ping($dbh)`. If connection changed or dropped -> return `new \WP_Error('indeterminate', 'Connection dropped.')`.
3. Execute `ROLLBACK`. If rollback fails -> return `new \WP_Error('indeterminate', 'Rollback failed.')`.
4. Lock timeout (1205) or deadlock (1213) returns retryable `\WP_Error('conflict')` **ONLY IF** rollback succeeded and thread ID matches `$saved_thread_id`.

### 3.2 Read-Only Reconciliation Under Fresh Coordinator Lock
Executes under fresh write lock (`SELECT option_id FROM {$prefix}options WHERE option_name = 'mf_velog_write_lock' FOR UPDATE` on fresh connection):

1. **Create Reconciliation (`reconcile_create`)**:
   - Inspect `wp_options` under lock for `mf_velog_create_<sha256(request_id)>`.
   - **Marker Exists**:
     - Stored marker shape: `array('type' => $type, 'created_id' => $id, 'payload_fingerprint' => $fp)`.
     - Verify stored `payload_fingerprint === hash('sha256', serialize($canonical))`.
     - If fingerprint or type mismatches: return `new \WP_Error('conflict', 'Request ID reused with different payload.')`.
     - Query stored post ID: verify post row, envelope row, and exact projection cardinality. Authorize actor and return committed record snapshot.
   - **Marker Missing**:
     - Under the fresh write lock, marker absence proves create transaction did not commit (due to atomic option/record insertion under coordinator lock).
     - Return `new \WP_Error('not_found', 'Request was not committed; safe to retry with the same request ID.')`. Safe retry applies strictly to the SAME request ID.
   - **Corrupt Marker**: Return `new \WP_Error('indeterminate', 'Corrupt request marker.')`.

2. **Save Reconciliation (`reconcile_save`)**:
   - Query `wp_posts` and `_mf_velog_record` for `$id` under fresh lock.
   - Target's `audit` array stores entries from `AuditEntry::to_array()` with `'request_id_sha256' => hash('sha256', $request_id)`.
   - Inspect all audit entries in position/version sequence:
     - If an entry matches `request_id_sha256 === hash('sha256', $request_id)`, `actor_id === $actor->ID`, and before/after match expected changes:
       The save committed. Recheck `AccessPolicy::authorize()` on current envelope and return committed record snapshot.
     - If no audit entry matches and `record_version === $expected_version`: save definitely did not commit. Return `new \WP_Error('not_found', 'Save was not committed; safe to retry with the same request ID.')`.
     - If no audit entry matches and `record_version > $expected_version`: another save committed. Return `new \WP_Error('stale_version', 'Target record version has advanced.')`.
     - If audit history is malformed or gap detected: return `new \WP_Error('indeterminate', 'Corrupt audit history.')`.

3. **`finally` Precedence**:
   - If `clean_post_cache()` throws an unhandled exception or fails after a commit, or if timeout restoration fails, return `new \WP_Error('indeterminate')` rather than returning false success.
   - `wp_cache_delete() === false` is treated as a normal cache miss.
   - Zero debug logs.

---

## 4. D1-F-016: Executable Fault Evidence & Architectural Gaps

### 4.1 Discrete Per-Statement Write Failure Injection (V2)
1. **Test Seam in RecordRepository**:
   `RecordRepository::set_test_statement_fault(?string $statement_name)`. In production, this is null and inert.
   Enumerate write steps:
   - Step 1: `wp_posts_insert`
   - Step 2: `envelope_meta_insert`
   - Step 3: `state_projection_insert`
   - Step 4: `custom_projection_insert`
   - Step 5: `create_marker_insert`
2. Test forces exception after each statement executes. Assert: Coordinator rolls back; raw SQL queries on `wp_posts`, `wp_postmeta`, and `wp_options` show 0 rows for that record. Post count unchanged.

### 4.2 Comprehensive Connection, Commit & Deadlock Controls (V4)
1. **Pre-COMMIT Disconnect**: Close connection before `COMMIT`. Assert: returns `indeterminate`, DB has 0 rows.
2. **Successful COMMIT with Lost Acknowledgement**: `COMMIT` executes on DB, test seam immediately closes connection before returning response. Assert: returns `indeterminate`. Raw DB has row; `reconcile_create()` / `reconcile_save()` under fresh lock verifies and returns snapshot.
3. **Lock Timeout (1205)**: 5-second wait on held row lock. Assert: observed error 1205, rollback succeeds, thread ID intact, returns retryable `conflict`.
4. **Architectural Gap: Failed COMMIT with Live Handle**: Standard MariaDB/MySQL without triggers cannot fail a plain COMMIT statement while keeping the connection open. Reported as **NOT VERIFIED**.
5. **Architectural Gap: 1213 Deadlock in Application Write Unit**: Because all VeLog write units serialize on the single row `mf_velog_write_lock`, internal deadlocks between VeLog write units are impossible. Reported as **NOT VERIFIED** for application units. A separate standalone database probe demonstrates MySQL 1213 error mapping in isolation.

---

## 5. Normal Builder Corrections (D1-F-017 & D1-F-018)

- **D1-F-017 (Process Cleanup)**: Atomic directory creation (`bin2hex(random_bytes(16))`), daemon PID tracking, graceful `SIGTERM` with 10s timeout before `SIGKILL`, zero `pkill -f`, verified removal before exit.
- **D1-F-018 (Schema Policy)**: `RecordSchema::register()` preserves `void` return and throws `\InvalidArgumentException` if any field lacks an exposure class (`public`, `internal`, `contact`). Core schemas sealed on init; automatic contact redaction in `build_snapshot()`.
