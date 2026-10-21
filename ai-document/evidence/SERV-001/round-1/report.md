# SERV-001 development and technical self-review — round 1

Date: 2026-10-21. Contract revision: 2.
Owner/implementer/reviewer: Codex / Developer.
Review mode: SELF_REVIEWED_DEVELOPMENT, intentionally non-independent.
Approval: Tester accepted VEH-001, requested further code and explicitly approved G-06 plus implementation: "Duyệt G-06 và triển khai SERV-001".

Technical decision: SELF_REVIEWED_DEVELOPMENT PASS. Feature/UAT status: AWAITING_MANUAL_ACCEPTANCE; Next actor Tester after LocalWP restoration/read-only readiness verification. No manual acceptance.

## Result and scope

Implemented complete creator/manager service draft workflow, required active vehicle/private customer/type and local date/reading at finalization, immutable historical customer identity and type snapshots, optional exact original-currency costs, manager-only reasoned finalized corrections, and full existing repository before/after audit. Draft ownership restrictions apply to list, ID and direct repository reads/writes. Customer contact fields never enter the service snapshot. Actor comes from the authenticated request, not a posted technician ID.

Service fields are validated as closed exact tuples; complete stored/merged maps are revalidated on reads and partial updates. Finalized vehicle/customer identity cannot be silently reassigned. Type changes require active selection and a fresh historical label; unchanged historical labels survive rename/deactivation. Archived vehicles reject draft changes/finalization but preserve finalized reads and reasoned manager corrections.

Private taxonomy management uses native taxonomy registration with public/REST/native mutation UI disabled. Versioned term/termmeta writes use the same pinned coordinator lock as service finalization, verify transactional table engines, validate the full chained audit and invalidate term caches after commit. No type defaults or automatic live data migration are added. Catalog bound is 1000 types.

Chronology validates both neighbours with exact canonical millimetres, orders by service date then stable descending ID, and places baseline after same-day services. Manager reason permits attributed exceptions. Current odometer is an authoritative computed read model under the existing lock, avoiding a stale persisted duplicate. Backdating/corrections recompute deterministically. Supported service history replaces the global baseline lock with vehicle-specific checks; unsupported history remains locked. Structural missing/duplicate service projections and malformed canonical values fail closed. Per-vehicle history safety bound is 10000; no silent truncation.

Native Services/Service types screens provide draft/finalize/correction actions, fixed notices, 50-row filtering/pagination and keyboard-scrollable regions. Action/object nonces, POST/capability checks, UUID/version/scalar validation and Settings unit/currency context protect handlers. Distance uses global Settings and no-drift historical conversion; costs retain original currency and precision. A final detail refinement formats cost in the current locale and labels unknown cost explicitly. Service-only Sass preserves native controls while constraining narrow layouts and long audit text. Existing admin asset allowlist now includes the exact two new hooks.

## Actual verification

- Full PHPUnit: 110 tests / 476 assertions PASS; unit-closing.txt. The pre-existing intentional capability rollback diagnostic is expected by its negative test.
- Full PHPCS/PHPStan PASS; lint-current.txt (complete current source). Latest localized-cost markup additionally passed targeted PHPCS and PHP syntax; cost-display-lint.txt.
- Node 24.21.0 production build PASS; build-final.txt. Generated admin CSS SHA-256: 32aa67f878a39d2290184b3149e387fada768417f8c4d7df7008f1802abc3eed. Existing customer live fixture pin remains unchanged; only its disposable path allows this exact asset.
- Node 24.21.0 workflow suite: 22/22 PASS; workflow.txt.
- Closing isolated service matrix: WP 6.4.3 and 6.7.2 / MariaDB 10.11.14, copied WP 7.1.3 / isolated MySQL 8.4.0; closing outputs retained separately from earlier runs. Domain, authenticated HTTP, concurrency, rollback and negative integrity cases pass; all three closing commands exited 0 after cleanup.
- Shared regressions: full VEH-001 and CUST-001 WP 6.7.2 smoke plus provisioning regression PASS, exit 0; vehicle-regression.txt and customer-regression.txt. The vehicle runner used SIGKILL during its owned DB cleanup after graceful shutdown timed out; it verified absence and exited 0. No application/test assertion failed in that closing regression.
- Local preparation is read-only: existing manager ID 2, technician ID 3 and subscriber ID 4 capabilities, active vehicle 69/private Customer 66, unknown baseline, km/USD/scale 2, empty type catalog; local-prerequisites.json. T01 creates a type through the feature under test; no backend seed prerequisite is hidden. No active-site user/data/Settings/fixture write or password change occurred.

PHP CLI/integration runtime was 8.3.6. PHP 8.1/minimum runtime, LocalWP web PHP, browser rendering, RTL, keyboard/screen-reader interactions and service manual acceptance are NOT VERIFIED by Developer. Full G-08 performance and new DATA-001 V1–V5 runs are not claimed. Current history/list hydration uses bounded repository reads; large-history performance remains a material follow-up rather than a claimed benchmark pass. Existing shared storage recovery exclusions remain historical, not silently resolved here.

## Meaningful negative cases

- Other technician draft read/list/write blocked, including direct repository mutation.
- Technician finalized correction blocked even with reason; manager requires nonempty reason, stale version cannot overwrite.
- Incomplete/invalid/inactive-type/customer finalization blocked; direct transition cannot supply missing authoritative snapshots. Archived vehicle draft edits fail; finalized corrections preserve archive state.
- Backdated insertion cannot replace a later current reading; same-day ties use ID. Moving latest earlier recomputes current. Vehicle-specific baseline edits check neighbours and do not lock unrelated vehicles.
- Real subprocess double-finalization has one winner and one transition audit. Type-deactivation/finalization race has only coherent serialized outcomes.
- Injected SQL failure rolls back service/audit/projections. Missing projection, corrupt type audit chain and forged stored canonical reading are rejected without producing a false current reading.
- Real HTTP draft create/replay, finalize, private foreign draft ID, pagination, manager corrections/stale conflict, Settings context and private type management pass. Valid technician-session nonce cannot authorize type writes; subscriber/anonymous cannot read private data.

## Earlier failures and corrections

Initial unit/runtime checks found missing declared service exposure policies and bootstrap adapters for the added init callback/taxonomy. These were fixed in production schema and the existing bootstrap/Loader verification adapters; no production fallback was introduced. Formatting/static issues were corrected. An overbroad test expectation edit was narrowed back to its intended plugin-idempotency test. The first HTTP pagination assertion looked for notes that the table intentionally does not render; it now checks the actual one-row second page. Earlier failing outputs are retained under initial/numbered filenames; only closing results are PASS claims.

## LocalWP handoff availability

The earlier read-only domain/account check succeeded, but the final live HTTPS login/asset checks returned HTTP 502. Bypassing proxy settings produced the same result. The Local router remains running; site nginx/PHP/MySQL processes are not running. The stopped site cannot be manually tested or have served CSS verified. No cause is inferred, no site data/configuration was changed, and no independent LocalWP services were force-started. Tester was asked to enable VeLog with LocalWP Start site; Developer retains prerequisite/readiness verification until HTTPS login and CSS match succeed. See local-asset.json.

## Review and cleanup

Reviewed service schema/field exposure, complete-field revalidation, creator/manager permissions, direct repository write guards with exact record identity, snapshot/audit immutability, pinned taxonomy transaction/cache boundaries, chronology/current-reading consistency, nonce/context/version/replay handling, escaping and native/scoped presentation. No independent reviewer or browser observation is claimed. Exact current source/generated hashes and owned runtime cleanup are recorded separately. Previous dirty work is preserved. No commit, push, deploy, release, external message or active-site fixture apply.

Cleanup verification: 13 owned disposable run directories and all associated DB/PHP processes are absent; cleanup.txt. Current source/generated hashes are recorded in source-sha256.txt.

Current parent/UAT, accepted VEH-001 records, checklist/focus and prompts pass consistency verification; handoff-check.json. git diff --check passes and git-status.txt records the preserved dirty workspace.

## LocalWP restored and UAT handoff — 2026-10-21

User explicitly reported "Đã bật lại site VeLog". Read-only HTTPS login and served admin CSS return HTTP 200; CSS exactly matches the current production hash. Existing manager 2/technician 3/subscriber 4 permissions, active vehicle 69/current active private Customer 66, unknown baseline, km/USD/scale 2 and empty type catalog were reverified. See local-restored-http.json and local-restored-prerequisites.json. No data/Settings/account/fixture write or credential reset occurred. Earlier 502 evidence is retained as history and is resolved. Parent/UAT/checklist/README now AWAITING_MANUAL_ACCEPTANCE with Next actor Tester, starting T01. No manual service case or feature DONE is inferred.
