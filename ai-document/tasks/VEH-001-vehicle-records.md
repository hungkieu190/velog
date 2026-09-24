# VEH-001: Vehicle identity and current customer

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: Codex planning conversation of 2026-09-21.
- Builder session reference: Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session.
- Related checklist items: VEH-001 / AC1–AC4 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates; require CUST-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY.

## Problem and intended behavior

No vehicle registration/current-owner workflow exists. Identity must work internationally and prevent concurrent exact duplicates without silently merging vehicles or rewriting service ownership history.

## Scope and references

- Allowed files:
  - New `src/Common/VehicleService.php`: validation, current customer change, archive/restore, baseline odometer.
  - New `src/Common/VehicleIdentifier.php`: explicit normalization and duplicate lookup keys.
  - New `src/Common/OdometerPolicy.php`: shared baseline/dated-reading ordering and decrease detection.
  - New `src/Admin/VehiclePage.php`, `src/Admin/VehicleListTable.php`: manager CRUD and staff list/detail.
  - Modify `src/Admin/AdminMenu.php`, `src/Core/Plugin.php`.
  - Tests under `tests/Unit/` and `tests/fixtures/`.
- Preserved: Core bootstrap scaffolding, private CPT architecture. No public passport, QR, photos, or owner portal.
- Reference documentation: [PLAN-002](PLAN-002-mvp-task-batch.md), [product-plan.md](../product-plan.md), [architecture.md](../architecture.md).

## Implementation blueprint

- Revision: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE (draft pending CUST-001 DONE and gates G-03/G-04/G-05).
- Payload: `plate`/jurisdiction and normalized key, `vin` and normalized key, `make`/`model`, optional `year` (1886 to current+1), `color`, `current_customer_id`, `preferred_odometer_unit`, optional baseline reading, state (`active`/`archived`), and audit/version.
- Normalization applies identically to lookup and write. Uniqueness covers archived and active records. Duplicate edit-self allowed.
- Baseline date required if reading supplied (cannot be future date). Unknown baseline remains null. Baseline updates after finalized services route through correction policy.
- Changing current customer updates vehicle record only; does not mutate existing service snapshots.

## Acceptance criteria

- [ ] VEH-001 / AC1: Plate-or-VIN registration validates international data and exact duplicate rules, including concurrent create. Status: DRAFT.
- [ ] VEH-001 / AC2: Owner relation/reassignment and archive/restore preserve links/history semantics. Status: DRAFT.
- [ ] VEH-001 / AC3: Unit-aware known/unknown baseline and dates are valid; no unreasoned lower-reading shortcut. Status: DRAFT.
- [ ] VEH-001 / AC4: Authorized search/detail/admin flows and negative security/accessibility checks pass. Status: DRAFT.

## Verification matrix

| Case | AC | Input / Scenario | Expected result |
|---|---|---|---|
| V1 | AC1 | Plate-only, nonstandard VIN, Unicode plate; same plate different jurisdiction; exact duplicate | Approved identities accepted; duplicates blocked; concurrent create winner |
| V2 | AC2 | Missing/archived customer; reassign; archive/restore | Invalid relations denied; audit records owner change; no historical rewrite |
| V3 | AC3 | Unknown vs 0; 1 mi; future date; decrease | Null/0 distinguished; canonical mm stored; invalid chronology rejected |
| V4 | AC4 | Manager create/search; technician read; subscriber denied | Only permitted views/writes; 50-row pagination; no contacts leaked to technician |

## History and archives

- Full pre-lean draft details, pseudocode, and extended verification instructions are archived verbatim at: [ai-document/history/VEH-001/pre-lean.md](../history/VEH-001/pre-lean.md) (SHA-256: `9c70532bffa41123f7e11b9d8d8cf7e525713ceed9aa0b39098437728dce3b44`).

### Chat handoff prompt

```text
Continue as Architect for VEH-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/VEH-001-vehicle-records.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is vehicle identity and current customer, AC1–AC4. Require CUST-001 DONE (and its foundation dependencies) and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
