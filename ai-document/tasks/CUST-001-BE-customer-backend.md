# CUST-001-BE: Private customer backend and admin contract

> G-10 restores this private customer-record contract. CUST-002 is retired. The backend checkpoint remains SELF_REVIEWED_BACKEND; Tester separately accepted all CUST-001-UAT cases on 2026-10-21.

## Current handoff
- Workstream: Backend
- Status: DONE
- Plan revision: 2
- Implementation round: 3
- Blueprint readiness: PASS — backend scope and required gates passed; FE code approval and explicit Tester acceptance are recorded in the linked workstreams.
- Owner: Developer
- Contributors and review mode: Codex / Backend Architect; SELF_REVIEWED_BACKEND (intentionally non-independent), technical checkpoint only.
- Related checklist items: CUST-001 / AC1–AC3 and backend portion of AC4.
- Latest report: Round 3 closes full lint/static, customer privacy/batch/UTF-8 and state-aware recovery checks. See ai-document/evidence/CUST-001-BE/backend-round-3/report.md. Tester subsequently accepted all CUST-001-UAT cases on 2026-10-21; see the UAT decision.
- Evidence: `ai-document/evidence/CUST-001-BE/backend-round-3/report.md`.
- Temporary resource cleanup: Verified: all owned isolated sites, database/PHP processes and scratch files removed; reusable fixtures/evidence retained.
- Next actor: Tester
- Next actor and exact next action: Execute VEH-001-UAT T01–T15 on prepared LocalWP, starting with T01, and report actual PASS/FAIL/BLOCKED; only Tester may accept the feature.

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

## Authorized continuation — 2026-10-15

The Product Owner instructed "làm tiếp" after the restoration handoff. Continue backend readiness under G-10. The bounded change map includes the existing customer/admin/repository files, tests/Unit/CustomerQueryTest.php, tests/Unit/CustomerServiceTest.php, tests/fixtures/cust-001-verify.php, tests/workflow/cust-001-endpoint.sh, and a read-only tests/fixtures/cust-001-uat-prepare.php prerequisite inventory. It never creates accounts/data and never executes as part of activation/page load. Any later LocalWP provisioning remains a separate explicitly authorized action.

The existing tests/workflow/product-smoke.sh runner may additionally test the inspected LocalWP core 7.1.3 by copying only allowlisted core source into a disposable install; never copy live configuration/content/database. Core test installation skips notification email.

Shared gate repair scope: PHPDoc/formatting and equivalent full ternary in src/Common/Vehicle/VehicleIdentifier.php; documentation in tests/Unit/VehicleIdentifierTest.php; formatting-only src/Common/Storage/RecordSchema.php; remove the redundant scalar check after string-only derive_projections() in RecordRepository, preserving missing-key rejection. No vehicle feature behavior is added.

Correct existing customer requirements: batch snapshot hydration instead of row lookups, actor-scoped POST search with opaque pagination tokens, strict malformed UTF-8 rejection, and native semantic PHP controls. Keep classic pages and selectors stable. Add positive/negative runtime assertions for these boundaries. Frontend CSS corrections remain a separate FE assignment; no backend agent asset/source edits. LocalWP may be inspected read-only; live fixture writes still require explicit authorization, not a blanket continuation.

## Audit recovery finding in shared-gate continuation

DATA-001 V4 exposed seven reconciliation failures. Source inspection found that CUST-001 saves append `__state` to before/after snapshots, while create snapshots and the old reconciler compare fields only. This predates the current batch decoder change. The bounded repair stays in RecordRepository: compare read-only normalized audit snapshots, using the registered initial state only for pre-state-format snapshots, and compare the final snapshot against both current fields and state. Never rewrite persisted history or remove explicit state checks. Existing request/actor/version/change comparisons remain authoritative. Repeat V1–V5 and add customer history recovery coverage; state-changing reconcile_save calls without an explicit state contract must continue to fail safely rather than guessing intent.

## Authorized UAT preparation — 2026-10-15

The Product Owner explicitly instructed "vậy là đến bạn xử lý à, làm đi thôi" in response to the documented preparation handoff. This supersedes the earlier live-write prohibition only for two new dedicated staff users, 51 pagination customers, one linked customer and one active linked vehicle specified in CUST-001-UAT. Backup first; no existing identities or records may be overwritten/adopted/deleted. Additional allowed fixture scope: `tests/fixtures/cust-001-uat-seed.php`, `tests/workflow/cust-001-uat-seed.sh`, CUST-001 invocation in `tests/workflow/product-smoke.sh`, synchronized UAT/task/checklist/README/environment docs and `ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/`. No runtime or frontend changes.

## UAT preparation checkpoint

SELF_REVIEWED_BACKEND: isolated WordPress 7.1.3/MySQL 8.4 provisioning regression and final lint pass; authorized LocalWP setup and read-only verification complete. See `ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/report.md`. No runtime or FE asset changes; Next actor Tester for manual UAT. This technical checkpoint does not accept the feature.

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
Status: DONE
Recipient: Tester
Intent: accept

Run VEH-001-UAT T01–T15 in ai-document/tasks/VEH-001-UAT-manual-test.md on https://velog.local/wp-admin/, starting with T01. Developer completed technical self-review and verified the authorized LocalWP fixtures; see ai-document/evidence/VEH-001-UAT/preparation-2026-10-21/report.md. Customer A/B/Archived IDs are 66/67/68; pagination vehicles are 69–119; subscriber test actor is ID 4. Keep pagination fixtures unchanged until T11 and use Customer B for T06. Record actual PASS/FAIL/BLOCKED and environment/tool details; missing RTL/screen-reader tools must be reported. Only explicit Tester acceptance permits DONE. No commit, deploy or release.
```
