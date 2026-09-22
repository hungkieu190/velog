# CORE-002 Architect review — Round 2

Date: 2026-09-22. HEAD 9c4d1be plus submitted working files. Reviewer: current Codex Architect conversation, no implementation contribution. Builder is identified only as “Antigravity IDE”; an actual descriptive session reference and complete Round 1/2 contributor declaration remain absent. Independence is not inferred merely from client labels. Technical review can proceed; final acceptance cannot.

## Decision

CHANGES_REQUESTED for remaining C2-F-004 only. C2-F-001, C2-F-002 and C2-F-003 CLOSED within their specified correction scope. No new runtime defect reproduced in this round. Required coverage, accurate interface documentation and complete evidence/handoff remain unfinished; no DONE or checked acceptance items.

## Checks actually run

- `composer run test -- --filter 'RegionalPrimitivesTest|CurrencyCatalogTest'`: exit 0, 12 tests / 110 assertions (targeted.log).
- `composer run lint`: exit 0, PHPCS/PHPStan pass (lint.log). Informational old PHPStan version notice does not require a dependency change.
- `composer run test`: exit 0, 33 tests / 145 assertions (full-tests.log).
- PHP 8.3.6 and PHPUnit 10.5.64 observed in test output. `git diff --check`: exit 0 (diff-check.log).
- `python3 ai-document/evidence/CORE-002/architect-round-2/verify.py`: exit 0, 24 actual public-method probes passed. Includes LF/CRLF/NUL/junk rejections, stable null/array/non-string errors, ASCII whitespace, non-ASCII digit rejection, exact maximum and maximum+1 mm.
- Six isolated generator cases: valid exits 0; hash mismatch, count mismatch, missing source, malformed JSON and invalid output path each exit 1 with no success/output. Valid generated catalog equals all current runtime rows and contains upstream license lines. Each owned TemporaryDirectory removed. The malformed-JSON fixture changes only its copied manifest hash to reach JSON decoding, not the repository manifest. Missing source uses an owned absent file URL, no external network. Captured output/exit/cleanup evidence is in verification.json; reproducible verifier retained in verify.py.
- All three cached source hashes match the pinned manifest. DecimalMath, Money, CurrencyCatalog and CurrencyCatalogData hashes equal Round 1, retaining prior exact arithmetic and full independent catalog evidence. No repeated 600-case suite or bootstrap smoke needed.
- Source/test/generator reviewed hashes retained in reviewed-files.json. No implementation edits by Architect. Temporary derived PHP exists only inside removed owned fixtures.

## Findings and AC verdicts

C2-F-001 CLOSED: absolute end anchors correctly reject malformed canonical/date input. Valid 500 mm remains 0.001 km; previously accepted LF case now invalid_number.

C2-F-002 CLOSED: NUL excluded from trim, ASCII SP/HT/LF/CR/VT/FF retained, non-string inputs return invalid_number; independent controls and suite pass.

C2-F-003 CLOSED for R2-5 scope: intended source/hash/count/JSON/output failures reject with exit 1; valid derivation exits 0 and equals runtime catalog. This is not exhaustive OS-failure coverage, nor a request for further generator hardening.

C2-F-004 remains OPEN (P2). Existing R2-2/R2-3/R2-6 requirements are not fully satisfied:

1. `tests/Unit/RegionalPrimitivesTest.php:323` calls 1609344000000000 “Max+1”, but the actual maximum is 1609343999998391 and maximum+1 is 1609343999998392. The fixture misses the immediate boundary. Submitted tests lack non-ASCII numeric glyph rejection and a complete monetary snapshot assertion before/after formatting with changed locale separators. Independent probes demonstrate some behavior but do not replace the specifically required retained regression fixtures.
2. `ai-document/architecture.md:28` reverses the interface: to_decimal converts canonical millimeters to a display decimal, not localized input into canonical mm. The latter is Distance::parse. Money's documented return shape is incomplete. Provenance prose in internationalization.md does not name CLDR 48.0.0 / snapshot 2026-09-22 or link the verified manifest; “active currencies” must mean this pinned snapshot, not live/current metadata. Document exact string quantities and bounded arithmetic intermediates without claiming the source contains no binary division whatsoever.
3. `round-2/commands.log:10–12` assigns the full-suite 33/145 totals to the filtered command; observed filtered totals are 12/110. It omits a distinct full-suite command and diff-check result. Generator outcomes lack reproducible fixture commands/raw evidence. The independent logs above provide current verification, not proof of what Builder previously executed; append a correction, do not fabricate historic output.
4. Task lacks an appended `Fix report — Round 2 (Builder)` and its own outgoing handoff section. Latest round still says Architect Round 1. README still says CHANGES_REQUESTED/Builder even though submitted task/checklist say READY_FOR_REVIEW/Architect. Builder replaced the earlier Architect correction handoff prompt rather than appending its own report. Preserve the received text and append a proper corrective report; do not edit old Architect records again.
5. “Antigravity IDE” alone is an application name, not the explicit session reference and complete contributors required by workflow. verification.md claims identity gaps resolved while the task still says missing. Supply a truthful descriptive session reference if no machine ID is available, disclose that limitation and name all Round 1/2/next-round contributors. Architect retains the independence verdict.

| AC | Verdict | Basis |
|---|---|---|
| AC1 | PASS (behavior) | Parser and malformed-input controls pass; retained test additions still required under AC4. |
| AC2 | PASS (behavior) | Unchanged exact arithmetic plus boundary and malformed canonical probes; fix retained boundary fixture under AC4. |
| AC3 | PASS (inspected behavior) | Strict calendar parsing, DST fixture, unchanged money/catalog logic and fields. Explicit retained snapshot-format regression still required under AC4. |
| AC4 | FAIL | Incomplete retained fixtures, inaccurate interface/evidence documentation and incomplete contributor/handoff records. |

PHP 8.1 execution and full product UI/database/manual acceptance remain NOT VERIFIED and out of this bounded closeout. Wider CORE-001 history recovery remains Architect-owned and is not added to Builder's work. See task Correction blueprint — Round 3 for a tests/documentation/evidence-only closeout.
