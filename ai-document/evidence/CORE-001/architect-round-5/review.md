# CORE-001 independent Architect review — Round 5

Date: 2026-09-21. Verdict: CHANGES_REQUESTED.

Reviewer: current Codex Architect conversation, which authored planning/review documents only. Implementation contributor: Antigravity Builder identified in the Round 5 report; prior contributor 866ba911-4817-495a-90db-c2e198e686cc remains recorded. Round 5 uses a descriptive session reference, not an asserted machine identifier. Reviewer is not an implementation contributor. No application/test-harness implementation was changed during this review.

## Executed checks and limits

| Check | Actual result | Evidence |
|---|---|---|
| composer run lint | Exit 0, PHPCS and PHPStan pass; informational PHPStan update notice does not fail the check | lint.log |
| composer run test | Exit 0; PHP 8.3.6; 21 tests / 35 assertions | test.log |
| bash tests/workflow/core001-smoke-controls.sh | Exit 0, V1–V5 headings and success emitted; assertions do not cover all claimed failure behavior | controls-and-diff.log |
| bash -n | Each of the three shell files checked separately, all exit 0 | controls-and-diff.log |
| git diff --check | Exit 2: trailing whitespace at task lines 379 and 395 in submitted Builder report/prompt | controls-and-diff.log |
| Detector processing-error injection | Detector incorrectly returns absence=1 after grep returns 2; normal_gate accepts clean translated output despite detector failure | reproductions.log |
| Cleanup removal-error injection | With set +e and originating exit 0, cleanup returns 0 and directory remains; with production-style set -e and originating exit 23, result is 9, losing original 23 | reproductions.log |
| Replay actual Builder V6 output through submitted helper | Both normal matrix outputs accepted; actual WP 6.7.2 early output detected and rejected by the same normal gate | matrix-log-replay.json |

Review command wrappers record actual inner exit codes; the Python capture process exiting successfully is not the checked command's verdict. Source SHA-256 values are in reviewed-files.json. No database was started or accessed. Full real smoke was not rerun independently; real-matrix evidence is inspected Builder output plus independent gate replay. Reviewer-created removal-error fixtures were cleaned by the reviewer after the injected failures, using only captured owned paths.

## Accepted progress

- F-001/F-002 remain CLOSED; bootstrap and PHP regression evidence are unchanged and pass.
- F-003's original pattern mismatch is fixed for the inspected HTML/plain warning shape. One detector is used by normal and negative paths, actual warning replay is rejected, and normal WP command status is captured.
- F-004 now uses mktemp -d with private permissions; child liveness and a bounded mysqladmin probe are present. Normal controls pass, and the actual matrix log shows MariaDB graceful shutdown.
- V6 log contains WP 6.4.3 and 6.7.2, translated Xin Chào for both, activation/deactivation/reactivation, the real early warning and negative replay. The recorded work directory no longer exists at review time. This is meaningful progress; it does not establish cleanup-failure behavior.

## Stable findings

### F-003 — OPEN, P2: processing errors still pass the normal gate

Location: tests/workflow/core001-smoke-functions.sh:14–18; test coverage at tests/workflow/core001-smoke-controls.sh:45–58.

The detector's if/else converts every nonzero grep result into 1 (absence), including execution/processing error 2. normal_gate accepts 1. Independent scoped grep fault injection returned detector_exit=1 and normal_gate=ACCEPT. This violates Round 5 S3/S4 and V2's explicit fail-closed contract.

The V2 control only calls the detector with no argument. It does not force an error during real normal_gate processing with valid translated output, so its success does not prove the gate rejects processing failures. This is the remaining part of F-003, not a new product requirement. Preserve the now-correct actual-warning matching/replay.

### F-004 — OPEN, P2: cleanup errors are masked or overwrite the original failure, and controls do not prove cleanup outcomes

Location: tests/workflow/core001-smoke-functions.sh:72–101; controls:106–187.

cleanup invokes rm without explicitly handling failure and then exits origin_status. With the same set +e context used by V4/V5 controls, a failed removal still returns 0 for a successful origin, leaving its directory behind. With production-style set -e, rm failure interrupts cleanup and replaces the original 23 with 9. Both were independently reproduced using only an owned temporary directory and injected rm failure. The success/failure exit preservation promised in Round 5 S7 is therefore unmet.

wait occurs only inside the live-PID branch; a recorded already-exited child is not explicitly waited in that branch. TERM/KILL/wait statuses are suppressed, and no final termination/removal outcome is recorded. Preserve the functioning normal graceful shutdown while making failure reporting explicit.

V4/V5 assert subshell exit codes but do not record/assert child termination, removed directories or elapsed bounds after those runs. V3 checks only allocation-only directory cleanup. The controls' sentinel also lacks owner cleanup on an early control failure. The evidence table overstates V4/V5 lifecycle proof. These gaps are within the existing F-004/AC4 resource contract, not a request for unrelated harness redesign.

## Additional handoff issues

- git diff --check does not match the Builder's reported exit 0; two newly appended documentation lines contain trailing spaces. Builder must correct the whitespace and rerun the command after the final report is written.
- Submitted checklist focus says READY_FOR_REVIEW but Next actor remains Builder and the action is still Round 5 implementation; README still describes the previous handoff. Reconcile on the next handoff without rewriting historical reports or touching WF-002's separate state.

## Criterion verdicts

| Criterion | Verdict | Reason |
|---|---|---|
| AC1 | PASS | Existing actual-entry/Loader/idempotence regressions pass; no runtime wiring change in this round |
| AC2 | FAIL | Real translation and warning replay pass; processing-error path still fails open, F-003 |
| AC3 | PASS | PHP lint/test pass, 21/35; no new runtime behavior/dependency introduced |
| AC4 | FAIL | F-004 cleanup error/verification gaps; submitted diff check and handoff metadata fail |

CORE-001 is not DONE. No acceptance checkbox is checked. Next: Builder follows Correction blueprint — Round 6 in the task, addressing the remaining F-003/F-004 contract and associated evidence/metadata only. Other planned features are not unlocked by this review. WF-002 remains its own approved assignment.

## Concurrent-work note after recording the verdict

The final whole-tree git diff --check still exits 2 and additionally reports newly appearing whitespace in scripts/progress-view.mjs from concurrent WF-002 work. See final-diff-check.log. Those dashboard edits are outside CORE-001 and were not changed or reviewed here; do not ask CORE-001 Builder to repair unrelated work. The two CORE-001 report whitespace errors were present in the initial review snapshot. All three reviewed CORE-001 harness file hashes remained unchanged through verdict recording.
