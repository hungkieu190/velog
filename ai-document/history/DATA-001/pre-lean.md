# DATA-001: Versioned private storage and retained data

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: DATA-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CORE-002 and CORE-003 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
Product records need consistent snapshots and queries without custom tables. Current uninstall deletes velog_settings, conflicting with retained operational configuration. Naive separate post/meta writes cannot ensure reliable finalization or duplicate prevention.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/Storage/RecordRepository.php`: typed create/read/list/compare-and-save gateway using existing WordPress posts/meta.
- New `src/Common/Storage/RecordSchema.php`: versioned payload validation per entity and registered protected metadata definitions.
- New `src/Common/Storage/WriteCoordinator.php`: exact scoped multi-record serialization/rollback policy after technical gate resolution.
- New `src/Common/Storage/AuditEntry.php`: validated actor/UTC/reason/before-after representation, persisted with authoritative payload.
- Modify `src/Core/PostTypes.php` metadata registration and `src/Core/Plugin.php` wiring if needed; `uninstall.php` and relevant Activator/Deactivator retention comments/behavior only.
- New `tests/Unit/RecordStorageTest.php`, `tests/fixtures/data-001-verify.php`; extend `tests/workflow/product-smoke.sh` persistence/failure scenarios.
- Update `ai-document/architecture.md` as schema authority and `ai-document/release-readiness.md` retention limitation. No customer/vehicle/service forms or new tables.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-002 and CORE-003 DONE.
- Remaining readiness gates: G-08; complete exact transaction/serialization/locking/recovery design and supported engine checks. Finalize indexed metadata schema and public repository interfaces against accepted primitives/types. This is an explicit technical blocker to READY, not discretion for Builder.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Proposed authoritative metadata key _mf_velog_record stores one versioned array per private post: schema_version, record_version, state, entity-specific fields, created_by/created_at_utc, updated_by/updated_at_utc and audit list. IDs are positive integers; exact amounts/distances strings; dates ISO calendar dates; no PHP objects from input. Registered meta is protected and show_in_rest false with capability-aware authorization. RecordRepository exposes get(type,id,actor), query(type,filters,page,actor), create(type,payload,actor), save(type,id,expected_version,changes,actor). Inputs cannot select arbitrary meta keys or overwrite actor/audit/version.

Indexed projections proposed: _mf_velog_vehicle_id, _mf_velog_customer_id, _mf_velog_service_date, _mf_velog_state, _mf_velog_plate_key, _mf_velog_vin_key, _mf_velog_due_date and _mf_velog_snooze_until. Exact schema/index types, query plans and synchronization must be finalized before READY. The payload is authoritative; an index is not a second independent business truth. Avoid optimistic-save ambiguity when unchanged update_post_meta returns false; version always increments exactly once on real accepted mutation.

A version comparison prevents lost updates on one existing record but cannot by itself prevent duplicate concurrent vehicle creation or coordinate vehicle-reading updates with service finalization. Proposed solution is a bounded per-shop write serialization boundary plus database transaction covering post/meta/projection changes, with engine checks, rollback and cache invalidation; no transaction command may be introduced without a reviewed exact design. If safe CPT-only coordination cannot be demonstrated on supported storage, return a design decision to Architect rather than pretending WordPress APIs provide atomicity.

Retain CPTs, metadata, settings, schema marker and service-type terms on uninstall. Remove only regenerable plugin transient/cache state whose absence cannot reinterpret history. Keep no automatic purge toggle. Network activation/multisite remains unsupported; explicitly fail setup before mutation there.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Inspect real WordPress DB engines and storage APIs in disposable site; document exact locking, contention timeout, transaction/cache ordering, crash recovery and negative tests. Obtain Architect readiness before implementation.
3. S3: Register protected versioned schema and implement authorized repository with expected-version semantics; implement only the approved coordination design.
4. S4: Reconcile uninstall with retention; verify reinstall reads retained data without resetting it. Exercise injected failure and concurrency at every write boundary; publish schema/interface evidence.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
mutate(actor, operation, expected_version):
  authorize and validate all inputs before writes
  acquire approved owned write boundary or return retryable conflict within timeout
  re-read authoritative state and validate version, relations, uniqueness
  begin approved coherent write unit
  write new payload + audit + all query projections
  on failure: rollback, invalidate affected caches, preserve original error
  on success: commit; invalidate caches; release owned boundary; return new version
  finally: release only owned resources; never claim success for partial commit
```

### Failure and resource lifecycle
Exact implementation of rollback, lock ownership/expiry and recovery is a readiness blocker. Proposed contention maximum 5 seconds, retryable conflict on exhaustion; never steal a live owner lock based only on elapsed time. Tests must kill only owned fixture worker and prove recovery. Unsupported engine/configuration must fail before writes with actionable notice, not silently weaken guarantees.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads.
- AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates.
- AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge.
- AC4: Metadata privacy, real storage failure/recovery and cache consistency verified.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Wrong type/ID, raw audit injection, unknown schema, invalid relation and exact numeric strings | RecordStorageTest::test_schema_and_access | Invalid mutations rejected; canonical strings unchanged |
| V2 | AC2 | Two readers save version 1; simultaneous same-identifier create; failure after each persistence step | tests/fixtures/data-001-verify.php concurrent workers | One permitted winner; stale/conflicting request fails; no orphan/index divergence or partial audit |
| V3 | AC3 | Create synthetic records/settings, uninstall, reinstall/reactivate | tests/fixtures/data-001-verify.php isolated copy only | Payloads/currency/units/config retained; no existing site touched |
| V4 | AC4 | Warm object cache; fail commit; process termination while owning write boundary | tests/fixtures/data-001-verify.php failure controls | Old coherent record remains or recoverable committed record; no indefinite lock; subsequent read consistent |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter RecordStorageTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=DATA-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/DATA-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for DATA-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/DATA-001-record-storage.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is versioned private storage and retained data, AC1–AC4. Require CORE-002 and CORE-003 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
