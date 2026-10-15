# CUST-001 backend readiness — round 3

Date: 2026-10-15
Owner/reviewer: Codex / Backend Architect
Review: SELF_REVIEWED_BACKEND (intentionally non-independent).
Backend checkpoint: DONE; parent feature is not accepted.
Next workstream: CUST-001-FE, Frontend Developer, CHANGES_REQUESTED.

## Problem and resulting behavior

G-10 restored independent private customer records. Remaining static failures were repaired without changing vehicle identifier behavior. Review then found contact search terms entering GET URLs and per-row customer hydration, contradicting the existing customer privacy/performance contract.

Customer search now posts exact validated criteria with an action-specific nonce and both manager/contact capabilities. Redirects contain only a random UUID reference to a non-autoloaded transient scoped to the actor for 15 minutes. Pagination retains the reference. Legacy GET terms, malformed/foreign/expired references and invalid pages fail safely; an expired search never silently broadens to the full list. Clear search is explicit.

RecordRepository::get_many() accepts at most 50 unique positive IDs, reads only private posts of the exact registered type, retains caller order, and reuses the existing repository batch decoder. Customer pages use the same five database reads for one or 50 rows. Missing/duplicate authoritative data and SQL failures fail closed; object-level policy and contact redaction remain in the repository. Shared batch reads additionally reject unsupported envelope schema versions.

Malformed UTF-8 is rejected for customer fields/search rather than treating a failed regular expression as clean text. Optional/non-unique contacts and active/archived states remain unchanged. Customer creation does not create WordPress users.

PHP markup uses native regular-text inputs, presentation form tables, column scopes, checkbox/filter labels, and core pagination button/current classes. No CSS/JS or generated assets were edited by Backend Architect. Current inherited CSS requires FE correction under the newly adopted design system; prior Round 2 approval is historical, not current approval of those overrides.

## Audit recovery regression found during verification

DATA-001 V4 initially failed seven reconciliation cases. The inspected HEAD already wrote __state into save snapshots while create snapshots and both reconciliation routines compared field-only shapes. This was a pre-existing CUST-001 integration defect, not a batch-query error.

Reconciliation now reads old field-only snapshots as the schema's initial state and preserves every explicit state. Chain comparisons and final-envelope comparisons include state. Persisted history is never rewritten; exact actor/request/version/change comparisons remain. A state-changing reconcile_save without an explicit state contract continues to refuse a mismatching transition rather than guessing intent. Existing V4 field-save recovery and customer create recovery after edit/archive/restore are exercised, including deliberately inconsistent audit state.

One fixture iteration attempted fault injection via protected metadata APIs, which correctly sanitized the write; the fixture was corrected to use direct SQL only in its disposable test site and restore the injected value. No production bypass was added. Earlier HTTP fixture assumptions about first-page ordering and post_title were also corrected: customer identity is held in the envelope/projections, not public post titles. All failed attempts are retained as diagnostic history.

## Bounded change map and source review

- CustomerPage/CustomerListTable: scoped search handler, safe server rendering and semantic native controls; existing mutation services, nonces and version guards retained.
- CustomerQuery/CustomerService: bounded hydration and strict UTF-8/page validation.
- RecordRepository: reusable bounded reader and existing decoder extraction, read-only audit comparison repair, redundant scalar-check removal after string-only projection derivation. Values/IDs are prepared or strictly bounded integers; table identifiers remain trusted WordPress table properties.
- VehicleIdentifier and VehicleIdentifierTest: PHPDoc/type information, formatting, and equivalent full ternary only. RecordSchema: formatting only. No vehicle feature behavior was implemented.
- Reusable customer integration/HTTP/unit coverage and read-only UAT prerequisite inventory live only under tests/. Production has no fixture-only execution path.
- Product runner supports isolated copies of allowlisted LocalWP core 7.1.3, never its config/content/database, and skips installation email. All new test writes use disposable databases.

## Verification

| Actual command / environment | Exit and result | Evidence under runs/ |
| --- | --- | --- |
| composer run lint | 0; full PHPCS and PHPStan pass | lint-verified.txt |
| composer run test | 0; 93 tests, 341 assertions | unit-verified.txt |
| Node 24.21.0 --test tests/workflow/*.test.mjs | 0; 20/20 pass | workflow-final.txt |
| product-smoke CUST-001 WP 6.4.3, MariaDB 10.11.14 | 0; backend and HTTP cases pass | wp643-complete.txt |
| product-smoke CUST-001 WP 6.7.2, MariaDB 10.11.14 | 0; serial recovery run passes | wp672-serial.txt |
| product-smoke CUST-001 WP 7.1.3 copied core, MariaDB 10.11.14 | 0; serial recovery run passes | wp713-serial.txt |
| product-smoke CUST-001 WP 7.1.3 copied core, MySQL 8.4.0 | 0; backend and HTTP cases pass | wp713-mysql84.txt |
| DATA-001 run.php --wp-version=6.7.2 | 0; V1–V4 assertions, negative control, V5 microdataset and cleanup pass; full G-08 remains NOT VERIFIED | data001-serial-summary.txt; data001-serial/run.txt |
| Read-only LocalWP prerequisite inventory | 0; candidate counts only, no provisioning | localwp-prerequisites-final.json |
| PHP syntax, shell syntax, fixture PHPCS, git diff --check | 0 | source verification and final handoff check |

PHP integration runtime was 8.3.6. The normal DATA-001 negative control intentionally emits failure text; its expected-negative result is verified by the harness. Earlier parallel runs had HTTP/shutdown timeouts during concurrent database initialization/copying. These are retained as FAIL; only the actual successful serial runs above close the gates. The final literal gettext wrappers for query errors were covered by the final lint/unit run; manual translation/RTL verification still belongs to Tester. No main-worktree frontend build/output was changed; tooling tests build only their owned disposable fixtures.

## Manual environment and remaining work

Read-only LocalWP inventory: HTTPS login endpoint 200, WordPress 7.1.3 with VeLog active, MySQL 8.4.0. CLI is PHP 8.3.6; LocalWP declares web PHP 8.2.29, not independently verified through HTTP. Inventory found one private Customer, zero dedicated manager/technician accounts, zero named pagination fixtures and zero active-link candidates. No live accounts/records were created or changed. tests/fixtures/cust-001-uat-prepare.php is read-only despite its name.

Backend Architect owns future preparation: two dedicated staff accounts, 51 synthetic pagination records and a linked-vehicle fixture, without overwriting existing records or sending email. The precise proposed scope is in CUST-001-UAT and still requires explicit live-write authorization after presentation review.

Frontend Developer must correct customer styles under the local admin UI skill. Tester retains all manual UI, color-scheme, keyboard, responsive, RTL, screen-reader and acceptance decisions. T01 PASS and T02 FAIL pending retest remain unchanged. No customer portal, native customer account integration, active-site migration, commit, deploy or release is included.

## Known inherited storage limits

The DATA-001 harness does not prove live-handle COMMIT failure, coordinator 1213 deadlock or persistent object-cache compatibility. Its 50-record microdataset is not the full 28,000-record G-08 benchmark. Those previously documented limits remain NOT VERIFIED; do not infer acceptance from this readiness round.

## Cleanup and artifact boundary

All 19 owned isolated-site/database directories were confirmed absent; no owned database or PHP server process remains. See cleanup.txt. Raw readiness logs were retained with disposable credential values redacted. Only approved reusable tests, task/docs and required evidence remain in the plugin. The native-user reversal backup outside the plugin remains intentionally preserved; it is not a scratch artifact. Main frontend sources/assets are unchanged.
