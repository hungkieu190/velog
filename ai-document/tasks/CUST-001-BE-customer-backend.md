# CUST-001-BE: Private customer backend and admin contract

## Current handoff
- Workstream: Backend
- Status: AWAITING_MANUAL_ACCEPTANCE
- Plan revision: 1
- Implementation round: 2
- Blueprint readiness: PASS
- Owner: Backend Architect
- Contributors and review mode: Backend Architect implementation and SELF_REVIEWED_BACKEND for T02 correction; Tester acceptance pending.
- Related checklist items: CUST-001 / AC1–AC3 and backend portion of AC4.
- Latest report: Approved T02 fix implemented; targeted static gates, 93 unit tests, MariaDB/MySQL fixtures and HTTP form regression pass. Full lint remains blocked by unrelated VEH-001 work.
- Evidence: `ai-document/evidence/CUST-001-BE/backend-round-2/report.md`.
- Temporary resource cleanup: Completed; disposable WordPress runners removed their owned resources.
- Next actor: Tester
- Next actor and exact next action: Retest T02 and related UAT cases; preserve T01 PASS and record actual results.

## Problem and intended behavior

Managers need secure customer create, edit, search, archive, and restore operations. Customer contact fields must not leak to technicians or unrelated WordPress users, and state transitions must preserve history and reject stale or linked archive attempts.

## Approved scope and boundaries

Allowed application files:

- `src/Common/Customer/CustomerService.php`
- `src/Common/Customer/CustomerQuery.php`
- `src/Common/Customer/CustomerRelations.php`
- `src/Common/Storage/RecordSchema.php`
- `src/Common/Storage/RecordRepository.php` only for a reusable, schema-driven query/transition primitive that cannot safely live in the customer service
- `src/Admin/CustomerPage.php`
- `src/Admin/CustomerListTable.php`
- `src/Admin/AdminMenu.php`
- `src/Admin/Assets.php` only for the exact customer page hook allowlist
- `src/Core/Plugin.php`
- `tests/Unit/CustomerServiceTest.php`
- `tests/Unit/CustomerQueryTest.php`
- `tests/fixtures/cust-001-verify.php`
- `tests/workflow/product-smoke.sh` only if task registration is required
- `ai-document/architecture.md`
- `ai-document/features/customer-records.md`
- `ai-document/walkthroughs/CUST-001.md`
- task, checklist, and evidence documentation for this workstream

Prohibited scope: Sass/CSS/JavaScript presentation changes, generated frontend assets, vehicle CRUD or reassignment behavior, customer WordPress accounts, marketing consent, export/erasure, hard delete, REST endpoints, public routes, active LocalWP data mutation, commit, push, deploy, or release.

## Stable backend contract

- Customer fields are `name`, optional `phone`, and optional `email`; state is `active` or `archived` in the DATA-001 envelope.
- `name` is trimmed Unicode plain text, 1–200 characters. `phone` is optional trimmed plain text up to 64 characters without a country-specific regex. `email` is optional, no longer than 254 characters, and must pass WordPress email validation without silently accepting a changed value.
- Contact fields use schema exposure `contact`. Only actors with `mf_velog_read_customer_contacts` receive them. Technician-safe summaries contain exactly `id`, `name`, and `state`.
- Duplicate names, phones, and emails are allowed. Internal record ID disambiguates customers.
- Manager search accepts one scalar term up to 100 characters and allowlisted state/sort/direction/page inputs. Default order is normalized name ascending with ID ascending as deterministic tie-break. Allowed alternatives are name or ID, ascending or descending. Page size is 50.
- Search projections are schema-derived and written atomically with the authoritative envelope. Contact projection values remain inaccessible to unauthorized application paths and are never emitted in URLs, notices, HTML attributes, or logs.
- Archive is allowed only when `CustomerRelations` finds zero active vehicles with the reserved current-customer projection. The relation check and state save must run under the accepted DATA-001 write coordination boundary to prevent a check/write race.
- The reserved vehicle projection contract is `_mf_velog_current_customer_id`, a positive decimal customer ID or absent. CUST-001-BE may define and read this contract; VEH-001 owns vehicle assignment validation and atomic writes.
- Create, edit, archive, restore, and each bulk item require manager capability, action-specific nonce at the HTTP boundary, scalar allowlisting, exact expected version where applicable, and a unique request ID. Repository/service authorization is repeated for direct callers.
- Bulk transitions are per-customer operations with per-item success/failure results. No cross-customer atomicity is claimed.
- PHP renders semantic native WordPress markup and all escaped labels/errors. The dependent frontend task may style the stable classes but cannot change backend behavior.

## Verification matrix

| Case | Expected result |
|---|---|
| Valid Unicode create/edit | Canonical fields persist; version and audit advance exactly once |
| Empty/oversized/control-markup name, malformed email, arrays | Typed rejection; prior record unchanged |
| Duplicate name/contact | Allowed; records remain distinguishable by ID |
| Manager list/search/sort/page | Bounded prepared query, stable order, 50 rows/page, no query in row loop |
| Technician/subscriber/anonymous reads and direct requests | Contacts and mutation paths denied; technician summary has only ID/name/state |
| Valid nonce with denied actor; invalid nonce with manager | Both reject without writes |
| Stale edit/archive/restore and replayed request ID | No stale overwrite; idempotent retry does not duplicate audit |
| Active vehicle relation | Archive rejected atomically; customer and vehicle remain unchanged |
| Reassigned/archived vehicle relation | Customer archive succeeds with version/audit update |
| Mixed bulk IDs | Each ID authorized and reported separately; malformed/foreign IDs fail closed |
| Asset hooks | Exact customer pages are allowlisted; unrelated admin pages remain clean |
| Disposable WordPress journey | Manager CRUD and negative controls pass without using active LocalWP data |

## Required gates

- `composer run lint`
- `composer run test -- --filter 'Customer(Service|Query)Test'`
- `composer run test`
- `bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.4.3`
- Repeat product smoke for WordPress 6.7.2 where the runner supports it.
- `git diff --check`
- Inspect backend diff, security boundaries, SQL plans for search/relation queries, cleanup, and `git status --short`.

## Acceptance criteria

- AC1 backend: validated customer CRUD and bounded stable search work in unit and real WordPress fixtures.
- AC2 backend: capability, contact redaction, nonce, direct-call authorization, escaping, and private routing pass positive and negative controls.
- AC3 backend: versioned archive/restore and active-vehicle blocking are race-safe and audited; no hard delete exists.
- AC4 backend: stable semantic markup, scoped page hooks, fixture setup, and walkthrough contract are ready for presentation verification.
- Backend Architect records `SELF_REVIEWED_BACKEND`; any failed required gate keeps the task open.

## UAT reopening — T02

Round 1 review is historical; Round 2 records the implemented T02 correction. See `ai-document/evidence/CUST-001-BE/uat-t02-triage/report.md`. Approved additional scope: `src/Common/Storage/WriteCoordinator.php`, `src/Admin/CustomerPage.php`, `tests/Unit/WriteCoordinatorTest.php`, `tests/fixtures/cust-001-verify.php`, `tests/workflow/product-smoke.sh`, and `tests/workflow/cust-001-endpoint.sh`. User approved this correction with "sửa đi". Implementation is complete and self-reviewed; see backend-round-2/report.md. Preserve unrelated vehicle/storage work.

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Tester
Intent: accept

T02 backend correction is SELF_REVIEWED_BACKEND. Reload Customers and repeat T02: create a Unicode customer, confirm the success notice and saved row, edit the phone, save, and reload to confirm persistence. Then run T04, T06, T07, T09, T10, and T13 for related regressions. T01 remains PASS; the original T02 FAIL remains recorded until your retest. Evidence: ai-document/evidence/CUST-001-BE/backend-round-2/report.md. Report PASS/FAIL with actual results; only Tester may authorize DONE after the remaining UAT cases are accepted.
```
