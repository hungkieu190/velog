# DATA-001 Architect review — Builder round 6

Date: 2026-09-30. Reviewer: Codex Architect. Intake: PASS: task, checklist and README agree on READY_FOR_REVIEW, Architect next, round 6; blueprint readiness PASS. Decision: **CHANGES_REQUESTED**. D1-F-021 proposal revision 4: **REVISE PROPOSAL**. No DATA-001 acceptance criterion is closed.

## Independent verification

- Reviewed current source diff, proposal, prior review and V4-J/N/O controls. `composer run lint`: exit 0; `composer run test`: exit 0, 80 tests/283 assertions. These unit checks do not execute the V4 WordPress fixture.
- Did not run the disposable WordPress runner: its current cleanup still signals a PID and removes the directory without proving process termination. V4-J/N/O therefore have no WordPress execution evidence.

## D1-F-021 approach gate — REVISE PROPOSAL

Revision 4 clarifies intentional `--keep-tmp` success and bounded socket shutdown, but it does not meet the round-6 blueprint:

1. `ps -o lstart,args` is not an immutable or unambiguous start identity. `lstart` has one-second resolution, and `args` is presentation text with possible truncation/formatting; string prefix and `contains` checks do not prove the exact executable and argument vector. Use Linux `/proc/<pid>/stat` start ticks and `/proc/<pid>/exe` plus NUL-delimited `/proc/<pid>/cmdline`, or give an equally strong executable identity proof. Bind it to the retained direct-child handle and validate exact datadir/pid-file arguments.
2. The missing/mismatched PID-file path says to “kill our known direct child,” contradicting the no-PID-signal safety boundary. Specify fail-closed behavior that never signals a reusable numeric PID. A failed startup may leave the run-owned directory and child for diagnosis; it must exit nonzero and report the owned process. Do not claim safe deletion or cleanup success in that state.
3. The `deletion_failure` control reverts to `chmod -w`, which is bypassable by privileged execution and may not prevent deletion of a directory via its parent. Use a deterministic injected failing remover and assert the injection ran. Cover no-signal-to-sentinel behavior and all early-failure cases with explicit exit/path/process oracles.

Submit a read-only revision 5 mapped to these points. `run.php` remains gated until Architect records APPROVED FOR IMPLEMENTATION.

## Code and control findings

- **D1-F-019 open.** The audit-chain code now checks adjacent states, final fields and duplicate *matching* request identity. V4-O creates a partial save but never reconciles it with its original change subset. Its duplicate control changes audit entry 1 to the *create* hash, then reconciles the separate request hash from entry 2; the implementation sees one match, so this does not exercise duplicate detection. Rewrite controls to reconcile the partial save successfully and duplicate the exact request hash being reconciled. Assert each corruption write actually changed one row and restore the original envelope between cases.
- **D1-F-020 open pending execution.** V4-J now normalizes whitespace, counts injections and removes the filter in `finally`; the static defects from round 6 are addressed. Run its four branches on both pinned WordPress versions after safe runner cleanup, with per-branch injection counts and error results.
- **D1-F-022 open pending execution.** `reconcile_create()` now compares entry 0 `after` with canonical request fields at every version; the round-6 code defect is addressed statically. V4-N checks more setup steps but still accepts a zero-row `wpdb->update()` as success and only checks projection count `> 0`, not exact expected value/cardinality. Add a version-2 or later tampered-create `after` control with intact projections and assert it returns `storage_unavailable`; execute on both WordPress versions.
- **D1-F-023 open.** V4-J no longer performs destructive DDL, but V4-O's duplicate control is false and V4-J/N/O have not run. Do not claim closure from 80 PHPUnit tests; those tests do not execute the disposable WordPress fixture.

Persistent-cache compatibility, live-handle COMMIT failure, coordinator 1213 deadlock, V3 regional/term retention and full G-08 product performance remain NOT VERIFIED. No product code was changed during this review.
