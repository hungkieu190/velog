# PLAN-002: Prepare the complete MVP implementation queue

## Current handoff
- Status: DRAFT
- Plan revision: 2
- Architect session reference: Codex conversation started 2026-09-21.
- Builder session reference: CORE-003 completed by Antigravity Builder; remaining draft tasks unassigned.
- Implementation contributors and reviewer independence check: Planning documents only; no implementation.
- Related checklist items: PLAN-002 / AC1–AC3; tasks CORE-002 through MVP-001.
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: Continuous planning requested 2026-09-21; G-02 approved 2026-09-22; G-08 benchmark target approved for planning 2026-09-25. Other proposed business rules remain pending.
- Latest round: CORE-003 accepted; DATA-001 draft revision 2 prepared — 2026-09-25.
- Next actor: Architect
- Next actor and exact next action: Architect presents DATA-001 revision 2 for Builder implementation approval and maintains remaining draft queue.

## Problem and intended behavior

PLAN-001 approved the international private single-shop MVP. CORE-001, CORE-002 and CORE-003 are accepted; DATA-001 is the next bounded assignment candidate. This batch defines ten bounded blueprints across P0–P4 so implementation can proceed across clear dependency boundaries.

## Scope and references

- Planning documents only. Covers dependency queue, product decisions G-01–G-08, and shared blueprint contract.
- Reference documentation: [product-plan.md](../product-plan.md), [internationalization.md](../internationalization.md), [decisions.md](../decisions.md), [architecture.md](../architecture.md), [testing-strategy.md](../testing-strategy.md), [build-and-release.md](../build-and-release.md).

## Dependency queue

| Order | Task | Accepted predecessors required | Outcome |
|---|---|---|---|
| 0 | [CORE-001](CORE-001-bootstrap-i18n.md) | Existing approved scope | Bootstrap hooks & translation (DONE) |
| 1 | [CORE-002](CORE-002-regional-primitives.md) | CORE-001 | Distance/money/date primitives (DONE) |
| 2 | [CORE-003](CORE-003-access-private-types.md) | CORE-001 | Roles/capabilities & private CPTs (DONE) |
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
- **G-08 (APPROVED for planning 2026-09-25)**: Acceptance dataset (1k customers, 2k vehicles, 20k services, 5k reminders); warm p95 <= 2s on specified list/search queries with recorded environment; operational retention on uninstall. Actual benchmark results remain NOT VERIFIED until the domain queries exist.

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
Status: DRAFT
Recipient: Architect
Intent: work

Continue PLAN-002 revision 2 planning. CORE-001 through CORE-003 are DONE, and G-02 and G-08 are approved. DATA-001 revision 2 has blueprint readiness PASS but still requires explicit approval of its bounded Builder implementation scope. Resolve remaining G-01/G-03–G-07 decisions in their owning tasks. Preserve role separation and checklist state; do not implement application code, dispatch Builder, commit, deploy or use the active database without authorization.
```
