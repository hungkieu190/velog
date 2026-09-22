# CORE-002 Architect acceptance — Round 4

Date: 2026-09-22. Baseline: `main` at `9c4d1be31a78db7448ef5244a7a8a1d9f4401287` plus the reviewed working files. Reviewer: current Codex Architect conversation; this reviewer made no application, test, or generator implementation changes.

## Decision

DONE. AC1–AC4 PASS. C2-F-001–C2-F-004 CLOSED. Round 4 completes the two retained regression guarantees left open by Round 3. No product UI, database, integration, release, or manual acceptance is inferred from this pure-helper task.

## Independent verification

- `composer run test -- --filter 'RegionalPrimitivesTest|CurrencyCatalogTest'`: exit 0; 12 tests / 117 assertions on PHP 8.3.6 and PHPUnit 10.5.64.
- `composer run lint`: exit 0; PHPCS and PHPStan pass. The PHPStan old-version notice is informational and does not change the result.
- `composer run test`: exit 0; 33 tests / 152 assertions.
- `git diff --check`: exit 0.
- Source inspection confirms the full USD snapshot is asserted before and after both formatter calls. The valid maximum distance assertion is outside the exception block; only immediate maximum+1 is inside the expected-rejection block.
- All eight runtime Regional class hashes and the catalog generator hash equal the independently reviewed Round 3 baseline. Only the scoped unit test changed in Round 4.
- Round 1 exact-arithmetic/catalog evidence and Round 2 malformed-input/generator evidence remain applicable because their reviewed inputs are unchanged. They were not rerun or misreported as new executions.

The Builder evidence reported 12/122 targeted and 33/157 full assertions. Fresh independent runs produced 12/117 and 33/152. The required assertions are present and pass, so this is an evidence-count correction rather than an unmet criterion. Architect acceptance records the observed totals.

## Criteria

- AC1 PASS: explicit locale parsing, unknown-versus-zero behavior, ASCII-only numeric grammar, NUL/control and malformed input rejection are covered.
- AC2 PASS: exact string arithmetic, km/mi conversion, half-up behavior, maximum acceptance and immediate overflow rejection are covered without persisted binary floats.
- AC3 PASS: money code/scale/catalog version and the complete snapshot survive alternate formatting; strict calendar and timezone/DST cases pass.
- AC4 PASS: scoped tests, full suite, PHPCS, PHPStan and diff check pass; catalog provenance and APIs are documented; no settings, hooks, database writes, runtime downloads, WooCommerce dependency, or frontend build changes were introduced.

## Identity and limits

Implementation submissions identify Antigravity IDE as Builder. Round 3 and Round 4 explicitly identify one continuous descriptive Antigravity session. Round 1 and Round 2 identify Antigravity Builder but expose no machine session ID; no other implementation contributor is disclosed in the shared reports. The current Codex Architect reviewer is a separate application/session and is not an implementation contributor. This limitation is recorded rather than inventing an identifier.

PHP 8.1 execution remains NOT VERIFIED. Product UI/database/integration/manual acceptance remains outside CORE-002 and NOT VERIFIED. No commit, deployment, publication, or active-site database access was performed.

