# DATA-001: Versioned private storage and retained data

## Current handoff
- Status: DRAFT
- Plan revision: 2
- Implementation round: 0
- Blueprint readiness: PASS (implementation approval pending)
- Architect session reference: Codex planning 2026-09-21; isolated storage spike 2026-09-25.
- Builder session reference: Unassigned.
- Implementation contributors and reviewer independence check: No application implementation; Codex Architect owns planning and must independently review later Builder code.
- Related checklist items: DATA-001 / AC1–AC4.
- Baseline branch and commit: `main` at `a70b985` plus staged accepted CORE-003 work; preserve that state.
- User approval reference and approved scope: PLAN-001 revision 3; on 2026-09-25 the user approved the proposed DATA-001 planning/verification approach and G-08 benchmark target. Builder implementation has not been dispatched.
- Latest round: Architect draft revision 2 and disposable storage spike.
- Latest report: `ai-document/evidence/DATA-001/architect-spike-1/review.md`.
- Evidence: `ai-document/evidence/DATA-001/architect-spike-1/`.
- Next actor: Architect
- Next actor and exact next action: Present revision 2 and its bounded pinned-connection contract for explicit user approval of Builder implementation; then set READY and hand off to Antigravity Builder.

## Problem and intended behavior

VeLog needs one private, versioned source of record for customer, vehicle, service and reminder data. A version check cannot prevent two concurrent creates or coordinate multi-record changes. Current `uninstall.php` deletes `velog_settings`, contrary to the approved retention rule.

## Scope and change map

- Proposed new Common classes: `Storage/RecordRepository.php`, `Storage/RecordSchema.php`, `Storage/WriteCoordinator.php`, `Storage/AuditEntry.php`. Repository owns persistence; schema owns trusted type/projection definitions; coordinator owns one bounded write unit; audit values are generated internally.
- Proposed existing changes: `src/Core/PostTypes.php` for protected, non-REST metadata registration; `src/Core/Plugin.php` only for required registration wiring; `uninstall.php` for retention. Update `ai-document/architecture.md` after the storage contract is proven.
- Tests: focused unit tests plus disposable WordPress fixtures on 6.4.3 and 6.7.2. Extend the product smoke runner only for DATA-001 cases without weakening CORE-003 controls.
- No forms, public routes, custom tables, product-specific plate/VIN/contact/service rules, active-site data migration, hard delete, or automatic purge. Later domain tasks register their own field validators and projection definitions; unregistered schemas fail closed.

## Implementation blueprint — revision 2

The bounded execution contract is [contract.md](../evidence/DATA-001/architect-spike-1/contract.md). It is the detailed API, SQL, error and test authority for this revision; the summary below must be read with it.

### Repository contract and invariants

1. Trusted code registers one schema per private CPT. `RecordRepository` offers `get(type,id,actor)`, bounded `query(type,filters,page,actor)`, `create(type,validated_fields,actor,request_id)`, and `save(type,id,expected_version,validated_changes,actor,reason)`. A trusted write-unit callback allows related records to be rechecked and mutated under one coordinator lock; user input cannot supply that callback or arbitrary SQL/meta keys. Reads and writes require the relevant CORE-003 capability and object policy. HTTP controllers in later tasks verify nonces before invoking the repository.
2. The single authoritative protected meta key `_mf_velog_record` stores `schema_version`, `record_version`, `state`, validated `fields`, `created_by/at_utc`, `updated_by/at_utc`, and append-only `audit`. Version begins at 1 and increments once per accepted mutation. Reject unknown schema versions, PHP objects, unknown fields, raw actor/version/audit input and malformed IDs. No binary floats. When a registered field contains a CORE-002 distance or money value, retain original value, unit/currency/scale/catalog provenance and exact canonical string; validate or derive with the accepted regional primitive. Historical currency meaning must not be recomputed from later settings/catalog revisions.
3. Schema definitions expose only fixed protected scalar projection keys. The payload is authoritative; projections must match it. Reject duplicate rows. Domain identifiers and query plans remain in later tasks. Queries use prepared values, allowlisted filters/sorts, stable ID tie breaks and bounded pages (maximum 50); no full `SELECT *` or one query per returned row.
4. Create requires a caller-generated request ID. Under the write lock, a transactional, non-autoloaded `wp_options` key derived from its hash records the created ID and payload fingerprint. A repeated matching request returns the existing result; a reused ID with different payload fails. This guards ambiguous retries. Retain the key on uninstall. Check schema-defined uniqueness under the same lock.

### Write boundary and failure behavior

1. Before a product write, confirm single-site mode, a supported MySQL/MariaDB `mysqli` connection, no pre-existing transaction, and InnoDB for the site's posts, postmeta and options tables. Unsupported state fails before product mutation. Create the fixed, non-autoloaded lock option idempotently before beginning the unit.
2. Pin the current connection; set a bounded InnoDB row-lock timeout (candidate: five seconds); begin a transaction and acquire `SELECT option_id FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE` on that fixed row. Timeout/error returns a retryable conflict after rollback. Do not use session-level `GET_LOCK` as the primary write boundary.
3. Re-read authoritative rows and relationships under the lock; check expected versions, unique keys, actor policy and request ID. Write only plugin-owned posts/meta/options on the pinned connection with parameterized SQL. Do not use `$wpdb->query()`/post/meta write helpers for transactional statements until an executable test proves their reconnect/hook behavior safe: local core retries queries after reconnect. Defer all non-database side effects until after commit. Treat any connection change, uncertain commit acknowledgement or unexpected SQL result as an indeterminate failure, never an automatic create retry.
4. Commit only after verifying post, payload and projection rows on the pinned connection. On any pre-commit failure, rollback. In `finally`, invalidate affected post and meta caches after either commit or rollback and release the transaction-scoped lock. Canonical repository reads bypass object cache; tests must prove no stale `get_post()`/meta result remains after failure. An ambiguous result is reconciled under a new lock using request ID and authoritative rows before retrying.
5. The lock serializes all VeLog writers for this single-shop MVP. A caller that bypasses the repository is outside the supported mutation contract; detect inconsistent/duplicate rows and fail closed. Connection failure, deadlock, lock timeout, cache invalidation failure and interrupted process must never be reported as a successful partial write. No nested write units.

### Retention and benchmark

- Uninstall retains all four private CPTs, metadata, request/lock options, settings including `velog_settings`, schema/version markers, and operational taxonomy terms. Remove only proved regenerable transients; no automatic purge or multisite migration. Test uninstall/reinstall in a disposable site only.
- G-08 is an approved acceptance target: 1,000 customers, 2,000 vehicles, 20,000 services and 5,000 reminders; warm p95 <= 2 seconds for specified operational list/search queries on a recorded isolated environment. DATA-001 must establish the benchmark generator and query-plan measurement contract. The final product benchmark belongs to MVP-001 when those domain queries exist; do not claim it passes now.

## Blueprint readiness and implementation limits

- **R1 PASS for planning**: Direct SQL on a pinned `mysqli` connection acquired a transaction-scoped options-row lock and committed/rolled back post/meta rows on WP 6.4.3 and 6.7.2. A separate disconnect probe showed the pinned handle did not auto-retry and the uncommitted row vanished. The full repository is not implemented.
- **R2 PASS for planning**: The isolated tests demonstrated stale `get_post()` after rollback and its removal with `clean_post_cache()` on both versions. The contract requires invalidation on commit and rollback and warmed-cache integration tests. A persistent cache drop-in remains NOT VERIFIED and is a Builder verification requirement, not a claim of compatibility.
- **R3 PASS for planning**: `contract.md` freezes the public API, errors, type registry, storage map, request-ID reconciliation and transaction/cache sequence. Entity-specific field/identifier rules stay in their later tasks and cannot be invented by Builder.
- **R4 PASS for planning**: The verification matrix below and `contract.md` specify failure, concurrency, cleanup and evidence controls. Actual DATA-001 application results remain NOT VERIFIED.
- User approved the planning approach and G-08 target. Explicit approval of this detailed revision 2 Builder scope is still required before changing status to READY or dispatching implementation.

## Acceptance criteria

- [ ] DATA-001 / AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads. Status: DRAFT.
- [ ] DATA-001 / AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates. Status: DRAFT.
- [ ] DATA-001 / AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge. Status: DRAFT.
- [ ] DATA-001 / AC4: Metadata privacy, real storage failure/recovery and cache consistency verified. Status: DRAFT.

## Verification matrix

| Case | AC | Scenario | Required evidence |
|---|---|---|---|
| V1 | AC1 | Unknown schema/field, forged actor/audit/version, malformed exact distance/money, stale version | Rejection without mutation; accepted canonical/original strings unchanged |
| V2 | AC2 | Two workers save version 1; simultaneous same unique-key create; same request ID retry; multi-record failure after each write | One coherent winner; conflict or exact idempotent result; no orphan/projection divergence |
| V3 | AC3 | Synthetic records/settings and request marker, uninstall, reinstall | IDs, historical units/currency, audit, options and terms retained |
| V4 | AC4 | Warm cache, lock timeout, failed SQL/commit, disconnect/crash and recovery | No false success, bounded wait, no persistent lock, coherent read after invalidation |
| V5 | G-08 | Generate agreed dataset, inspect query plans and sample bounded list/search latency | Record versions/environment and p95; report NOT VERIFIED until domain queries exist |

## History and archives

- Revision 1 and extended draft rationale: [pre-lean archive](../history/DATA-001/pre-lean.md).
- Planning spike: [architect-spike-1](../evidence/DATA-001/architect-spike-1/review.md). These observations are not application verification.

### Chat handoff prompt

```text
Status: DRAFT
Recipient: Architect
Intent: work

DATA-001 revision 2 has Blueprint readiness PASS and is ready for user review. CORE-002 and CORE-003 are DONE; the user approved the planning approach and G-08 target on 2026-09-25. Present the pinned-connection contract and verification matrix for explicit Builder implementation approval. Application behavior, persistent-cache compatibility and G-08 performance remain NOT VERIFIED. Do not dispatch Builder, change application code, commit, deploy or use the active site database before approval.
```
