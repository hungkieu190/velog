# CORE-004 Architect proposal review — round 6

## Intake and decision

- Intake: PASS. Task, checklist, and README consistently identify `READY_FOR_REVIEW` and Architect as next actor. Builder round-6 proposal revision 4 and report exist. No escalated application code was changed.
- Decision: **REVISE PROPOSAL**. The implementation gate remains closed for CORE4-F-002, F-004, and F-005.
- This is the fourth rejected proposal review after two failed implementation reviews. Proposal reviews do not increase the implementation failure count; F-002/F-004/F-005 remain at two consecutive implementation review failures.

## Blocking findings

### CORE4-F-002 — raw option contract and write outcomes remain incomplete

1. `read_raw_option()` is described as returning the raw string, but the missing-row branch returns a default array. The proposal does not define a single return type or a result structure that carries both exact raw bytes and validated settings. This ambiguity affects `get_settings()` and `save_settings()` at the CAS boundary.
2. The update result table omits `$wpdb->query() === false`. Checking only `0` and `$wpdb->last_error` is insufficient. The first-insert plan still says “`INSERT IGNORE` or unique-key violation check”; an implementation proposal must choose one exact path and classify duplicate conflict separately from SQL failure.
3. Successful cache invalidation names only the option key. The missing-option insert path also needs the `notoptions` negative-cache entry and relevant `alloptions` state handled, with a fresh-process assertion.
4. The proposed concurrency action is always fired in production. “No callbacks are registered” does not disable it: another plugin can register a callback and alter or block a settings write. Gate the seam behind an explicit test-only condition that is false in production, and prove normal saves do not execute the barrier.
5. The unconfigured-state contract does not state exact `record_version`, schema/catalog and empty-value invariants. Replace “may be” with an exact accepted envelope and exact rejection cases.

### CORE4-F-004 — historical oracle remains noncanonical

6. The proposed schema validators are still permissive: `is_numeric()` followed by `(int)` accepts and truncates fractional/numeric-string odometers; any string is accepted as a distance unit or currency code; any integer scale is accepted independently of the code. The round-5 blueprint explicitly required validators that reject malformed and noncanonical regional identity. Use an integer canonical odometer policy, `km|mi`, `CurrencyCatalog::get()` and exact catalog-derived scale.
7. The switch sequence again gives only `JPY -> USD` as an example. The approved matrix has required km/mi plus JPY/USD/KWD and locale changes since proposal revision 1. Name the exact sequence and assert the complete envelope and parsed identity after every switch.
8. The proposal says the actual handler will be tested but still gives no executable request mechanism, commands, expected HTTP/exit outcomes, or how `wp_die()` paths are captured. Provide the concrete isolated runner and failure propagation.

### CORE4-F-005 — lint and cleanup plan remains incomplete

9. Independent `composer run lint -- --report=full` exits 1 with 28 warnings: 6 unknown-capability warnings, 20 line-length warnings, and 2 direct-query warnings. Revision 4 addresses capability configuration and direct-query suppressions but says nothing about the 20 line-length warnings. F-005 cannot pass with the current plan. List the affected files/lines and fix formatting rather than configuring away the line-length standard.
10. The proposed capability property syntax is plausible for the installed `WordPress.WP.Capabilities` sniff, but the implementation evidence must run the installed PHPCS and show those six warnings are removed. Add only capabilities actually registered by `Capabilities.php`.
11. Cleanup still does not define how the disposable WordPress/database instance is created or removed; “wp-env or an isolated test DB” is not a selected mechanism. The function removes only the temp directory. It also validates only that `TMP_DIR` is nonempty before recursive deletion, does not initialize/check child PIDs before use, and `INT`/`TERM` handlers clean up without explicitly exiting with the original signal/failure status. Choose one existing project-supported isolation runner, name its owned database/site resources, validate a task-specific temp prefix/path, stop and wait for initialized owned PIDs only, remove the owned disposable environment, and preserve the original exit status.
12. Evidence must be written to a new implementation round directory after approval. `builder-round-6` is the read-only proposal round and must not be reused for implementation evidence.

## Repeated-defect audit

| Stable finding | Repeated unmet requirement | Proposal rounds affected | Current assessment |
|---|---|---|---|
| CORE4-F-005 | Exact lint findings and a plan that reaches lint exit 0 | Revisions 1–4 / Architect rounds 3–6 | Four proposal rounds; revision 4 still omits all 20 line-length warnings. |
| CORE4-F-005 | Owned, complete cleanup on success and failure | Revisions 1–4 / Architect rounds 3–6 | Four proposal rounds; destructive deletion was removed, but disposable DB/site cleanup remains undefined. |
| CORE4-F-004 | Valid canonical historical schema and JPY/USD/KWD matrix | Revisions 1–4 / Architect rounds 3–6 | Schema shape improved, but strict validators and the KWD execution step remain missing after explicit round-5 instructions. |
| CORE4-F-002 | Decision-complete raw-row CAS and failure classification | Revisions 1–4 / Architect rounds 3–6 | Binary CAS and input validation improved, but raw/missing return types, `false`, insert choice and negative-cache handling remain unresolved. |

These are continuing defects, not new findings. Renaming or splitting them does not reset their history.

## Evidence inspected

- `ai-document/evidence/CORE-004/builder-round-6/proposal-rev4.md` and `report.md`.
- Architect proposal reviews from rounds 3–5 and Builder proposals from revisions 1–4.
- Current `ShopSettings.php`, `RegionalSettingsPage.php`, `RecordSchema.php`, `CurrencyCatalog.php`, `Capabilities.php`, `phpcs.xml`, and installed WPCS capability sniff.
- Independent command: `composer run lint -- --report=full` exited 1 with 28 warnings. No source or test code was changed by Architect.

## Required revision 5 format

Builder must submit a concise delta proposal that answers blocking items 1–12 one by one. For each item, state the exact file/symbol, chosen mechanism, executable command, expected exit/result, and evidence path. Do not repeat already accepted prose. Do not implement F-002/F-004/F-005 until Architect records `APPROVED FOR IMPLEMENTATION`.

F-001, F-003, and F-006 remain unverified on the normal implementation path.
