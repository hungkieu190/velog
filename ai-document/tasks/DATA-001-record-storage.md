# DATA-001: Versioned private storage and retained data

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: Codex planning conversation of 2026-09-21.
- Builder session reference: Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session.
- Related checklist items: DATA-001 / AC1–AC4 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates; require CORE-002 and CORE-003 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior

Product records need consistent snapshots and queries without custom tables. Current uninstall deletes velog_settings, conflicting with retained operational configuration. Naive separate post/meta writes cannot ensure reliable finalization or duplicate prevention.

## Scope and references

- Allowed files:
  - New `src/Common/Storage/RecordRepository.php`: typed CRUD gateway using WordPress posts/meta.
  - New `src/Common/Storage/RecordSchema.php`: versioned payload validation and protected metadata definitions.
  - New `src/Common/Storage/WriteCoordinator.php`: scoped multi-record serialization/rollback policy.
  - New `src/Common/Storage/AuditEntry.php`: actor/UTC/reason/before-after representation.
  - Modify `src/Core/PostTypes.php`, `src/Core/Plugin.php`, `uninstall.php`.
  - Tests under `tests/Unit/` and `tests/fixtures/`.
- Preserved: existing Core bootstrap scaffolding, WordPress post architecture. No customer/vehicle/service forms or new database tables.
- Reference documentation: [PLAN-002](PLAN-002-mvp-task-batch.md), [product-plan.md](../product-plan.md), [architecture.md](../architecture.md).

## Implementation blueprint

- Revision: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE (draft pending prerequisites CORE-002 and CORE-003 DONE, and gate G-08).
- Authoritative metadata key `_mf_velog_record` stores versioned array per private post: `schema_version`, `record_version`, `state`, fields, `created_by`/`at`, `updated_by`/`at`, and `audit` list.
- Indexed projections: `_mf_velog_vehicle_id`, `_mf_velog_customer_id`, `_mf_velog_service_date`, `_mf_velog_state`, `_mf_velog_plate_key`, `_mf_velog_vin_key`, `_mf_velog_due_date`, `_mf_velog_snooze_until`.
- Bounded serialization boundary plus DB transaction covering post/meta/projection changes.
- Uninstall retains records, metadata, settings, and terms. Network activation unsupported.

## Acceptance criteria

- [ ] DATA-001 / AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads. Status: DRAFT.
- [ ] DATA-001 / AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates. Status: DRAFT.
- [ ] DATA-001 / AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge. Status: DRAFT.
- [ ] DATA-001 / AC4: Metadata privacy, real storage failure/recovery and cache consistency verified. Status: DRAFT.

## Verification matrix

| Case | AC | Input / Scenario | Expected result |
|---|---|---|---|
| V1 | AC1 | Wrong type/ID, raw audit injection, unknown schema, invalid numeric strings | Invalid mutations rejected; canonical strings unchanged |
| V2 | AC2 | Two readers save version 1; simultaneous same-identifier create | Stale/conflicting request fails; no orphan/index divergence |
| V3 | AC3 | Create synthetic records/settings, uninstall, reinstall/reactivate | Payloads/currency/units/config retained; no existing site touched |
| V4 | AC4 | Warm object cache; fail commit; process termination while owning write boundary | Old coherent record remains or recoverable committed record; no indefinite lock |

## History and archives

- Full pre-lean draft details, pseudocode, and extended verification instructions are archived verbatim at: [ai-document/history/DATA-001/pre-lean.md](../history/DATA-001/pre-lean.md) (SHA-256: `692d61a1c9f298c847139941d2115b4d70670f8108843044f96ee1f4eb692674`).

### Chat handoff prompt

```text
Continue as Architect for DATA-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/DATA-001-record-storage.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is versioned private storage and retained data, AC1–AC4. Require CORE-002 and CORE-003 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
