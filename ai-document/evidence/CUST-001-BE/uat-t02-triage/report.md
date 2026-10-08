# CUST-001 — T02 failure triage

Date: 2026-10-08
Owner: Backend Architect
Status: CHANGES_REQUESTED
Implementation: Not started; proposed correction awaits explicit approval.

## Tester report

- T01: PASS (user reported Done).
- T02: FAIL at customer creation: entering data and clicking Create Customer produces no visible result in admin. The edit/persistence remainder of T02 was not performed.
- No other UAT case is marked passed. Exact test URL, submitted values, and post-submit redirect are not yet provided.

## Confirmed local findings

- LocalWP site mapping identifies velog.local, PHP 8.2.29, MySQL 8.4.0.
- Read-only database probe SELECT VERSION(): exit 0, 8.4.0.
- Read-only database probe SELECT @@in_transaction: exit 1, ERROR 1193 (HY000), Unknown system variable 'in_transaction'. No records, settings, or schema were changed.
- RecordRepository::create invokes WriteCoordinator::check_environment before writing. That method executes the failing query and returns storage_unavailable on error. Valid create requests on this database therefore fail the pre-write check.
- CustomerPage::redirect permits storage_unavailable and forbidden, but render_notice has no messages for those codes. The resulting page can show no status notice after rejection.
- The current admin-post handler is registered through AdminMenu; no frontend JavaScript intercepts this form in the reviewed source.
- Retained PHP logs end with older September failures and do not establish a new fatal error for this UAT attempt.
- These findings establish a local compatibility defect and missing feedback. Correlation to the user's exact submitted request awaits the post-submit URL; no browser test or customer creation was performed by the agent.

## Proposed bounded correction

1. Replace the unsupported transaction-state probe with a verified MySQL/MariaDB-compatible check. Preserve rejection of active/nested transactions and fail closed when state cannot be determined. Do not bypass the storage safety check or alter database engine settings.
2. Render safe, translated and escaped error notices for every permitted redirect code, including storage_unavailable and forbidden, without exposing SQL, credentials or customer data.
3. Add regression coverage for transaction-state detection (idle/active/unknown), notice coverage, and valid create on supported database engines using isolated tests; run relevant existing backend checks.
4. Return T02 plus create/update/failure regressions to Tester. Manual acceptance and DONE remain exclusively the user's decision.

Frontend approval remains code-only; the new finding belongs to Backend Architect. No CSS changes are proposed. No processes or scratch resources were created by this investigation.
