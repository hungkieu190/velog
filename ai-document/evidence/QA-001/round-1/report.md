# QA-001 round 1 — paused checkpoint, 2026-10-21

## Current state and resume point

Status: IN_PROGRESS. Scheduling: PAUSED_BY_OWNER. Next actor: Developer. The Product Owner explicitly asked to stop, save exact progress for next session and commit/push this checkpoint. Work has stopped; QA-001 and major features are not newly accepted or DONE. Historical accepted CUST-001/VEH-001 remain unchanged.

**Next action:** Diagnose the reminder benchmark SQL temporary-table failure, inspect the reminder-to-vehicle join plan, then rerun the full G-08 benchmark and affected runtime matrix. Wire/execute ZIP-only retention verification afterwards. Read this report before starting new code; do not restart completed work or treat partial benchmark results as a full PASS.

## Implemented fixes

1. Reminder title `0` was incorrectly rejected by an empty-value check; it is now a legitimate nonempty title. A new unit regression demonstrated the original failure.
2. Repeating completion after a lost response returned invalid_state. Reminder mutations now match committed request hash, actor, operation and base version before terminal/stale checks, returning the stored result without an extra audit. Reusing a request for a different operation/base version is rejected. Operation-specific field allowlists also reject unsupported keys.
3. PHPStan reported five stale-property-narrowing diagnostics around wpdb last_error in new query classes; error checks now use empty consistently with the existing codebase.
4. Literal-true union return declarations require newer syntax than declared PHP 8.1 support. Runtime declarations use bool|WP_Error while existing true-or-error behavior/docs remain. Real PHP 8.1 runtime is unavailable and is NOT VERIFIED.
5. Source formatting errors corrected. Narrow line-length waivers for intact native HTML, translation messages and SQL follow the existing query/page convention; security/correctness sniffs remain enabled. No dependencies were changed.
6. Latest performance fixes: ServiceQuery starts with indexed state metadata using the standard meta_key index and controlled join order. ServiceHistoryQuery filters/sorts bounded small relation rows first, then batch-loads authoritative envelopes with cardinality checks. This avoids sorting large audit blobs before vehicle filtering. No custom/core-table index or schema migration was added.

## Actual verification, with scope limits

| Check | Recorded result | Evidence / important limit |
| --- | --- | --- |
| Full PHPUnit | PASS: 115 tests / 502 assertions | unit-final.txt, after latest SQL changes. Expected negative capability rollback diagnostic is emitted by a test. |
| Full quality gate (syntax, PHPCS, PHPStan) | PASS | quality-final.txt; before the two latest SQL performance changes. Those two files additionally passed targeted PHPCS; full quality must be repeated at resume. |
| Workflow, configured Node 24.21.0 | PASS: 22/22 | workflow-final.txt; before latest SQL performance changes. Initial default Node 20 invocation and pre-fix style failures are preserved separately. |
| Production build | PASS: 4 files | build-final.txt; no Sass change afterwards. |
| QA domain + authenticated HTTP on WP 6.4.3 / MariaDB | PASS | qa-wp643.txt; before latest SQL performance changes. |
| QA domain + HTTP on WP 7.1.3 / MariaDB and isolated MySQL 8.4.0 | PASS | qa-wp713.txt and qa-wp713-mysql84.txt; before latest SQL performance changes. |
| QA domain + HTTP on WP 6.7.2 / MariaDB | PASS before benchmark | qa-benchmark-closing.txt; latest SQL code covered by these functional assertions. The combined command then failed at benchmark, so overall exit is not PASS. |
| Customer and vehicle shared regressions | PASS, exit 0 | customer-regression.txt and vehicle-regression.txt, WP 6.7.2; before latest SQL changes. |
| Manual UI/RTL/keyboard/screen-reader, PHP 8.1, installed ZIP lifecycle | NOT VERIFIED | Remain explicit debt. |

Isolated QA covers finalized history filters/count/order/50+1 pages, correction reason/audit/contact privacy, bounded query counts, known/unknown/zero readings, date OR distance, snooze/resume/completion/replay, stale/denied requests, vehicle archive/restore, transactional current-index rollback, concurrent completion with one transition audit, missing/duplicate projection failure and authenticated page/POST access. Existing service fixture is included, covering service finalization races and chronology/security cases. No active-site fixture or credential change was made in QA-001.

## Benchmark — partially measured, FAIL overall

Dataset is exactly 1000 private customers, 2000 vehicles, 20000 finalized services, 5000 reminders. Setup clones canonical shapes from real domain writes into a disposable database and preserves audit structure; it does not measure write throughput. Five warmups and thirty measured calls are configured for each read; counts/page sizes are asserted. Bulk-load optimizer statistics are refreshed before final sampling. Environment details are in environment.txt; this is a local measurement, not a production SLA.

Latest completed query p95 values in qa-benchmark-closing.txt:

| Query | p95 seconds | Result |
| --- | ---: | --- |
| Customer search | 0.09485 | PASS |
| Vehicle search | 0.07695 | PASS |
| Finalized service list | 0.21616 | PASS |
| Filtered vehicle history | 0.60745 | PASS |
| Due reminder queue | No completed sample set | FAIL: SQL storage error |

The reminder query failed with `Error on delete of /tmp/#sql-temptable-...MAI (Errcode: 2 No such file or directory)`, surfaced correctly as storage_unavailable. The combined benchmark exited with failure; **G-08 is not passed**. Root cause has NOT been established. Do not silently drop the failure or present four successful reads as full acceptance.

Additional findings:
- Initial bulk data without refreshed statistics produced vehicle p95 ~3.26s. Statistics refresh corrected that plan, but service/history still required the source optimizations above; initial service p95 ~5.10s is retained.
- reminder-plan.txt shows vehicle alias vp using type_status_date with estimated 2000 rows rather than a primary-key lookup for `vp.ID=v.meta_value`. A possible diagnostic is explicit numeric casting of the validated vehicle projection. **This candidate is not implemented or verified.** reminder-plan-cast.txt only records an attempted probe after the disposable DB had already been removed; it failed to connect and proves no optimization.
- Superseded initial benchmark processes were deliberately terminated after diagnosed slow plans so source could be corrected. Their logs remain partial/failed, not PASS. The latest closing run ended with the reminder SQL failure.

## Commands for resuming

Use Node 24 explicitly: prepend `/home/ecommercelife/.nvm/versions/node/v24.21.0/bin` to PATH. Default shell Node is 20.

- `vendor/bin/phpunit --configuration=tests/phpunit.xml`
- `npm run check` with Node 24 PATH.
- `npm run test:workflow` with Node 24 PATH (takes several minutes and creates/deletes isolated packaging artifacts).
- `VELOG_QA_BENCHMARK=1 bash tests/workflow/product-smoke.sh --task=QA-001 --wp-version=6.7.2`
- After query fixes, rerun QA-001 on WP 6.4.3 and WP 7.1.3/MySQL 8.4.0. For MySQL set VELOG_TEST_MYSQLD to `/home/ecommercelife/.config/Local/lightning-services/mysql-8.4.0/bin/linux/bin/mysqld` and pass --db-engine=mysql.

Consider giving each isolated DB an owned tmpdir before reproducing the temporary-table failure, and record the diagnosis rather than assuming it resolves the issue. Never use the active LocalWP DB. All runner temporary resources must be cleaned even on failure.

## Unfinished ZIP-only verification

`tests/fixtures/qa-retention-verify.php` is newly written but **not wired into the runner and not executed**. Intended flow: generate an archive inside an owned disposable project copy; preserve tests outside the installed plugin; install ZIP-only runtime without development fallback; run domain/HTTP checks; snapshot exact private records/meta/settings, uninstall and reinstall the ZIP, then verify snapshot hashes and manager capabilities. No such package was generated in the workspace or published. Existing workflow packaging-boundary tests passed but are not a substitute for this install/lifecycle check.

## Cleanup and checkpoint

cleanup.json tracks ten owned disposable run directories. No associated processes or run directories remain. Failure diagnostics were preserved from owned /tmp files before removal. No pre-existing/tracked files were deleted. Active LocalWP data and private credential files were not changed during this QA phase. Existing dirty VeLog feature/workflow work is included in the owner-authorized Git checkpoint; no deploy or release is authorized. Source hashes and diff check are saved alongside this report.

Checkpoint review: staged source/documentation whitespace check passed. Raw tool output whitespace is retained verbatim; full staged whitespace checks therefore report padding/blank lines in evidence. Credential-pattern scan found no staged secrets. See checkpoint-review.json.
