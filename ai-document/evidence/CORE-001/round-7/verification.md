# CORE-001 Verification - Round 7

## Finding-to-case mapping

- **R7-1 (F-004 command status)**: `tests/workflow/core001-smoke-controls.sh` executed the 4-way `errexit` and `origin` matrix for two `rm` failure types (leaving dir vs removing dir). All assertions passed, exit codes correctly propagated or overridden to 1 if cleanup failed but origin was 0.
- **R7-2 (F-003 and existing safety)**: `core001-smoke-controls.sh` executed early warning detector logic, normal_gate, dead-child timeouts, injected failures, and signals successfully.
- **R7-3 (AC2/AC4 real integration)**: `core001-smoke.sh` output correctly redirected to `isolated-smoke.log` (fresh run). Ran successfully against WP 6.4.3 and 6.7.2. Early warning appropriately triggered and detected on 6.7.2. Clean translations loaded via `velog-vi.mo`. Database and child process correctly terminated and removed on exit.
- **R7-4 (AC3 regression gates)**: Syntax checks, `composer run lint`, and `composer run test` all executed cleanly with 0 exit code. PHPUnit reports 21 tests and 35 assertions.
- **R7-5 (Evidence/handoff)**: All evidence captured cleanly. Trailing whitespace verified via `git diff --check`.

## Limitations & Gaps

- PHP 8.1 execution remains NOT VERIFIED (ran on PHP 8.3.6).
- `progress.css` formatting was an out-of-scope deviation introduced previously and is preserved without modification.
- Historical `round-5` logs contain previous overclaimed evidence which is retained unchanged.
