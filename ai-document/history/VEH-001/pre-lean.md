# VEH-001: Vehicle identity and current customer

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: VEH-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CUST-001 DONE (and its foundation dependencies); reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
No vehicle registration/current-owner workflow exists. Identity must work internationally and prevent concurrent exact duplicates without silently merging vehicles or rewriting service ownership history.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/VehicleService.php`: vehicle validation, current-customer change, archive/restore and baseline odometer.
- New `src/Common/VehicleIdentifier.php`: explicit normalization and duplicate lookup keys; no locale-specific plate regex.
- New `src/Common/OdometerPolicy.php`: shared baseline/dated-reading ordering and decrease detection, later extended by SERV-001.
- New `src/Admin/VehiclePage.php`, `src/Admin/VehicleListTable.php`: manager CRUD and staff permitted detail/list; all queries via repository.
- Modify `src/Admin/AdminMenu.php` and `src/Core/Plugin.php` wiring; optional admin Sass and matching production output.
- New `tests/Unit/VehicleServiceTest.php`, `tests/fixtures/veh-001-verify.php`; update `ai-document/architecture.md` and `ai-document/features/vehicle-records.md`.
- No public passport, QR, photos, VIN lookup service, owner portal or service entry UI.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CUST-001 DONE (and its foundation dependencies).
- Remaining readiness gates: G-03/G-04/G-05; approve conservative Unicode-preserving identifier policy, jurisdiction requirement, reading chronology and archive behavior. Verify DATA-001 duplicate serialization before READY.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Payload: plate/display jurisdiction and normalized identity key, VIN/display normalized key, make/model (<=100 each), optional year (proposed 1886 through local current year+1), color <=64, current_customer_id, preferred_odometer_unit, optional baseline reading with original/unit/canonical/date, active/archived state and DATA-001 audit/version. Optional year range requires explicit confirmation in G-04 before READY. Require active customer when creating/reassigning; changing current customer does not mutate any existing service snapshot.

G-04 normalization applies identically to lookup and write. Encode jurisdiction+plate without ambiguous concatenation; uniqueness covers archived and active records. VIN uniqueness is independent of plate and does not require 17 characters. Duplicate edit-self is allowed; no bypass override. Display exact stored spelling alongside normalized matching behavior. Avoid building uniqueness from locale-dependent case folding or unsupported intl extensions.

Baseline date is required if a reading is supplied; cannot be future local date. Unknown baseline remains null. All reading updates carry original unit/canonical value. Baseline changes after finalized services exist are not an independent editable shortcut: route through SERV-001's correction policy or deny until it exists. Archived vehicles remain readable by authorized staff but cannot receive new services/reminders; ownership and identifiers are retained. Manager may restore with active owner and uniqueness revalidation. Do not silently unarchive the customer.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Approve identifier/range policy; implement normalization and shared reading order fixtures using CORE-002 and DATA-001.
3. S3: Implement manager vehicle registration/edit/reassignment/archive and staff scoped list/detail; enforce relation existence/active state and concurrent uniqueness.
4. S4: Verify same/near-duplicate identities, Unicode, cross-unit readings and archive restrictions; document current-owner/history boundary before service implementation.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
create_or_update_vehicle(actor,input,expected_version):
  authorize manager; parse identity/customer/reading and require configured defaults where needed
  within DATA-001 write boundary:
    reread active customer; check normalized plate+jurisdiction and VIN excluding self
    reject conflict including archived matches; save complete payload + projections + audit
reassign_owner(): update current owner + audit only; never modify services
archive(): save archived state; downstream queries exclude from actionable workflows
```

### Failure and resource lifecycle
Uniqueness lookup and persistence must share DATA-001 serialization; ordinary lookup-before-insert is insufficient. On failed owner/reading/identity validation, leave all prior fields and projections unchanged. Conflicts identify an existing vehicle only when actor may view it. Bulk archive/restore reports each object and rechecks ownership.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Plate-or-VIN registration validates international data and exact duplicate rules, including concurrent create.
- AC2: Owner relation/reassignment and archive/restore preserve links/history semantics.
- AC3: Unit-aware known/unknown baseline and dates are valid; no unreasoned lower-reading shortcut.
- AC4: Authorized search/detail/admin flows and negative security/accessibility checks pass.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Plate-only, nonstandard VIN, Unicode plate; same plate different jurisdiction; exact duplicate archived; edit self; simultaneous create | VehicleServiceTest::test_identifiers; real fixture concurrency | Approved identities accepted; exact duplicate blocked; one concurrent winner, no orphan |
| V2 | AC2 | Missing/archived customer; reassign; archive/restore; forged type ID | tests/fixtures/veh-001-verify.php | Invalid relations denied; audit records owner change; no historical rewrite |
| V3 | AC3 | Unknown vs 0; 1 mi; future date; decrease; conflicting baseline save | VehicleServiceTest::test_odometer_baseline | Preserve null/0 distinction and 1609344 canonical mm; invalid chronology/stale changes rejected |
| V4 | AC4 | Manager create/search/reassign; technician read and forbidden POST; subscriber direct request; Unicode query | tests/fixtures/veh-001-verify.php; ai-document/walkthroughs/VEH-001.md | Only permitted view/write; stable 50-row pages; no contacts leaked to technician |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter VehicleServiceTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=VEH-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/VEH-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for VEH-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/VEH-001-vehicle-records.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is vehicle identity and current customer, AC1–AC4. Require CUST-001 DONE (and its foundation dependencies) and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
