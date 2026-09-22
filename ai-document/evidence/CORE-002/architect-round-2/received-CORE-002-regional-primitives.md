# CORE-002: Exact regional value primitives

## Current handoff
- Status: READY_FOR_REVIEW
- Plan revision: 2; correction blueprint Round 2
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Antigravity IDE
- Implementation contributors and reviewer independence check: Current Codex Architect review session has not implemented these files. Submitted contributor identity is missing; no final independence/acceptance claim.
- Related checklist items: CORE-002 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect Review — Round 1; C2-F-001–C2-F-004 OPEN; Correction blueprint — Round 2 ready.
- Next actor: Architect
- Next actor and exact next action: Review CORE-002 round 2 implementation and evidence against acceptance criteria.

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

### Chat handoff prompt

```text
Continue as Architect for CORE-002, READY_FOR_REVIEW. Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the new test evidence in ai-document/evidence/CORE-002/round-1/. Scope is exact regional value primitives, AC1–AC4. I have implemented the pure parsing, date logic, and exact conversion primitives in src/Common/Regional/, along with 100% passing tests (29 tests, 90 assertions) and 0 lint/static analysis errors. Please review the implementation and verification evidence against the acceptance criteria. If approved, mark the task DONE; otherwise, provide correction feedback for the next round. Do not implement or apply fixes yourself.
```


## Review — Round 1 (Architect, 2026-09-22)

Decision: CHANGES_REQUESTED. [Independent review, AC verdicts and findings](../evidence/CORE-002/architect-round-1/review.md); [observed boundary/oracle/catalog results](../evidence/CORE-002/architect-round-1/verification.json). AC1–AC4 FAIL for acceptance; lint and full suite pass (29 tests / 90 assertions). C2-F-001–C2-F-004 remain OPEN. Current reviewer is the same Codex Architect conversation that inspected the pre-implementation task; no code implementation contribution. Builder identity must still be supplied; no identity invented.

### Baseline recovery — approved revision 2

This is an explicit recovery from the revision-2 text read earlier in this conversation, not new product scope or a claim to have restored missing history verbatim. The task received for review had reverted to revision 1; before-review copies are retained in architect-round-1/. The user denied intentionally resetting the documents. Cause remains unknown. Existing revision-1 and Builder report text above are retained as history; conflicting old contracts/readiness/approval statements are superseded here.

Previously recorded approval: user rejected manual currency code/scale entry, approved a bundled selectable catalog with automatic precision and no WooCommerce dependency, and requested Builder handoff. CORE-001 Round 10 acceptance was observed earlier and its review artifact remains at ../evidence/CORE-001/architect-round-10/review.md. Its missing task/history updates need separate Architect reconciliation; do not reopen/fix its harness in CORE-002. G-02–G-08 remain pending.

Recovered bounded contract:

- Stateless static classes in MF\VeLog\Common\Regional: DecimalInput, internal DecimalMath, Distance, Money, CalendarDate, Formatter, CurrencyCatalog, CurrencyCatalogData. ABSPATH guards and PHPDoc. No hooks, settings, UI, database, new dependency, WooCommerce, runtime network, exchange rate or build change. CORE-004 owns the searchable selector.
- DecimalInput::parse(mixed input, string separator, int max_fraction): string. Strings only, maximum 256 input bytes; explicit dot/comma separator; precision 0–4; ungrouped ASCII digits with optional separator followed by digits. Trim ASCII outer whitespace, reject signs/exponents/grouping/controls elsewhere and NUL everywhere. Normalize leading zeros, preserve entered fraction. InvalidArgumentException message invalid_number / invalid_separator / invalid_precision. Empty/null is not zero; callers own optional null.
- DecimalMath operates on decimal strings using normalization, length/lexical comparison, bounded-small-factor multiply/divide and carry/remainder; no full-quantity cast or BCMath/intl dependency. Small intermediates stay within 32-bit signed bounds for these operations.
- Distance::parse(mixed input, string unit, string separator): {original_value,unit,canonical_mm}. km/mi only, nonnegative <=999999999.999, <=3 decimals. q is original value scaled by 1000; km mm=q*1000; mi mm=half-up(q*1609344/1000). Stable invalid_unit/out_of_range plus parser errors.
- Distance::to_decimal(string canonical_mm, string unit, int places=3): fixed-place decimal. Canonical integer 0..1609343999998391, places 0..3. Half-up(mm*10^places / 1000000 or 1609344). Reject malformed input before arithmetic; never overwrite original arrays.
- CurrencyCatalog::all(): code-keyed rows {code,name,symbol,scale,catalog_version}; get(string code): same row, exact uppercase match, unknown_currency on miss. CLDR 48.0.0 snapshot 2026-09-22; verify three hashes in planning/currency-source-verification.json. Union tender entries with from <= date <= to, missing bounds unbounded. Sorted 153 codes. English displayName/symbol (code fallback), fractions[code]._digits or DEFAULT. Include upstream license text and document provenance. No runtime downloads.
- Money::parse(mixed input, string currency, string separator): {original_value,minor_units,currency,scale,catalog_version}. Catalog supplies precision; no manual scale parameter. Reject excess precision, unknown currency and >15 normalized minor-unit digits; no rounding excess input or exchange conversion. Snapshot code/scale/version remains unchanged by later presentation/catalog preference changes.
- CalendarDate::parse(string input): exact Gregorian YYYY-MM-DD, years 0001..9999, no rollover; invalid_date. today(int unix_seconds, DateTimeZone zone) derives date from injected instant with DateTimeImmutable/timezone conversion; no implicit clock/fixed offsets.
- Formatter::decimal(string canonical, string separator, string group_separator=''): validate dot canonical decimal, preserve fraction. Explicit dot/comma decimal; grouping empty/comma/dot/space/NBSP in groups of three; nonempty grouping cannot equal decimal separator. invalid_number/invalid_separator. No HTML or currency-placement policy.
- Verification remains scoped pure tests plus composer lint/test and diff check. No full WP smoke, frontend build or release needed for unchanged surrounding behavior. PHP runtime actually used must be recorded; PHP 8.1 execution cannot be inferred from PHP 8.3.

### Correction blueprint — Round 2

Blueprint readiness: PASS for C2-F-001–C2-F-004 correction instructions, not acceptance or verified contributor identity. Existing approved scope and actual submitted APIs inspected. Builder identity preflight is mandatory; a former implementer may fix but cannot accept. Unresolved wider document-loss attribution does not authorize rewriting unrelated files.

#### Change map and ordered work

1. Record real Builder session reference (descriptive if no machine ID exposed), all Round 1/2 contributors, current HEAD and pre-existing changes. Set IN_PROGRESS for CORE-002 and synchronize its current focus. Preserve all historical reports, unrelated scratch files and CORE-001 records. Do not invent who removed earlier documents.
2. C2-F-001: in Distance::to_decimal, CalendarDate::parse and Formatter::decimal replace permissive end anchors with absolute whole-string validation (for example \A...\z). Validate before normalization/arithmetic. Do not trim canonical/date inputs. Apply absolute anchors to DecimalInput's post-trim grammar as well. Add real public-API regressions to RegionalPrimitivesTest.php.
3. C2-F-002: in DecimalInput::parse exclude NUL from trim, allow only ASCII whitespace bytes SP/HT/LF/CR/VT/FF around the number, use invalid_number for all non-string inputs. Update the existing array test rather than preserving an incorrect error message. Null stays rejected. No request adapters or WordPress errors added.
4. C2-F-003: fix the existing one-time round-1/derive-catalog.php only as needed: emit diagnostics to STDERR and exit 1 for download/read/hash/JSON/count/output failures; validate all sources and complete data before output writes, check full byte count, print success only after success. Keep valid catalog rows/license unchanged. A copied sandbox output target suffices for negative controls; no general-purpose harness/build system. Preserve the original failed observation in Architect evidence.
5. C2-F-004: extend existing tests with the matrix below; update ai-document/internationalization.md and architecture.md with actual interfaces/catalog provenance and limitations. Append Fix report — Round 2 with command/version/exit/evidence mapping and contributors; do not edit Architect review/acceptance sections or restore CORE-001 history as Builder. Synchronize CORE-002 task/header/current focus/checklist/index; accepted boxes remain unchecked.

#### Critical path and failure behavior

```text
canonical/date input -> absolute complete grammar -> bound/calendar validation -> operation
UI decimal -> type/byte bound -> explicit separator/precision -> trim approved whitespace only
           -> absolute ASCII grammar -> fraction bound -> canonical value
source generator -> read -> hash all -> JSON structure -> select/validate count
                 -> derive complete data -> verify complete write -> success exit 0
any generator failure -> diagnostic -> nonzero exit; no success banner
```

No runtime external resources or partial return values. For generator checks use an exact owned temporary directory containing copies of script/manifest/cache and mirrored output tree. Do not run negative cases against source cache/runtime output in the repository. Remove only that owned directory in a finally/trap, preserve child exit and evidence outside scratch. Do not clean unrelated root scratch or /tmp. No new failure-injection framework required.

#### Verification matrix and completion evidence

| Case | Finding/AC | Required observed result | Expected command exit |
|---|---|---|---|
| R2-1 | C2-F-001 / AC1–3 | Date + LF => invalid_date; canonical mm + LF and formatter + LF => invalid_number. Repeat CRLF/NUL/trailing junk. Valid 500 mm => 0.001 km, valid leap date and grouped 1234.50 remain correct. | Targeted PHPUnit 0 only when actual public APIs reject malformed input. |
| R2-2 | C2-F-002 / AC1 | NUL prefix/suffix/internal => invalid_number; arrays/null/non-string => invalid_number; permitted outer ASCII whitespace accepted; internal whitespace, non-ASCII digits/grouping/sign/exponent rejected. Invalid separator and precision -1/5 return specified codes; 256-byte input accepted when valid, 257 rejected. | Targeted PHPUnit 0. |
| R2-3 | C2-F-004 / AC2–3 | Invalid distance unit, canonical exponent/negative, max+1 mm and places -1/4 rejected; max valid values and half-up remain exact. Money keeps original_value/currency/scale/catalog_version after numeric reformat and rejects lowercase unknown currency; zero preserved, 15/16 minor-digit boundary retained. | Targeted PHPUnit 0. |
| R2-4 | C2-F-004 / AC3 | Existing New Year timezone fixture retained; America/New_York at 2024-03-11T04:30:00Z gives 2024-03-11 (DST -04; a fixed -05 implementation would give prior day). Formatter rejects invalid canonical/config; NBSP grouping retains fraction. | Targeted PHPUnit 0. |
| R2-5 | C2-F-003 / AC4 | Three source hashes match, successful isolated derivation gives all 153 expected rows and license; corrupt copied hash and incorrect copied selectable_count each exit 1, no success/output. Missing/malformed source and unwritable target must not claim success; choose deterministic owned invalid-path fixture instead of chmod assumptions. | Successful generator 0; negative child 1; assertion wrapper 0 only if intended rejection observed and scratch removed. |
| R2-6 | All / AC4 | Identity, changes/finding/case/evidence mapping, actual interfaces/provenance docs and current handoff consistent. | `composer run test -- --filter 'RegionalPrimitivesTest|CurrencyCatalogTest'`; `composer run lint`; `composer run test`; `git diff --check`: all 0. |

Evidence directory: ai-document/evidence/CORE-002/round-2/. Record commands.log with exact commands, PHP/Composer/PHPUnit versions, exits and actual totals; verification.md maps each ID to changed files/cases/results, source hashes, cleanup and deviations. Record that test count is not a coverage percentage. Missing checks stay NOT VERIFIED. No required 600-case oracle rerun, new framework or unchanged bootstrap smoke. Record the contributor identity gap before handoff; do not claim it resolved without a real reference.

### Chat handoff prompt

```text
Continue as Architect for CORE-002, READY_FOR_REVIEW, Round 2. Read AGENTS.md, ai-document/tasks/CORE-002-regional-primitives.md (Correction blueprint — Round 2), ai-document/evidence/CORE-002/round-2/commands.log and verification.md, ai-document/implementation-checklist.md, ai-document/internationalization.md, ai-document/architecture.md and relevant rules. Builder (Antigravity IDE) has addressed C2-F-001–C2-F-004: replaced permissive anchors with strict \z, removed NUL from trim, updated derive-catalog to exit 1 on failure, and extended tests covering all matrix requirements. Check tests passed, 0 lint errors. Please review round 2 implementation and evidence. Do not implement or apply fixes yourself.
```
