# Local development and test environment

Environment information supplied by the Product Owner on 2026-10-15.

- Platform: LocalWP.
- Site: VeLog local development and test site.
- WordPress Admin: https://velog.local/wp-admin/
- phpMyAdmin: http://localhost:10078/?username=root&db=local
- Database selected by the phpMyAdmin URL: `local`.
- Local WordPress credentials: stored in the Git-ignored `.env.velog-local` file in the plugin root. Do not copy credentials into tracked documentation, evidence, or release artifacts.

These addresses describe the current working site. They do not identify a disposable integration fixture. Readiness, connectivity, authentication, installed versions, and test results have not been verified by recording this information.

Source inspection during CUST-002 planning found `wp-includes/version.php` reporting WordPress 7.1.3 and no `wp-content/db.php` or `wp-content/object-cache.php` file at the inspected paths. This does not verify the running site's PHP/database versions, active integrations or authentication. Completed disposable native API feasibility probes cover WordPress 6.4.3 and 6.7.2; current LocalWP-version compatibility is NOT VERIFIED.

Use this LocalWP site as the reference environment for development and Tester / Product Owner manual UAT. Automated destructive tests and migrations still require the owning task's explicit scope and approved data preparation. This note does not authorize a database migration or alter task readiness.


## Read-only readiness inspection — 2026-10-15

- HTTPS wp-login endpoint returned HTTP 200. No browser/manual verification was performed.
- Read-only WP-CLI inventory loaded WordPress 7.1.3 with VeLog active; database SELECT VERSION() returned MySQL 8.4.0.
- Inspection used system PHP CLI 8.3.6 with the LocalWP MySQL socket configured per command. LocalWP service configuration declares web PHP 8.2.29; the web PHP runtime is not independently verified.
- LocalWP's site identifier is JGoPhkg1p; its MySQL socket is `/home/ecommercelife/.config/Local/run/JGoPhkg1p/mysql/mysqld.sock`. The plain system WP-CLI defaults to `/run/mysqld/mysqld.sock` and cannot reach this site without selecting the LocalWP socket. Do not change the site's wp-config.php to accommodate the system CLI.
- Inventory: one private Customer, zero named UAT pagination fixtures, zero accounts with the dedicated manager/technician roles, and zero active-vehicle link candidates. These are prerequisite counts, not schema validity, authentication or manual test results.
- The reusable `tests/fixtures/cust-001-uat-prepare.php` helper is read-only. Despite its filename it never provisions accounts/data. Further live fixture setup requires an explicit approved scope and remains Backend Architect's responsibility.
- Current source was also exercised on copied core 7.1.3 in an isolated database. That result does not verify LocalWP's web PHP/database combination or its browser behavior.

## Authorized UAT preparation — 2026-10-15

The Product Owner authorized the exact CUST-001-UAT fixture scope. Setup and read-only verification completed after isolated WordPress 7.1.3/MySQL 8.4 checks and a private database export. Accounts: `velog_uat_manager` and `velog_uat_technician`; passwords only in `/home/ecommercelife/.codex/private/velog/cust001-uat-credentials.json` (0600; parent 0700; outside public/repository). Do not publish/copy credentials into evidence.

Pagination customers: IDs 13–63, query `UAT Customer ` returns 50+1. Linked customer 64 and active vehicle 65 are reserved for T08. Existing user/domain data stayed unchanged; no WordPress mail attempts. Fixtures remain for manual UAT; never reset/adopt/delete records automatically. See `evidence/CUST-001-UAT/preparation-2026-10-15/report.md` and `tasks/CUST-001-UAT-manual-test.md`. Prepared data does not establish browser/manual acceptance. The earlier inventory is historical. RTL locale and screen-reader availability still require Tester verification.
