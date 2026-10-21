# VEH-001 correction — round 2 / contract revision 3

Date: 2026-10-21.
Owner, implementer and reviewer: Codex / Developer.
Review mode: SELF_REVIEWED_DEVELOPMENT, intentionally non-independent.
Technical result: PASS. Parent/FE/UAT: AWAITING_MANUAL_ACCEPTANCE, Next actor Tester; BE DONE is technical only.
Approval: Product Owner requested Year dropdown and global odometer settings; approved the proposed implementation with "ok sửa".

## Resulting behavior and review

Year is an optional native WordPress select, ordered from site-current-year + 1 down to 1886. Backend year validation still rejects forged values. Vehicle screens no longer offer per-vehicle preferred or initial-reading unit selectors. Form input, list and technician detail use ShopSettings.distance_unit. Legacy preference metadata remains readable for compatibility and is omitted by new form writes; it does not govern input or rendering.

Original baseline tuples remain provenance. Presentation converts canonical millimetres to the system unit using exact decimal arithmetic at three places. Submitting the unchanged rounded display preserves the exact original tuple; a date-only correction retains exact distance. Actual reading changes are parsed using the server settings snapshot and retain the existing canonical decrease/reason and finalized-history guards. This includes zero, unknown, localized decimal input and maximum historical mile readings whose kilometre display exceeds the new-entry range. No float conversion or historical migration is used.

An actor/session unit-context nonce rejects forms opened before a system-unit change. That path discards attempted values rather than relabelling an old value in a new unit. Failed-input values also carry their originating unit and are ignored if settings change before redisplay. Missing/corrupt Settings disable editing with fixed recovery guidance. The reading list never falls back to per-record units. Existing POST capabilities, action/object nonces, strict UUID/version/scalar validation, private customer projections, audit/transaction/uniqueness checks and escaped fixed notices remain intact.

Reviewed five changed correction files: VehiclePage.php, VehicleListTable.php, OdometerPolicy.php, VehicleCoreTest.php and veh-001-endpoint.sh. No dependency/CSS/JS changes. Production rebuild retains the prior admin CSS SHA-256 13a9bc27e6fd0d14795c64e43984eb15eea96b31eef90640ac4ca8ac6703b8f4. Contract amendment, G-11, architecture, tasks, checklist, README and Vietnamese UAT describe the new rule. Earlier revision 2 technical evidence remains historical.

## Verification

- Full composer lint: PHPCS and PHPStan PASS; lint-final.txt. Initial lint warning (long signature) fixed; initial output retained in lint.txt.
- Full PHPUnit: 103 tests / 422 assertions PASS; unit.txt. Existing intentional capability-rollback negative diagnostic is expected, not a failing assertion.
- Node 24.21.0 production build PASS; build.txt. Four generated outputs rebuilt; prior CSS hash retained.
- Node 24.21.0 workflow tests: 22/22 PASS; workflow.txt.
- Isolated WP 6.4.3 and 6.7.2 / MariaDB 10.11.14 vehicle domain, authenticated HTTP, concurrency, rollback/SQL-failure, access controls and fixture preparation regressions PASS; wp643-verified.txt and wp672-verified.txt.
- Target copied WP 7.1.3 / isolated MySQL 8.4.0: same complete matrix PASS, exit 0; wp713-mysql84-verified.txt. All three closing smoke commands exited 0 after cleanup.
- Added authenticated HTTP coverage: native dropdown, forged year, no unit selectors, stale system-unit context rejection, forged legacy-unit override ignored, converted display/no-drift update, missing/corrupt Settings fail closed. Unit coverage includes locale conversion, zero, maximum-mile preservation and date-only changes.
- Current task/checklist/prompt consistency PASS for all four vehicle tasks and current focus; handoff-check.json. Existing dashboard diagnostics for historical CORE-004 Next actor None and the non-task contract document remain outside this correction.
- Local site read-only check: distance_unit km; Customers 66/67 active and 68 archived; local-readonly.json. No live data/Settings/account/fixture writes or reseeding performed during this correction.

PHP CLI/integration version 8.3.6; minimum PHP 8.1 and LocalWP web PHP not independently verified. No browser/manual PASS or feature acceptance is claimed. Shared storage and frontend assets are unchanged in this correction; prior round-1 customer/DATA integration evidence remains historical and was not rerun.

## Failed attempts and resolution

The first unit command launch raced creation of the evidence directory and did not start; it was rerun successfully after directory creation. First isolated HTTP run reached all domain cases but had no shop configuration, so the new fail-closed UI correctly omitted the form. The reusable HTTP fixture now explicitly configures its disposable shop. Subsequent initial matrix runs exposed a test-helper error: require_configured() returns only public configured values, not record_version. The helper now uses get_settings() for versioned updates. Earlier runtime attempts remain in wp672.txt, wp672-final.txt, wp643.txt and wp713-mysql84.txt. These failures are not represented as PASS. No production failure bypass was added.

## Acceptance and cleanup

Tester feedback was triaged as two approved revision-3 changes, not a blanket case verdict or acceptance. Updated UAT requires T04, T07/T09 and relevant regressions before full T01–T15 acceptance. Tester alone records PASS/FAIL/BLOCKED and DONE. Owned run directory/process cleanup and exact source hashes are recorded separately. Private existing backup/manifest/credentials remain intact. No external message, commit, push, deploy or release.
