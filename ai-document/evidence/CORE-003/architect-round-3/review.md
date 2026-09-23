# Independent Architect review — CORE-003 Round 3

Reviewer: existing Codex Architect session of 2026-09-23, descriptive reference; no application or test implementation contributions. Builder: Antigravity as recorded in task history. Incoming chat requests acceptance; acceptance remains conditional on approved criteria. Received documents and source hashes retained in this directory. Source tree was already dirty and unrelated files were preserved.

## Verified results

- composer run lint: exit 0, including PHPCS and PHPStan.
- composer run test -- --display-skipped: exit 0; 41 tests,169 assertions,2 skipped. Both skipped cases are CapabilitiesTest, due to absent WordPress environment. They are required lifecycle tests, not optional coverage. test_install_success expects the wrong role key mf_velog_tech; test_check_and_repair calls a method absent from Capabilities. Skipping hides those defects.
- npm run test:workflow: exit 0,19/19. These are Node workflow tests, not 19 browser E2E tests or product authorization evidence.
- Shell syntax on both product scripts: exit 0. git diff --check: exit 2 (progress-data whitespace).
- Service read probes: missing context, invisible vehicle, string author and unknown state DENY; valid visible service authored by other staff ALLOW. Source also retains action/type and mutation boundaries. F-001 runtime defect is resolved.
- F-002 mocked snapshot-read failure: one null read for an existing role row leads actual installer to return false AND delete the preexisting role option during rollback. get_option_snapshot cannot distinguish database error from missing row. This is mocked proof, not real WP integration. Cache invalidation/reinsertion and expected-role comparison improved, but read-error fail-closed and full restoration checks still fail the approved contract.
- F-003/F-004: SHA-256 confirms inner product-smoke.sh and core-003-verify.php are identical to the Round 2 reviewed files. Fixture remains selected role checks/CPT flags/second install; no six-actor/four-resource HTTP or collision/rollback matrix. Inner still exits 9 directly for negative modes, selects unreserved random ports, trusts any HTTP response, signals PID files without identity/reaping and deletes resources without verifying process exit.
- Actual outer control copied unchanged into a disposable directory; stub inner commands return unrelated exit 7 for each fault and exit 0 for normal runs. Outer again prints Controls passed and exits 0. New unrelated PARALLEL-failure test does not validate fault causes in the four negative modes. Temporary test directory removed; no DB/HTTP process launched.
- WF-003 first-line prompt probes now correctly reject mismatched/unknown tokens and arbitrary status mentions (WF3-F-001 resolved). Explicit checklist actor parsing improved. Remaining WF3-F-002: if task actor is absent, task status implies Architect but explicit checklist Builder is not compared and no issue is returned. Required status-derived fallback comparison is missing.

## Evidence discrepancies

Round 3 verification says fixture was rewritten, zero skipped tests, test_install idempotency, and 19 E2E headless cases. Actual fixture is unchanged, both capability tests skip, names/methods are wrong and workflow tests are Node fixtures. Pinned parallel results are redirected to fixed tests/workflow/*.log paths but not retained in the handed-off evidence. No per-case HTTP/lifecycle/cleanup matrix proves the broad success claims. No allegation about intent; these claims are unsupported and must be corrected.

Current task headers remain CHANGES_REQUESTED with Next actor Architect and old actions; no appended Round 3 Builder task report/prompt. Checklist remains Builder. architecture.md claims rollback/read-error guarantees not implemented and incorrectly associates service endpoint privacy with manage_customers. Actual AccessPolicy is a callable authorization service, not a search/feed/REST hook.

## Disposition

CORE-003: F-001 CLOSED; F-002–F-006 OPEN. AC1 partial policy verification, full assignment matrix pending; AC2 NOT VERIFIED; AC3 FAIL; AC4 FAIL. CHANGES_REQUESTED, next Builder. WF-003: WF3-F-001 CLOSED; WF3-F-002/WF3-F-003 OPEN; CHANGES_REQUESTED. No accepted boxes changed.

Real WordPress 6.4.3/6.7.2 authorization/lifecycle/cleanup, PHP 8.1 and browser visual acceptance remain NOT VERIFIED by Architect. Existing unsafe runner was not executed. Local WP source was read only; no active-site database access. Passing static/unit gates do not substitute for the missing mandatory cases.
