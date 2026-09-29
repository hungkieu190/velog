# DATA-001: Versioned private storage and retained data

## Current handoff
- Status: READY_FOR_REVIEW
- Plan revision: 5
- Implementation round: 4
- Blueprint readiness: PASS for approved revision-5 storage approach; D1-F-021 cleanup proposal revision 2 submitted for review.
- Builder implementation approach: Builder round 4 — corrected D1-F-019/020/022/023 in `RecordRepository.php`; revised V4-J/V4-L negative controls in `v4_cache_failure.php`; submitted D1-F-021 read-only cleanup design revision 2 at `ai-document/evidence/DATA-001/builder-round-4/cleanup-proposal-d1f021.md`.
- Architect session reference: Codex planning 2026-09-21; isolated storage spike 2026-09-25; review round 1 2026-09-29; proposal review 5 2026-09-29; round 4 review 2026-09-29.
- Builder session reference: Antigravity implementation round 4 on 2026-09-29.
- Implementation contributors and reviewer independence check: Antigravity corrected D1-F-019/020/022/023 and submitted D1-F-021 read-only proposal rev 2; Codex Architect must independently review proposal and diffs.
- Related checklist items: DATA-001 / AC1–AC4.
- Baseline branch and commit: `main` at `d438715` before this handoff's documentation changes; preserve accepted CORE-002/CORE-003 work.
- User approval reference and approved scope: PLAN-001 revision 3; on 2026-09-25 the user approved the proposed DATA-001 planning/verification approach and G-08 benchmark target. On 2026-09-29 the user explicitly approved DATA-001 revision 2 Builder implementation after the Architect presented its pinned-connection contract, scope and verification matrix.
- Latest round: Builder implementation round 4.
- Latest evidence: `ai-document/evidence/DATA-001/builder-round-4/cleanup-proposal-d1f021.md` (Revision 2).
- Temporary resource cleanup: No disposable WordPress fixtures run this round (blocked until D1-F-021 cleanup is approved and fixed per task instruction).
- Next actor: Architect
- Next actor and exact next action: Codex Architect reviews D1-F-021 read-only cleanup proposal revision 2 at `ai-document/evidence/DATA-001/builder-round-4/cleanup-proposal-d1f021.md` and records APPROVED FOR IMPLEMENTATION or REVISE PROPOSAL. Also reviews D1-F-019/020/022/023 corrections in `src/Common/Storage/RecordRepository.php` and negative controls in `tests/fixtures/wp-integration/data001/v4_cache_failure.php`. Do not manually accept or claim the task is fully complete.

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
- User approved the planning approach and G-08 target, then explicitly approved this detailed revision 2 Builder scope on 2026-09-29. Application behavior remains NOT VERIFIED.

## Acceptance criteria

- [ ] DATA-001 / AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads. Status: CHANGES_REQUESTED.
- [ ] DATA-001 / AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates. Status: CHANGES_REQUESTED.
- [ ] DATA-001 / AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge. Status: CHANGES_REQUESTED.
- [ ] DATA-001 / AC4: Metadata privacy, real storage failure/recovery and cache consistency verified. Status: CHANGES_REQUESTED.

## Repeated-finding escalation ledger

Implementation reviews count by underlying defect, even when the review assigns a new ID. The round-3 proposal review is not an implementation review and does not increase these counts.

| Underlying defect | Review history | Consecutive failed implementation reviews | Next attempt |
|---|---|---:|---|
| Authorized bounded query/count | D1-F-001 (round 0), D1-F-010 (round 1), D1-F-013 (round 2) | 3 | Escalated: approved correction approach required |
| Unique/projection integrity | D1-F-003 (round 0), retained as open in round 1, D1-F-014 (round 2) | 3 | Escalated: approved correction approach required |
| Transaction classification/reconciliation | D1-F-002 and D1-F-004 (round 0), D1-F-008 and retained reconciliation obligation (round 1), D1-F-015 (round 2) | 3 | Escalated: approved correction approach required |
| Real failure and multi-record controls | D1-F-005 (round 0), D1-F-012 (round 1), D1-F-016 (round 2) | 3 | Escalated: approved correction approach required |
| Owned-process cleanup | D1-F-017 (round 2), D1-F-021 (round 3) | 2 | Escalated: cleanup design submitted at `builder-round-4/cleanup-proposal-d1f021.md`; AWAITING ARCHITECT APPROVAL |
| Extensible sensitive-field policy | D1-F-018 (round 2) | 1 | Normal Builder correction |

The ledger tracks review failure, not whether every subpart was unchanged. Independent review must verify closure per subpart. New findings do not inherit an unrelated defect's count.

Approach gate result: D1-F-013–D1-F-016 are **APPROVED FOR IMPLEMENTATION** by Architect approach review 5, subject to its binding constraints. The review counts and findings remain open until independent implementation review. Live-handle COMMIT failure and coordinator 1213 controls are NOT VERIFIED and are not waived by approach approval.

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
- Builder round 0 report: [builder-round-0](../evidence/DATA-001/builder-round-0/report.md).
- Architect round 0 review and correction blueprint revision 3: [architect-round-0](../evidence/DATA-001/architect-round-0/review.md). D1-F-001–D1-F-006 identified.
- Builder round 1 report: [builder-round-1](../evidence/DATA-001/builder-round-1/report.md). Builder claimed D1-F-001–D1-F-006 resolved; Architect round 1 rejected the verification claim.
- Architect round 1 review and correction blueprint revision 4: [architect-round-1](../evidence/DATA-001/architect-round-1/review.md). The claimed fixture passes were invalid; D1-F-007–D1-F-012 opened.
- Builder round 2 report: [builder-round-2](../evidence/DATA-001/builder-round-2/report.md). Negative control and submitted V1–V4 fixtures passed on disposable WordPress 6.4.3 and 6.7.2; the Builder's full-resolution claim was not accepted.
- Architect round 2 review and correction blueprint revision 5: [architect-round-2](../evidence/DATA-001/architect-round-2/review.md). D1-F-013–D1-F-018 and overlapping prior obligations remain open.
- Builder round-3 proposal: [proposal](../evidence/DATA-001/builder-round-3/proposal.md). Architect approach review 1: [REVISE PROPOSAL](../evidence/DATA-001/architect-proposal-review-1/review.md) for escalated defects. The subsequent workflow change limits the approach gate to repeatedly failed defects; D1-F-017 and D1-F-018 remain normal corrections.
- Builder proposal revision 2: [proposal](../evidence/DATA-001/builder-round-3/proposal.md). Architect approach review 2: [REVISE PROPOSAL](../evidence/DATA-001/architect-proposal-review-2/review.md) for D1-F-013–D1-F-016; no implementation approval for these escalated defects.
- Builder proposal revision 3: [proposal-rev3](../evidence/DATA-001/builder-round-3/proposal-rev3.md). Architect approach review 3: [REVISE PROPOSAL](../evidence/DATA-001/architect-proposal-review-3/review.md) for D1-F-013–D1-F-016; the escalated approach is still unapproved.
- Builder proposal revision 4: [proposal](../evidence/DATA-001/builder-round-3/proposal.md) (prior revision preserved as `proposal-rev3.md`). Architect approach review 4: [REVISE PROPOSAL](../evidence/DATA-001/architect-proposal-review-4/review.md) for D1-F-013–D1-F-016.
- Builder proposal revision 5: [proposal-rev5](../evidence/DATA-001/builder-round-3/proposal-rev5.md) (also synchronized at [proposal](../evidence/DATA-001/builder-round-3/proposal.md); revision 4 preserved as [proposal-rev4](../evidence/DATA-001/builder-round-3/proposal-rev4.md)). Addresses all four blocking points from Architect approach review 4.
- Architect approach review 5: [APPROVED FOR IMPLEMENTATION](../evidence/DATA-001/architect-proposal-review-5/review.md) for D1-F-013–D1-F-016, subject to the review's binding constraints. Implementation, V4 gaps and acceptance remain open.
- Builder round 3 report: [builder-round-3](../evidence/DATA-001/builder-round-3/report.md). Implementation of proposal revision 5 and D1-F-017/D1-F-018; negative control and V1–V4 fixtures pass on WP 6.4.3 and 6.7.2.
- Architect round 3 review: [CHANGES_REQUESTED](../evidence/DATA-001/architect-round-3/review.md). D1-F-019–D1-F-023 remain open; the owned-process cleanup defect has reached the repeated-finding gate.
- Builder round 4 partial: D1-F-019/020/022/023 corrected in `RecordRepository.php`; D1-F-023 negative controls (V4-J–V4-M) added in `v4_cache_failure.php`; D1-F-021 read-only cleanup design submitted at `ai-document/evidence/DATA-001/builder-round-4/cleanup-proposal-d1f021.md`. Lint exits 0; 80 unit tests, 283 assertions pass. No disposable WP fixtures run (blocked until D1-F-021 cleanup approved).
- Architect round 4 review: [CHANGES_REQUESTED; D1-F-021 REVISE PROPOSAL](../evidence/DATA-001/architect-round-4/review.md). The partial corrections have blocking semantic and evidence gaps.

### Chat handoff prompt

```text
Status: READY_FOR_REVIEW
Recipient: Architect
Intent: review + approach-gate

Act as Codex Architect for DATA-001, reviewing Builder round 4 (part 2) work.

First: Review the D1-F-021 cleanup design proposal revision 2 at ai-document/evidence/DATA-001/builder-round-4/cleanup-proposal-d1f021.md. Record APPROVED FOR IMPLEMENTATION or REVISE PROPOSAL before Builder may change run.php.

Second: Review D1-F-019/D1-F-020/D1-F-022/D1-F-023 corrections in src/Common/Storage/RecordRepository.php and negative controls revised in tests/fixtures/wp-integration/data001/v4_cache_failure.php. No disposable WP fixtures were run this round per task instruction.

Live-handle COMMIT failure, coordinator 1213 deadlock, persistent-cache compatibility, and full G-08 product performance remain NOT VERIFIED. Do not self-accept, claim DATA-001 is complete, or touch the active site/database.
```
