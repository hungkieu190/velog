# CORE-002 Architect review — Round 3

Date: 2026-09-22. HEAD 9c4d1be plus submitted working files. Reviewer: current Codex Architect conversation, no implementation contribution. Builder supplied a descriptive Antigravity CORE-002 Round 3 reference; complete Round 1/2/3 contributors and whether this is one implementing session remain unconfirmed. User clarification requested; no fabricated identity.

## Decision

CHANGES_REQUESTED; C2-F-004 remains OPEN for two retained regression-test issues. C2-F-001–C2-F-003 remain CLOSED: all eight runtime class hashes and the generator hash match independently reviewed Round 2. Do not reopen runtime/generator work. There is no newly reproduced product implementation defect.

## Actual verification

- Targeted command: exit 0, 12 tests / 120 assertions (targeted.log).
- composer run lint: exit 0, PHPCS/PHPStan pass (lint.log).
- Full composer run test: exit 0, 33 tests / 155 assertions (full-tests.log).
- PHP 8.3.6, PHPUnit 10.5.64 observed. PHP 8.1 specifically NOT VERIFIED.
- Submitted git diff --check: **exit 2**, trailing whitespace at task line 100 (diff-check.log). Builder's reported exit 0 does not describe the received final state. This is an evidence discrepancy, not a product defect; retain failure and record post-review check separately.
- Only RegionalPrimitivesTest.php differs among runtime/test/generator inputs hashed in Round 2. New digit rejection and all money snapshot fields/pinned version assertions exist. API direction and pinned CLDR provenance docs are corrected. These parts of C2-F-004 pass inspection.
- Generator, numerical oracle, real WordPress smoke, build/release and product UI/database/manual checks were not rerun: inputs unchanged or outside scope. Reuse Round 2 generator/probe evidence and Round 1 arithmetic/catalog evidence explicitly, not as new executions.

## Remaining C2-F-004 test gaps

1. `test_money_snapshot` checks each snapshot field before Formatter calls and checks rendered text, but never checks the original array afterwards. The explicitly requested R3-2 retained assertion that the complete snapshot remains unchanged is missing. Add a complete expected snapshot and assert equality before and after both locale formats.
2. `test_distance_and_money_validations` places the valid maximum assertion and max+1 rejection inside the same try/catch. If to_decimal rejects the valid maximum with out_of_range, the catch succeeds and the max+1 call is skipped; the test can pass the regression it is supposed to reject. Assert maximum acceptance outside the rejection catch; catch only the invalid max+1 call. This is a defect in the requested boundary test, not a request to change runtime logic or build a mutation framework.

## Received handoff/history discrepancy and recovery

Received task says CHANGES_REQUESTED while outgoing prompt says READY_FOR_REVIEW; checklist/index also remain at Round 2 correction state. The new Builder Fix report — Round 3 exists, but earlier Architect Review Round 1/2, recovered revision-2 contract and correction blueprints were removed from the task.

Read-only inspection of `patch_fix_report.php` found `preg_replace('/### Chat handoff prompt[\s\S]*$/', '', $c)`: it deletes from the first handoff heading to EOF, not just the final prompt. Read-only simulation against the saved task removes the same earlier sections. No execution log was inspected; do not assert who ran it or use this as proof of the older CORE-001 document-loss cause. Reviewer did not execute the script. Source copy and simulation are retained in history-loss.json and observed-patch_fix_report.php.txt.

Architect restored its own review/planning records from the Round 2 received task archive, retained review.md, and a explicitly labeled reconstruction of the previously issued Round 3 instructions from this conversation. The received Round 3 Builder report is preserved; its content is not rewritten into acceptance. Whitespace in the maintained restored document is normalized, with the received bytes retained as evidence. This is review-document maintenance, not application/test fixes. Next Builder must append its report without rewriting/removing prior sections. Current task/checklist/index synchronized to this verdict by Architect.

## AC disposition and next action

AC1–AC3 behavior remains PASS based on unchanged accepted input hashes and passing tests. AC4 remains FAIL because the required two regression guarantees are not complete; contributor confirmation remains necessary for final acceptance. No accepted checkbox checked. Complete the tests-only Correction blueprint — Round 4 plus truthful contributor/evidence report. No generator/harness/runtime changes. Wide CORE-001/planning history recovery remains separate Architect work; no new Builder gate introduced.
