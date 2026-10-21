# Deferred test and fix debt — code-first phase

Authority: Product Owner instruction on 2026-10-21 to complete all code before testing/fixing. This register is scheduling, not PASS evidence or acceptance. Historical successful results remain preserved.

| ID | Scope | State | Prerequisites / later action |
| --- | --- | --- | --- |
| SERV-001-UAT | Existing T01–T15 service manual acceptance | TEST_DEBT / DEFERRED | Reverify LocalWP/data; run preserved cases and record actual results later. |
| HIST-001-TEST | History filters/order/privacy/audit/batching/manual UI | TEST_DEBT / DEFERRED | Implemented source, synthetic history, role/locale/accessibility tools. |
| REM-001-TEST | Reminder validation/OR thresholds/snooze/completion/index consistency/concurrency | TEST_DEBT / DEFERRED | Implemented source, dates/readings, archive/race/rollback fixtures; validate G-07 proposal. |
| MVP-001 | Packaging/install, international end-to-end, retention/security and G-08 benchmark | TEST_DEBT / DEFERRED | All runtime code; explicitly authorized packaging; isolated PHP/WP/database matrix and accepted benchmark dataset. |
| REGRESSION-DEBT | Previous customer/vehicle/service/bootstrap/workflow suites after new code | TEST_DEBT / DEFERRED | Run full suites during consolidated test/fix phase; previous results do not cover subsequent source changes. |

Compilation/build and PHP syntax checks may be run during coding to keep deliverables loadable. No browser/manual/functional/benchmark result may be inferred. Known defects and performance limits must be recorded here or linked reports; do not silently call the application verified or production-ready.

## CODE-001 limitations and follow-up

- New source has no executed unit, integration, runtime, browser, manual, regression or performance coverage. Prior SERV-001 results do not cover repository after-write hooks, reminder schema, current index or new menu/query/UI code.
- Detailed queued scopes: [HIST-001-TEST](tasks/HIST-001-TEST.md) and [REM-001-TEST](tasks/REM-001-TEST.md). These are deferred scope inventories; exact runnable cases and prerequisites are still to be prepared in the test phase.
- Static source inspection found no coding-standard errors in the seven new files, but 80 line-length warnings remain. No PHPStan or full-project quality gate was run during this code phase.
- History loads at most 10000 finalized records per vehicle; larger history fails closed and requires a later query/index strategy. Global structural preflights and current-index refresh cost are unbenchmarked. Queue count/page reads may observe concurrent changes; validate acceptable behavior and strengthen consistency if needed.
- Exercise current-index initialization and updates with rollback, retry, connection/commit failure, simultaneous service/reminder/vehicle changes, archived records and cache persistence. Include server-generated completion timestamp/replay behavior.
- Existing disposable customer fixture pins the earlier approved CSS hash; refresh that pin through source review before running regressions. Live-site fixture pin remains historical and must not be bypassed.
- G-07 semantics were implemented from the prior proposal under the general code-first authorization. Product validation is still outstanding; no separate G-07 approval is invented.

## QA-001 technical phase resumed

Product Owner authorized the consolidated technical test/fix phase after CODE-001. HIST-001-TEST, REM-001-TEST and REGRESSION-DEBT technical portions are now IN_PROGRESS under QA-001. Existing DEFERRED rows describe the prior checkpoint; manual/product acceptance, G-07 confirmation, package and full benchmark remain outstanding until separately evidenced. No technical result implies those debts are closed.

## Owner pause checkpoint — QA-001 round 1

QA-001 is IN_PROGRESS / PAUSED_BY_OWNER, Next actor Developer. See evidence/QA-001/round-1/report.md for exact completed work and command scope. Unit 115/502 and workflow 22/22 passed. Early runtime matrix/customer/vehicle regressions passed; newest service/history SQL optimizations require repeat matrix/full quality checks. Four G-08 reads meet p95 <=2s; reminder benchmark failed with a SQL temporary-table deletion error, so full G-08 remains FAIL/unresolved. ZIP retention fixture exists but is unwired/unexecuted. Manual, real PHP 8.1, persistent-cache/commit-failure exclusions and G-07 business acceptance remain debt. No major feature acceptance is inferred. Current Git commit/push is authorized by owner; work resumes only on the owner's next instruction.
