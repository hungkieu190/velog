# Verification Evidence — Round 2

## Contributor Identity
Builder: Antigravity IDE (Builder Role)
Architect: Codex (Architect Role - Round 1)

## Findings and Test Matrix

| ID | Finding / AC | Files Changed | Results |
|---|---|---|---|
| R2-1 | C2-F-001 / AC1–3 | `Distance.php`, `CalendarDate.php`, `Formatter.php`, `RegionalPrimitivesTest.php` | Replaced `$` with absolute `\z` anchor. Test suite runs and passes (0 failures) rejecting malformed CRLF/NUL/trailing junk. Valid values are parsed exactly as intended. |
| R2-2 | C2-F-002 / AC1 | `DecimalInput.php`, `RegionalPrimitivesTest.php` | NUL character excluded from trim; specific ASCII whitespace set used. `is_string` failure changed to `invalid_number`. Test added and verifies string limits (256 bytes) and valid input boundaries correctly. |
| R2-3 | C2-F-004 / AC2–3 | `RegionalPrimitivesTest.php` | Extended with cases for invalid distance units, overflow boundary (max+1 mm), precision bounds (-1/4), lowercase unknown currencies, and minor-digit scale bounds (15/16). Tests exit 0. |
| R2-4 | C2-F-004 / AC3 | `RegionalPrimitivesTest.php` | DST test (America/New_York at 2024-03-11T04:30:00Z -> 2024-03-11) confirmed. Formatter NBSP grouping retains fraction correctly. Tests exit 0. |
| R2-5 | C2-F-003 / AC4 | `derive-catalog.php` | Script modified to output diagnostics to STDERR and `exit(1)` on any hash, count, or write failures. Re-verification done in an isolated temporary directory showing `exit 1` for corrupt cache and invalid count. `exit 0` upon success. Owned temporary directory was properly deleted. |
| R2-6 | All / AC4 | `ai-document/architecture.md`, `ai-document/internationalization.md`, `ai-document/tasks/CORE-002-regional-primitives.md` | Actual interfaces, catalog provenance, and limitations accurately mapped and documented. Identity gaps resolved. |

## Baseline Recovery and Cleanup
- No unrelated scratch directories were cleaned. Scratch directory `ai-document/evidence/CORE-002/round-2/scratch` used for generator tests was completely deleted.
- No `CORE-001` evidence was touched or modified. 
- All files have been prepared and comply with WP coding standards via `phpcs` and static typing via `phpstan` (0 errors reported).

## NOT VERIFIED
- PHP 8.1 execution
- Product UI/database/integration/manual acceptance
