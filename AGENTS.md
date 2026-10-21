# VeLog agent instructions

## Current checkpoint — 2026-10-21: QA-001 paused by owner

The Product Owner requested stopping work, saving exact progress for the next session and committing/pushing the checkpoint. QA-001 remains IN_PROGRESS with Scheduling: PAUSED_BY_OWNER and Next actor Developer. Resume from ai-document/evidence/QA-001/round-1/report.md when work is resumed. The reminder benchmark is failing; do not claim complete G-08, QA-001 DONE or functional acceptance. Commit/push of this checkpoint is explicitly authorized; deployment and publication remain separate.


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

- Developer implements source, builds generated assets, records verification and writes concrete Vietnamese UAT instructions. Browser/manual verification, responsive/RTL/keyboard/screen-reader observations and final acceptance belong to Tester. Screenshots are optional.
- After applicable code/build/static/runtime checks and explicit self-review pass, the feature and UAT move to AWAITING_MANUAL_ACCEPTANCE, Next actor Tester. Only explicit Tester acceptance permits DONE.
- On FAIL, Developer triages and corrects the appropriate BE/FE scope, then returns affected cases/regressions to Tester.
- Developer prepares prerequisites explicitly; missing prerequisites remain BLOCKED. Active-site fixture writes require their own authorized bounded scope.

## 1. Startup: Role resolution and announcement

Before task work, read [agent-roles.json](ai-document/agent-roles.json). Current mapping: `codex` is Developer (backend and frontend); the user is Tester / Product Owner. Antigravity has no active assignment.
1. Identify client from host runtime. Resolve assigned role from `assignments[client].role`. Never switch or infer roles.
2. Send a short Vietnamese acknowledgement once per session: confirm reading AGENTS.md, project, role, and client. Do not repeat every turn or pause for confirmation.
3. Role boundary: Developer owns all technical work and self-review. Tester / Product Owner alone performs manual acceptance and accepts major features as functionally DONE. Do not invent a separate frontend agent or independent code reviewer.
4. Host-injected rules count as read; verify role mapping once per session. Re-read instructions when modified on disk.
5. At the start of every work session, inspect the relevant current task, checklist, and README, then state in Vietnamese the concrete next action, responsible actor, and any approval or prerequisite needed before starting. A startup acknowledgement alone is insufficient. Developer proactively inspects the dependency queue to identify the next work; do not ask the user to select a task already established by project records. If no task can be established or the records disagree, state the missing information or mismatch and the exact clarification needed; never invent the next task.

## 2. Communication and language

- Reply to the user in Vietnamese.
- Write code, comments, documentation, and inter-agent handoff prompts in English.
- Be evidence-based: identify risks and assumptions; do not agree merely to please.
- State scope and approach before changing code; obtain explicit user approval.
- External actions (git commit/push/deploy/release) require explicit user authorization.
- Every final response must state in Vietnamese the concrete next action, who performs it, and any required approval or prerequisite, including read-only and instruction-reading turns. If the requested scope is complete and no further task is established, say so and identify the scope or decision needed from the user. Do not end with only a generic readiness statement. This communication rule does not authorize task-state changes or replace the mandatory handoff prompt when applicable.

## 3. Workflow and manual handoffs

Follow the [Developer / Tester workflow](ai-document/backend-frontend-workflow.md). Automated dispatch, receipts, and background signals are retired.
- Tasks progress through standard statuses: `DRAFT`, `READY`, `IN_PROGRESS`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `BLOCKED`, `AWAITING_MANUAL_ACCEPTANCE`, `DONE`.
- Developer creates feature tasks and mandatory Vietnamese `<ID>-UAT`; BE/FE subtasks may be retained for bounded scopes but have the same Developer owner. Developer owns PHP, hooks, validation, capabilities/nonces, persistence, SQL/concurrency, JavaScript/Sass/CSS, view templates, generated assets, tests, fixtures and documentation.
- Before implementation, validate status, authoritative Next actor, blueprint readiness PASS, synchronized checklist/README and explicit scope approval. Resolve metadata mismatches before source work.
- Developer reviews its own full diff and applicable security, lint, unit, integration, runtime and build evidence; record SELF_REVIEWED_DEVELOPMENT and verification limitations. No independent review is claimed.
- A major parent task and UAT move to AWAITING_MANUAL_ACCEPTANCE with Next actor Tester only after technical gates pass. They move to DONE only after explicit Tester manual PASS. Failed cases return to Developer as CHANGES_REQUESTED.
- Next actor is authoritative: Developer or Tester. Status does not infer ownership.
- Synchronize task, checklist and README before handoff. Keep exactly one English copy-ready prompt under `### Chat handoff prompt` with Status, Recipient (`Developer` or `Tester`), Intent and exact next action/evidence.
- Any turn changing status, Next actor, review decision, workstream ownership or manual acceptance must reproduce the relevant task prompt verbatim exactly once in the final response. Keep it consistent with synchronized records; never omit it because a prior turn included it.
- Temporary test files: Create scratch scripts and fixtures in a uniquely named, task-owned temporary directory outside the plugin source tree when possible. Track their paths and remove owned temporary files/processes before handoff, including failure paths. Keep only approved reusable tests and required evidence. Inspect `git status --short` for accidental scratch files and report cleanup results. Never delete pre-existing or tracked files without a separate, reviewed cleanup scope.

## Customer identity decision — G-10, 2026-10-15

Customer identity uses private `mf_velog_customer` records, independent of WordPress user accounts. Staff authentication remains native WordPress. Do not resume retired CUST-002 native-user code, create customer accounts, or build custom authentication without a new approved scope. Follow CUST-001 and decisions.md G-10; historical G-09 specifications are superseded.

## 4. WordPress coding and security essentials

Security takes precedence over performance and convenience. Follow `rules/` and WordPress standards:
- Guard PHP files: `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Validate/sanitize inputs; escape outputs for context (`esc_html`, `esc_attr`). Never use raw `$_GET`/`$_POST`.
- Enforce capabilities (`current_user_can`) and nonces for state-changing operations.
- Use `$wpdb->prepare()` for SQL queries; avoid `SELECT *`.
- Prefix functions/hooks with `mf_velog_` / `mf_`; namespace PHP classes under `MF\VeLog\`.
- Single responsibility per function. No debug logs (`var_dump`, `console.log`).

## 5. Build, release and conditional reading map

- For every wp-admin screen design, backend view contract, or admin presentation review, read the project-local [wordpress-plugin-admin-ui skill](.agents/skills/wordpress-plugin-admin-ui/SKILL.md) and [VeLog admin design system](ai-document/admin-design-system.md) before implementation. The VeLog design system resolves project-specific choices; current security, Developer ownership, approval, and Tester verification rules remain authoritative. Skill examples are reference material, not production-ready code or permission to add dependencies.
- Read only what is needed for the assigned scope:
  - Architecture: [architecture.md](ai-document/architecture.md)
  - Workflow & task template: [backend-frontend-workflow.md](ai-document/backend-frontend-workflow.md)
  - Build & release: [build-and-release.md](ai-document/build-and-release.md) (assets built via `npm run dev`/`npm run production`; release via `npm run release`)
  - Project checklist: [implementation-checklist.md](ai-document/implementation-checklist.md)
  - Security rules: [rules/security.md](rules/security.md)
  - Test and fixture placement: [rules/testing.md](rules/testing.md)
  - Specific domain rules in `rules/` when touching those areas.
