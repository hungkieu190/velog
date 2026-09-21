# SERV-001: Service drafts, finalization and corrections

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: SERV-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require VEH-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
No service workflow exists. Staff must record real maintenance with durable original units/currency and attributed corrections; editing a finalized record cannot quietly erase history or alter old ownership.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/ServiceService.php`: draft creation/save/finalize and manager correction; actor/state/version rules.
- New `src/Common/ServiceTypeService.php`: manager-managed service taxonomy terms; active/inactive selection and safe snapshots.
- Extend `src/Common/OdometerPolicy.php`: neighbor checks, deterministic current reading and reasoned corrections.
- New `src/Core/ServiceTypes.php`: private mf_velog_service_type taxonomy registration/capabilities via Loader; no public UI/REST routes.
- New `src/Admin/ServicePage.php`, `src/Admin/ServiceTypePage.php`: native forms and authorized actions.
- Modify `src/Core/Plugin.php`, `src/Admin/AdminMenu.php` wiring; optional scoped admin Sass/JS plus production output.
- New `tests/Unit/ServiceWorkflowTest.php`, `tests/fixtures/serv-001-verify.php`; update `ai-document/architecture.md` and `ai-document/features/service-history.md`.
- No timeline/filter screen (HIST-001), uploads, invoicing, automatic reminders, hard deletion or public endpoints.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: VEH-001 DONE.
- Remaining readiness gates: G-05/G-06; settle chronological decrease exceptions and owner snapshot timing; approve concrete multi-record commit/read-model recovery from DATA-001. Define taxonomy capability mapping and archived/draft behavior.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Service payload: vehicle_id, service_date, reading original/unit/canonical, one active service_type_id and label snapshot, plain-text notes <=10000 characters, technician_id, customer_id/name snapshot, optional exact cost amount/currency/scale, draft/finalized state, audit/version. Technical field-size defaults and required type selection are proposals to confirm under G-06. Technician ID comes from authenticated creator, never client-provided impersonation. Manager-created service attributes creator honestly; assignment to other technicians is outside scope.

Draft may be incomplete and is visible/editable only to creator or manager; finalization requires active vehicle/current customer, valid date <=local today, known reading and active type. Finalized record is visible to operational staff with name-only customer projection. Snapshot customer and type at finalization; preserve those values during later corrections unless an explicitly reasoned manager correction targets them. Type rename/deactivation cannot relabel old records. Propose no term deletion once referenced, only inactive marker; default taxonomy terms are not invented.

Dated reading order: service_date then service post ID, newest first; baseline has documented fixed tie precedence after same-day finalized services. New reading must fit both older and newer known neighbor readings, unless manager reasoned exception. Unknown never compares as zero. Backdated insertion may affect chronology validation but updates current only if it becomes the latest eligible reading. Correcting a latest service date/reading recomputes current from remaining finalized history/baseline. Read model and history update share DATA-001 write unit; no guessed eventual consistency.

Repeated finalization of same expected version returns an explicit already-finalized/conflict outcome without duplicate audit or new service. Corrections require manager capability, expected version, reason 1–1000 characters and append full previous/new snapshots with authenticated actor/UTC time. Never overwrite earlier audit events. Archived vehicle blocks draft save/finalization/new service; old finalized history remains readable. Historical correction on archived vehicle is proposed manager-only and must not reactivate it.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Freeze state, type and snapshot contracts with product decisions; add tests for actor/state/chronology combinations.
3. S3: Implement taxonomy management and domain workflow through repository/coordinator; authoritative transition owns audit/current-reading update.
4. S4: Add native draft/finalize/correct UI with clear finalization confirmation and reason field; run failure/interleaving and unit/currency/history invariants.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
finalize(actor,id,expected_version):
  authorize creator technician or manager; verify draft/version inside write boundary
  validate active relations, required fields, settings snapshots and neighbor readings
  snapshot current customer/type; append transition audit
  persist finalized service and recomputed current vehicle reading coherently
correct(actor,id,expected_version,reason,patch):
  require manager + finalized + nonempty reason
  capture old snapshot; validate proposed complete record and neighbor exceptions
  append before/after audit; save service + recomputed vehicle reading in same unit
```

### Failure and resource lifecycle
DATA-001 must cover service and vehicle projections in one reviewed coherent write unit. A failed update cannot leave finalized service with old current reading. Preserve expected-version conflicts with clear reload guidance; do not retry mutating operations blindly. Taxonomy save failure leaves service draft unchanged. No irreversible delete action or bulk finalization.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Creator/manager draft and finalization rules enforce required relations, dates, reading and type.
- AC2: Optional costs and original units/customer/type snapshots preserve historical meaning.
- AC3: Corrections/decreases are manager-only, attributed/reasoned and cannot erase prior audit.
- AC4: Backdating/ties/concurrent transitions and failures preserve coherent current reading and service state.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Own/other technician draft; manager; invalid nonce; archived vehicle; empty type/reading; future date | ServiceWorkflowTest::test_transition_matrix; real fixture | Allowed complete finalization succeeds; invalid/unauthorized transitions leave draft unchanged |
| V2 | AC2 | JPY/USD/KWD cost; owner/type/settings change after finalization | tests/fixtures/serv-001-verify.php | Exact stored amount/unit and historical owner/type unchanged; no mixed-currency sum |
| V3 | AC3 | Lower reading; manager reason; empty reason; technician correction; repeated correction | ServiceWorkflowTest::test_correction_history; real fixture | Reasoned manager change accepted; others rejected; ordered audit preserves every before/after snapshot |
| V4 | AC4 | Backdated service, equal date IDs, correction moving latest older, double finalization, injected projection failure | tests/fixtures/serv-001-verify.php concurrent/failure cases | Deterministic latest reading; one successful transition; coherent rollback and no duplicated audit |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter ServiceWorkflowTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=SERV-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/SERV-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for SERV-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/SERV-001-service-workflow.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is service drafts, finalization and corrections, AC1–AC4. Require VEH-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
