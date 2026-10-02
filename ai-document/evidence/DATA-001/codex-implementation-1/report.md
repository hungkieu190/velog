# DATA-001 direct implementation: cleanup runner

Date: 2026-10-02. Contributor: Codex, following the user's explicit request to implement directly. This is implementation evidence, not independent acceptance.

## Changes

- The runner writes fresh per-run evidence outside the plugin tree by default and resolves the server executable before comparing `/proc/<pid>/exe`.
- A failed shutdown command remains a failed run. The runner attempts bounded socket recovery, polls its direct child, and deletes the exact owned directory only after exit is observed. A forced removal failure remains a failed run while a subsequent removal reclaims the owned directory.
- The cleanup control no longer signals a numeric PID or removes a daemon directory. It checks nonzero exits, markers, and absence of each run's exact temporary directory. Its logs are owned by a unique temporary directory and removed on exit.

## Verification

- `tests/fixtures/wp-integration/data001/cleanup_control.sh`: exit 0; PID mismatch, shutdown failure, removal failure, and sentinel controls pass.
- `composer run lint`: exit 0.
- `composer run test`: exit 0; 80 tests, 283 assertions.
- Disposable WordPress 6.4.3 and 6.7.2 fixtures: exit 0, V4 assertions pass, and owned temporary directories removed. Logs: `6.4.3/run.txt` and `6.7.2/run.txt`.
- `git diff --check`: exit 0.

## Resource state and limits

This run's temporary evidence directories were removed after retaining the two logs above. Two pre-existing directories from earlier Builder runs remain at `/tmp/velog_data001_6.4.3_15397_06272e2c89c5e520` and `/tmp/velog_data001_6.4.3_21258_2954370b54eba6fc`; they were not removed because ownership and prior failure state were not independently established.

Live-handle COMMIT failure, coordinator 1213 deadlock, persistent-cache compatibility, and full G-08 product performance remain NOT VERIFIED. The contributor cannot independently accept this implementation.

## User decision

On 2026-10-02 the user explicitly directed completion without another tester and asked to start the next task. DATA-001 is recorded as DONE by direct user acceptance with the four NOT VERIFIED items above retained as exceptions. This decision is not represented as independent verification.
