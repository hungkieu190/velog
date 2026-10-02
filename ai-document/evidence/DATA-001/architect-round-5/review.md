# DATA-001 Architect review — Builder round 4 part 2

Date: 2026-09-30. Reviewer: Codex Architect. Intake: PASS. Task, checklist and README agree on READY_FOR_REVIEW and Architect as next actor; revision-5 blueprint readiness is PASS. The submitted proposal and source are present. Decision: **CHANGES_REQUESTED**. D1-F-021 proposal revision 2: **REVISE PROPOSAL**. No acceptance criteria are closed.

## Independent verification

- Reviewed the committed Builder source, D1-F-021 proposal revision 2, previous Architect review, audit representation, runner cleanup, and V4-J–M fixture. The prior `d438715` baseline did not track these source files; review therefore used current code and prior findings rather than claiming a narrow Git delta.
- `composer run test`: exit 0, 80 tests and 283 assertions. `composer run lint`: exit 0 (PHPCS and PHPStan). `git diff --check`: exit 0 after documentation changes. `git status --short` shows only the three synchronized documentation files and this review directory; no scratch files were created.
- No disposable WordPress runner was executed: its current PID-based cleanup is unsafe. Thus V4-J–M, both pinned WordPress versions and actual failure controls remain NOT VERIFIED for this submission.

## D1-F-021 approach gate — REVISE PROPOSAL

Revision 2 correctly identifies the check-to-signal race, but checking identity after `posix_kill()` only detects a wrong signal after it has been sent. It cannot satisfy the requirement to avoid signaling an unrelated process. `cmdline` containing `--datadir` is not enough to prove executable identity or exact `--pid-file` association. A missing PID file during startup must not leave a possibly running child with no cleanup owner. A vanished socket does not prove natural process exit. The proposed `chmod 000` deletion failure control is unreliable when the runner has permission to remove the parent directory (and when run as root).

Required revision 3 blueprint:

1. In `tests/fixtures/wp-integration/data001/run.php`, launch the daemon as a direct child with a retained process handle, not a shell background command. Record the child PID and process start identity immediately, before later setup steps. Verify the executable and exact datadir/pid-file arguments, and reject contradictory PID-file data. An unproven child is a cleanup failure; preserve the owned directory and diagnostics.
2. Shut down through the run-owned Unix socket with a bounded database-admin command, then use the retained child handle and start identity to establish termination. Do not use PID-directed `posix_kill()` or a process-group signal. If graceful shutdown cannot be proven, fail the runner and retain the directory for diagnosis. Do not infer exit from socket disappearance or PID-file absence. If a different mechanism is proposed, it must prove that it cannot target a reused PID before approval.
3. Delete only the exact run-owned directory after the child is proven stopped. Check removal exit and path absence; preserve diagnostics and return nonzero on any uncertainty or failure. Handle every early `try`/`finally` path and `--keep-tmp` explicitly. Never delete the datadir of a live or unproven process.
4. Add executable controls outside the production site for missing/incorrect identity, graceful-shutdown failure, child-exit uncertainty and removal failure. Assert nonzero exit, retained directory when required, and no signal to an unrelated sentinel process. Use a deterministic removal failure injection, not permissions that a privileged runner can bypass. Record owned paths/PIDs and cleanup results.

This decision keeps the escalated finding gated. Builder must submit a read-only revision 3 proposal mapped to this blueprint before changing or running `run.php`.

## Code and fixture review

- **D1-F-019 remains open.** `RecordRepository::reconcile_save()` now compares the original change subset with the matched before/after snapshot and checks its index. However, `count(audit) === record_version` is not proof of a gap-free audit: it does not require entry 0 to be create, every later entry to be save, each `before` to equal the previous `after`, or final `after` to equal envelope fields. A second matching request hash can silently replace the first match. Corrupt history may therefore yield a committed snapshot. Validate the entire chain and reject duplicate request identity with `indeterminate`; add partial multi-field and corrupted-chain controls.
- **D1-F-020 partially corrected.** Four `null` query results now fail with fixed messages, avoiding raw database error disclosure. This is a useful correction. The current V4-J only forces the count query failure, so page, preflight and batch failure paths remain unverified. Add targeted injected failures for each read and assert `storage_unavailable`, no empty-success result and no SQL detail in caller-facing messages.
- **D1-F-022 partially corrected.** The create audit entry now binds request hash, actor and canonical `after`, and `verify_write()` checks projections on the pinned handle. It still does not check create entry `before` is empty, that the create entry is the sole version-1 event, or that later audit transitions and final envelope fields agree. Reconcile must reject a marker/candidate with malformed audit history; add a negative control that corrupts those relationships while preserving projection rows.
- **D1-F-023 remains open.** V4-L now uses the same request ID with a wrong expected version, addressing the prior false control. V4-J restores the renamed table in `finally`, but does not inspect the restore query result, and a failed restore can leave the test database broken. The `SHOW TABLES` check alone is insufficient to prove the original table was restored rather than replaced. Avoid destructive DDL for query error injection; if retained, verify rename-back result and stop all later fixtures on failure. V4-J–M have no new executable WordPress results.

Live-handle COMMIT failure, coordinator 1213 deadlock, persistent-cache compatibility, V3 regional/term retention and full G-08 product performance remain NOT VERIFIED. No runtime code was changed in this review.
