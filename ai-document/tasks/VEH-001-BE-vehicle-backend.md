# VEH-001-BE: Vehicle backend contract and persistence

## Current handoff

- Workstream: Backend
- Status: IN_PROGRESS
- Plan revision: 1
- Implementation round: 1
- Blueprint readiness: PASS
- Owner: Backend Architect
- Contributors and review mode: Backend Architect implementation and `SELF_REVIEWED_BACKEND` required.
- Related checklist items: VEH-001 / AC1–AC3 and backend portion of AC4.
- Evidence: `ai-document/evidence/VEH-001-BE/backend-round-1/`.
- Temporary resource cleanup: Pending implementation.
- Next actor: Backend Architect
- Next actor and exact next action: Extend the approved identifier foundation through schema, persistence, authorization, and server-side verification, then self-review.

## Approved scope and boundaries

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
Status: IN_PROGRESS
Recipient: Backend Architect
Intent: work

Implement VEH-001-BE only. The identifier foundation and unit tests are in progress; continue through schema, persistence, authorization, and server-side verification. CUST-001-BE, G-04, and G-05 are approved prerequisites; frontend and UAT workstreams remain deferred. Preserve private capability, nonce, optimistic-version, audit, customer-contact, identifier-uniqueness, and odometer-chronology boundaries. Do not edit frontend assets, implement service workflow/reset behavior, commit, deploy, release, or use the active database.
```
