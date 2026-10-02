# Builder implementation report: Round 9

## Focus
Correct D1-F-021 owned-process cleanup and its negative controls per Architect round 9 review and approved revision 5 approach. 

## Actions taken
- **Unsafe cleanup control removed**: Replaced `kill -9` in `cleanup_control.sh` with safe socket-based recovery for the stranded test instance (`mysqladmin --socket=... shutdown`).
- **Shutdown verification**: Updated `run.php` to fail the run if `$shutdown_exit` or `$close_ret` are nonzero, avoiding false passes even if the child process exited after a failed command.
- **Identity and path checks**: 
  - Corrected `comm` parser regex in `run.php` to correctly read `/proc/$PID/stat` and validate `$start_ticks`.
  - Added strict revalidation before tmp deletion: confirmed `!is_dir("/proc/$mysqld_pid")` and exact path `preg_match('#^/tmp/velog_data001_\d+\.\d+\.\d+_\d+_[a-f0-9]{16}$#')`. 
- **Evidence preservation**: Updated `$evidence_dir` in `run.php` to `builder-round-9`, preserving historical evidence. **I have also used `git restore` to revert the tracked changes to the `builder-round-3/6.4.3/run.txt` and `builder-round-3/6.7.2/run.txt` logs that were unintentionally overwritten in the previous round.**
- **Verification execution**:
  - `cleanup_control.sh`: Passed. All bounded negative controls correctly detected failures (PID mismatch, shutdown fail, rm fail, unrelated sentinel) and safely recovered using sockets without unverified PID signaling.
  - `composer run test` (PHPUnit): Passed (80 tests, 283 assertions).
  - `composer run lint`: Passed.
  - `git diff --check`: Clean (exited 0).
  - Disposable WP fixtures on 6.4.3 and 6.7.2 ran successfully and wrote evidence to `ai-document/evidence/DATA-001/builder-round-9/`.

## Resource cleanup
Visual verification and automated negative control checks confirm no orphaned sockets, `datadir`, or scratch files remain. Safe socket recovery successfully reclaimed the intentionally stranded negative control instance.

## Remaining NOT VERIFIED cases
- Live-handle COMMIT failure.
- Coordinator 1213 deadlock.
- Persistent-cache compatibility.
- Full G-08 product performance.

## Next steps
Task is set to `READY_FOR_REVIEW`. Next actor: Architect.
