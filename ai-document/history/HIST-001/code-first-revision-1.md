# HIST-001: Private service history and correction visibility

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: HIST-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require SERV-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
Services need a usable retrieval view. A timeline must make corrections visible, show original units/currencies and preserve stable pagination even with equal service dates.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/ServiceHistoryQuery.php`: authorized bounded query/filter projections and correction history retrieval.
- New `src/Admin/ServiceHistoryPage.php`, `src/Admin/ServiceHistoryListTable.php`: per-vehicle timeline, date/type filters, stable pagination and accessible correction details.
- Extend `src/Admin/VehiclePage.php` with history entry link and current-reading provenance; wire through `src/Core/Plugin.php` as required.
- Optional `src/css/admin.scss`/`src/js/admin.js` for progressive disclosure only with production output.
- New `tests/Unit/ServiceHistoryTest.php`, `tests/fixtures/hist-001-verify.php` and walkthrough.
- Update `ai-document/features/service-history.md` and query design in `ai-document/architecture.md`. No editing business rules, exports, public views, new endpoints or mixed-currency aggregation.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: SERV-001 DONE.
- Remaining readiness gates: Confirm finalized-only staff history versus creator/manager draft list; resolve safe no-bulk-mutation exception for history table. Reinspect repository indexed queries and technician contact projections.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Use finalized records for staff timeline, ordered service_date DESC then ID DESC with 50 rows/page. Drafts have an explicit separate author/manager view governed by SERV-001. Date range is inclusive and validated as two calendar dates with start<=end; service-type filtering is by stable term ID, with inactive historical types still selectable for history. Limit free-text search to approved plain-text service note/type-label fields, <=100 characters; use parameterized query and no unbounded result set.

Render stored original distance/unit and cost/currency/scale; optional converted display must be separately labeled and never replaces original. Show service date distinctly from UTC-derived localized audit timestamp. Show creator, historical customer name and correction indicator; technician does not receive contact details from current or historical payload. Correction expansion uses semantic details/summary or server-side navigable detail and exposes author/time/reason/before-after values to authorized staff. Always escape notes/reasons; do not render user HTML. Stable per-record permalink is admin-only and reauthorizes on access.

No destructive bulk action is meaningful in history; preserve WordPress table navigation/sorting/search without fake bulk controls. Loading long history must not issue one customer/term query per row: read snapshots and batch necessary user lookups. Current-reading display includes source record/date so backdated entry behavior is understandable.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Confirm read projection/filter contract and stable ordering against accepted service schema; no change to finalized business state.
3. S3: Implement private per-vehicle timeline and correction detail links; display immutable identity snapshots and reading provenance.
4. S4: Exercise pagination/filter edges and direct access, inspect query count, then perform full keyboard/RTL/long-content walkthrough.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
history(actor,vehicle,filters,page):
  authorize vehicle history; validate bounded filters/page
  query finalized vehicle records sorted date DESC, ID DESC with bounded page
  project stored snapshots and correction indicators; batch allowed actor labels
  render escaped results, preserved filters and deterministic navigation
correction_detail(actor,service): reauthorize; return approved before/after projection only
```

### Failure and resource lifecycle
Bad filters cannot broaden access or silently switch vehicles. Query failure displays an error distinct from an empty history. No database mutations in read paths; do not log sensitive query content. Repeated direct request performs authorization every time; no shared cached HTML across actors.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Authorized vehicle timeline/filter/search uses deterministic newest-first pagination.
- AC2: Historical units/currencies/customer/type and visible corrections are accurate.
- AC3: Private/draft/contact boundaries hold for direct/query/detail paths without per-row lookup growth.
- AC4: Keyboard, long content, RTL and mobile retrieval journey is manually demonstrated.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | 101 services, identical dates, date boundaries, inactive type, invalid filter/page/sort | ServiceHistoryTest::test_filters_and_order; real fixture | Stable expected IDs across 3 pages without duplicates; invalid filter rejected/explicitly corrected |
| V2 | AC2 | Corrected record; changed owner/currency/units/type; escaped script-like notes | tests/fixtures/hist-001-verify.php | Original identity and audit shown accurately; no script execution or relabeled costs |
| V3 | AC3 | Unauthorized direct link; other technician draft; contacts in audit; compare 1 vs 50 rows | tests/fixtures/hist-001-verify.php | Protected data absent; fixed/batched lookup count rather than one lookup per row |
| V4 | AC4 | Keyboard filter/pagination/correction expansion at narrow width and RTL | ai-document/walkthroughs/HIST-001.md | Readable dates/identifiers and reachable controls; retrieval demonstrated |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter ServiceHistoryTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=HIST-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/HIST-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for HIST-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/HIST-001-service-timeline.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is private service history and correction visibility, AC1–AC4. Require SERV-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
