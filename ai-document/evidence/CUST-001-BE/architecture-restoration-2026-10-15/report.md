# CUST-001 architecture restoration — 2026-10-15

## Authority and result

The Product Owner explicitly approved restoring the original architecture after discussing operational customer records versus login identities. G-10 supersedes G-09 and the CUST-002 implementation/adapter approvals. This is a source restoration, not a live-data rollback or functional acceptance.

Thirteen tracked PHP/test files match the inspected pre-native implementation at Git HEAD `ec16d25` byte for byte; see restored-sha256.txt. Twenty-two untracked native-only runtime/fixture files were removed after a complete backup of tracked changes and untracked files. The backup is outside the plugin at `/home/ecommercelife/.codex/backups/velog/customer-native-reversal-20261015T031349Z` (tracked.patch and untracked.tar.gz). Historical CUST-002 documents/evidence remain in the repository, explicitly retired. No unrelated tracked source or production assets were changed. The existing PHPCS custom contact capability declaration is retained.

## Restored contract

- Private `mf_velog_customer` record IDs; DATA-001 authoritative versioned storage, audit and coordination.
- Required name; optional phone/email; duplicate contact values permitted.
- Active/archived operational states and G-03 active-vehicle archive guard.
- Staff accounts/authentication use WordPress; customer records do not create native users or implement separate authentication.
- No invitation/reset/authentication hooks, native customer role, migration page, read-only legacy freeze, or native-user vehicle schema marker.
- Native WordPress admin forms and existing presentation assets retained. Project-local admin UI skill/design system and LocalWP documentation retained.

## Verification

| Command / check | Actual result |
| --- | --- |
| composer run test | PASS: 93 tests, 337 assertions; unit.txt. |
| Targeted PHPCS on restored customer classes, admin pages, composition, roles and customer unit tests | PASS: no output, exit 0; targeted.txt. |
| Targeted PHPStan on restored customer classes, admin pages, composition and roles | PASS: no errors, exit 0; targeted-phpstan.txt. |
| CUST-001 isolated WordPress 6.4.3 fixture and authenticated HTTP regression | PASS: MariaDB 10.11.14, customer create/edit/search/stale/archive/restore/contact boundaries and HTTP nonce/notices; wp643.txt. |
| CUST-001 isolated WordPress 6.7.2 fixture and authenticated HTTP regression | PASS: same backend/HTTP assertions on MariaDB 10.11.14; wp672.txt. |
| composer run lint | FAIL: PHPCS exit 2; pre-existing VehicleIdentifier/VehicleIdentifierTest errors and shared storage formatting warnings remain. See lint.txt. |
| composer run phpstan | FAIL: exit 1; four missing array value types in VehicleIdentifier and redundant scalar check in RecordRepository. All are present in the restored HEAD baseline. See phpstan.txt. |
| Runtime reference scan | No NativeCustomer, CustomerAccountPolicy, CustomerIdentityGuard, CustomerInvitation, CustomerMigration or native-user-v1 references remain in src or active unit/bootstrap tests. |
| git diff --check | PASS after restoration/documentation updates. |

Full-project lint is not clean. No existing vehicle/storage behavior was changed to conceal these failures. Customer source restoration is complete; project-wide technical readiness and UAT preparation remain with Backend Architect.

## Environment, cleanup and limitations

PHP CLI 8.3.6; fixtures run in disposable WordPress installs on socket-only MariaDB, with owned PHP servers and cleanup traps. These are automated backend/HTTP checks, not manual UI observations. LocalWP is the documented development/manual-UAT environment, but this restoration does not connect to or alter its database. No actual LocalWP account/data prerequisites are inferred from isolated test success.

Tester T01 PASS and T02 FAIL pending retest are unchanged. CUST-001 parent/BE are IN_PROGRESS; FE/UAT remain BLOCKED pending technical readiness/preparation, Next actor Backend Architect. CUST-002 is BLOCKED/retired without acceptance. VEH-001 no longer depends on native-user migration; its own scope and gates require revalidation. No commit, deploy or release was performed.

- Cleanup verified: `/tmp/velog-product-smoke.cAO1ptsk` was removed by the fixture runner.

- Cleanup verified: `/tmp/velog-product-smoke.px7Koz83` was removed by the fixture runner.
