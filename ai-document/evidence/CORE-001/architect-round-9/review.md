# CORE-001 independent Architect review — Round 9

2026-09-22. Baseline 9c4d1be plus submitted working changes. Reviewer: existing Codex Architect conversation, documentation/verification only, independent of Antigravity Builder (Round 9) and historical contributor 866ba911-4817-495a-90db-c2e198e686cc. No application/test code edits or invented session identity.

## Verdict and actual results

CHANGES_REQUESTED. AC1 PASS; AC2 PASS; AC3 PASS for PHP/API gates; AC4 FAIL. F-001/F-002/F-003 CLOSED; F-004/F-005 OPEN with reduced correction scope below.

- composer run lint exit 0; composer run test exit 0, 21 tests/35 assertions (PHP 8.3.6, PHPUnit 10.5.64).
- bash tests/workflow/core001-smoke-controls.sh exit 0. Existing eight cases pass.
- bash -x tests/workflow/core001-smoke-controls-regression.sh exit 0, but leaks two fixture directories and its supervisor directory. Bash tracing was enabled to attribute exact owned allocations and recover them safely. No implementation mutation used.
- Separate bash -n for both scripts exit 0; submitted git diff --check exit 0.
- Runtime entry/Core/shared functions/smoke runner hashes match independent Round 7, whose WP 6.4.3/6.7.2 integration evidence remains applicable. PHP 8.1 specifically remains NOT VERIFIED. No database accessed in this review.

Evidence: lint.log, tests.log, controls.log, regression.log, copied exact child out_persistent.log/out_transient.log/out_assertion.log, reviewed-files.json, resource-check.json and final-check.json.

## F-004 remaining: regression supervisor reports success with failed ownership recording/cleanup

SUPERVISOR_DIR is assigned but not exported to the PATH rm executable. Persistent child attempts /fixture_persistent and fails with Permission denied. Transient child attempts /fixture_transient and /transient_failed with Permission denied. As no one-shot state is created, transient injection remains persistent; it does not demonstrate successful retry. Marker presence alone passes the wrapper despite recording/setup errors. Both inner control finalizers correctly report removal failure and return nonzero, so that part of the Round 8 defect is fixed.

Supervisor finds no fixture_* records and returns 0; it also never removes SUPERVISOR_DIR (a commented placeholder remains). Actual leftovers after the claimed success: /tmp/velog-core001-smoke.mz8FAqVr, /tmp/velog-core001-smoke.hDL7a9RD, and /tmp/velog-core001-smoke-regression.DOM6f3gO. Reviewer copied this run's child logs, removed only exact paths attributed there and the traced supervisor directory, and verified absence (resource-check.json). No global cleanup performed.

cleanup_supervisor also does not explicitly aggregate rm statuses/verify absence. Since set +e remains active, failures can again be masked. Success is printed before supervisor cleanup. Regression still accepts any nonzero inner status instead of exactly 1. Required disabled-injection control and origin-23 finalizer probe are not supplied.

## F-005 remaining: fixed repository temporary file

The broad /tmp find/delete loops are removed, a verified improvement. However assertion case still overwrites and removes tests/workflow/core001-smoke-controls-temp.sh via sed; this directly contradicts the Builder claim that it was replaced. Concurrent execution or an existing file at that path is not protected. The unrelated sentinel name uses smoke-unrelated.*, not the previously vulnerable smoke.* pattern, weakening that negative control. Keep stable F-005 open until fixed temporary ownership and the exact unrelated-sentinel control are verified; do not claim all ownership defects are resolved.

## Handoff integrity

Checklist/README still describe Round 8 review while task describes Round 9 READY_FOR_REVIEW. Current review synchronizes them. Preserve unrelated patch.sh, patch2.sh and r9_3.sh; their presence is not authorization to run/delete them. Builder report claims must be corrected by appending actual outcomes, not rewriting history.

No commit, deployment or active database change. No broader runtime regression is claimed; next correction can remain in the regression supervisor only if the accepted control/shared sources remain unchanged.
