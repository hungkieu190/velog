# SERV-001: Service drafts, finalization and corrections

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: Codex planning conversation of 2026-09-21.
- Builder session reference: Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session.
- Related checklist items: SERV-001 / AC1–AC4 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates; require VEH-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY.

## Problem and intended behavior

Staff must record real maintenance with durable original units/currency and attributed corrections. Editing a finalized record must not quietly erase history or alter old ownership.

## Scope and references

- Allowed files:
  - New `src/Common/ServiceService.php`: draft creation/save/finalize and manager correction.
  - New `src/Common/ServiceTypeService.php`: manager-managed service taxonomy terms.
  - Extend `src/Common/OdometerPolicy.php`: neighbor checks, deterministic current reading, reasoned corrections.
  - New `src/Core/ServiceTypes.php`: private taxonomy registration.
  - New `src/Admin/ServicePage.php`, `src/Admin/ServiceTypePage.php`: admin forms and actions.
  - Wiring in `src/Core/Plugin.php`, `src/Admin/AdminMenu.php`.
  - Tests under `tests/Unit/` and `tests/fixtures/`.
- Preserved: Core bootstrap scaffolding, private CPT architecture. No timeline screen (HIST-001), uploads, or invoicing.
- Reference documentation: [PLAN-002](PLAN-002-mvp-task-batch.md), [product-plan.md](../product-plan.md), [architecture.md](../architecture.md).

## Implementation blueprint

- Revision: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE (draft pending VEH-001 DONE and gates G-05/G-06).
- Service payload: `vehicle_id`, `service_date`, `reading` (original/unit/canonical), `service_type_id` and label snapshot, `notes`, `technician_id`, `customer_id`/name snapshot, optional exact cost, state (`draft`/`finalized`), `audit`/`version`.
- Drafts visible/editable only to creator or manager. Finalization requires active vehicle, current customer, valid date <= today, known reading, and active type.
- Finalized record snapshots customer name and service type at finalization. Corrections require manager capability, reason (1–1000 chars), and append before/after snapshots without overwriting audit history.
- Current vehicle reading recomputed deterministically by service date descending, then ID descending.

## Acceptance criteria

- [ ] SERV-001 / AC1: Creator/manager draft and finalization rules enforce required relations, dates, reading and type. Status: DRAFT.
- [ ] SERV-001 / AC2: Optional costs and original units/customer/type snapshots preserve historical meaning. Status: DRAFT.
- [ ] SERV-001 / AC3: Corrections/decreases are manager-only, attributed/reasoned and cannot erase prior audit. Status: DRAFT.
- [ ] SERV-001 / AC4: Backdating/ties/concurrent transitions and failures preserve coherent current reading and service state. Status: DRAFT.

## Verification matrix

| Case | AC | Input / Scenario | Expected result |
|---|---|---|---|
| V1 | AC1 | Own/other technician draft; manager; archived vehicle; empty type | Allowed finalization succeeds; unauthorized transitions rejected |
| V2 | AC2 | JPY/USD/KWD cost; owner/type change after finalization | Exact stored amount/unit and historical snapshots unchanged |
| V3 | AC3 | Lower reading with/without reason; technician correction | Reasoned manager change accepted; others rejected; audit preserved |
| V4 | AC4 | Backdated service, equal date IDs, double finalization | Deterministic latest reading; one successful transition; coherent rollback |

## History and archives

- Full pre-lean draft details, pseudocode, and extended verification instructions are archived verbatim at: [ai-document/history/SERV-001/pre-lean.md](../history/SERV-001/pre-lean.md) (SHA-256: `f38e7598362a3ba89038b5c6a9ca928dc7e251a4a7d6ddb0a759d3c381a81ab6`).

### Chat handoff prompt

```text
Continue as Architect for SERV-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/SERV-001-service-workflow.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is service drafts, finalization and corrections, AC1–AC4. Require VEH-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
