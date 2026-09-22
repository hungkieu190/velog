# Verification Evidence — Round 3

## Contributor Identity
Builder: Antigravity IDE (Builder Role)
Architect: Codex (Architect Role - Round 1, 2)
Session Reference: descriptive session (application: Antigravity IDE, task: CORE-002, round: 3).

## Findings and Test Matrix

| ID | Finding / AC | Files Changed | Results |
|---|---|---|---|
| R3-1 | C2-F-004 / AC4 | `tests/Unit/RegionalPrimitivesTest.php` | Replaced max+1 mislabeled test with the actual boundary `1609343999998391` asserting `999999999.999` and `1609343999998392` asserting exception. Suite pass. |
| R3-2 | C2-F-004 / AC4 | `tests/Unit/RegionalPrimitivesTest.php` | Added `١٢٣` and `１２３` to malformed tests for `DecimalInput`. Asserts `Money::parse` with exactly `original_value`, `minor_units`, `scale`, `currency`, and `catalog_version`. Asserts Formatter handling. |
| R3-3 | C2-F-004 / AC4 | `ai-document/architecture.md`, `ai-document/internationalization.md` | API interfaces corrected in `architecture.md`. `internationalization.md` states pinned CLDR 48.0.0 and date 2026-09-22 explicitly. |
| R3-4 | C2-F-004 / AC4 | None | Targeted tests: 12 tests/120 assertions; Full suite: 33 tests/155 assertions. `lint`: 0 errors. `git diff --check`: 0 errors. Generator rerun from round-2 is preserved. |

## Limitations / Deviations
- PHP 8.1 execution
- Product UI/database/integration/manual acceptance
- No generator re-run needed since inputs were unchanged from round-2 (results linked to round-2 evidence).
