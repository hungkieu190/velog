# Developer and Tester workflow

## Owner decision — 2026-10-21: Consolidated technical test/fix resumed

The Product Owner replied "ố kề,làm" to the proposed next consolidated test/fix phase after CODE-001 implementation completion. Developer is authorized to run technical tests, create isolated fixtures and fix demonstrated defects under QA-001. The earlier code-first testing pause has ended for this scope; preserve historical evidence and outstanding manual/product/benchmark debt. No active Tester gate, feature acceptance, live-data fixture authorization or external release/commit is implied.


## Owner decision — 2026-10-21: Code first; all testing is deferred debt

The Product Owner instructed: "bỏ qua hết tester, kể từ bây giờ các task test sẽ là các task nợ nhé; làm hết code đi đã, test và fix sau". This supersedes earlier test/Tester gates for ongoing and subsequent implementation.

- Codex continues all remaining MVP application code directly, without waiting for Tester/UAT, test environments or test/fix rounds between features. The current instruction authorizes the remaining documented code queue; do not repeatedly request implementation permission for it.
- Preserve already recorded test results and accepted features. Move unperformed manual, unit/integration/runtime/performance/regression work to explicit test-debt tasks; do not mark skipped work PASS or accepted.
- Distinguish implementation completion from functional acceptance. Parent features may remain AWAITING_MANUAL_ACCEPTANCE with Next actor Developer for the next code item; this status and outstanding UAT are not implementation dependencies. A code checkpoint may be DONE only when explicitly labelled implementation-only. Do not infer product acceptance.
- Test-debt tasks use DRAFT with Debt: TEST_DEBT and Scheduling: DEFERRED; they are not current Tester handoffs. Keep their cases and prerequisites for a later consolidated test/fix phase. No new test suite or fixture preparation is required during the code phase.
- Perform necessary compilation/build and PHP syntax checks to produce loadable artifacts, inspect source/security boundaries, and record actual limitations. These checks are not functional tests. Do not defer a known syntax/build failure or bypass capabilities/nonces/validation just to keep coding.
- Keep source/generated assets synchronized. Do not run functional suites, browsers, benchmarks, test seeds or live fixture writes merely to close a feature during this phase. Actual known functional issues may be recorded as fix debt; disclose material limitations honestly.
- User remains Product Owner and may resume testing later. Codex is sole Developer; no active Tester gate or separate reviewer is required. External commit/push/deploy/release still requires explicit authorization.


## Owner decision — 2026-10-21: One Developer and one Tester

The Product Owner explicitly instructed: "từ giờ bạn làm luôn backend, chỉnh sửa vào trong dự án agents nhé; giờ chỉ có bạn là dev, tôi là test, xong". Codex is the sole Developer for architecture, planning, backend, frontend, generated assets, fixtures, tests and technical self-review. The user remains Tester / Product Owner and exclusively performs manual acceptance. Separate Backend Architect/Frontend Developer and Antigravity assignments are retired for future work; historical implementation/review evidence is preserved. BE/FE suffixes may remain as scope subdivisions owned by the same Developer. No separate-agent code approval is required or claimed. Record SELF_REVIEWED_DEVELOPMENT for combined code review; retain historical SELF_REVIEWED_BACKEND labels as technical evidence. Successful checks never imply manual PASS or feature DONE.

## Startup and session continuity

Read AGENTS.md and resolve the host from agent-roles.json. Codex is Developer; user is Tester / Product Owner. No other client has an active implementation assignment. Acknowledge the configured project/client/role once per session in Vietnamese. At every session start inspect the relevant task, checklist and README, then state the exact next action, actor and prerequisites. Every final response also states these, including read-only turns. If no next task is explicitly supplied, Developer inspects the dependency queue and proposes the next work; do not ask the user to identify a task that the repository already establishes. Report missing/conflicting records without inventing readiness.

## Planning and implementation

1. Developer reads approved product decisions, inspects current source and preserves unrelated changes.
2. Create a bounded decision-complete blueprint with allowed files, data/view/action contracts, failure behavior, ordered steps, verification matrix, evidence path and cleanup. Obtain explicit user scope approval before source changes; existing approval persists and must not be requested again.
3. Validate synchronized task/checklist/README metadata, authoritative Next actor and blueprint readiness PASS; then implement all backend/frontend source directly. BE/FE subtasks are optional scope subdivisions owned by Developer, never separate mandatory agent handoffs.
4. Before wp-admin views, read the local wordpress-plugin-admin-ui skill and admin-design-system.md. Use native WordPress controls, scoped styles and exact data/security boundaries. Build generated assets from source; do not edit generated output manually.
5. Inspect the complete diff, execute applicable lint/unit/static/integration/runtime/build gates, and record actual results, failures and limitations. Record SELF_REVIEWED_DEVELOPMENT only after required technical gates pass. It is intentionally non-independent.
6. Prepare the Vietnamese UAT and explicit environment/accounts/synthetic data. Additional active-site writes require their own bounded authorization. Missing preparation remains Developer-owned BLOCKED; never silently skip cases.
7. Set feature/UAT and applicable BE/FE subdivisions to AWAITING_MANUAL_ACCEPTANCE, Next actor Tester, after technical completion. Give exactly one current copy-ready prompt. No browser observation or manual PASS is inferred from code review/builds.

## Tester acceptance and correction

Tester executes manual UI, responsive/RTL, keyboard/focus, screen-reader, error-state, pagination and asset-loading cases. Screenshots/videos are optional. Each UAT includes environment, account roles, concrete data, numbered actions, per-action expectations, relevant invalid/permission/concurrency/recovery cases, a per-case PASS/FAIL/BLOCKED table and exact response format.

Only explicit Tester PASS permits DONE. Developer may synchronize the recorded decision; it never self-accepts a feature. On FAIL, Developer triages to the correct technical scope, records CHANGES_REQUESTED, corrects and self-reviews, then returns affected cases and regressions to Tester. BLOCKED must identify the missing prerequisite and responsible actor. Technical subtask DONE is only a self-reviewed checkpoint until parent UAT acceptance.

## Status and handoff contract

Statuses: DRAFT, READY, IN_PROGRESS, READY_FOR_REVIEW (technical review pending; not an invented independent reviewer), CHANGES_REQUESTED, BLOCKED, AWAITING_MANUAL_ACCEPTANCE, DONE. Next actor is explicitly Developer or Tester. Keep historical actor/review evidence unchanged; current work uses the new model.

Synchronize task, implementation-checklist.md and README.md. Each task keeps exactly one current English prompt under `### Chat handoff prompt`:

```text
Status: <STATUS>
Recipient: <Developer|Tester>
Intent: <work|review|accept>

<Scope, evidence, decision and exact next action>
```

Any turn changing task status, Next actor, review decision, ownership or manual acceptance must reproduce the relevant task prompt verbatim exactly once in its final response. The Vietnamese next-action statement must match the prompt and synchronized records.

## Verification and cleanup

Follow WordPress standards, capabilities/nonces, strict validation, contextual escaping, prepared SQL and existing transaction/cache boundaries. Keep reusable tests under tests/ and generated assets consistent with source. Record actual commands, versions, exits and NOT VERIFIED limitations. Use unique task-owned temporary resources outside source; clean owned files/databases/processes on success/failure. Never delete unrelated/tracked files without reviewed cleanup scope. Inspect git status --short and git diff --check before handoff. Commit/push/deploy/release still require explicit authorization.

## Task template

````markdown
# <ID>: <Title>

## Current handoff
- Workstream: <Development|Backend|Frontend|Tester>
- Status: <STATUS>
- Plan revision: <N>
- Blueprint readiness: <PASS|INCOMPLETE>
- Owner: <Developer|Tester>
- Contributors and review mode: <SELF_REVIEWED_DEVELOPMENT|pending|Tester manual acceptance>
- Related checklist items:
- Latest report:
- Evidence:
- Temporary resource cleanup:
- Next actor: <Developer|Tester>
- Next actor and exact next action:

## Approved scope and executable blueprint

## Verification matrix and acceptance criteria

## Open findings

### Chat handoff prompt

```text
Status: <STATUS>
Recipient: <Developer|Tester>
Intent: <work|review|accept>

<Exact next action and evidence>
```
````
