# DATA-001 Architect storage spike — 2026-09-25

This is disposable planning evidence, not DATA-001 implementation or acceptance. Each probe created its own MariaDB data directory under `/tmp`, used dummy WordPress credentials, stopped its server, and removed its directory. The active site and its database were not used.

## Observations

- [probe.txt](probe.txt): WordPress 6.7.2 installed `wp_posts`, `wp_postmeta`, and `wp_options` as InnoDB on MariaDB 10.11.14. A transaction inserting a post and meta row rolled both back. `GET_LOCK` serialized two sessions, but it is separate from the database transaction.
- [wp-api-probe.txt](wp-api-probe.txt): `wp_insert_post()` plus `update_post_meta()` inside a transaction rolled back in the database. Immediately after rollback, `get_post()` still returned the now-absent post from WordPress object cache; `clean_post_cache()` made it disappear. Cache invalidation after both outcomes is a required design invariant.
- [crash-probe.txt](crash-probe.txt): Killing a client while the server was executing `SLEEP(6)` did not immediately free its advisory lock. A contender timed out after one second; after the server query completed, the lock was free and the uncommitted row was absent. A bounded contention result and no automatic blind retry are necessary.
- [rowlock-probe.txt](rowlock-probe.txt): A transaction-scoped InnoDB `SELECT ... FOR UPDATE` lock on a dedicated `wp_options` row made a second writer fail with error 1205 under a one-second lock timeout. The contender succeeded after holder commit. This is the preferred candidate over a session-level advisory lock.
- [pinned-6.4.3.txt](pinned-6.4.3.txt) and [pinned-6.7.2.txt](pinned-6.7.2.txt): Direct SQL on a pinned `mysqli` handle acquired the options-row lock, rolled back a new post/meta pair, cleared the stale WordPress post cache, then committed a second post/meta pair. The same observations held on both WordPress versions.
- [disconnect-probe.txt](disconnect-probe.txt): Closing a pinned connection before commit made commit fail and the uncommitted WordPress post row disappear. The probe did not invoke a WordPress write helper or auto-retry.
- Local WordPress `class-wpdb.php` `query()` retries a failed query after reconnect on MySQL error 2006. Therefore `$wpdb` write helpers cannot by themselves guarantee that a transaction-bound write remains on the connection which acquired the lock. The candidate coordinator uses direct SQL on a pinned connection for every transactional statement and fails closed on connection loss; broader hook/drop-in compatibility remains to be verified during implementation.

## Limits

No DATA-001 repository, real persistent object cache, full cross-process WordPress repository mutation, or retention workflow was implemented or tested. The probes support a candidate blueprint only. PHP/WordPress versions beyond those stated above and other database engines remain unverified.
