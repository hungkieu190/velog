# VEH-001 authorized LocalWP UAT preparation

Date: 2026-10-21 (Asia/Bangkok).
Owner: Developer (Codex).
Authorization: Product Owner replied "OK làm luôn" to the exact fixture package in preparation-proposal-2026-10-21.md. This authorizes backup/provisioning/verification, not manual feature acceptance.

## Actual execution and result

1. Verified that current application/test source hashes match the technically reviewed snapshot and that the existing isolated UAT seed regression passed. Live plan returned writes=false and no collision/pending manifest.
2. The first wp db export attempt failed before any fixture apply: the WP-CLI shell charset probe selected /tmp/mysql.sock and its dump-option conversion rejected a tablespaces option. No database change occurred. Switched to the installed MySQL 8.4 mysqldump directly, passing a private temporary defaults file and the explicit LocalWP socket; the defaults file was removed immediately. No wp-config.php or service configuration was changed.
3. The completed single-transaction database dump is /home/ecommercelife/.codex/private/velog/veh001-preseed-20261021-100001.sql, mode 0600, outside public/repository. It is nonempty and ends with the dump completion marker. See backup.json for size/hash. The failed zero-byte attempt was removed during owned cleanup.
4. Captured before-state hashes for all three existing user rows/usermeta and all 54 existing domain post rows/meta. Ran the existing authorized helper apply against the exact site as administrator. The helper blocked mail, used stable request identities and one coordinated customer/vehicle transaction, and kept credentials/manifest private.
5. Independent helper verify passed: three Customer fixtures, 51 active Vehicle fixtures, subscriber role/batch/password verification and 50+1 vehicle pagination. Existing staff were reused without role/password changes.
6. Independent after-state comparison confirmed all pre-existing users, roles/passwords/usermeta and domain posts/meta unchanged. Totals changed only as approved: users 3 → 4, customers 53 → 56, vehicles 1 → 52. WordPress mail attempts: 0.
7. Login endpoint returned HTTP 200. Generated admin.css fetched from the LocalWP URL matches the reviewed production bytes, SHA-256 13a9bc27e6fd0d14795c64e43984eb15eea96b31eef90640ac4ca8ac6703b8f4. These are HTTP/asset checks, not browser observations.

## Prepared records and accounts

- Customer A 66: Vehicle UAT Owner A, active.
- Customer B 67: Vehicle UAT Owner B, active.
- Customer Archived 68: Vehicle UAT Owner Archived, archived.
- Vehicles 69–119: VELOG-VEH-UAT-PAGE-001 through -051, active, current customer 66, km preference, unknown baseline. Keep unchanged until pagination UAT T11.
- Existing manager 2: velog_uat_manager; existing technician 3: velog_uat_technician. Original credential file remains unchanged outside public/repository.
- New subscriber 4: velog_vehicle_uat_denied. Credentials only at /home/ecommercelife/.codex/private/velog/veh001-uat-manifest.json.credentials.json, mode 0600. No password is included in evidence or handoff text.
- Private manifest: /home/ecommercelife/.codex/private/velog/veh001-uat-manifest.json, mode 0600; retained lock also 0600, directory 0700. These and the backup are intentional retained preparation artifacts, not scratch resources.

## Manual handoff and limitations

Parent VEH-001, FE and UAT are AWAITING_MANUAL_ACCEPTANCE, Next actor Tester. BE remains a DONE technical SELF_REVIEWED_DEVELOPMENT checkpoint. UAT T01–T15 are unrun; Developer did not claim browser/manual results. Tester provides browser, actual RTL locale and screen reader; missing tools are BLOCKED for the relevant case.

T06 instructions were clarified to create a separate vehicle for Customer B 67. Customer A 66 already has the 51 pagination vehicles and cannot be used for a single-vehicle archive scenario without touching that dataset. T03–T10 use vehicles created during manual cases; preserve pagination vehicles until T11. This is documentation clarification only; no application source changed.

PHP CLI 8.3.6, WordPress 7.1.3 and MySQL 8.4.0 were observed. Web PHP and browser behavior remain Tester verification. No migrations, existing-record reset/deletion, customer login account, commit, deployment or release occurred.

## Evidence and cleanup

before.json / after.json record exact preservation hashes and counts; plan.json, apply.json and verify.json record actual helper results without passwords. mail.json records zero mail attempts. http-assets.json and backup.json record serving/backup checks. scratch wrappers, temporary defaults and the failed empty backup were removed after verification; the successful backup, manifest/credentials and lock remain private by design. See final-checks.txt for final synchronization, source/diff and cleanup checks.
