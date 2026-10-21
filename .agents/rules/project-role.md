---
trigger: always_on
---

## Owner decision — 2026-10-21: Code first; all testing is deferred debt

The Product Owner instructed: "bỏ qua hết tester, kể từ bây giờ các task test sẽ là các task nợ nhé; làm hết code đi đã, test và fix sau". This supersedes earlier test/Tester gates for ongoing and subsequent implementation.

- Codex continues all remaining MVP application code directly, without waiting for Tester/UAT, test environments or test/fix rounds between features. The current instruction authorizes the remaining documented code queue; do not repeatedly request implementation permission for it.
- Preserve already recorded test results and accepted features. Move unperformed manual, unit/integration/runtime/performance/regression work to explicit test-debt tasks; do not mark skipped work PASS or accepted.
- Distinguish implementation completion from functional acceptance. Parent features may remain AWAITING_MANUAL_ACCEPTANCE with Next actor Developer for the next code item; this status and outstanding UAT are not implementation dependencies. A code checkpoint may be DONE only when explicitly labelled implementation-only. Do not infer product acceptance.
- Test-debt tasks use DRAFT with Debt: TEST_DEBT and Scheduling: DEFERRED; they are not current Tester handoffs. Keep their cases and prerequisites for a later consolidated test/fix phase. No new test suite or fixture preparation is required during the code phase.
- Perform necessary compilation/build and PHP syntax checks to produce loadable artifacts, inspect source/security boundaries, and record actual limitations. These checks are not functional tests. Do not defer a known syntax/build failure or bypass capabilities/nonces/validation just to keep coding.
- Keep source/generated assets synchronized. Do not run functional suites, browsers, benchmarks, test seeds or live fixture writes merely to close a feature during this phase. Actual known functional issues may be recorded as fix debt; disclose material limitations honestly.
- User remains Product Owner and may resume testing later. Codex is sole Developer; no active Tester gate or separate reviewer is required. External commit/push/deploy/release still requires explicit authorization.


# Project role startup

Read root AGENTS.md and ai-document/agent-roles.json before work. The 2026-10-21 owner decision assigns Codex as the sole Developer for backend and frontend, and the human as Tester / Product Owner. Antigravity has no active role and must not infer or resume the retired frontend assignment. An unassigned client stops role-dependent work and reports the missing assignment. Reading this adapter never changes the actual host identity. Historical agent contributions remain evidence only. Follow the current Developer/Tester workflow and Vietnamese session/next-action communication rules.
