# VEH-001-BE: Vehicle backend contract and persistence

## Current handoff

- Workstream: Backend
- Status: BLOCKED
- Plan revision: 1
- Implementation round: 1
- Blueprint readiness: INCOMPLETE — revalidate existing vehicle scope, shared gates, fixtures and UAT against restored CUST-001 record IDs.
- Owner: Backend Architect
- Contributors and review mode: Backend Architect implementation and `SELF_REVIEWED_BACKEND` required.
- Related checklist items: VEH-001 / AC1–AC3 and backend portion of AC4.
- Evidence: `ai-document/evidence/VEH-001-BE/backend-round-1/`.
- Latest report: G-10 restores private customer IDs and removes the CUST-002 dependency. Vehicle source is unchanged; its existing scope and shared technical gates still need revalidation.
- Temporary resource cleanup: No temporary resources created by this planning reconciliation; historical cleanup is not reverified.
- Next actor: Backend Architect
- Next actor and exact next action: G-10 removes the native-user dependency. Revalidate VEH-001 against private customer record IDs and its existing technical gates before resuming work.

## Approved scope and boundaries

G-10 restores CUST-001 private customer IDs. Revalidate the existing scope and required gates before resuming implementation; this reconciliation adds no vehicle behavior.

Implement vehicle domain validation, schema/projections, private manager/staff backend handlers, owner relation validation, archive/restore, optimistic versions, audit, and server-side tests/fixtures. Allowed application files are `src/Common/Vehicle/`, `src/Common/Storage/RecordSchema.php`, `src/Admin/VehiclePage.php`, `src/Admin/VehicleListTable.php`, `src/Admin/AdminMenu.php`, `src/Core/Plugin.php`, relevant server-side tests/fixtures, and technical/evidence documentation.

Do not change Sass/CSS/JavaScript/generated assets, public routes, REST routes, customer data policy, service workflow, physical odometer resets, commits, deployment, release, or active-site data.

## Stable backend contract

- Require a plate with jurisdiction, or a VIN; preserve display values and use G-04 normalization for uniqueness across active and archived records.
- Validate optional year (1886 through current year + 1), owner relation, state, and baseline. A baseline has original unit, canonical millimetres, and a non-future local date; null is distinct from zero.
- Only managers mutate vehicles. Authorized staff reads never expose customer phone/email.
- Every mutation uses capability, action-specific nonce at HTTP boundaries, exact version, unique request ID, and audit data. Archive/restore retains identifiers and does not rewrite service history.
- G-05 governs chronology: a decrease needs manager reason; backdated readings validate against neighbours and do not automatically replace current reading; resets are out of scope.

## Required gates

- `composer run lint`
- `composer run test -- --filter Vehicle`
- `composer run test`
- applicable isolated WordPress fixture on 6.4.3 and 6.7.2
- `git diff --check`, backend diff/security review, and owned-resource cleanup.

## Acceptance criteria

- AC1 backend: validated unique vehicle identifiers are persisted race-safely.
- AC2 backend: owner relation, authorization, nonce, archive/restore, version, and audit controls fail closed.
- AC3 backend: baseline chronology preserves unknown versus zero and G-05 rules.
- AC4 backend: private server-side routes/handlers and fixture contract are ready for deferred presentation/UAT work.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

G-10 removes the CUST-002 native-user dependency. Revalidate VEH-001 scope, private customer-record ownership, shared locks, technical gates, fixtures and UAT before resuming implementation. Preserve vehicle source and historical evidence. No active-site database changes, commit, deploy, or release.
```
