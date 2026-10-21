# REM-001: Manual maintenance thresholds and queue

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: REM-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require HIST-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
Managers need actionable maintenance reminders based on recorded dates/odometer values, not live telemetry. No automatic delivery, recurrence or generated service entry is part of this MVP.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/ReminderService.php`: validate create/edit/snooze/complete and immutable transition audit.
- New `src/Common/ReminderDuePolicy.php`: pure local-date/canonical-distance OR predicate with snooze precedence and unknown handling.
- New `src/Common/ReminderQuery.php`: paged authorized due/upcoming/snoozed/completed queue; exact pre-pagination semantics.
- New `src/Admin/ReminderPage.php`, `src/Admin/ReminderListTable.php`; extend menu/vehicle links and Plugin composition.
- Optional scoped admin sources plus production output; no WP-Cron registration.
- New `tests/Unit/ReminderPolicyTest.php`, `tests/fixtures/rem-001-verify.php`; update `ai-document/architecture.md` and `ai-document/features/maintenance-reminders.md`.
- No email, SMS, customer portal, interval engine, automatic recurrence/service creation or reopening completion.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: HIST-001 DONE.
- Remaining readiness gates: G-05/G-07; settle snooze/completion/archive rules; ensure indexed due queries and canonical-distance comparisons meet G-08 without unbounded scans.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Payload: vehicle_id, title 1–200 plain-text characters, optional due_date, optional threshold original/unit/canonical, active/snoozed/completed state, optional snooze_until date, completed_by/at_utc and standard version/audit. At least one trigger; threshold nonnegative and explicit unit. Existing past dates/below-current thresholds are allowed and immediately due, with visible feedback. Manager alone manages and reads queue under proposed G-02; technician sees vehicle history without reminder management.

Effective state is computed without a scheduled job: completed stays completed; archived vehicle is suspended; future snooze_until suppresses due; otherwise compare local today and current recorded canonical distance. OR semantics: date hit or known reading >=threshold makes due. Unknown reading cannot satisfy distance trigger and must display that limitation; date may still make it due. At snooze date, return to active effective evaluation but do not silently edit stored audit on GET. No timezone-offset arithmetic.

Completion uses manager/nonce/version checks; repeat submission never adds duplicate completion audit or creates service. Proposed completed reminders immutable; create another explicitly if needed. Archiving/restoring a vehicle preserves reminders and changes eligibility without rewriting their original thresholds.

Queue filtering happens before pagination and count, including cross-record vehicle state/reading and snooze. A fetch-50-then-filter algorithm is incorrect. Define exact prepared join/query or accepted projection-maintenance strategy at readiness using DATA-001; avoid loading every reminder into PHP and N+1 vehicle queries. Retain original threshold in display after shop preference changes.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Approve state rules and freeze pure due predicate with clock injection and boundary fixtures.
3. S3: Implement versioned reminder transitions and authorized query strategy; integrate queue/forms without scheduling side effects.
4. S4: Test dates/DST/unknown/cross-unit values, repeated/stale requests and archive effects; verify correct totals/paging plus user queue journey.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
effective_status(reminder, vehicle, now):
  if completed: return completed
  if vehicle archived: return suspended
  today = local_calendar_date(now, WordPress timezone)
  if snooze_until exists and today < snooze_until: return snoozed
  date_hit = due_date exists and today >= due_date
  distance_hit = known vehicle reading and threshold exists and current_mm >= threshold_mm
  return due if date_hit OR distance_hit else upcoming
complete(actor,id,version): authorize; atomic state/version check; append one completion audit
```

### Failure and resource lifecycle
Transitions reuse DATA-001 expected-version and rollback; query failure is not presented as zero due reminders. Do not persist a guessed reading when unknown. Deadline calculation takes injected time so tests do not depend on wall clock. No asynchronous process to clean up beyond the common isolated runner.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Manual reminder validation requires valid vehicle/title and at least one trigger.
- AC2: OR due, unknown/zero, cross-unit, local-date and snooze rules are exact and stable.
- AC3: Manager transitions/archive effects are attributed, concurrent-safe and generate no notifications/services.
- AC4: Due queue filtering/count/paging and accessible manager workflow pass real WordPress checks.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Neither trigger; date-only; distance-only; both; archived/foreign vehicle | ReminderPolicyTest::test_validation; real fixture | Valid reminders accepted; missing triggers/relations rejected without writes |
| V2 | AC2 | At threshold, just below, unknown, 0; 1 mi=1609344mm; midnight/DST; snooze day | ReminderPolicyTest::test_due_matrix | Exact predicate result; snooze suppresses until date; unit preference has no effect |
| V3 | AC3 | Double complete; stale snooze; valid nonce technician; archive/restore vehicle | tests/fixtures/rem-001-verify.php | One completion audit; denied mutations unchanged; suspension/restoration reevaluates; no mail/cron/service side effect |
| V4 | AC4 | Mixed 150 reminders; all due results beyond first raw page; queue actions | tests/fixtures/rem-001-verify.php; ai-document/walkthroughs/REM-001.md | Correct count/page membership; manager completes/snoozes selected item; no N+1 vehicle fetches |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter ReminderPolicyTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=REM-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/REM-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for REM-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/REM-001-maintenance-queue.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is manual maintenance thresholds and queue, AC1–AC4. Require HIST-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
