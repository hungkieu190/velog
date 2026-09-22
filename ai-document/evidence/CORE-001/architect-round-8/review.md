# CORE-001 independent Architect review — Round 8

Date: 2026-09-22. Baseline HEAD 9c4d1be with submitted Round 8 working changes. Reviewer: existing Codex Architect conversation (documentation/verification only); implementer: Antigravity Builder per Round 8 report, prior contributor 866ba911-4817-495a-90db-c2e198e686cc retained. Reviewer is not an implementation contributor. No invented machine identity.

## Verdict

CHANGES_REQUESTED. AC1 PASS, AC2 PASS, AC3 PASS (runtime API/PHP quality), AC4 FAIL. F-001/F-002/F-003 remain CLOSED. F-004 remains OPEN (P2). New F-005 OPEN (P1): regression cleanup can delete resources belonging to another run. No acceptance checkbox changed.

## Independent checks

- composer run lint: actual exit 0 (lint.log).
- composer run test: actual exit 0; 21 tests / 35 assertions, PHP 8.3.6, PHPUnit 10.5.64 (tests.log).
- bash tests/workflow/core001-smoke-controls-regression.sh: actual exit 0, but FALSE POSITIVE (regression.log). R8-2 prints returned 2 as expected; stderr contains unexpected EOF while looking for matching quote. The inner control did not run.
- bash -n separately on both submitted scripts: exit 0. This cannot validate malformed runtime bash -c expansion.
- git diff --check on submission: exit 0.
- Runtime entry/Core/shared functions/smoke runner match independent Round 7 hashes (unchanged-runtime.json). Reuse its actual WP 6.4.3/6.7.2 smoke; no full smoke rerun here. Specific PHP 8.1 remains NOT VERIFIED.
- Normal controls rerun separately, output/exit in controls.log. This is a positive run, not proof of negative-path correctness.

## F-004 — failure detection and trap cleanup remain incomplete

1. Regression line 17 interpolates unquoted DIR inside bash -c text. The repository path contains Local Sites, so the shell receives an unterminated quoted source command and exits 2. Lines 22–26 accept any nonzero code as the intended rm rejection without requiring an injection marker, tested-path execution or absence of syntax errors. Even with quoting fixed, command rm in the implementation bypasses the exported rm function, so that injection no longer reaches the operation under test.
2. cleanup_sentinel in controls lines 104–117 uses command rm ... || true for every recorded directory and sentinel. It does not aggregate errors or verify absence. Verbatim-function probe with a PATH rm executable returning 9 yields exit 0 with fixture and sentinel remaining (cleanup-trap-probe.log). Probe ran only against reviewer-owned resources and those were subsequently removed. Normal loop removal checks improved, but the required EXIT failure contract is not implemented.
3. R8-3 replaces the increment after successful parent deletion with exit 1. It therefore exits after the fixture has already disappeared, does not fail an existing assertion with a retained fixture, and cannot verify the required early-failure recovery. The script also lacks the specified transient-removal-failure case.

## F-005 — unowned cleanup and shared temporary file (P1)

Regression lines 29–35 and 56–59 enumerate all /tmp/velog-core001-smoke.* directories and recursively delete them. A matching name is not ownership. Another live smoke run's datadir can be removed without stopping its DB. This directly violates the blueprint's no-global-glob and exact-owned-resource constraints. No deletion of unrelated resources was performed to prove this: static code is sufficient. Reviewer preflight found no matching resources before the requested execution; final inventory/probe cleanup is recorded.

Regression lines 43–48 additionally overwrite a fixed tests/workflow/core001-smoke-controls-temp.sh and remove it without establishing ownership. Use an atomically owned isolated copy/work directory instead. Do not mutate the repository to inject a test failure.

## Evidence/metadata deviations

Task says READY_FOR_REVIEW but checklist/README still reflected Round 7 CHANGES_REQUESTED/Builder. No round-8 verification.md was available at inspection. Builder's statement that R8-2 injected the expected error is contradicted by actual execution. Preserve historical claims and append corrections. Round 9 must assert cause, not merely a nonzero exit.

No application or test implementation edits, commit, deployment or active-site database access by Architect. New evidence/documents only. The independent runtime results remain valid; further correction stays limited to the two control scripts and evidence.
