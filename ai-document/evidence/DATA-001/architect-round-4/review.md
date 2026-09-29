# DATA-001 Architect review — Builder round 4 partial work

Date: 2026-09-29. Reviewer: Codex Architect. Intake: **PASS** (`READY_FOR_REVIEW`, Architect next actor, task/checklist/README synchronized). Overall decision: **CHANGES_REQUESTED**. Cleanup approach decision: **REVISE PROPOSAL** for D1-F-021. No DATA-001 acceptance.

## Independent evidence

- Read the cleanup proposal, previous round review and binding proposal review 5, production source and V4-J–V4-M fixture changes. `src/Common/Storage/` and fixtures are untracked, so the tracked `git diff` cannot prove their delta; review used current source and prior findings.
- Independently ran `composer run lint` (exit 0, PHPCS/PHPStan 0 errors), `composer run test` (exit 0, 80 tests/283 assertions) and `git diff --check` (exit 0). No disposable WordPress fixture was run this round because `run.php` still has the unsafe cleanup implementation. New V4 assertions have no executable result yet.

## D1-F-021 approach gate — REVISE PROPOSAL

The proposal correctly recognizes PID reuse, proposes a `/proc/<pid>/stat` start-time identity and checks removal failure. It is not yet safe enough to approve:

1. The failure matrix says absent/zero PID, unreadable `/proc` and identity mismatch merely skip termination and continue deletion. If the harness started mysqld but cannot prove its exit, these cases must fail the suite and preserve diagnostics. Do not report cleanup success or remove an active daemon's socket/datadir. Differentiate a proven natural exit from lost identity.
2. Capture and validate the child identity immediately after launch, before subsequent setup can fail; `finally` must handle every early failure. Verify the PID file, process start time and executable/command association with this run's `--datadir`/`--pid-file`. A start-time match alone does not establish that the originally recorded PID was this run's mysqld.
3. Specify what happens if PID identity changes between verification and `posix_kill`. The proposed check-then-signal sequence has a PID reuse race. Use a safe process handle/identity mechanism where available, or explicitly fail closed and explain the residual risk. Never signal an unverified PID.
4. Add executable negative controls for missing/invalid identity, signal failure and directory deletion failure; assertions must prove nonzero runner exit and no signal to another process. A vague plan to mark the cleanup-failure control NOT VERIFIED does not meet the repeated-finding gate.

Submit revision 2 read-only. Do not modify or run `run.php` until approach approval.

## Round-4 code and fixture findings

1. **D1-F-019 remains open / P1.** `RecordRepository.php:1822-1832,1865-1924` hashes `$canonical_changes` and compares it with audit `after`, but `save()` stores the complete merged `$new_fields` in audit (`:680-705`). A partial update of a record with multiple fields is therefore falsely classified as `conflict`. The claimed gap-free sequence is never checked: `$idx` and `$prev_required_version` are unused. The matched entry is accepted whenever the *current* version exceeds the expected version; its own version position and `before`/`after` transition are not checked. Reconcile using the original change subset against the matched transition, validate all entries and positions, and return `indeterminate` on malformed history. Add multi-field partial-save, same-request/wrong-version, gap, and before/after corruption controls.
2. **D1-F-020 partially corrected / P1.** The four SQL reads now inspect `last_error`, but `RecordRepository.php:192,247,274,291` concatenates raw database error text into returned `WP_Error` messages. This can expose table identifiers or SQL details to callers. Use fixed messages, retain diagnostic detail only in controlled evidence. A `null` result without `last_error` still becomes a successful empty count/page or preflight; fail closed on any nonconforming result. V4-J tests only the count query, not page/preflight/batch failures.
3. **D1-F-022 partially corrected / P1.** `reconcile_create()` now invokes `verify_write()` for projections, but only requires *some* create audit entry (`:1729-1745`). It does not bind that entry's request hash, actor, version position and canonical `after` fields to the idempotency marker and candidate. Extend the pinned-handle integrity proof and negative controls.
4. **D1-F-023 remains open / P1.** V4-L (`v4_cache_failure.php:547-574`) explicitly switches to a different request ID, so it does not test its stated same-request/wrong-version case. V4-J renames `wp_postmeta` with DDL and restores it only on the straight-line path (`:425-448`); an exception can leave the disposable database broken. Provide a guaranteed restore in `finally` and independently confirm restore success. V4-J–M were not executed on either WordPress version. Post-COMMIT lost-ack, live-handle COMMIT failure, coordinator 1213 and V3 regional/term retention obligations remain unproven.

Persistent-cache compatibility and full G-08 product performance remain **NOT VERIFIED**. Live-handle COMMIT failure and coordinator 1213 remain **NOT VERIFIED**. Preserve reviewer independence and previous evidence.
