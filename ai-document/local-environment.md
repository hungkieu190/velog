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

## VEH-001 UAT preparation — 2026-10-21

The Product Owner explicitly authorized the reviewed live fixture package with "OK làm luôn". Private database backup completed before apply; authorized helper apply and independent verify passed. Existing staff velog_uat_manager ID 2 and velog_uat_technician ID 3 retain their roles/passwords and old private credential file. The new denied-role subscriber velog_vehicle_uat_denied is user ID 4; its password is only in /home/ecommercelife/.codex/private/velog/veh001-uat-manifest.json.credentials.json (0600). Private manifest and lock remain outside public/repository.

New private customers: A 66, B 67, Archived 68. New active pagination vehicles: 69–119, VIN prefix VELOG-VEH-UAT-PAGE, linked to A 66, km preference and unknown baseline. Query pagination is 50+1. Existing users/roles/passwords/usermeta and domain posts/meta were compared before/after and preserved; no WordPress mail attempts occurred. Totals: 4 users, 56 customers, 52 vehicles. Do not reset/reseed after manual UAT begins.

WordPress 7.1.3, MySQL 8.4.0, PHP CLI 8.3.6. Login endpoint HTTP 200 and served generated CSS hash verified; web PHP and browser behavior are not independently verified. See tasks/VEH-001-UAT-manual-test.md and evidence/VEH-001-UAT/preparation-2026-10-21/report.md. Parent/FE/UAT are AWAITING_MANUAL_ACCEPTANCE, Next actor Tester; no manual PASS is inferred.

## SERV-001 technical handoff — 2026-10-21

VEH-001 is now accepted DONE by explicit Tester confirmation. SERV-001 source and isolated technical gates pass, with no active-site fixture/user/Settings writes. Read-only prerequisites succeeded for manager 2, technician 3, subscriber 4, active vehicle 69/current Customer 66, unknown baseline, km/USD/scale 2 and empty type catalog. T01 creates a type through the new feature rather than a hidden backend seed.

Final HTTPS login/asset requests returned HTTP 502, including a direct no-proxy request. Only the router process is running; site nginx/PHP/MySQL processes are stopped. No cause is inferred and no Local service was force-started. Developer owns readiness verification after Tester enables the site in LocalWP. Until login HTTP 200 and served CSS matches the current production asset, SERV-001/UAT remain BLOCKED. See evidence/SERV-001/round-1/local-asset.json and report.md.

## SERV-001 environment restored — 2026-10-21

The user reported restarting VeLog. Developer reverified login and CSS HTTP 200 with exact production CSS hash, and unchanged UAT account/data/Settings prerequisites; see evidence/SERV-001/round-1/local-restored-http.json and local-restored-prerequisites.json. This resolves the preceding HTTP 502 block. SERV-001/UAT are AWAITING_MANUAL_ACCEPTANCE, Next actor Tester, starting T01. No live data/credential changes or manual acceptance are claimed.
