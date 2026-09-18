# CORE-001 independent Architect review — Round 1

Date: 2026-09-18. Baseline HEAD: f04d4fc. Decision: CHANGES_REQUESTED. Reviewed actual working-tree diff and the two untracked test files, not only the Builder summary. No runtime application files were modified during review.

## Commands actually run

| Check | Exit | Actual result |
|---|---|---|
| composer run lint | 0 | PHPCS passed; PHPStan checked 6/6 files, no errors. Existing old-version advisory only; no dependency upgrade performed. |
| composer run test | 0 | PHP 8.3.6, PHPUnit 10.5.64; 16 tests, 26 assertions |
| git diff --check | 0 | No whitespace errors |
| Isolated actual WordPress l10n.php path probe | 1 | Registry path differs from expected plugin languages directory; see architect-round-1-path-probe.log |
| Temporary baseline-entry mutation | 0 | Current tests still pass 16 tests/26 assertions with HEAD velog.php lacking the bootstrap fix; see architect-round-1-bootstrap-mutation.log |

The path probe loads the actual local WordPress 7.1.1 l10n.php function with a capturing registry stub and plugin_dir_path stub. It does not bootstrap WordPress or connect to a database. The mutation uses copied tests and HEAD velog.php with the existing Composer autoloader resolving current src; no repository application file was overwritten. Both temporary fixtures were removed automatically. These targeted probes do not replace real WordPress integration.

## Findings

### CORE-001-F-001 — P1 — Wrong translation directory (AC2)

Location: src/Core/Plugin.php:130; tests/Unit/I18nLifecycleTest.php:200–208.

The third argument to load_plugin_textdomain() is relative to WP_PLUGIN_DIR. plugin_dir_path(VELOG_PLUGIN_FILE) returns an absolute path. Actual local WordPress wp-includes/l10n.php:1011 prefixes WP_PLUGIN_DIR to that argument, registering a duplicated filesystem path. Bundled catalogs under the plugin's languages directory will not be discovered through this registration (a separately installed global language pack could mask the bug).

The test only checks that the string contains languages and lacks a double slash, so it accepts this invalid absolute path. Use a WordPress plugin-relative path derived from the existing basename constant or plugin_basename(), assert the exact expected domain/path (velog/languages for the standard fixture), and update the inaccurate docblock about VELOG_PLUGIN_DIR. Verification: the path probe resolves to the actual languages directory and a non-English bundled fixture translates after init in real WordPress.

### CORE-001-F-002 — P2 — Bootstrap regression is not tested (AC1/AC3, PLAN-F-004)

Location: tests/Integration/BootstrapTest.php:126–140 and tests/Unit/LoaderRunTest.php.

Tests call Plugin::get_instance()/run() directly; none load velog.php or invoke its registered plugins_loaded callback. The copied suite with the baseline broken velog.php still passes all 16 tests. Therefore the cited tests verify run() idempotency, not the original missing-bootstrap call. Action registration is counted but representative action/filter execution with arguments is also not covered.

Add a regression through the actual entry path using captured hooks or a separate process, execute callbacks and assert argument/filter-result behavior. Verify that removing the root run() wiring makes the behavioral assertion fail, not merely a reflection lookup for a newly added private property. Keep singleton isolation without exposing test-only production APIs. Static inspection indicates the new mf_velog_bootstrap() is wired correctly, but PLAN-F-004 cannot be accepted closed until regression and runtime evidence exist.

### CORE-001-F-003 — P2 — Required isolated WordPress evidence missing (AC2/AC4)

Location: ai-document/evidence/CORE-001/round-1-evidence.md, NOT VERIFIED section; task verification instructions.

No disposable WordPress installation, actual lifecycle/translation fixture, early-warning diagnostic, activation cycle or requested WP version matrix has been exercised. The report transparently marks these missing, but transparency is not satisfaction of the original ACs. No exact attempted setup command or concrete environment blocker is supplied. The current prompt's abbreviated AC4 wording does not explicitly waive the task's integration requirements.

Run and record the required checks on disposable resources with exact versions, commands, outputs and cleanup. Use WP 6.4 and an available WP >=6.7 on PHP >=8.1; PHP 8.1 specifically may remain NOT VERIFIED if unavailable, with the actual tested PHP version reported. Do not conflate local CLI PHP execution with a WordPress site run. If setup is blocked, give the precise attempts/error and return the blocker; do not weaken ACs or declare DONE.

## Criterion verdicts

| Criterion | Verdict | Reason |
|---|---|---|
| AC1 | FAIL — verification incomplete | Static bootstrap wiring and run() guard look correct; required real-entry regression and callback behavior evidence absent (F-002). |
| AC2 | FAIL | init hook/domain are correct; languages path is wrong (F-001); real fixture and diagnostic checks NOT VERIFIED (F-003). |
| AC3 | FAIL — partial checks PASS | Public API signatures, activation hooks, version and dependency scope preserved; lint/test PASS. Required failing-before/passing-after bootstrap regression absent (F-002). |
| AC4 | NOT VERIFIED — acceptance blocked | No isolated WordPress evidence; declarations alone do not satisfy AC4 (F-003). |

## Scope and decision

No new product features/dependencies or changes to Loader.php were found. Existing manual/plan edits are pre-existing and preserved. Builder's premature Architect-review heading contains only a handoff request, not an independent review; this document is the actual round-1 review.

Task is CHANGES_REQUESTED, Builder next. No CORE-001 acceptance checkbox is checked and no criterion item is changed. Checklist current-focus metadata and project index are synchronized; criterion lines retain their earlier READY assignment labels per the user's instruction not to update checklist items on failure. They are not acceptance results; this table and task current handoff own the review verdict. PLAN-F-004 remains open. No commit, deployment, active-site database access or application fix performed.
