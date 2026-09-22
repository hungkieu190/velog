# CORE-002 independent review — Round 1

Date: 2026-09-22. Baseline HEAD: 9c4d1be, plus uncommitted/untracked submitted files. Reviewer: current Codex Architect conversation (descriptive reference, no machine ID asserted); this session has made no implementation contribution. Implementer identity is absent from the submitted task and is NOT VERIFIED. Do not infer independence from a client label. The user denied intentionally resetting documents; that reply did not identify the implementer.

Decision: CHANGES_REQUESTED. No acceptance checkboxes closed.

## Evidence actually inspected and executed

- All eight Regional PHP classes, both new unit test files, Builder round-1 verification.md and derive-catalog.php, source manifest/cache, task/checklist/index, relevant architecture/security/coding rules and callers. Only Regional helpers and tests call these new APIs; no runtime hook or database integration found in the scoped code.
- `composer run lint`: exit 0, PHPCS and PHPStan passed. PHPStan emitted an informational old-version notice; no upgrade is requested.
- `composer run test`: exit 0, PHP 8.3.6, PHPUnit 10.5.64, 29 tests / 90 assertions. This is the full suite, including 21 pre-existing tests; eight new tests account for 55 additional assertions. It is not coverage of every contract.
- Initial `git diff --check`: exit 0.
- Independent PHP probes invoked the real classes through tests/bootstrap.php: outputs recorded in verification.json. Their wrapper exited 0 because it captured observations; that is NOT a claim the observed validators passed.
- Python integer oracle (seed 20260922): 300 valid distance parses and 300 valid distance display conversions compared with exact integer half-up arithmetic; zero mismatches. This does not cover malformed input or prove every possible input.
- All three cached source SHA-256 values match the pinned planning manifest. Independent extraction from cached CLDR data matches all 153 complete runtime rows (code/name/symbol/scale/version). No runtime downloads or WooCommerce use found. License text is present in CurrencyCatalogData.php.
- Generator hash-mismatch control: copied generator, manifest and cache into a reviewer-owned TemporaryDirectory, corrupted only copied currencyData.json, ran the copied script. It printed hash mismatch but exited 0; no runtime file created. Temporary directory confirmed removed. Real generator was NOT run against repository output.
- Reviewed source/test hashes are recorded in reviewed-files.json. No application, test or generator fixes made by Architect.
- NOT VERIFIED: PHP 8.1 execution, product UI/database/integration/manual acceptance, complete submitted contributor identity and explanation/recovery of vanished historical documents. Those product flows are outside this pure-helper correction scope.

## AC verdicts

| Criterion | Verdict | Reason |
|---|---|---|
| AC1 | FAIL | NUL padding accepted; wrong stable error message for non-string input; malformed canonical formatting accepted. |
| AC2 | FAIL | Valid arithmetic fixtures and 600 oracle cases pass, but malformed canonical input with LF is accepted and changes magnitude. |
| AC3 | FAIL | Currency catalog and valid money examples pass; strict calendar parser returns a date containing LF. Snapshot/DST coverage incomplete. |
| AC4 | FAIL | Lint/full suite pass and scoped runtime code is pure, but required negative fixtures, command/evidence mapping, contributor record and documentation handoff are incomplete; generator signals source rejection as process success. |

## Findings

### C2-F-001 — P1: Whole-input validation permits a final newline

`Distance.php:78`, `CalendarDate.php:27`, `Formatter.php:29` use `$`, which can match before a final LF. Actual `Distance::to_decimal("500\n", "km", 3)` returns `0.005` because the newline reaches digit arithmetic as an extra zero; valid `500` returns `0.001`. `CalendarDate::parse("2024-02-29\n")` returns that malformed string. Formatter preserves a final LF. Reject these inputs before arithmetic/formatting; canonical APIs must not trim malformed input into validity. This violates existing exact-input requirements, not a new feature request.

### C2-F-002 — P2: Decimal input strips NUL and violates stable error contract

`DecimalInput.php:45` includes NUL in trim characters: `"\0" . "123" . "\0"` becomes valid `123`. NUL is not ASCII whitespace. At line 30, arrays/null raise message `Input must be a string` instead of revision-2 `invalid_number`; the submitted test locks in that divergence. Use the explicit ASCII whitespace set (space, HT, LF, CR, VT, FF), excluding NUL; reject remaining non-digit/non-separator content. Preserve optional-value semantics: callers own null, parser rejects it. Retain stable message codes, without requiring numeric exception codes.

### C2-F-003 — P2: Source verification failure exits successfully

`round-1/derive-catalog.php:33` and the count-mismatch branch use die(string), which returns process exit 0. Reproduced in an isolated copy with corrupt cached source. A successful command cannot be used as provenance evidence when validation failed. The one-time development generator must fail nonzero for hash/count/read/decode/write errors and print success only after complete output write. No new generator framework is requested.

### C2-F-004 — P2: Handoff loses approved baseline and omits required coverage/identity

On receipt, task header was DRAFT revision 1 / Unassigned although outgoing prompt and checklist rows said READY_FOR_REVIEW. Current focus pointed at CORE-001 Round 7. The revision-2 executable blueprint and later CORE-001/planning updates observed earlier in this same conversation had disappeared; cause unknown, user denies intentional reset. Historical evidence still exists. Do not attribute deletion to Builder without evidence, or interpret the restored old draft as revocation of prior approval.

The Builder report has no identity, baseline, command exits/versions, per-criterion mapping, cleanup/deviation report, or architecture/internationalization updates. Tests omit invalid canonical distance/unit/places, stable null/array errors, unsupported digits, several parser/config boundaries, money snapshot fields/preference invariance and a DST-sensitive case. Passing existing fixtures does not fulfill omitted requirements. Architect restores the necessary CORE-002 contract below in the task; wider CORE-001/history reconciliation remains Architect-owned, not permission for Builder to rewrite acceptance history.

Follow the task's Correction blueprint — Round 2. All four finding IDs remain OPEN until independent re-review.
