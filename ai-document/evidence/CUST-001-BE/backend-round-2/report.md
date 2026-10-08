# CUST-001-BE — Round 2: T02 correction

Date: 2026-10-08
Owner/reviewer: Codex / Backend Architect
Review: SELF_REVIEWED_BACKEND (non-independent, bounded T02 correction)
Status: AWAITING_MANUAL_ACCEPTANCE
Next actor: Tester
Authorization: User explicitly approved implementation: "sửa đi".

## Problem and resulting behavior

MySQL 8.4 rejects SELECT @@in_transaction, so valid customer writes failed the environment preflight. CustomerPage allowed storage_unavailable/forbidden redirects without rendering their notices. The correction prepares a write transaction using SQL supported by MySQL and MariaDB and displays fixed, translated, escaped notices for both error codes.

The coordinator requires autocommit=1 and successful SET TRANSACTION READ WRITE on the actual mysqli handle. It rejects explicit active/read-only transactions, implicit transaction mode, and unavailable handles. It never commits or rolls back caller work as a probe. The statement sets the next transaction's access mode to READ WRITE, not session/global isolation. Direct run() callers receive the guard before lock-row initialization as well. Existing authorization, nonces, transactional write lock, rollback, and idempotency remain enforced.

References: [MySQL SET TRANSACTION](https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html), [MariaDB SET TRANSACTION](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-transaction).

## Change scope

- src/Common/Storage/WriteCoordinator.php: portable transaction preparation and direct-run guard.
- src/Admin/CustomerPage.php: storage and permission notices. Existing search-input type edit predates this correction.
- tests/Unit/WriteCoordinatorTest.php: disconnected-handle regression.
- tests/fixtures/cust-001-verify.php: live idle/active/read-only/autocommit tests; pending writes survive rejection and disappear on caller rollback; safe notice rendering. Vehicle fixture now supplies a VIN required by the existing in-progress VEH-001 schema; no vehicle implementation was changed.
- tests/workflow/product-smoke.sh: optional --db-engine=mysql with VELOG_TEST_MYSQLD, isolated database initialization, engine version output, and customer HTTP runner.
- tests/workflow/cust-001-endpoint.sh: authenticated POST, exact row-count increment, persisted Unicode/contact values, success redirect/notice, invalid nonce rejection, and safe error notices. Refuses non-disposable site paths.
- Task/UAT/checklist/README and this evidence.

## Verification

- PHP 8.3.6 CLI.
- composer run test -- --filter 'Customer|WriteCoordinator': exit 0; 13 tests, 30 assertions.
- composer run test: exit 0; 93 tests, 337 assertions (unit.log).
- vendor/bin/phpcs --standard=phpcs.xml src/Common/Storage/WriteCoordinator.php src/Admin/CustomerPage.php tests/Unit/WriteCoordinatorTest.php: exit 0 (changed-phpcs.log).
- vendor/bin/phpstan analyse --configuration=phpstan.neon src/Common/Storage/WriteCoordinator.php src/Admin/CustomerPage.php: exit 0 (changed-phpstan.log).
- bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.4.3: final exit 0 on MariaDB 10.11.14 (mariadb-wp643-final.log).
- VELOG_TEST_MYSQLD=/home/ecommercelife/.config/Local/lightning-services/mysql-8.4.0/bin/linux/bin/mysqld bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=6.7.2 --db-engine=mysql: final exit 0 on MySQL 8.4.0 (mysql84-wp672-final.log).
- Both final runs passed customer create/update/search/stale-write/archive/restore/contact redaction and active-vehicle rejection, plus transaction guards and authenticated HTTP form submission.
- bash -n on both changed shell runners and git diff --check: exit 0.

## Limits and initial failures

The initial engine runs passed new guards and customer creation, then failed because the old linked-vehicle fixture omitted an identifier now required by the pre-existing VEH-001 work. Initial logs are retained; adding a fixture VIN resolved both final runs.

Full composer run lint did NOT pass (PHPCS exit 2): pre-existing VehicleIdentifier/VehicleIdentifierTest violations and warnings in vehicle-related RecordSchema/RecordRepository edits. Full composer run phpstan did NOT pass (exit 1): five iterable-type errors in VehicleIdentifier. See full-lint.log and phpstan.log. These unrelated files were not changed or approved by this correction; no project-wide clean-gate claim is made. Changed-file gates pass.

All tests used disposable databases/sites. No customer was created or modified in the active LocalWP site by the agent. The active plugin source contains the fix; Tester must reload and manually repeat T02 there. Automated HTTP verification is not manual/browser acceptance. All owned disposable services/directories and failure scratch logs were removed. CSS/generated frontend assets were not changed. No commit/push/deploy occurred.

## Tester handoff

T01 remains user PASS. T02 original FAIL is preserved with retest pending. Reload Customers; create a Unicode customer; verify notice and row; edit phone and reload. Then run related T04/T06/T07/T09/T10/T13 and the remaining unperformed UAT cases. Only Tester may accept DONE.
