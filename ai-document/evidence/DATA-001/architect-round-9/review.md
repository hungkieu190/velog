# DATA-001 Architect review — Builder round 8

Date: 2026-10-02. Reviewer: Codex Architect. Intake: PASS. Task, checklist, and README identify DATA-001 round 8 as READY_FOR_REVIEW with Architect next; blueprint readiness is PASS; Builder round-8 report and both version logs exist. Decision: **CHANGES_REQUESTED**. No acceptance criterion is closed.

## Evidence reviewed

- Diff of `run.php`, `v4_cache_failure.php`, and `cleanup_control.sh`; Builder report and WordPress 6.4.3/6.7.2 logs.
- `git diff --check` exits 0. Both logs report V4-N/O assertions and fixture completion. These results support the V4 corrections but do not establish the cleanup controls.
- The unsafe cleanup control was not executed in this review. It contains unverified PID signaling and recursive deletion.

## Findings

### D1-F-021 — Owned-process cleanup remains open

1. **Unsafe negative control:** `cleanup_control.sh:50-60` parses a PID and path from output, sends `kill -9` to the numeric PID without a fresh `/proc` identity check, then runs `rm -rf` without proving child exit or exact path ownership. This violates round-8 binding constraints. Replace with socket recovery and direct-child identity verification. Preserve resources if exit cannot be proved. PID-mismatch and removal-failure controls also do not assert process/path state; the unrelated-sentinel option exits before acquiring resources.
2. **False success on shutdown failure:** `run.php:467-482` records but never checks `$shutdown_exit` or nonzero `$close_ret`. A failed shutdown command followed by daemon exit can report PASS. Check both outcomes and retain failure even after recovery.
3. **Incomplete identity and removal checks:** `run.php:204,235-268` does not resolve both executable paths. Its non-greedy `comm` parser can select an earlier `)` in a process name, and it does not validate parsed start ticks. Cleanup at lines 480-500 does not revalidate child identity and exact owned path before deletion. Fail closed on missing or changed identity.
4. **Evidence ownership:** `run.php:37-48` still writes into `builder-round-3/<version>/run.txt`; tracked round-3 evidence was overwritten. Use a run-owned path and retain historical logs.

## Required verification

- Correct runner and control script under approved D1-F-021 proposal revision 5 and round-8 binding constraints. No new proposal is required. Do not substitute PID-directed signals.
- Run bounded negative controls for PID-file/identity mismatch, shutdown error, uncertain child exit, removal failure, and unrelated sentinel. Assert exit code, diagnostic, process identity/state, exact path state, and sentinel survival. Record owned paths/processes and safe recovery; avoid fixed `/tmp/cleanup*.log` files.
- Then rerun both disposable WordPress versions, lint, PHPUnit, and `git diff --check`. Preserve round-8 logs and write next-round evidence.
- Live-handle COMMIT failure, coordinator 1213 deadlock, persistent-cache compatibility, and full G-08 performance remain NOT VERIFIED.

### Chat handoff prompt

```text
Status: CHANGES_REQUESTED
Recipient: Builder
Intent: implementation + verification

Act as Antigravity Builder for DATA-001. Read ai-document/evidence/DATA-001/architect-round-9/review.md. Resolve D1-F-021 under the approved revision-5 approach and round-8 binding constraints. Eliminate unverified PID signaling and deletion from cleanup_control.sh, make shutdown/close failures nonzero even after recovery, complete identity/path checks, and write new run-owned evidence without overwriting historical logs. Execute safe bounded negative controls before both disposable WordPress fixtures, lint, PHPUnit, and diff checks. Report process/path cleanup and return READY_FOR_REVIEW. Do not self-accept or touch the active site/database.
```
