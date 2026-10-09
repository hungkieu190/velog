# CUST-002: Native WordPress customer identities

## Current handoff

- Workstream: Backend planning
- Status: DRAFT
- Plan revision: 1
- Blueprint readiness: INCOMPLETE
- Owner: Backend Architect
- Contributors and review mode: Product Owner approved G-09; backend blueprint pending.
- Related checklist items: CUST-002 / AC1–AC4.
- Latest report: G-09 replaces the CUST-001 private-CPT customer model. No source or database changes were made for this decision.
- Evidence: `ai-document/decisions.md` — G-09.
- Temporary resource cleanup: No temporary resources created.
- Next actor: Backend Architect
- Next actor and exact next action: Produce a decision-complete CUST-002-BE blueprint and migration contract before any source change or VEH-001 continuation.

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

## Acceptance criteria

- AC1: Customer identity has one authoritative native WordPress user representation with no dual-write synchronization.
- AC2: Login, account state, capabilities, email uniqueness, and privacy boundaries are enforced by server-side WordPress integration.
- AC3: Legacy customer data migration is preflighted, resumable, idempotent, and fails safely on identity conflicts.
- AC4: Vehicle ownership and customer administration are updated to native user IDs with exhaustive positive/negative tests and manual UAT.

### Chat handoff prompt

```text
Status: DRAFT
Recipient: Backend Architect
Intent: work

Create the decision-complete CUST-002-BE blueprint for G-09 before changing source code. Define the native WP_User mapping, customer role/account-state authentication controls, privacy boundaries, and an idempotent migration that reports duplicate or missing identity data without silent changes. Rebase VEH-001 only after this contract is approved. No commit, deploy, release, or active-site database migration.
```
