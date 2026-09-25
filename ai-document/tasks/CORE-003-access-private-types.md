# CORE-003: Capabilities and private record types

## Current handoff
- Status: DONE
- Plan revision: 8
- Implementation round: 7
- Blueprint readiness: PASS
- Architect session reference: Codex planning 2026-09-21; review Round 5 on 2026-09-24; review Round 6 on 2026-09-24; review Round 7 on 2026-09-25.
- Builder session reference: Antigravity Builder 2026-09-25.
- Implementation contributors and reviewer independence check: Antigravity Builder (implementer); Codex Architect (independent reviewer).
- Related checklist items: CORE-003 / AC1–AC4.
- Baseline branch and commit: main at `9c4d1be31a78db7448ef5244a7a8a1d9f4401287`.
- User approval reference and approved scope: PLAN-001 rev 3; 2026-09-22 user approved G-02 and CORE-003 scope AC1–AC4.
- Latest round: Independent Architect acceptance of Builder Round 7; AC1–AC4 PASS; F-003, F-005, F-006 CLOSED.
- Latest report: ai-document/evidence/CORE-003/architect-round-7/review.md
- Evidence: ai-document/evidence/CORE-003/round-7/ and ai-document/evidence/CORE-003/architect-round-7/
- Next actor: Architect
- Next actor and exact next action: Codex Architect continues dependency-ordered planning for DATA-001; preserve accepted CORE-003 implementation and evidence.

## Problem and intended behavior

No product CPTs or dedicated capabilities exist. Login alone cannot grant operational access; generic post editing, REST, search and direct URLs must not expose these records.

## Scope and references

- New files: `src/Common/AccessPolicy.php`, `src/Core/Capabilities.php`, `src/Core/PostTypes.php`.
- Modified files: `src/Core/Plugin.php`, `src/Core/Activator.php`.
- Test files: `tests/Unit/AccessPolicyTest.php`, `tests/fixtures/core-003-verify.php`, `tests/workflow/product-smoke.sh`, `product-smoke-controls.sh`.
- Documentation: `ai-document/architecture.md`.

## Correction blueprint (Revision 8)

Architect review of round 6: CHANGES_REQUESTED. Scope remains CORE-003 AC1–AC4. Apply these corrections to the allowed test and evidence files; application code changes require a demonstrated product defect.

1. **F-003 (open)**: The native GET denial checks in `tests/fixtures/core-003-verify.php` accept any 302 location containing `wp-admin` (lines 217 and 227). Require a specific denial destination and reject a redirect back to the requested edit/create resource. Establish a successful authenticated HTTP control for every actor whose denial is tested, including subscriber; validate manager/technician redirects by destination rather than only excluding `wp-login.php`.
2. **F-005 (open)**: `test_restore_failure_does_not_propagate()` sets the ledger option absent. Its rollback therefore deletes the option and never invokes the configured failing `wpdb->update` path. Make the restore failure reachable with stateful mocks, assert the resulting state and failed restore behavior, and preserve a separate successful restoration case. Do not claim the failure path is covered until demonstrated.
3. **F-006 (open)**: Round-6 raw `*.log` files exist locally but are ignored by `.gitignore`; only `report.md` is tracked. Include the required raw per-case evidence in a portable, tracked form so another reviewer can verify WP 6.4.3/6.7.2 results, HTTP codes/headers, lifecycle snapshots, negative causes, exits, and cleanup. Update the report to distinguish actual verified checks from claims unsupported by retained evidence.
4. Rerun the relevant PHP, workflow, shell, diff, and isolated WordPress gates after corrections. Synchronize this task, checklist, and README; submit one copy-ready Architect handoff prompt. Do not mark DONE.

### Previous correction blueprint (Revision 7)

Allowed implementation files: `tests/Unit/CapabilitiesTest.php`, `tests/fixtures/core-003-verify.php`, `tests/workflow/product-smoke.sh`, `tests/workflow/product-smoke-controls.sh`; `src/Core/Capabilities.php` only if a verified runtime rollback defect requires it. Update `ai-document/architecture.md` only for a demonstrated documentation mismatch. Preserve unrelated accepted code and WF-003/WF-004/WF-005 scope.

1. **F-002 / F-005**: Inject a one-shot failure after a real capability/option mutation in an isolated WordPress fixture. Snapshot option existence, raw value, autoload, role grants, and loaded role state before and after; assert exact restoration and a subsequent successful reactivation. Unit tests must make database mocks stateful and assert resulting state for both successful and failed restores, not only return values.
2. **F-003**: For each actor and CPT, first establish authenticated identity with a successful, role-appropriate HTTP control. Require known denial semantics on native/REST/direct URLs: reject 5xx; inspect redirect destinations instead of accepting every 3xx. Keep nonce-bearing denied POST checks and assert exact post state is unchanged. Cover repair, collision, reactivation, and failure rollback using actual before/after state.
3. **F-004**: Make each negative mode reach its intended validator and emit a unique cause marker. The outer harness must assert that marker with the expected exit, isolate directories it owns, retain per-run logs, and verify process identity, stop, `wait`/reap, and absence; cleanup failures must fail the run. Signals must not turn interrupted runs into success.
4. **F-006**: Save raw per-case results for WordPress 6.4.3 and 6.7.2, including runtime versions, HTTP codes and relevant headers, lifecycle snapshots, each negative cause, exits, process cleanup, and parallel isolation. Record any unavailable runtime explicitly as NOT VERIFIED. Synchronize task/checklist/README and submit a concise Builder report.

Verification matrix: `composer run lint`, `composer run test -- --display-skipped` (zero required skips), `npm run test:workflow`, `bash -n` on both runner scripts, `git diff --check`, `product-smoke-controls.sh`, and normal isolated smoke for both pinned WordPress versions. Retain raw commands, exits, and logs. Blueprint readiness: PASS for this bounded correction; user-approved CORE-003 AC1–AC4 scope remains unchanged.

## Acceptance criteria

- [x] CORE-003 / AC1: Explicit manager/admin/technician policy is enforced at object and primitive levels. Status: DONE.
- [x] CORE-003 / AC2: Anonymous, subscriber and unrelated editor cannot discover private records through public/native endpoints. Status: DONE.
- [x] CORE-003 / AC3: Activation/reactivation and role collisions preserve unrelated roles/capabilities. Status: DONE.
- [x] CORE-003 / AC4: Real WordPress permission/lifecycle evidence and runner failure/cleanup controls pass. Status: DONE (round-7 local runs and controls passed; portable tracked normalized evidence retained).

## Open findings

- **F-001**: CLOSED (registration timing on init).
- **F-002**: RESOLVED (retained in Round 7 verification).
- **F-003**: CLOSED — fixture: authenticated HTTP positive control for subscriber (profile.php 200); manager/technician destination validated; GET/POST denials reject redirects back to requested resources and require specific deny destinations; 5xx rejected; mutation state verified unchanged.
- **F-004**: RESOLVED (retained in Round 7 verification).
- **F-005**: CLOSED — unit test: `test_restore_failure_does_not_propagate` makes failing `wpdb->update` reachable and asserts `$restore_failure_invoked`, safe failure handling (`install() === false`), and resulting state across all options; separate successful restoration test preserved. 45 tests, 224 assertions, 0 skipped.
- **F-006**: CLOSED — portable tracked `.txt` and `.md` evidence in `ai-document/evidence/CORE-003/round-7/` (lint, phpunit, workflow, syntax, diff, runtime, smoke 6.7.2, smoke 6.4.3, smoke controls, report.md); CRLF/trailing whitespace normalized; git diff --check (exit 0) and git diff --cached --check (exit 0) verified.

## History and archives

- Full pre-lean task history and revisions 1–5 are archived verbatim at: [ai-document/history/CORE-003/pre-lean.md](../history/CORE-003/pre-lean.md).

## Architect intake — Round 7 (2026-09-25)

Result: FAIL. Technical acceptance review stopped at incoming validation under `ai-document/architect-builder-workflow.md`.

- Task and README declare READY_FOR_REVIEW, while the task acceptance criteria still declare CHANGES_REQUESTED. The checklist criteria declare READY_FOR_REVIEW; task/checklist synchronization is incomplete.
- `git status --short -- ai-document/evidence/CORE-003/round-7` reports `?? ai-document/evidence/CORE-003/round-7/`. The `.txt` evidence is not ignored, but it is not tracked. The report's claim that the files are tracked is inaccurate.

Builder must synchronize task, checklist, and README statuses and ensure the portable round-7 evidence is tracked before resubmission. Architect has not accepted or rejected the technical fixes in this intake.

## Architect technical review — Round 7 (2026-09-25)

Disposition: CHANGES_REQUESTED. Intake metadata now matches and round-7 evidence is staged. F-003 and F-005 corrections are supported by code and local verification. F-006 remains open:

- **F-006 / staged evidence whitespace**: `git diff --cached --check` exits 2. The staged smoke evidence contains CRLF on every line (6.4.3: 289 lines; 6.7.2: 478; controls: 317), reported as trailing whitespace. `lint.txt` also has trailing whitespace and an extra blank line. The Builder's `git diff --check` evidence covers only unstaged changes, so the stated clean diff gate does not cover the files newly staged in round 7. Normalize portable evidence line endings and whitespace, restage, and record both staged and unstaged diff checks with exit codes. Preserve the substantive raw test results.

Reviewer reran `composer run lint` (exit 0), `composer run test -- --display-skipped` (exit 0; 45 tests, 224 assertions), and shell syntax (exit 0). Local round-7 WordPress logs show successful 6.4.3/6.7.2 fixture results and controls, but they were not independently rerun in this review. Reviewer reran `npm run test:workflow` (exit 0; 20/20). No application-code correction requested.

## Architect acceptance — Round 7 (2026-09-25)

AC1–AC4 PASS; F-003, F-005, and F-006 CLOSED after independent source, evidence, quality-gate, and WP 6.4.3/6.7.2 smoke verification. Review record: `ai-document/evidence/CORE-003/architect-round-7/review.md`. No commit, push, deploy, or release was performed.

### Chat handoff prompt

```text
Status: DONE
Recipient: Architect
Intent: work

CORE-003 AC1–AC4 are independently accepted after Round 7. Continue dependency-ordered planning for DATA-001 using the accepted CORE-003 access policy and private post types. Preserve CORE-003 code and evidence. Do not begin Builder implementation without an approved DATA-001 blueprint.
```
