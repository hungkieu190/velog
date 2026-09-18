# CORE-001 independent Architect review — Round 3

Date: 2026-09-18. Decision: CHANGES_REQUESTED.

## Commands and evidence reviewed

- composer run lint: exit 0; PHPCS passes, PHPStan 6/6 files with no errors; existing PHPStan version advisory only.
- composer run test: exit 0; PHP 8.3.6, PHPUnit 10.5.64; 21 tests, 35 assertions.
- git diff --check: exit 0.
- Independently read tests/Unit/LoaderRunTest.php, tests/workflow/core001-smoke.sh and the entire isolated-smoke.log, including the failed first attempt and subsequent run. Script was not rerun: it has defective cleanup and cannot establish the missing assertions in its current form.
- Current src/Core/Plugin.php and velog.php retain the previously reviewed scope. Dashboard edits in scripts/progress-data.mjs, scripts/progress-view.mjs and src/css/progress.css were observed but are outside CORE-001 review; left untouched and not accepted by this review.

## Finding dispositions

### CORE-001-F-001 — CLOSED (unchanged)

Relative translation path fix retained; no new path defect found.

### CORE-001-F-002 — CLOSED

New action test captures Loader's registered callable, executes it with hello_action and checks the component received it. New filter test executes its registered callable and asserts hello_filtered. Together with the existing Plugin::run idempotency tests and independently verified root-entry regression from Round 2, these satisfy the requested representative callback behavior. The suite is still mocked/subprocess testing, not a substitute for real WordPress translation verification.

### CORE-001-F-003 — OPEN (P2) — Smoke test does not validate translation or warning timing

Location: tests/workflow/core001-smoke.sh:58–75; isolated-smoke.log and Builder report Round 3.

Accepted evidence: isolated MariaDB and WordPress 7.1.1 setup and VeLog activation succeeded on the second run. The former environment blocker is resolved. The first run failed creating the mu-plugin directory and remains useful failure history.

Unmet evidence:

1. No non-English locale/catalog is installed or generated. The mu-plugin logs __(Some String, velog), and the result is unchanged Some String. It asserts neither expected translated text nor domain load. This output would also occur with a broken translation path. Provide a known catalog-backed different translation, loaded from an isolated plugin copy's languages directory, and assert it after normal init. Do not write fixture catalogs through the current symlink into the working plugin.
2. wp eval executes after normal WordPress bootstrap. Re-firing init and plugins_loaded does not restore the pre-init lifecycle. The supposed early negative control is therefore late, no warning appears in the log, and || echo masks command failure. Builder's claim that it produced a warning is unsupported by the submitted script/log. Install diagnostic instrumentation before normal bootstrap (for example via an isolated mu-plugin), capture the relevant warning event, assert absence on the correct path and presence on a genuinely early negative control in a separate process with a loadable catalog. Preserve nonzero failure status. Report detector results, not a generic CLI success.
3. Only WP 7.1.1 installation/activation is logged. WP 6.4, deactivation/reactivation and explicit smoke PHP-version/exit evidence are absent. Run the specified matrix and lifecycle or report precise unavailable checks; never infer these from PHPUnit's PHP version or plugin activation alone.

Verification: the positive catalog assertion must fail when the path is broken; the diagnostic control must actually trigger the expected event before init; normal bootstrap must not. Logs must identify pinned WP versions, PHP version, commands/exits and resource cleanup. Correct Round 3 claims in an appended report without erasing history.

### CORE-001-F-004 — OPEN (P2) — Cleanup races database shutdown and reuses a fixed directory

Location: tests/workflow/core001-smoke.sh:4, 19–25, 34–35.

cleanup sends kill and immediately rm -rf's the datadir without waiting. Both logged runs show MariaDB failing to write ib_buffer_pool.incomplete because the directory is already gone, despite Cleanup complete. The fixed /tmp/velog-core001-smoke directory and a PID read from a file also fail to isolate concurrent/stale runs safely.

Use a unique mktemp-owned work directory; capture the DB child PID directly, poll readiness rather than assuming a sleep suffices, request graceful shutdown and wait for the owned child to exit before deleting its datadir. Preserve the primary command's failure exit code and report cleanup failures. Never kill a process from an unvalidated reused PID file. Verification: success and deliberately failed smoke runs clean up only their own directory, leave no DB child, and produce no datadir-removal race errors. Review did not execute the unsafe cleanup.

## AC verdicts

| AC | Verdict | Reason |
|---|---|---|
| AC1 | PASS | Root wiring, idempotency and representative action/filter behavior covered by inspected passing regressions. |
| AC2 | FAIL — verification method invalid | init/domain/path checks pass; real translated string and correctly timed diagnostic control absent (F-003). Actual translation behavior remains NOT VERIFIED. |
| AC3 | PASS | Lint/tests pass; reviewed runtime public API and dependency scope unchanged. Unrelated dashboard edits not part of acceptance. |
| AC4 | FAIL — incomplete/incorrect evidence | Isolated provisioning/activation demonstrated, but requested lifecycle/matrix/translation evidence incomplete and cleanup defective (F-003/F-004). |

PLAN-F-004 wiring fix is verified at automated regression level; retain its overall open acceptance state until the required real lifecycle evidence is complete. Task header was IN_PROGRESS although the report/user handoff said READY_FOR_REVIEW; review reconciles it to CHANGES_REQUESTED and requests accurate next handoff metadata. No acceptance checkboxes changed. No application fixes, commit, deployment or active-site database access performed by Architect.
