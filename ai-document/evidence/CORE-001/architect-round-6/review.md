# Independent Architect review — CORE-001 Round 6

Date: 2026-09-21. Reviewer: current Codex Architect task, started with the user's AGENTS.md/status inspection request and authorized by “ok làm đi”. This session has only inspected implementation, run verification and written review documentation. Implementer: Antigravity Builder identified in Round 6; prior contributor 866ba911-4817-495a-90db-c2e198e686cc remains recorded. Reviewer is not an implementation contributor. No machine session ID is invented.

## Decision

CHANGES_REQUESTED. AC1 PASS; AC2 PASS; AC3 PASS for PHP behavior/API and quality gates; AC4 FAIL for incomplete cleanup contract and evidence/handoff requirements. F-001/F-002 remain CLOSED. F-003 CLOSED. F-004 remains OPEN (P2). No acceptance checkbox is checked; PLAN-F-004 final acceptance remains pending CORE-001 completion.

## Inspected implementation and independent results

Baseline HEAD: 2aa3b8d2760faab20b88008b9e63e78327765259. Working tree includes unrelated PLAN-002 and WF-002 changes, preserved. reviewed-files.json records SHA-256 of the three harness files and runtime entry/Core files inspected. Existing bootstrap, singleton guard, init translation callback and plugin-relative language path remain intact.

- composer run lint: exit 0, PHPCS and PHPStan pass (lint.log).
- composer run test: exit 0, 21 tests / 35 assertions, PHP 8.3.6, PHPUnit 10.5.64 (tests.log).
- bash tests/workflow/core001-smoke-controls.sh: exit 0 (controls.log).
- bash -n run separately for each of the three shell files: each exit 0 (syntax-*.log). Builder's one bash -n command with three filenames parses only the first script; the independent separate checks resolve this review gap.
- Submitted git diff --check: exit 0 (diff-check.log); final documentation check recorded separately.
- Actual warning from the latest Builder WP 6.7.2 log: detector=0, clean gate=0, contaminated gate=1 (replay.log). The submitted controls also exercise plain/HTML warnings, other domain, sed/grep errors and nonzero WordPress exit. F-003's processing-error defect is fixed.
- Builder's latest real run begins at 09:11:15 in round-6/isolated-smoke.log (line 130); inspected both WP 6.4.3/6.7.2 translation, activation/deactivation/reactivation and negative warning/replay observations. Both recorded work directories are absent now. Full real smoke was NOT independently rerun: the submitted runner still writes into historical round-5 evidence. No database was started or accessed by this review. Logs are Builder evidence, not an independent integration execution.

## F-004 — cleanup command errors are still discarded

Location: tests/workflow/core001-smoke-functions.sh:108–111. rm -rf is followed by `|| true`; only directory presence is checked. Round 6 blueprint explicitly required capturing removal status as well as checking absence. A scoped command injection delegates to real rm to remove the owned fixture, then returns 9. Actual cleanup returns 0 with origin 0 under both set +e and set -e. Origin 23 remains 23. Reproduction: reproductions.log. This is a fault-injection proof of the required exit-status contract; it is not a claim that normal deletion currently fails or that data was lost. No fixture remains from this reproduction.

Required correction: capture rm's exit code in an errexit-safe conditional, mark cleanup failed for any nonzero result even when the path disappeared, retain the original nonzero workflow status, and still independently check path absence. The new negative control must invoke the actual shared cleanup, overriding only rm.

Existing cleanup-error controls cover only (+e, origin 0) and (-e, origin 23), not the required four combinations. Retain current successful child/readiness/signal controls and cover the full cross-product for both retained-directory and removed-but-error injections. Do not replace the tested cleanup function with a stub.

## Evidence and scope deviations

- Current handoff at task top remained Round 5 / Builder while README/checklist and the latest outgoing prompt said Round 6 / Architect. This review synchronizes them to its current decision; Builder must synchronize its next outgoing handoff.
- round-6/commands.log explicitly copies round-5/isolated-smoke.log. The copied file contains runs at 08:57 and 09:11. The latest run is identifiable, but this does not preserve round separation requested by the blueprint; future runs must use a distinct output destination and capture real exits. Preserve existing historical logs unchanged from this point onward.
- Builder reports running phpcbf on src/css/progress.css outside CORE-001 scope. This is a disclosed scope deviation, not part of CORE-001 acceptance. No safe per-session baseline exists here to isolate/revert that concurrent WF-002 formatting; do not revert or further modify it as part of CORE-001. No dashboard behavior is verified by this review.
- Round 6 report claimed full matrix coverage and separate syntax coverage beyond what its recorded commands implement. Append a correction in Round 7; preserve historical claims as history, do not rewrite them into retroactive successes.

## Limits

Full independent WordPress integration, PHP 8.1 execution, dashboard behavior and release/package checks are NOT VERIFIED in this review. No frontend build is needed for this shell/documentation correction. No application code, dependencies, generated assets, active-site database, commits or deployment were changed.
