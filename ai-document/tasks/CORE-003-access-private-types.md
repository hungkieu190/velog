# CORE-003: Capabilities and private record types

## Current handoff
- Status: READY_FOR_REVIEW
- Plan revision: 7
- Implementation round: 6
- Blueprint readiness: PASS
- Architect session reference: Codex planning 2026-09-21; review Round 5 on 2026-09-24.
- Builder session reference: Antigravity Builder 2026-09-24.
- Implementation contributors and reviewer independence check: Antigravity Builder (implementer); Codex Architect (independent reviewer).
- Related checklist items: CORE-003 / AC1–AC4.
- Baseline branch and commit: main at `9c4d1be31a78db7448ef5244a7a8a1d9f4401287`.
- User approval reference and approved scope: PLAN-001 rev 3; 2026-09-22 user approved G-02 and CORE-003 scope AC1–AC4.
- Latest round: Builder Round 6; blueprint revision 7.
- Latest report: ai-document/evidence/CORE-003/round-6/report.md
- Evidence: ai-document/evidence/CORE-003/round-6/
- Next actor: Architect
- Next actor and exact next action: Codex Architect independently reviews round-6 evidence, verifies F-002–F-006 fixes against blueprint revision 7, and returns disposition.

## Problem and intended behavior

No product CPTs or dedicated capabilities exist. Login alone cannot grant operational access; generic post editing, REST, search and direct URLs must not expose these records.

## Scope and references

- New files: `src/Common/AccessPolicy.php`, `src/Core/Capabilities.php`, `src/Core/PostTypes.php`.
- Modified files: `src/Core/Plugin.php`, `src/Core/Activator.php`.
- Test files: `tests/Unit/AccessPolicyTest.php`, `tests/fixtures/core-003-verify.php`, `tests/workflow/product-smoke.sh`, `product-smoke-controls.sh`.
- Documentation: `ai-document/architecture.md`.

## Correction blueprint (Revision 7)

Allowed implementation files: `tests/Unit/CapabilitiesTest.php`, `tests/fixtures/core-003-verify.php`, `tests/workflow/product-smoke.sh`, `tests/workflow/product-smoke-controls.sh`; `src/Core/Capabilities.php` only if a verified runtime rollback defect requires it. Update `ai-document/architecture.md` only for a demonstrated documentation mismatch. Preserve unrelated accepted code and WF-003/WF-004/WF-005 scope.

1. **F-002 / F-005**: Inject a one-shot failure after a real capability/option mutation in an isolated WordPress fixture. Snapshot option existence, raw value, autoload, role grants, and loaded role state before and after; assert exact restoration and a subsequent successful reactivation. Unit tests must make database mocks stateful and assert resulting state for both successful and failed restores, not only return values.
2. **F-003**: For each actor and CPT, first establish authenticated identity with a successful, role-appropriate HTTP control. Require known denial semantics on native/REST/direct URLs: reject 5xx; inspect redirect destinations instead of accepting every 3xx. Keep nonce-bearing denied POST checks and assert exact post state is unchanged. Cover repair, collision, reactivation, and failure rollback using actual before/after state.
3. **F-004**: Make each negative mode reach its intended validator and emit a unique cause marker. The outer harness must assert that marker with the expected exit, isolate directories it owns, retain per-run logs, and verify process identity, stop, `wait`/reap, and absence; cleanup failures must fail the run. Signals must not turn interrupted runs into success.
4. **F-006**: Save raw per-case results for WordPress 6.4.3 and 6.7.2, including runtime versions, HTTP codes and relevant headers, lifecycle snapshots, each negative cause, exits, process cleanup, and parallel isolation. Record any unavailable runtime explicitly as NOT VERIFIED. Synchronize task/checklist/README and submit a concise Builder report.

Verification matrix: `composer run lint`, `composer run test -- --display-skipped` (zero required skips), `npm run test:workflow`, `bash -n` on both runner scripts, `git diff --check`, `product-smoke-controls.sh`, and normal isolated smoke for both pinned WordPress versions. Retain raw commands, exits, and logs. Blueprint readiness: PASS for this bounded correction; user-approved CORE-003 AC1–AC4 scope remains unchanged.

## Acceptance criteria

- [x] CORE-003 / AC1: Explicit manager/admin/technician policy is enforced at object and primitive levels. Status: READY_FOR_REVIEW.
- [x] CORE-003 / AC2: Anonymous, subscriber and unrelated editor cannot discover private records through public/native endpoints. Status: READY_FOR_REVIEW.
- [x] CORE-003 / AC3: Activation/reactivation and role collisions preserve unrelated roles/capabilities. Status: READY_FOR_REVIEW.
- [x] CORE-003 / AC4: Real WordPress permission/lifecycle evidence and runner failure/cleanup controls pass. Status: READY_FOR_REVIEW (fully verified on WP 6.4.3 and 6.7.2 + negative controls).

## Open findings

- **F-001**: CLOSED (registration timing on init).
- **F-002**: PENDING REVIEW — fixture: velog_snap_state() before/after; rollback asserts exact option existence/value/autoload + in-memory role state; reactivation test. Unit: test_restore_failure_does_not_propagate asserts resulting state for all three options.
- **F-003**: PENDING REVIEW — fixture: positive auth controls per actor; direct ID requires 404; REST requires 404/403; 302 validated to wp-login.php; 5xx rejected; mutation state verified.
- **F-004**: PENDING REVIEW — inner runner: VELOG_CAUSE marker per mode; CLEANUP_FAIL→exit 1; per-run logs retained. Controls harness: cause marker asserted per mode; owned HARNESS_DIR; child absence verified; parallel logs retained.
- **F-005**: PENDING REVIEW — 45 tests 220 assertions 0 skipped; restore failure test asserts resulting state.
- **F-006**: PENDING REVIEW — raw logs in round-6/ (smoke-6.7.2.log, smoke-6.4.3.log, smoke-controls.log); runtime.log records PHP 8.3.6 and MariaDB 10.11.14; real WP integration and negative controls fully verified (exit 0).

## History and archives

- Full pre-lean task history and revisions 1–5 are archived verbatim at: [ai-document/history/CORE-003/pre-lean.md](../history/CORE-003/pre-lean.md).

### Chat handoff prompt

```text
Status: READY_FOR_REVIEW
Recipient: Architect
Intent: review

Codex Architect: Review CORE-003 round-6 independently. Builder (Antigravity) addressed F-002–F-006 per blueprint revision 7.
Evidence: ai-document/evidence/CORE-003/round-6/report.md and raw logs in that directory.
Changed files: tests/fixtures/core-003-verify.php, tests/Unit/CapabilitiesTest.php, tests/workflow/product-smoke.sh, tests/workflow/product-smoke-controls.sh.
Quality gates: lint exit 0, PHPUnit 45 tests 220 assertions 0 skipped exit 0, workflow 20/20 exit 0, bash -n both scripts exit 0, git diff --check exit 0.
Integration gates: WP 6.7.2 smoke exit 0, WP 6.4.3 smoke exit 0, product-smoke-controls.sh (4 negative controls + parallel normal mode) exit 0. FULLY VERIFIED with raw logs retained in round-6/.
Return DONE or CHANGES_REQUESTED with specific findings.
```
