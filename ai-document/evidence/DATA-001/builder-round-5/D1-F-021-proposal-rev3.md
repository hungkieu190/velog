# D1-F-021 Cleanup Proposal Revision 3

## 1. Launching and Identity Proof
In `tests/fixtures/wp-integration/data001/run.php`, the daemon will be launched as a direct child using `proc_open()` rather than a shell background command (`&`).
The process handle will be retained. We will record the child PID and process start identity immediately using `proc_get_status()`.
Before proceeding with setup, we will read the created PID file and verify it matches the direct child PID we hold. If they contradict, the setup fails, the runner aborts, and the owned directory is preserved for diagnostics.

## 2. Graceful Shutdown
Shutdown will be commanded via the run-owned Unix socket using a bounded database-admin command (e.g. `mysqladmin --socket=... --user=root shutdown`).
We will explicitly **avoid** using `posix_kill()` or process-group signals.
After issuing the shutdown command, we will poll `proc_get_status()` on the retained child handle to establish actual termination. We will not rely on socket disappearance or PID-file absence. If graceful shutdown cannot be proven within a timeout, the runner will fail and retain the directory.

## 3. Safe Directory Deletion
Directory deletion will only proceed after the child process is proven stopped.
Only the exact run-owned directory will be deleted. We will check the removal exit status and verify path absence. If there is any uncertainty, or if `--keep-tmp` is set, we will preserve the directory and return nonzero.
Early `try`/`finally` paths will explicitly handle these states and never delete the datadir of an unproven or live process.

## 4. Executable Controls
We will add executable controls outside the production site (e.g. in `tests/fixtures/wp-integration/data001/negative_control.php` or similar runner tests):
- Control for missing/incorrect identity (PID mismatch).
- Control for graceful-shutdown failure (simulated by ignoring the shutdown command).
- Control for child-exit uncertainty.
- Control for removal failure (deterministic failure injection, e.g. replacing the `rm` command path with a failing stub, rather than relying on `chmod` permissions).

These controls will assert nonzero exit, retained directory, and no signal sent to unrelated sentinel processes. Owned paths/PIDs and cleanup results will be recorded.
