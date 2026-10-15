# CUST-001 UAT prerequisite preparation — 2026-10-15

## Authorization and scope

Product Owner authorized the previously documented bounded LocalWP preparation with "vậy là đến bạn xử lý à, làm đi thôi". Backend Architect owns this fixture work. The scope is two new staff users, 51 synthetic pagination customers, one separate linked customer and one active linked vehicle, using the accepted private-record architecture. No native customer accounts, runtime changes, browser tests, commit, deployment or release.

Implementation: `tests/fixtures/cust-001-uat-seed.php`, `tests/workflow/cust-001-uat-seed.sh`, and one CUST-001-only call in `tests/workflow/product-smoke.sh`. The existing inventory helper remains read-only. Provisioning uses the repository/WriteCoordinator, a connection-scoped fixture mutex, durable private credentials, a non-autoload ownership manifest and deterministic create request IDs. Unknown identities/name-prefix/VIN/manifest collisions fail closed. Existing passwords and roles are never reset. Partial checkpoint failures require manual reconciliation; the helper does not adopt orphaned records or delete data.

## Environment and private backup

Reference development/manual test environment: LocalWP at `https://velog.local/wp-admin/`; WordPress 7.1.3, MySQL 8.4.0, VeLog 0.1.0. CLI PHP 8.3.6. Configured LocalWP web PHP 8.2.29 remains independently NOT VERIFIED. Socket is selected per CLI command; wp-config.php is unchanged. Read-only plugin inventory returned VeLog as the only active plugin.

Private pre-write database export: `/home/ecommercelife/.codex/backups/velog/cust001-uat-2026-10-15-preparation/before-uat.sql`, 958672 bytes, directory 0700/file 0600. Export completed with exit 0 using `MYSQL_UNIX_PORT=<LocalWP socket> wp db export <private path> --single-transaction --skip-lock-tables --skip-plugins --skip-themes`. SHA-256: `89bfd7077dc50669d007b1fe7b406499eb575ac23e4c6edeac93440c87204836`. No restore was performed.

Read-only plan: `local-plan.json`; two new staff users and 53 new private domain records (52 customers plus one vehicle); no existing fixture manifest or conflicts. No credentials or identities created by plan.

## Verification record

- PHP syntax and explicit `vendor/bin/phpcs --standard=WordPress tests/fixtures/cust-001-uat-seed.php`: exit 0. Read-only SQL and CLI file I/O have documented fixture-only sniff exceptions; variable comparisons retain expected/actual order.
- Both shell scripts pass `bash -n`.
- Initial isolated run refused before fixture writes because the disposable target used localhost instead of 127.0.0.1. The helper now accepts either exact loopback hostname with a port only under the owned disposable root. See `isolated.log`.
- Second isolated run reached the post-create staff credential/ownership guard and failed closed. See `isolated-r2.log`. Diagnostic messages were split by invariant. A separate no-write probe confirmed that WordPress 7.1.3 password hashing trims whitespace while direct verification does not; random fixture credentials now exclude whitespace. No live staff accounts existed at either failed run.
- Final isolated run: PASS, exit 0, `isolated-r3.log`. Command: `VELOG_TEST_MYSQLD=/home/ecommercelife/.config/Local/lightning-services/mysql-8.4.0/bin/linux/bin/mysqld bash tests/workflow/product-smoke.sh --task=CUST-001 --wp-version=7.1.3 --db-engine=mysql`. Existing customer backend and HTTP checks pass. New controls pass: plan does not create identities/manifest/credentials; wrong URL, insufficient actor permissions and a pre-existing username refuse apply; fresh apply creates exactly two staff users, 52 customers and one linked vehicle; verify validates stored password hashes, roles/ownership, exact active fields/versions, both pagination pages and vehicle projection; reapply preserves manifest and credential bytes; changed technician role refuses apply without resetting it. Manifest contains no plaintext passwords; no WordPress mail attempts. All disposable data is removed after the run.
- Full project gates: `composer run lint` exit 0 (PHPCS and PHPStan no errors); `composer run test` exit 0, 93 tests / 341 assertions. The capability rollback warning is emitted by the existing restore-failure fault-injection unit test, not a LocalWP runtime failure. `npm run test:workflow` under Node 24.21.0 exits 0, 20/20 pass. Logs: `lint.log`, `unit.log`, `workflow.log`. Initial fixture style/documentation findings were corrected; final explicit fixture PHPCS exits 0.
- `git diff --check` exits 0. Final fixture/runner/asset hashes are in `source-sha256.txt`.
- Live apply and separate verify: PASS, both exit 0. Invoked `php -d mysqli.default_socket=<LocalWP socket> /usr/local/bin/wp eval-file tests/fixtures/cust-001-uat-seed.php apply|verify https://velog.local /home/ecommercelife/.codex/private/velog/cust001-uat-credentials.json --user=admin --path=<LocalWP public root> --skip-themes`, sequentially. See `local-apply.json`, `local-verify.json`, `local-inventory-after.json`.
- New accounts: `velog_uat_manager` ID 2, role `mf_velog_manager`; `velog_uat_technician` ID 3, role `mf_velog_technician`. Credentials only in the private file, mode 0600; directory 0700. Password values are absent from documentation/evidence. Stored passwords were checked without displaying them; browser authentication remains a Tester action.
- New pagination Customer IDs 13–63 (51 records); separate linked customer ID 64; active vehicle ID 65. Search `UAT Customer ` returns exactly 51, split 50+1 with exact IDs. Live totals: 53 Customer records (one pre-existing plus 52 new), one active linked vehicle, one account per dedicated staff role. No customer login accounts.
- Before/after SHA-256 comparison covers all pre-existing customer/vehicle post+meta rows and pre-existing user identity/email/password hashes/capabilities. It passed unchanged. WordPress mail attempts: 0. The helper blocks wp_mail during provisioning and stores no passwords in its ownership option. No existing identities/records were modified, adopted or deleted.

## Boundaries and limitations

The 13 unchanged backend/test files in the prior round-3 source manifest retain their hashes; the product runner changes only by the explicit UAT seed-test invocation in this continuation. Approved Sass/CSS hashes remain `c9e2453e290e92f5023614fb3615be81f2cd07ffc92f5c15181512e5eb914e5d` and `a651d52dd770433291508b939c5c1792c05953f2ebff253cb3a77bfcf267be89` respectively. No asset rebuild is needed because source/assets were not changed.

Automated preparation checks do not establish UI login, browser asset loading, responsive/RTL/keyboard/screen-reader behavior or feature acceptance. Tester must run T01–T20; historical T01 PASS and T02 FAIL pending retest remain intact. RTL locale/screen-reader availability belongs to manual prerequisites and must be reported BLOCKED if unavailable. Existing backend round-3 NOT VERIFIED limitations remain unchanged.

Review decision: SELF_REVIEWED_BACKEND for preparation only. CUST-001, CUST-001-FE and CUST-001-UAT are AWAITING_MANUAL_ACCEPTANCE, Next actor Tester. CUST-001-BE remains its technical DONE checkpoint. No new manual case is marked PASS.

Cleanup: all three owned isolated directories (`/tmp/velog-product-smoke.c92R2HMs`, `.wMNJGAzW`, `.AGWODjB6`), PHP/database services and failed-run diagnostic copies were checked/removed. The reusable fixtures and required evidence remain. `git status --short` shows only pre-existing work and the documented helpers/evidence/docs; no scratch scripts or secrets were introduced into the plugin. Live fixture records and private credentials are intentionally retained for Tester. No deletion of pre-existing data is in scope.
