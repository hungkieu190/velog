# PLAN-002: Prepare the complete MVP implementation queue

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: Codex conversation started 2026-09-21.
- Builder session reference: CORE-003 assigned to Antigravity Builder; remaining draft tasks unassigned.
- Implementation contributors and reviewer independence check: Planning documents only; no implementation.
- Related checklist items: PLAN-002 / AC1–AC3; tasks CORE-002 through MVP-001.
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: Continuous planning requested 2026-09-21; G-02 approved 2026-09-22 for CORE-003. Other proposed business rules remain pending.
- Latest round: CORE-003 dispatch update — 2026-09-22.
- Next actor: Architect
- Next actor and exact next action: Builder implements CORE-003 revision 2. Architect maintains remaining draft queue and resolves G-03–G-08 before future assignments.

## Problem and intended behavior

PLAN-001 approved the international private single-shop MVP. CORE-001 and CORE-002 are accepted; CORE-003 is the next bounded assignment. This batch defines ten bounded blueprints across P0–P4 so implementation can proceed across clear dependency boundaries.

## Scope and references

- Planning documents only. Covers dependency queue, product decisions G-01–G-08, and shared blueprint contract.
- Reference documentation: [product-plan.md](../product-plan.md), [internationalization.md](../internationalization.md), [decisions.md](../decisions.md), [architecture.md](../architecture.md), [testing-strategy.md](../testing-strategy.md), [build-and-release.md](../build-and-release.md).

## Dependency queue

| Order | Task | Accepted predecessors required | Outcome |
|---|---|---|---|
| 0 | [CORE-001](CORE-001-bootstrap-i18n.md) | Existing approved scope | Bootstrap hooks & translation (DONE) |
| 1 | [CORE-002](CORE-002-regional-primitives.md) | CORE-001 | Distance/money/date primitives (DONE) |
| 2 | [CORE-003](CORE-003-access-private-types.md) | CORE-001 | Roles/capabilities & private CPTs (IN_PROGRESS) |
| 3 | [DATA-001](DATA-001-record-storage.md) | CORE-002, CORE-003 | Versioned storage & retention |
| 4 | [CORE-004](CORE-004-regional-settings.md) | CORE-002, CORE-003, DATA-001 | Explicit setup & shared admin shell |
| 5 | [CUST-001](CUST-001-customer-records.md) | CORE-004, DATA-001 | Customer create/edit/search/archive |
| 6 | [VEH-001](VEH-001-vehicle-records.md) | CUST-001 | Vehicle identity, owner, baseline odometer |
| 7 | [SERV-001](SERV-001-service-workflow.md) | VEH-001 | Draft/finalized services & corrections |
| 8 | [HIST-001](HIST-001-service-timeline.md) | SERV-001 | Searchable private service timeline |
| 9 | [REM-001](REM-001-maintenance-queue.md) | HIST-001 | Maintenance queue & reminders |
| 10 | [MVP-001](MVP-001-acceptance-package.md) | All preceding tasks | MVP integration, performance & package |

## Consolidated proposed product decisions

- **G-01**: Regional input (localized decimals, no thousands grouping in inputs, ASCII digits, max 3 decimals for km/mi, 15 digits for money). Pending.
- **G-02 (APPROVED 2026-09-22)**: Roles `mf_velog_manager` and `mf_velog_technician`; administrator gets plugin capabilities. Manager manages settings/records/corrections; technician manages own service drafts.
- **G-03**: Customer name 1–200 chars, optional phone/email; archive instead of hard delete; active vehicle reassignment required before customer archive. Pending.
- **G-04**: Plate OR VIN required; plate uniqueness by jurisdiction + normalized plate; VIN up to 64 chars; year 1886 to current+1. Pending.
- **G-05**: Service finalization requires odometer reading/date; decrease requires manager reason; backdated insertion rules. Pending.
- **G-06**: Drafts editable by creator/manager; finalization snapshotting; corrections manager-only with reason and before/after audit; active service type required. Pending.
- **G-07**: Reminders by date/distance; snooze to calendar date; completed is terminal. Pending.
- **G-08**: Acceptance dataset (1k customers, 2k vehicles, 20k services, 5k reminders); warm p95 <= 2s; operational retention on uninstall. Pending.

## Shared blueprint contract summary

- **Architecture**: Core bootstrap/Loader composition; named callbacks; Common services own logic; Admin owns forms/tables; private CPTs + protected meta. No custom tables or public REST.
- **Security**: POST writes with action/object nonces and capability checks; strict unslashed scalar validation; contextual output escaping.
- **Quality Gates**: `composer run lint`, `composer run test`, `git diff --check`, `npm run production` (when frontend assets change). Real WordPress tests run in isolated runner.

## Acceptance criteria

- [x] PLAN-002 / AC1: Ten bounded draft tasks cover the approved P0–P4 journey with dependencies and specifications.
- [x] PLAN-002 / AC2: Pending product choices and technical gates are visible.
- [ ] PLAN-002 / AC3: User decisions recorded and tasks promoted individually. Status: PENDING.

## History and archives

- Full pre-lean draft details, extended contract specifications, and verification logs are archived verbatim at: [ai-document/history/PLAN-002/pre-lean.md](../history/PLAN-002/pre-lean.md) (SHA-256: `d75b354f3c04fd54310d8f395313ace05ea78e727aa1ed67dfbdc37e365b26a5`).

### Chat handoff prompt

```text
Continue as Architect for PLAN-002, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md, ai-document/implementation-checklist.md and all ten task links in the Dependency queue. Draft scope covers CORE-002 through MVP-001. Next: resolve G-01–G-08, complete technical gates, and reinspect accepted dependencies before any READY assignment. Preserve role separation and checklist acceptance state. Do not implement application code, start Builder, commit, deploy or touch the active database.
```
