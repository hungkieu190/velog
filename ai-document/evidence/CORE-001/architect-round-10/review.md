# CORE-001 independent acceptance — Round 10, revision 2

Date: 2026-09-22. Reviewer: existing Codex Architect conversation, documentation and independent verification only. Implementer: Antigravity Builder Round 10; prior recorded contributor 866ba911-4817-495a-90db-c2e198e686cc retained. Reviewer is not an implementation contributor. Baseline HEAD 9c4d1be plus reviewed working files; exact accepted source hashes in reviewed-files.json. No commit created.

## Decision

DONE under the explicitly user-approved bounded closeout, Round 10 revision 2. AC1 PASS, AC2 PASS, AC3 PASS, AC4 PASS. B1 PASS, B2 PASS. F-004 and F-005 CLOSED for this scope; F-001/F-002/F-003 remain CLOSED. PLAN-F-004 resolved by accepted bootstrap/runtime verification. Historical failures remain valid history, not retroactively converted to passing runs.

## Independent checks actually run

- composer run lint: exit 0, PHPCS/PHPStan (lint.log).
- composer run test: exit 0, 21 tests / 35 assertions, PHP 8.3.6 and PHPUnit 10.5.64 (tests.log).
- bash -n tests/workflow/core001-smoke-controls-regression.sh: exit 0 (syntax.log).
- bash -x tests/workflow/core001-smoke-controls-regression.sh: exit 0 (regression.log). Tracing observes allocations, expected case statuses and finalizer without changing implementation. Actual RES_NONE=0, RES_PERSISTENT=1, RES_TRANSIENT=1, RES_ASSERTION=1; marker/state/assertion checks succeed. Final success is emitted after supervisor cleanup, not before.
- Submitted git diff --check: exit 0; post-documentation check recorded in final-check.json.

## B1 and B2 verification

The supervisor context is exported and validated in external wrappers; ledger writes are checked. The assertion case runs against copies in the supervisor-owned isolated copy directory (path includes spaces). No fixed repository scratch write remains and that path is absent. No global /tmp cleanup enumeration remains. Cleanup uses recorded fixture paths and owned sentinel/supervisor only, with injection disabled and captured rm statuses/absence checks.

Trace confirms persistent failure reached its marker and recorded path; transient one-shot state succeeds and its path is absent after parent retry; assertion rejection and ledger checks succeed. The unrelated marker content comparison succeeds before its owner removes it. Supervisor recovers its recorded persistent fixture and removes its sentinel/scratch/directory. Independent post-process inspection confirms all three recorded fixture paths and both supervisor/sentinel paths are absent (verification.json). No root-relative state-write errors or leaked resources were observed. No manual reviewer recovery was needed.

This acceptance is evidence for the specified scenarios and B1/B2, not proof of every possible OS failure. Optional deeper failure permutations remain TOOL-001 DRAFT and do not block this task, as approved by the user. The extra none case is a normal-run baseline; it is not claimed to prove a disabled-injection meta-verifier. Unexecuted supervisor_fail branch is not credited as test coverage.

## AC evidence and reuse

AC1: accepted actual bootstrap entry/idempotency/callback regressions remain in the passing suite; runtime source unchanged.
AC2: correct init registration/plugin-relative path and fixture translation; retained independent Round 7 WP 6.4.3/6.7.2 real integration, warning detection/rejection and lifecycle evidence. Shared/runtime hashes unchanged.
AC3: current lint/tests pass; no runtime/API/dependency changes in this correction.
AC4: retained isolated runtime versions/commands/cleanup evidence plus current independent B1/B2 verification and contributor separation. All required criteria satisfied within revision 2.

verification.json compares controls, shared helpers, smoke runner and runtime entry/Core to Round 9 reviewed hashes; all unchanged. Round 9 already compared runtime/shared inputs with independent Round 7. No full smoke rerun or active database access in this review. PHP 8.1 specifically remains NOT VERIFIED; observed PHP 8.3.6 satisfies CORE-001's PHP >=8.1 matrix wording. Broader product, release, deployment and manual MVP acceptance are not inferred.

## Next action

Architect returns to PLAN-002 DRAFT: consolidate unresolved product gates G-01–G-08 and technical readiness, then issue only individually approved READY tasks. CORE-001 completion does not dispatch any subsequent Builder feature task. TOOL-001 remains deferred and nonblocking. No commit, deployment or active-site data change authorized/performed.
