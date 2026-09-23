# Independent review — CORE-003 Round 2

Reviewer: same Codex Architect conversation of 2026-09-23; no application/test implementation contributions. Implementer: Antigravity Builder as declared in task Round 2 and verification.md. Working tree was already dirty; received documents and source hashes are retained alongside this report. No acceptance issued.

## Actual checks

- composer run lint: exit 2, four PHPCS errors in the new CapabilitiesTest (lines 18–20). Full pipeline stops before PHPStan.
- composer run phpstan, independently invoked: exit 0, no errors (17 source files).
- composer run test: exit 0, 39 tests / 165 assertions / 1 incomplete. CapabilitiesTest only calls markTestIncomplete; it supplies no lifecycle mock or test coverage.
- npm run test:workflow: exit 1, 17 tests / 15 pass / 2 fail. Release fixture checks are blocked by the same PHPCS errors. This does not establish a separate release implementation defect.
- bash -n on product-smoke.sh and product-smoke-controls.sh: exit 0 each.
- git diff --check: exit 2, trailing whitespace in scripts/progress-data.mjs:37,42.
- Actual AccessPolicy with minimal trusted actor stub: read_records on service with only type => ALLOW; draft/author=5/vehicle_visible=false => ALLOW; invalid state/string author/visible=true => ALLOW. All violate the approved service-context contract. Other previously reported action binding/contact grants were improved; this is partial correction, not closure of F-001.
- Unchanged fixture assertion helper with forced false input: counter 1, exit 1. The original counter bug is fixed; fixture coverage is still incomplete.
- Mocked storage failure via persistence-probe.php: install_return=true, persisted_final_cap=false, schema=1. The actual install method accepts a partial role write because it compares stored bytes only with the old snapshot. This is a mocked fault demonstration, not real WordPress evidence.
- Mocked inner commands against unchanged product-smoke-controls.sh: each negative inner command reports unrelated failure and exits 7; normal inner commands exit 0. Outer reports Controls passed and exits 0 without checking reason or resource absence. Temp executable was removed by TemporaryDirectory cleanup; no database/HTTP runner was launched.
- Dashboard probes: explicit Status: READY followed on the same line by return READY_FOR_REVIEW produces no issue for a READY_FOR_REVIEW task; READY_FOR_REVIEWING and arbitrary mention also produce no issue. parseChecklist ignores Next actor and readProgress interprets Exact next action prose as an actor, generating a false conflict.

## Evidence limitations

Round 2 commands.log contains only syntax, summarized PHPUnit and workflow output, with no raw lint or pinned WP smoke/control invocation results, versions, statuses, timing or cleanup artifacts. verification.md mostly describes code edits in its Actual result column. architecture.md remains unchanged. README and WF-003 current header are stale. Do not treat these descriptions as executed verification.

Real WP 6.4.3/6.7.2, runtime rollback, HTTP actor/endpoint matrix, safe parallel cleanup and PHP 8.1 remain NOT VERIFIED independently. Runner still lacks ownership/reaping and real fault-path controls, so it was not executed. No active WordPress bootstrap/database was used. Local core files were read only to check option/cache semantics.

## Verdict

F-001–F-006 remain OPEN with partial improvements as recorded in task Review Round 2. AC1 FAIL; AC2 NOT VERIFIED; AC3 FAIL; AC4 FAIL. CORE-003 CHANGES_REQUESTED. Related WF-003 AC2/AC3 defects remain and receive their own correction handoff. Existing CORE-001/002 historical acceptance is unchanged.
