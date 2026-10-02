# D1-F-021 Cleanup Proposal — Revision 4

This proposal describes the safe cleanup strategy for disposable WordPress test runners, addressing Architect round 6 feedback regarding process start identity, exact executable/argument verification, bounded socket shutdown, explicit `--keep-tmp` behavior, and deterministic negative controls.

## 1. Process Identity and Validation

- **Capture Start Identity**: When `proc_open()` launches `mariadbd`, we read the child's PID via `proc_get_status()`. We immediately capture its immutable start time (`lstart`) and command-line arguments (`cmdline`) by parsing `ps -o lstart= -o args= -p <PID>`. This `(PID, lstart)` tuple forms the definitive process identity.
- **Exact Executable and Argument Verification**: We parse the `cmdline` from `ps` to assert it strictly starts with `mariadbd` (or `mysqld`) and contains our exact `--datadir=<tmp>` and `--pid-file=<tmp/mysql.pid>` arguments. Any mismatch throws a setup failure.
- **Daemon Forking and PID Files**: The test runner runs MariaDB in the foreground (`proc_open` without `--daemon`), so it does not fork; the `proc_open` handle remains the direct parent.
  - We read the generated `.pid` file once the socket is ready. 
  - If the `.pid` file is absent, or its contents contradict our retained direct-child PID, we classify it as an **early setup failure**, kill our known direct child, and exit with an error.

## 2. Bounded Shutdown and Exit Verification

- **Bounded Shutdown**: Instead of signals, we shut down the server using a Unix socket command with a strict timeout: `timeout 5 mysqladmin --socket=<tmp/mysql.sock> -u root shutdown`. 
- **Command/Timeout/Exit Classifications**:
  - **Success**: `mysqladmin` exits 0.
  - **Timeout**: `timeout` exits 124. This is a **shutdown failure**.
  - **Rejection/Crash**: `mysqladmin` exits nonzero (not 124). This is a **shutdown failure**.
- **Child Exit Proof**: After a successful `mysqladmin` call, we call `proc_get_status()` on the retained handle. We poll up to 2 seconds. If `running` becomes `false`, the exact child has exited. If it remains `true`, this is a **child-exit uncertainty** failure.
- **No Unproven Conversion**: We only proceed to directory deletion if `mysqladmin` exits 0 AND `proc_get_status()` confirms the direct child has terminated.

## 3. `--keep-tmp` Operator Intent vs. Failure

- **Operator Intent**: If the script is invoked with `--keep-tmp`, we perform the bounded shutdown as usual but **skip** directory deletion. If all tests pass and shutdown succeeds, the script returns `0` (success). The temporary files are intentionally preserved for debugging.
- **Cleanup Failure**: If `--keep-tmp` is NOT passed, but directory deletion fails (e.g. permission error, files left open), the script prints diagnostics and returns a nonzero exit code (`1`).
- **Preservation on Test/Setup Failure**: If any test fails, or if setup/shutdown fails, the script will naturally return nonzero (`1`) and preserve the temporary directory (by bypassing deletion), printing its path for diagnostics.

## 4. Deterministic Failure Injection

- **Mechanism**: We introduce a `--simulate-cleanup-failure=<type>` flag to the runner.
- **Negative Controls**:
  - `identity_mismatch`: Modifies the expected `lstart` in memory before shutdown, triggering an identity mismatch abort.
  - `shutdown_timeout`: Uses a mock script for `mysqladmin` that `sleep 10`, triggering the `timeout 5` boundary (exit 124).
  - `shutdown_error`: Uses a mock script that exits `1`.
  - `child_uncertainty`: Mocks `mysqladmin` to exit 0 without actually signalling the DB, so the process remains running.
  - `deletion_failure`: Removes write permissions from the tmp directory right before deletion (`chmod -w`), causing `rm -rf` (or PHP `rmdir`) to fail.
- **Assertion**: For each negative control run, the test harness asserts that the exit code is nonzero, the correct classification error string is printed, and the temporary directory was preserved.

## 5. File/Function Change Map

**`tests/fixtures/wp-integration/data001/run.php`**:
- `start_db()`:
  - Add `ps` identity capture `(PID, lstart, args)` and validation against exact `--datadir` and `--pid-file`.
  - Add `.pid` file content verification against retained PID.
- `stop_db( $proc, $identity, $socket )`:
  - Execute `timeout 5 mysqladmin ... shutdown`.
  - Validate exit status (0 vs 124 vs other).
  - Poll `proc_get_status( $proc )` for termination.
  - Check `lstart` identity before taking action.
- `cleanup()`:
  - Check `$argv` for `--keep-tmp`. If present, skip deletion, return 0 if tests passed.
  - Add `chmod` failure injection if `--simulate-cleanup-failure=deletion` is present.
- `main()`:
  - Wrap execution in try/finally to ensure `stop_db` is called.
  - Route `--simulate-cleanup-failure` to the respective failure paths.
