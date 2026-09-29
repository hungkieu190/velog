# DATA-001 D1-F-021 Cleanup Design Proposal (Revision 2)

- **Finding**: D1-F-021 (Repeated-finding, 2nd consecutive failed review)
- **Author**: Antigravity Builder
- **Date**: 2026-09-29
- **Status**: AWAITING_ARCHITECT_APPROVAL — do NOT implement until Architect records APPROVED FOR IMPLEMENTATION

---

## Problem statement

The previous cleanup proposal (Revision 1) lacked strict identity validation, failed open on missing identity (skipping termination but continuing deletion), and did not resolve the check-to-signal race. The Architect requires:
1. Failing closed on unproven daemon identity/termination (preserve diagnostics, do not delete datadir).
2. Capturing identity immediately at launch, validating executable/command association.
3. Addressing the check-to-signal race (fail closed if identity changes).
4. Executable negative controls for identity, signal, and deletion failures.

---

## Proposed approach

### A. Capture and validate child identity at launch

Immediately after the socket appears (or timeout), we read the PID from the `--pid-file`. We must firmly establish that this PID belongs to *our* mysqld instance before doing anything else.

1. **Read start time**: Read `/proc/$pid/stat` field 22 (start ticks).
2. **Validate command line**: Read `/proc/$pid/cmdline`. It must contain the exact `--datadir` path created for this run. A start-time match is not enough; the `cmdline` proves it is our daemon.
3. If `/proc` is unreadable, or the `cmdline` does not match, or the PID file is absent when we expect it: **Abort setup immediately**. Throw an exception or exit nonzero. Store the validated `$mysqld_pid`, `$mysqld_start_ticks`, and a flag `$mysqld_identity_proven = true`.

### B. Fail-closed cleanup logic in `finally`

The `finally` block must handle cleanup safely, even if setup failed early.

1. If `$mysqld_identity_proven` is false (or not set), but a PID was read, we **cannot** safely signal it. We must skip termination, skip directory deletion, log "Unproven identity", set `$cleanup_failed = true`, and exit nonzero. Diagnostics are preserved.
2. Differentiating natural exit from lost identity:
   - If `is_owned_mysqld()` (which checks start_ticks) is false, but we previously proved identity, did it exit naturally?
   - We check if the socket file is gone, or if `cmdline` changed. If it exited naturally, it's safe to delete the dir. But if we can't prove it was a natural exit vs lost identity, we fail closed (do not delete dir, set `$cleanup_failed = true`).

### C. The check-to-signal race and mitigation

In pure PHP without `pidfd_open` / `pidfd_send_signal` (Linux 5.1+ syscalls), an irreducible TOCTOU (time-of-check to time-of-use) race exists:
1. We check `/proc/$pid/stat` (Identity matches).
2. Kernel context switch: mysqld exits, PID is reclaimed by a new process.
3. We call `posix_kill($pid, SIGTERM)`. (Wrong process receives signal).

**Mitigation & Fail Closed**:
We perform the check, send the signal, and immediately check again.
If the identity changed or became unreadable during the second check, we might have hit the race. We log a critical warning and set `$cleanup_failed = true` (failing the suite) to surface the anomaly. We accept the residual risk of the signal having been sent, but we explicitly fail closed on the test outcome.

### D. Bounded termination sequence

1. **Verify**: `if (!is_owned_mysqld()) { $cleanup_failed = true; skip... }`
2. **SIGTERM**: `posix_kill($pid, SIGTERM)`. Immediately verify identity again.
3. **Poll**: Wait up to 10s for `/proc/$pid` to disappear.
4. **SIGKILL**: If still present, verify identity. If matches, `posix_kill($pid, SIGKILL)`. Verify identity again. Wait up to 3s.
5. **Final Check**: If the process is still running with our identity, or if identity was lost during signaling, `$cleanup_failed = true`.

Only if termination is proven successful (or proven natural exit prior to signal) do we proceed to `rm -rf`.

### E. Exact directory removal and exit propagation

If `$cleanup_failed` is false:
1. `exec("rm -rf " . escapeshellarg($tmp_base) . " 2>&1", $rm_out, $rm_exit);`
2. If `$rm_exit !== 0` or `file_exists($tmp_base)`, set `$cleanup_failed = true`.

If `$cleanup_failed` is true at the end of `finally`, the script sets `$overall_exit = 1` and exits.

---

## Executable Negative Controls

We will add a new test script: `tests/workflow/cleanup-safety-controls.php`.
This script will `exec()` modified versions of `run.php` (by injecting environment variables or replacing commands via a test harness) to trigger and verify:

1. **Missing/Invalid Identity**: Harness is given a fake PID (e.g., PID 1). The setup must abort, `rm -rf` must NOT be called, and harness exits nonzero.
2. **Signal Failure**: A mock mysqld ignores SIGTERM. The harness must escalate to SIGKILL, and if that is mocked to fail, the harness must set `$cleanup_failed = true`, leave the dir intact, and exit nonzero.
3. **Directory Deletion Failure**: `chmod 000 $tmp_base` before cleanup. `rm -rf` will fail. The harness must set `$cleanup_failed = true` and exit nonzero.

These controls guarantee that the failure paths in the cleanup logic are actually executed and behave as designed.

---

## Scope boundaries

- Changes limited to `tests/fixtures/wp-integration/data001/run.php` and the new negative control script.
- No changes to `src/` or production code.
- Fails closed on non-Linux systems without `/proc`.
