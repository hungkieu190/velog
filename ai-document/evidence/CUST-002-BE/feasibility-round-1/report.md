# CUST-002 native API feasibility — 2026-10-15

## Scope and authorization

Product Owner approved continued blueprint work and the listed product rules with "ok bạn làm đi". These are disposable engineering probes, not production implementation or functional acceptance. No active LocalWP database or credentials were used. No VeLog runtime PHP, frontend assets or database schema was changed.

## Environment and commands

- CLI PHP 8.3.6.
- MariaDB 10.11.14, newly initialized task-owned datadir, Unix socket only, networking disabled.
- Separate fresh WordPress 6.4.3 and 6.7.2 installations, no VeLog activation or third-party plugins.
- External production HTTP/email delivery was not used. Test installation used `--skip-email`.
- Runtime prototype: subclass of wpdb refuses check_connection/db_connect while pinned; native user writes continue through wp_insert_user.
- Raw successful output: `successful-run.txt`.
- Initial failed probe: `initial-run.txt`. The database-failure assertion incorrectly required last_error to be nonempty. Core query returns false before populating that property when reconnect is refused. The assertion was corrected to check the return value and an independent database read for absence of the write. No application behavior was changed to make the probe pass.
- Retained reproduction entry point: `bash tests/fixtures/cust-002-feasibility/run.sh`. The retained runner was rerun successfully (exit 0, 32 PASS assertions); see retained-run.txt. It generates its fixture admin password, preflights tools and owns cleanup outside the source tree.

## Observed outcomes

Each WordPress version produced 16 PASS assertions (32 total), plus successful runner exit:

| Probe | Observed result |
|---|---|
| Native user creation and metadata inside a pinned transaction | Visible inside that transaction |
| Rollback plus explicit cache cleanup | Native user, login lookup, metadata and option-based audit absent |
| Native commit | Unicode name, state and native-ID audit reference persisted |
| Disconnect then write, explicit reconnect while pinned | Write returned false, no autocommit orphan row, explicit reconnect refused |
| Negative hook side effect | File written by user_register survived database rollback, as expected |
| Concurrent baseline API inserts with same email | Two rows; deterministic barrier placed after native existence checks |
| Same concurrent inserts with named lock and fresh final duplicate check | One row and one native empty_data rejection |

The primitive's empty_data error is not the final customer-facing error contract. The runtime guard must preserve typed internal context for VeLog handlers and present fixed safe notices for other native callers.

## Limits and next decision

This proves selected primitives, not complete native-user atomicity. It does not verify VeLog roles, auth/cookies/application passwords, migration, audit/idempotency implementation, stale versions, cache drop-ins, arbitrary plugin callbacks, full admin HTTP flows, MySQL, PHP 8.1, ambiguous COMMIT recovery, all failure paths or performance. Only same-email create concurrency was exercised; same-login and concurrent update are mandatory implementation regressions.

The negative control rules out a blanket claim that transactions can roll back arbitrary user hooks or mail. The proposed supported callback/connection boundary is in `ai-document/native-customer-write-contract.md`; it requires an explicit Product Owner compatibility decision before runtime implementation. New tables or core UNIQUE indexes are not proposed.

## Cleanup

Both exploratory database processes shut down; their sockets were absent before cleanup. Owned scratch scripts, downloaded WordPress copies, datadirs, barriers and hook side-effect files were removed after preserving these redacted logs. The retained test runner uses a new task-owned temporary directory for every run and removes it after its database stops. Cleanup of pre-existing files/processes is outside this task.
