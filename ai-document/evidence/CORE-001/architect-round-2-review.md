# CORE-001 independent Architect review — Round 2

Date: 2026-09-18. Decision: CHANGES_REQUESTED. Application files unchanged by review.

## Actual verification

- composer run lint: exit 0; PHPCS pass; PHPStan 6/6 files, no errors. Existing PHPStan version advisory is not a gate failure.
- composer run test: exit 0; PHP 8.3.6, PHPUnit 10.5.64; 19 tests, 31 assertions.
- git diff --check: exit 0.
- Independently copied the actual bootstrap-entry-verify.php fixture into a temporary tests/fixtures directory and symlinked the existing Composer vendor directory. Current velog.php: exit 0, PASS with domain velog and path velog/languages. The same fixture with only mf_velog()->run() changed to mf_velog(): exit 1, "FAIL: No callbacks registered on init after plugins_loaded." Both runs load current Core code; temporary copies were automatically removed. This proves behavioral root-wiring sensitivity without changing the working tree.
- Read-only environment discovery: command -v found /usr/sbin/mariadbd, /usr/sbin/mysqld, /usr/bin/mariadb-install-db, /usr/bin/mysql and /usr/local/bin/wp. No database connection/start was performed by Architect.
- Historical evidence/README.md:33 records WF-001 using a temporary site with a dedicated MariaDB datadir/socket, then shutting it down and removing it. This does not prove a fresh setup will pass, but contradicts assuming the only available path is the active site's DB.

## Finding dispositions

### CORE-001-F-001 — CLOSED for path defect

Plugin.php now passes dirname(plugin_basename(VELOG_PLUGIN_FILE)) . '/languages', relative to WP_PLUGIN_DIR. I18nLifecycleTest asserts the exact domain and velog/languages value. The entry fixture confirms callback execution with those arguments. Actual bundled translation remains NOT VERIFIED under F-003; do not conflate closing the path defect with passing all AC2.

### CORE-001-F-002 — PARTIALLY RESOLVED, remains open (P2)

The positive fixture now requires actual velog.php and exercises its registered callback. Architect's same-fixture omit-run mutation fails correctly, resolving the missing entry-point regression. The Builder's separate mutation fixture does not load or mutate velog.php, so its negative control alone is not proof of root regression sensitivity; Architect independently supplied that proof above.

Remaining acceptance gap: tests/fixtures/bootstrap-entry-verify.php implements do_action(string $hook) with callback() and discards accepted-argument values; add_filter delegates to add_action and no apply_filters/filtered return value is tested. No representative action with arguments or filter execution is covered anywhere in the PHP tests. Complete the originally requested AC1/F-002 behavior test: queue a callback receiving multiple known arguments and a filter returning a transformed value, execute through captured callable dispatch or actual WP hooks, and verify repeated Plugin::run does not multiply execution. Do not merely assert registration counts or private fields. No production defect is claimed from this missing test alone.

### CORE-001-F-003 — OPEN (P2), blocker not established

Builder correctly leaves RWP-1–RWP-5 NOT VERIFIED and records a reported wp core is-installed connection failure in the active site's directory. That diagnostic supports only failure of that site's configured DB connection; it does not support the broader claim that a disposable database cannot be created. A separate datadir/socket was neither attempted nor ruled out. Required executables are present and WF-001 documents an isolated approach.

Correction: attempt isolated setup using separate temporary resources, record exact commands/exits/versions and sanitized output, then run the required real translation/lifecycle/debug/activation checks; if it fails, report that precise failure and cleanup. Do not use the active site's database or disclose its credentials. No new dependency installation is authorized. The task's full AC2/AC4 are unchanged; documenting NOT VERIFIED is honest but not acceptance evidence.

## Criterion verdicts

| AC | Verdict | Evidence / remaining work |
|---|---|---|
| AC1 | FAIL — partial PASS | Root wiring/idempotency verified in stubbed entry fixture; representative callback argument/filter execution requirement missing (F-002). |
| AC2 | NOT VERIFIED — partial PASS | init/domain/relative path pass; non-English bundled fixture and real early-translation diagnostic checks absent (F-003). |
| AC3 | PASS | Existing public API/lifecycle declarations and dependency scope preserved; lint/tests pass; Architect same-fixture mutation proves root regression sensitivity. |
| AC4 | NOT VERIFIED | No actual isolated WordPress lifecycle/translation evidence; broad environment blocker unsubstantiated (F-003). |

PLAN-F-004: bootstrap wiring correction verified by executable stubbed-entry regression; retain open pending the task's real WordPress acceptance. No new runtime code defect found in the reviewed path fix. No DONE and no acceptance boxes checked. Current task/checklist/index status synchronized to CHANGES_REQUESTED. Prior reports preserved. No commit, deployment or active-site database access performed.
