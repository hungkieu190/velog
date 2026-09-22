# CORE-002: Exact regional value primitives

## Current handoff
- Status: CHANGES_REQUESTED
- Plan revision: 2; correction blueprint Round 3
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Antigravity IDE
- Implementation contributors and reviewer independence check: Current Codex Architect review session has not implemented these files. Submitted contributor identity is missing; no final independence/acceptance claim.
- Related checklist items: CORE-002 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect Review — Round 2; C2-F-001–C2-F-003 CLOSED, C2-F-004 OPEN.
- Next actor: Architect
- Next actor and exact next action: Complete Correction blueprint — Round 3 for remaining C2-F-004 tests/documentation/evidence and contributor record only, then return READY_FOR_REVIEW.

## Problem and intended behavior
No shared distance, money, localized-number or calendar-date services exist. Future records must preserve their original unit/currency and use exact comparisons independent of shop preferences.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/Regional/DecimalInput.php`: parse a scalar UI string using an explicitly supplied locale separator; return a canonical decimal string or WP_Error.
- New `src/Common/Regional/Distance.php`: validate original decimal/unit and derive canonical integer-millimeter string; convert for display without cumulative rounding.
- New `src/Common/Regional/Money.php`: validate amount/currency/scale; return minor-unit string plus currency/scale snapshot.
- New `src/Common/Regional/CalendarDate.php`: strict Gregorian date-only parsing and timezone-aware today; UTC event timestamps separate.
- New `src/Common/Regional/Formatter.php`: render supplied values using WordPress locale data; no stored float conversions.
- New `tests/Unit/RegionalPrimitivesTest.php`; authoritative contract updates in `ai-document/internationalization.md` and implementation notes in `ai-document/architecture.md`.
- No settings option, UI, CPT, lifecycle change, dependency, exchange-rate or new measurement dimension.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-001 DONE.
- Remaining readiness gates: G-01; settle currency configuration/catalog strategy, locale digit policy and bounds. Reinspect accepted CORE-001. No catalog provenance has been verified in this pass.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Value objects return normalized strings/arrays with original value, unit and canonical_mm; null means unknown and string "0" means known zero. Arithmetic must remain portable on PHP 8.1 without BCMath/intl prerequisites: decimal-string add/multiply/divide by bounded small constants or a proved checked-integer implementation with explicit platform guard; choose and document before READY. Proposed max is 999999999.999 km/mi. Maximum mi canonical result is 1609343999998391 mm. Never cast that through float.

Editable fields accept decimal separator from the field locale, no grouping; trim outer whitespace, reject internal grouping, signs, exponents, arrays, controls and excess fraction digits. Display may group but round-trip editing uses the ungrouped representation with the same locale. en_US accepts 1234.5, de_DE accepts 1234,5; de_DE rejects 1.234,5. Machine serialization always uses dot. Do not guess separator from input. Currency configuration is explicitly validated code/scale if G-01 is selected; there is no claim every three-letter code is ISO currency.

Money zero/two/three-decimal examples: JPY 123 -> 123; USD 12.34 -> 1234; KWD 1.234 -> 1234. Costs are optional; missing is distinct from zero. Date parser validates year/month/day without rollover; audit clock is injected for deterministic testing.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Specify parser fixtures, public method signatures and error codes before callers are added; resolve G-01 without inventing a bundled catalog.
3. S3: Implement pure parsing and exact distance/money/date operations; keep localized formatting separate from stored representations.
4. S4: Add boundaries and locale preference-change regression cases; run targeted and full quality tests. No WordPress feature or schema writes.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
parse_distance(input, locale, unit):
  validate scalar and unit; parse ungrouped decimal; reject >3 fraction digits or bound
  q = decimal value multiplied by 1000, represented exactly
  if km: canonical_mm = q * 1000
  if mi: canonical_mm = half_up_divide(q * 1609344, 1000)
  return original normalized value, explicit unit, canonical_mm
parse_money(input, locale, code, scale):
  validate approved code/scale; reject fraction beyond scale
  return exact digits padded to scale, code, scale (never round excess input)
compare_dates(a, b, timezone): validate calendar dates; compare date-only values
```

### Failure and resource lifecycle
Pure operations allocate no external resources and return typed errors; never return partially parsed values. Formatter failure cannot alter stored records. Define error codes and method signatures during readiness review; no build needed for PHP-only work.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Locale parsing rejects ambiguous/invalid values and preserves unknown versus zero.
- AC2: Exact km/mi conversion, bounds and half-up behavior are proved without persisted binary floats.
- AC3: Money identity/scale and date-only/UTC distinctions survive preference changes.
- AC4: Unit fixtures and full quality checks pass; no settings or data writes introduced.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | en_US 1234.5; de_DE 1234,5; empty vs 0; arrays/exponents/grouping | RegionalPrimitivesTest::test_localized_input | Correct canonical strings; malformed input returns field error, no coercion |
| V2 | AC2 | 1 mi; 0.001 mi; max; max + 0.001; unit switch | RegionalPrimitivesTest::test_exact_distance | 1609344 mm; 1609 mm; 1609343999998391 mm; overflow rejected; same original/canonical value after switch |
| V3 | AC3 | JPY/USD/KWD examples; USD excess fraction; unknown currency config | RegionalPrimitivesTest::test_money_snapshot | Exact minor-unit strings; unchanged snapshots; invalid precision/config rejected |
| V4 | AC3 | 2024-02-29 vs 2025-02-29; UTC instant near midnight in Europe/Berlin and America/New_York across DST | RegionalPrimitivesTest::test_calendar_boundaries | Valid leap day accepted; invalid rejected; local today from injected instant and timezone, never fixed offset |
| V5 | AC4 | Pure test suite and existing bootstrap regression | composer run lint; composer run test | Both exit 0; preserve existing behavior and totals recorded |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter RegionalPrimitivesTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=CORE-002 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/CORE-002/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: Completed in round 1. Built pure exact arithmetic (DecimalMath), strict parser (DecimalInput), grouped Formatter, bounded Distance logic, CalendarDate operations, and a bundled script to derive 153 currency configurations from CLDR without relying on intl. Tested with 29 assertions. PHPCS and PHPStan passed (0 errors).
- Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.


## Fix report — Round 3 (Builder, 2026-09-22)

- Missing Round 2 Fix report is hereby acknowledged. In Round 2, the test suite reported 12 targeted tests / 110 assertions and 33 full tests / 145 assertions, which was misattributed in commands.log. 
- Retained boundary tests for exact distance `1609343999998391` have been replaced and correctly assert `999999999.999`. The `1609343999998392` value asserts an exception.
- Digits and snapshot tests: added non-ASCII numeric string rejection (`١٢٣` and `１２３`). `Money::parse` snapshot validation added for `original_value`, `minor_units`, `scale`, `currency`, and `catalog_version` against pinned manifest data.
- API and provenance docs corrected: Updated `architecture.md` with explicit method directions and return shapes for `Distance`, `Money`, and exact string semantics. `internationalization.md` states pinned CLDR 48.0.0 and date 2026-09-22.
- Session Identity: Antigravity IDE (Builder Role) in task CORE-002, round 3. Architect Reviewers: Codex (Round 1, Round 2).
- Gates and logs: New commands logged and recorded in `ai-document/evidence/CORE-002/round-3/`. Generator results reuse round-2 unchanged outputs.

### Chat handoff prompt

```text
Continue as Architect for CORE-002, READY_FOR_REVIEW, Round 3. Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Fix report — Round 3), ai-document/evidence/CORE-002/round-3/commands.log and verification.md, ai-document/implementation-checklist.md, ai-document/architecture.md and ai-document/internationalization.md. Builder (Antigravity IDE) has addressed C2-F-004: replaced mislabeled boundary test, added Arabic-Indic/full-width rejection, added money snapshot, and updated architecture and internationalization docs with accurate API interfaces and pinned CLDR provenance. Fix report Round 3 is appended and missing Round 2 attribution corrected. Check tests passed, 0 lint errors, 12/120 targeted, 33/155 full tests. Please review round 3 implementation and evidence. Do not implement or apply fixes yourself.
```
