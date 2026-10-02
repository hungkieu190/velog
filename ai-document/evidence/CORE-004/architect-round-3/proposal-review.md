# CORE-004 Architect proposal review — round 3

## Decision

**REVISE PROPOSAL** for escalated CORE4-F-002, F-004 and F-005. No code edits for those findings are approved yet. This is a proposal review, not a third implementation review; their consecutive failure counts remain two.

The proposal at `ai-document/evidence/CORE-004/builder-round-3/F-002-F-004-F-005-proposal-rev1.md` repeats the round-2 invariants and names broad files. It does not yet describe the concrete implementation and test mechanism needed for safe execution:

1. **F-002 / F-001 boundary:** Name the function that reads the exact stored option row/serialized bytes, how it distinguishes missing from malformed data, how `get_settings()` exposes a safe result to the admin form, and how CAS uses the raw value rather than a normalized/re-serialized array. Specify error codes for unsupported schema, invalid version/scale/code and malformed POST fields, plus how the handler reacts without warnings or writes. Do not silently replace invalid stored fields with defaults.
2. **F-004 real concurrent oracle:** Describe the two independent processes/connections, their synchronization point before the conditional UPDATE, how each sees the same prior version, and what exact option row/version is asserted after one winner and one loser. A sequential second POST using an old version does not test the race.
3. **F-004 historical oracle:** Map the seed to the actual `RecordRepository::create( string $type, array $fields, WP_User $actor, string $request_id )` contract and identify valid fields/record type. Name the full envelope/metadata bytes captured before and after settings and locale switches, and how canonical distance and currency/scale identity are asserted. Direct `postmeta` insertion of a synthetic payload is not an accepted oracle.
4. **F-005 quality and cleanup:** Correct `phpcs.xml.dist` to the actual `phpcs.xml` if that is the intended file. State which exact lint warnings will be fixed versus narrowly configured, how commands and runtime versions will be logged, and how every owned WordPress/database/process/temp resource is identified and removed on success and failure. Include where manual UI observations and NOT VERIFIED cases are recorded.

Submit revision 2 as a read-only proposal. If a listed interface or runner cannot support the requested oracle, show the contradiction with file/symbol evidence and propose the smallest testable adjustment. Do not implement escalated findings before an explicit APPROVED FOR IMPLEMENTATION decision.

## Separate F-001, F-003, F-006 review

- F-001: The report repeats output from a sequential stale-version fixture. No independent simultaneous-writer or option-row evidence was supplied. Verification remains open.
- F-003: `Assets::enqueue_styles()` has an exact hook allowlist and points to `assets/css/admin.css`, but the cited smoke output contains no URL or enqueue assertion. Verification remains open.
- F-006: `AdminMenu::add_menu_pages()` now uses the existing `mf_velog_read_records` capability and provides a root callback. This addresses the static mismatch. The claimed role-navigation CLI script/log is not in the evidence directory, so runtime verification remains open.
- Intake metadata: task and README say READY_FOR_REVIEW/Architect while checklist says CHANGES_REQUESTED/Builder. The task's prompt is also stale. Synchronize all three to CHANGES_REQUESTED/Builder for the revised proposal handoff.

## Review scope

Reviewed current staged source, proposal rev1, Builder round-3 report and available evidence files. Independent `git diff --check` exited 2 due to trailing whitespace in the Builder round-3 report. No application code was changed by Architect. No runtime smoke was repeated because this decision concerns an incomplete read-only proposal, and the submitted excerpts do not independently prove the claims.
