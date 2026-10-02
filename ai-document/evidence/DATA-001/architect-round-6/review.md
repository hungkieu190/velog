# DATA-001 Architect review — Builder round 5

Date: 2026-09-30. Reviewer: Codex Architect. Intake: PASS. Task, checklist and README state READY_FOR_REVIEW, Architect next, revision-5 blueprint PASS and Builder round 5. Proposal and diff exist. Decision: **CHANGES_REQUESTED**. D1-F-021 proposal revision 3: **REVISE PROPOSAL**. No acceptance criterion or code finding is closed.

## Independent checks

- Reviewed `git diff` for `RecordRepository.php` and V4 fixture, proposal revision 3, previous review and WordPress `wpdb::query()` filter behavior. `composer run lint`: exit 0; `composer run test`: exit 0, 80 tests/283 assertions; `git diff --check`: exit 0.
- The disposable WordPress runner was not executed: `run.php` still uses PID-directed signals and unchecked directory removal. No fresh WordPress result exists for V4-J–N. This review changes documentation only.

## D1-F-021 proposal — REVISE PROPOSAL

The direct-child handle and socket-only shutdown address the principal wrong-PID signal risk. The proposal is not yet decision-complete against the round-5 blueprint:

1. `proc_get_status()` provides PID/running/exit status, not a process start-time identity. Specify how start identity is captured and checked, and verify the exact executable, `--datadir`, and `--pid-file` arguments. State the behavior if the daemon forks, the PID file is absent, or its PID contradicts the retained child.
2. Specify a bounded shutdown command and authentication, all command/timeout/exit failure classifications, and proof that the retained direct child exited before deletion. Do not convert an unproven child into a successful cleanup.
3. `--keep-tmp` is an explicit operator option: distinguish intentional retention from cleanup failure. State whether successful tests with intentional retention return zero, and ensure failure still returns nonzero and preserves diagnostics. Give a deterministic failure-injection mechanism and assert it was actually activated.
4. Provide a concrete file/function change map and control oracles for every early setup failure, identity mismatch, shutdown failure, child-exit uncertainty, deletion failure and unrelated-process sentinel. Submit revision 4 read-only; do not change or run `run.php` before approval.

## Code findings

- **D1-F-019 open.** `reconcile_save()` now validates create/save order, adjacent snapshots, final fields and duplicate matching request hashes. However, V4 adds no partial multi-field save or corrupted-chain test. No real WordPress execution was supplied. Closure requires executable controls that corrupt an intermediate `before`, final `after`, and duplicate request identity, plus a partial save preserving other fields. Reject malformed history as `indeterminate`.
- **D1-F-020 open.** Fixed caller-facing messages remain, but V4-J's page marker `ORDER BY p.ID ASC LIMIT` cannot match the actual SQL, which has a newline between `ASC` and `LIMIT`; that case runs without injected failure. The test does not assert that any target filter fired. Make each target match exact generated SQL (or normalize whitespace), count injections, and require exactly one hit per case. Use `try/finally` to remove the `query` filter on exceptions. Run count/page/preflight/batch cases and verify `storage_unavailable` and no SQL leak.
- **D1-F-022 open.** In `reconcile_create()`, the original create `after` is compared with the requested canonical fields only when `record_version === 1` (`RecordRepository.php:1810-1819`). For a later version, a wrong create `after` can remain chain-consistent with later entries and pass if final envelope fields match. Preserve the first create `after` separately and compare it with the idempotency marker's canonical payload for every version. Reject duplicate create request identity and malformed array keys. Add a version-2 or later negative control with intact projections.
- **D1-F-023 open.** V4-J removes destructive DDL, which is an improvement, and V4-N adds a corrupt-audit case. But V4-N does not assert successful raw-envelope read, corruption write, or intact projections before reconciliation, so it can pass for the wrong reason. The page case in V4-J is inert. Add setup assertions and the missing controls, then run the pinned WordPress fixtures after the cleanup gate is approved and implemented.

Live-handle COMMIT failure, coordinator 1213 deadlock, persistent-cache compatibility, V3 regional/term retention and full G-08 product performance remain NOT VERIFIED. No self-acceptance, product code change, commit or deployment occurred.
