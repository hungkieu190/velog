# CUST-001: Private customer management

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: CUST-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CORE-004 and DATA-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
Managers cannot yet create or find customers; technician access to vehicle history must not imply unrestricted access to customer contact information.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/CustomerService.php`: create/update/archive/restore validation, safe summary projection and linked-active-vehicle check.
- New `src/Admin/CustomerPage.php`: manager create/detail/edit and authorized action handling.
- New `src/Admin/CustomerListTable.php`: paged native list, name/contact search for manager, allowlisted sorting and archive/restore bulk action.
- Modify `src/Admin/AdminMenu.php` and `src/Core/Plugin.php` composition; optional scoped `src/css/admin.scss` with production output.
- New `tests/Unit/CustomerServiceTest.php`, `tests/fixtures/cust-001-verify.php` and walkthrough.
- Update `ai-document/architecture.md` customer schema and `ai-document/features/customer-records.md` user behavior. No customer WP accounts, marketing consent, export/erasure or vehicle CRUD.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-004 and DATA-001 DONE.
- Remaining readiness gates: G-02/G-03; confirm contact-field visibility and archive/restore policy. Reinspect accepted repository/admin interfaces before READY.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Payload: name, optional phone/email, active/archived state and standard DATA-001 version/audit. Trim outer whitespace; plain-text name 1–200, phone <=64, email <=254 and validate if nonempty. Reject disallowed controls/markup rather than accepting changed identifiers silently; support Unicode names and international phone text without country regex. Duplicate customer names/contacts are allowed; internal ID disambiguates selection.

Manager reads/changes contacts; technician vehicle/service association views receive only {id,name,state}, never hidden contact fields in HTML/data attributes. No standalone contact search for technician. Search uses bounded <=100-character terms, 50 rows/page, parameterized query values, stable name/ID sort. Archive checks active vehicle relations through repository under write coordination; reject while any exist with links only visible to authorized manager. Restore increments version and audit; no physical delete action. Every bulk object separately authorized and reports success/failure without claiming all-or-nothing across unrelated customers.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Define customer schema/summary projection on DATA-001; write contact visibility and archive relation fixtures.
3. S3: Implement domain service and native manager forms/list; reuse menu/assets and repository, never direct unguarded meta writes.
4. S4: Verify valid/invalid/duplicate/concurrent changes and all actor request paths; demonstrate archive/restore and keyboard/mobile/RTL with synthetic names.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
save_customer(actor, input, expected_version):
  authorize manager, validate fields, repository.create_or_save(validated snapshot)
archive_customer(actor,id,expected_version):
  within approved write boundary: reject if active vehicles still reference id
  save archived state + attributed audit
customer_summary(actor,id): authorize operational read; return id/name/state only
```

### Failure and resource lifecycle
Use DATA-001 rollback/version behavior. Archive relation recheck occurs within the same approved write boundary as vehicle reassignment, not a racy pre-check. Invalid form preserves harmless entered values for correction without leaking contacts through URLs, shared notices or logs.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Manager creates/edits/searches Unicode customer records with explicit validation and stable pagination.
- AC2: Customer contact data and every write are protected across list/detail/direct request paths.
- AC3: Archive/restore preserves records and rejects active-vehicle linkage; stale writes/bulk checks behave correctly.
- AC4: Real WordPress CRUD, negative controls and accessible admin journey have evidence.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Unicode names, empty name, duplicate contact, international phone, malformed email, page boundary | CustomerServiceTest::test_customer_validation | Valid records preserve data; duplicates allowed; invalid name/email rejected |
| V2 | AC2 | Manager vs technician/subscriber/anonymous; raw form POST and direct detail | tests/fixtures/cust-001-verify.php | Only approved access; technician summary excludes contacts even in markup/error output |
| V3 | AC3 | Active vehicle link; archive after reassignment; restore; stale version; mixed bulk IDs | tests/fixtures/cust-001-verify.php | Link blocks archive; allowed transitions preserve data/audit; per-object results and no stale overwrite |
| V4 | AC4 | Create -> find -> edit -> archive/restore; keyboard/RTL/narrow viewport | ai-document/walkthroughs/CUST-001.md | Manager journey complete; visible field errors; manual observations recorded |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter CustomerServiceTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/CUST-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for CUST-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/CUST-001-customer-records.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is private customer management, AC1–AC4. Require CORE-004 and DATA-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
