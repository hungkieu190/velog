# CUST-002: Native WordPress customer identities

> RETIRED BY G-10 (2026-10-15). The specifications and old approvals below are historical; they do not authorize native-user implementation. BLOCKED is used because the workflow has no cancelled status. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md.

## Current handoff

- Workstream: Backend planning
- Status: BLOCKED
- Plan revision: 4
- Blueprint readiness: INCOMPLETE — G-10 retires this native-user direction; no further implementation or UAT.
- Owner: Backend Architect
- Contributors and review mode: Product Owner approved G-09, B2 choices and the bounded initial adapter; backend readiness work remains with Backend Architect.
- Related checklist items: CUST-002 / AC1–AC4.
- Latest report: Native-user direction withdrawn by the Product Owner on 2026-10-15. Unaccepted runtime/test additions were removed after backup; earlier evidence is historical, not feature acceptance.
- Evidence: `ai-document/native-customer-write-contract.md`; `ai-document/evidence/CUST-002-BE/feasibility-round-1/report.md`.
- Temporary resource cleanup: Owned disposable databases and scratch directories removed; reusable probes retained in tests/fixtures/cust-002-feasibility/.
- Next actor: Backend Architect
- Next actor and exact next action: Do not resume CUST-002. Follow CUST-001 restoration under G-10; this document is historical only.

## Problem and intended behavior

VeLog customer identity must be native WordPress user identity, not a private customer CPT. WordPress user APIs are authoritative; VeLog must not synchronize a duplicate customer store.

## Approved scope and boundaries

- Each customer is one `WP_User` with a unique required `user_login` and `user_email`, plus the `mf_velog_customer` role.
- Customer account state is `pending`, `invited`, `active`, or `archived`; every non-active state is denied authentication at the server boundary.
- Password invitation and reset use WordPress APIs. VeLog never exposes, creates a recoverable copy of, or stores a password.
- VeLog-specific state/version/audit data uses namespaced user meta or an approved VeLog audit store. Vehicle ownership uses the native user ID.
- Existing `mf_velog_customer` records require a resumable, idempotent migration. Duplicate/missing identity data is reported for manager remediation; it is never silently merged, overwritten, or synthesized as a final identity.
- Out of scope until a later approved blueprint: source changes, data migration, email delivery configuration, customer portal/UI work, commit, deployment, release, and active-site database access.

## Required design decisions for blueprint readiness

1. Define exact WordPress field mapping and the namespaced meta schema, including optimistic version and audit retention.
2. Define a minimal customer role/capability policy that cannot grant staff access or expose customer data through native endpoints.
3. Define server-side authentication denial for pending, invited, and archived accounts, including password-reset/invitation boundaries.
4. Define idempotent migration preflight, dry-run report, conflict remediation, batching, rollback/failure semantics, and audit preservation.
5. Replace CUST-001 test/UAT cases that assume duplicate or optional email and private-CPT customer storage.
6. Rebase VEH-001 ownership validation and relation checks on native user IDs only after the CUST-002 contract is approved.

## Proposed delivery split — 2026-10-15

- [CUST-002-BE](CUST-002-BE-native-customer-backend.md): Proposed native identity, account/authentication controls, privacy, persistence, migration and verification contract. DRAFT; Next actor: Backend Architect; B2 and the adapter compatibility boundary are approved; remaining technical readiness work is pending.
- [CUST-002-FE](CUST-002-FE-native-customer-ui.md): Proposed presentation scope only. DRAFT; Next actor: Backend Architect; no frontend dispatch before the backend view contract is stable.
- [CUST-002-UAT](CUST-002-UAT-native-customer-test.md): Vietnamese manual test draft. DRAFT; Next actor: Backend Architect for preparation; Tester remains the manual execution/acceptance owner.

Current environment is [LocalWP](../local-environment.md). URLs and credentials supplied by the owner are not test evidence or migration authorization. Restore-to-pending and pending/invited vehicle ownership were approved by the owner's later reply, not by writing the drafts. The candidate [native write contract](../native-customer-write-contract.md) and [feasibility report](../evidence/CUST-002-BE/feasibility-round-1/report.md) document the material new compatibility restriction. Primitive results are not application acceptance.

## Acceptance criteria

- AC1: Customer identity has one authoritative native WordPress user representation with no dual-write synchronization.
- AC2: Login, account state, capabilities, email uniqueness, and privacy boundaries are enforced by server-side WordPress integration.
- AC3: Legacy customer data migration is preflighted, resumable, idempotent, and fails safely on identity conflicts.
- AC4: Vehicle ownership and customer administration are updated to native user IDs with exhaustive positive/negative tests and manual UAT.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

G-10 retires CUST-002 without acceptance. Preserve historical evidence and follow CUST-001 restoration; do not implement native customer accounts, invitation/reset hooks, or migration. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md. No active-site database changes, commit, deploy, or release.
```
