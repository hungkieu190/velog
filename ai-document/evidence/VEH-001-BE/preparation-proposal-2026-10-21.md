# VEH-001 LocalWP UAT preparation proposal

Status: APPROVED for the exact live fixture package below by Product Owner response "OK làm luôn" on 2026-10-21. Execution and verification are recorded separately; approval is not manual feature acceptance.
Owner: Developer (Codex).
Site: https://velog.local/ at /home/ecommercelife/Local Sites/velog/app/public.

## Current read-only facts

WordPress 7.1.3, PHP CLI 8.3.6, MySQL 8.4.0. The two existing CUST-001 staff accounts remain present with manager/technician roles. The site currently has one private vehicle, so the 51-result vehicle pagination case is not prepared. Counts and roles do not establish authentication, browser behavior, RTL or screen-reader availability. No LocalWP data was written by VEH-001 implementation or inventory.

## Exact proposed writes

1. Export the current LocalWP database to a private file outside the public root and repository, mode 0600, before writes. Do not change wp-config.php or database service configuration.
2. Reuse velog_uat_manager and velog_uat_technician. Do not change their passwords, roles or existing private credential file.
3. Create three separate private customers: Vehicle UAT Owner A, Vehicle UAT Owner B, Vehicle UAT Owner Archived. The third is archived via the shared write unit; contact fields are absent.
4. Create 51 active VIN-only vehicles VELOG-VEH-UAT-PAGE-001 through VELOG-VEH-UAT-PAGE-051, linked to the new Owner A, preferred unit km, baseline unknown. These are a dedicated vehicle pagination dataset and do not alter the existing CUST-001 linked vehicle/customer.
5. Create one dedicated WordPress subscriber velog_vehicle_uat_denied for permission denial cases. This is a test actor without VeLog capabilities, not a customer account. Its random password goes only to a private mode-0600 file outside public/repository. Block WordPress mail during preparation.
6. Persist a private mode-0600 manifest with task/site/actor/batch/request identity and owned record IDs/digests. The mode-0600 lock remains beside the manifest to serialize preparation safely. Stable request IDs and one transaction preserve the customer/vehicle batch on retries. A pending subscriber remains attributable if later batch work fails; retries may resume it only with its exact batch marker, role and private password.
7. Verify exact records, 50+1 pagination, roles and private credentials. Repeated apply verifies unchanged fixtures and never resets user observations; collisions or drift fail closed. Record the new IDs in VEH-001-UAT before Tester handoff. Keep fixtures for UAT; no automatic deletion/reset.

## Ready implementation and verification

- tests/fixtures/veh-001-uat-seed.php provides read-only plan and explicit apply/verify modes with exact site/admin boundary, private-path checks, exclusive lock, stable request IDs, shared transaction, protected credentials, mail blocking, rollback and drift/collision refusal.
- tests/workflow/veh-001-uat-seed.sh exercises plan/apply/verify/reapply only in product-smoke disposable sites and compares pre-existing user/password/role and authoritative record rows before/after. It proves drift refusal without resetting test results.
- Live plan evidence: backend-round-1/local-preparation-plan.json. Disposable execution evidence: backend-round-1/uat-preparation-regression.txt. The latter must pass before any live apply.
- The helper requires a nonempty private backup for live apply. No live apply or backup export is performed under the earlier source-only implementation approval.

## Explicit exclusions

No existing account/role/password changes, customer accounts, edits to existing domain records, email delivery, migrations, commits, deployment or release. RTL locale and screen reader remain Tester-supplied tools. Any missing prerequisite is recorded BLOCKED rather than skipped.

## Execution completed

The approved package was applied and independently verified on 2026-10-21. See ../VEH-001-UAT/preparation-2026-10-21/report.md for backup, preservation evidence and actual record IDs. Parent/FE/UAT are AWAITING_MANUAL_ACCEPTANCE, Next actor Tester. No manual feature acceptance is claimed.
