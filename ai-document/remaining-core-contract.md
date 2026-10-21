# Remaining MVP core — code-first contract, 2026-10-21

Authorization: user instructed completing all code and deferring all tests/fixes. Implement HIST-001 and REM-001 using current service/vehicle storage and native admin UI. Product rules follow previously documented proposals; G-07 business validation remains explicit debt, not a fabricated standalone approval. Existing security/retention/external-action boundaries remain.

## History

Finalized-only per-vehicle timeline, stable date/ID descending, inclusive date/type/note/historical-label filters, 50 rows per page, immutable customer/type/cost history and corrected before/after projections for operational staff. Distance display follows G-11 system units; original readings remain provenance in stored/audit data. Read one validated vehicle, batch service envelopes and actor labels; no customer/type query per row. Bounded history scan (10000 service records per vehicle, matching existing chronology limit) validates/filters before pagination and count; performance is unverified debt. No mutations on read. Add native History screen and links from Vehicles/Services.

## Reminder queue

Manager-only manual reminders: active vehicle, title 1–200, date and/or nonnegative exact distance threshold; stored active/snoozed/completed. Due means date reached OR known canonical odometer >=threshold. Future snooze suppresses until its date; completion is terminal; archived vehicle gives effective suspended state. No GET writes, cron, delivery, recurrence or generated services. Fields/version/nonce/actor/audit are authoritative. New/edited distances use Settings and retain original tuples for unchanged conversions. Completion actor/time generated server-side.

Prepared SQL applies effective-state filters before count/pagination. Introduce a derived current-odometer index maintained within the existing pinned transaction after service/vehicle writes, initialized on reminder creation. It never changes authoritative vehicle/service values or versions. Shared repository after-write hooks keep service changes, baseline changes and indexes coherent or roll back together; validate cardinality and invalidate vehicle caches after commit. No active-site backfill is run. Existing canonical records receive an index when first used by a reminder, or through later writes.

Allowed files: new Common/Service/ServiceHistoryQuery.php, Admin/ServiceHistoryPage.php; new Common/Reminder/ classes and Admin/ReminderPage.php; derived index in Common/Storage/; repository after-write hooks, schema declarations, service/vehicle guard integration, admin menu/assets/links, scoped Sass/generated output and technical/workflow/debt docs. Reuse regional arithmetic, private CPTs, coordinator and audit. No dependencies or test fixtures during code phase.

## Completion

Developer inspects source/security/transaction/query boundaries and runs only necessary PHP syntax/build checks. Functional/unit/integration/runtime/manual/performance work remains in test-debt.md. Application code completion does not mean accepted feature or verified package. MVP-001 is predominantly test/release acceptance and remains deferred; existing release tooling must continue to include runtime code without publishing or creating a release without authorization.
