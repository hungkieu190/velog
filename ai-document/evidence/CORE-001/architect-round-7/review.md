# CORE-001 independent Architect review — Round 7

Date: 2026-09-22. Baseline: 9c4d1be (clean tracked working tree before review). Reviewer: the same Codex Architect conversation that reviewed Round 6; documentation and independent verification only, no implementation contribution. Round 7 implementer: Antigravity Builder per task report; prior contributor 866ba911-4817-495a-90db-c2e198e686cc retained. Reviewer is independent of those implementers; no machine session ID invented.

## Verdict

CHANGES_REQUESTED: AC1 PASS, AC2 PASS, AC3 PASS, AC4 FAIL for the remaining outer-control cleanup false success. F-001/F-002/F-003 remain CLOSED. F-004 remains OPEN only for the control-owner defect described below. The shared cleanup rm-status defect from Round 6 is fixed and independently verified across all eight required combinations. Do not repeat that implementation work.

## Independent evidence

- lint.log: composer run lint exit 0 (PHPCS/PHPStan).
- tests.log: composer run test exit 0, 21 tests / 35 assertions, PHP 8.3.6, PHPUnit 10.5.64.
- syntax-*.log: each of the three shell scripts separately parsed with bash -n, all exit 0.
- controls.log: bash -x tests/workflow/core001-smoke-controls.sh exit 0. Trace records all eight removal cases and actual exit 1/23, V4 readiness durations 0/2 seconds, child/PID and path absence assertions, signal 143, detector processing-error and warning controls.
- smoke.log and isolated-smoke.log: actual independent bash tests/workflow/core001-smoke.sh with CORE001_LOG_FILE set to this review directory, subprocess exit 0. WP 6.4.3 and 6.7.2 on PHP 8.3.6, MariaDB 10.11.14, fixture translation Xin Chào, lifecycle activation/deactivation/reactivation, actual WP 6.7.2 early warning detected, contaminated output and nonzero WP exit rejected. Final cleanup status 0.
- resource-check.json: owned smoke directory absent and DB PID 11432 absent; all reviewed source and historical Round 5/6/7 evidence hashes unchanged from review start.
- diff-check.log: submitted git diff --check exit 0. Final documentation check recorded separately.

## F-004 remaining defect (P2): outer control owner masks cleanup failure

Location: tests/workflow/core001-smoke-controls.sh:210–245 and cleanup_sentinel at lines 104–108. The eight-case loop runs under set +e. Parent rm at line 244 is unchecked and path absence is not asserted. The EXIT trap removes only the sentinel, not the retained fixture directories. Several assertion branches also chain exit 1 after rm with &&, allowing a failing rm to skip the intended exit.

Reproduction in outer-cleanup-probe.log invokes the actual control script via source in a disposable Bash process. It overrides only rm, returning 9 for /tmp/velog-core001-smoke.* when BASH_SUBSHELL is 0; all child cleanup/injections remain unchanged, other rm calls delegate to command rm. The suite prints All controls passed and returns 0 while four leave_dir fixtures remain. The independent reviewer subsequently removed each exact captured fixture and verified absence. No database is involved in this reproduction.

This violates the existing Round 7 requirement that the outer owner remove its captured fixtures, preserve assertion failures and never mask cleanup failure. It is a harness/evidence integrity defect, not a demonstrated plugin runtime defect or active-site data loss. Correction is limited to the outer control owner and its regression evidence.

## Evidence limitations and observations

Builder's recorded controls pipeline captures tee's status without pipefail; it does not itself prove the control command exit. Independent direct subprocess execution resolves the normal-run exit evidence. New reports must capture the actual process exit. Existing Builder logs omit some successful ownership/duration details; the independent shell trace supplies these observations without changing source. Historical logs preserved.

PHP 8.1 specifically remains NOT VERIFIED; the task requires PHP >=8.1 and this round used 8.3.6. No broader version/release certification, dashboard review or product feature acceptance is claimed. The shared runtime and smoke checks need not be rerun for the next control-only correction if their hashes remain unchanged. No production database accessed, application/test code edited, commit, publish or deploy performed by Architect.
