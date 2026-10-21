# CODE-001 implementation-only handoff — 2026-10-21

## Decision and scope

Product Owner explicitly instructed completing all application code first, deferring tests/fixes and removing Tester gates. Codex is sole Developer. HIST-001 and REM-001 remaining application source is complete; CODE-001 is DONE as an implementation-only checkpoint. No major feature is newly accepted and no runtime readiness is claimed. Functional parents remain AWAITING_MANUAL_ACCEPTANCE with Next actor Developer; all test scheduling is DEFERRED.

## Delivered code and source inspection

- Private finalized history: inclusive date/type/text filters, date/ID descending order, 50-row pages, unfiltered current odometer provenance, original snapshots/currency and whitelisted escaped correction before/after fields. Vehicle is authorized once and actor labels fetched in a batch; no customer/type lookup per row. Required meta cardinality and malformed envelopes fail closed. A 10000-record per-vehicle scan limit is explicit.
- Manager-only manual maintenance queue: required title/active vehicle/at least one trigger; date OR known exact distance; unknown distinct from zero; future snooze, read-derived expiry, resume and terminal completion. Completion attribution is generated server-side. Native private CPTs, capability/nonces, UUID/version checks, closed fields, escaping and repository audit are reused.
- Queue filters/count/paging operate on SQL effective state before bounded hydration. Projection agreement is checked for hydrated records. Reminder-triggered odometer index initialization and existing service/vehicle refresh use the pinned write unit; failed after-write effects return errors to roll back. Index values are derived, not authoritative record values/versions. Vehicle cache invalidation uses existing touched-ID handling. These are source observations, not runtime proofs.
- Native admin menus, vehicle/service links, scoped Sass and generated CSS added. Existing release configuration recursively covers runtime directories; no release was created.
- Workflow override installed in AGENTS.md, role/rule documents and both set-up-new workflow documents; current task/checklist/README and test debt synchronized. Historical accepted customer/vehicle work and prior evidence retained.

## Actual mechanical checks

| Check | Actual result | Evidence |
| --- | --- | --- |
| Production asset build | Completed; build reports 4 files | build.txt |
| PHP syntax, final source | 50 source files, 0 errors | php-syntax.txt |
| Coding-standard source inspection | 0 errors, 83 line-length warnings across inspected files (80 in seven new files; 3 in schema) | source-standards.txt |
| Tracked diff whitespace | Exit 0 | diff-check.txt |
| Exact inspected source/generated hashes | Recorded, not a functional result | source-sha256.json |

The first syntax pass found a missing closing brace in ReminderPage. It was corrected before the successful final syntax pass; initial failure is preserved in php-syntax-initial.txt. Formatting was applied with PHPCBF. Full-project static analysis, PHPStan, automated suites, live requests, browser checks and data fixtures were not run in this code phase. No functional PASS is recorded.

Current generated admin.css SHA-256: `f773ea03bc1a3924cceb2afe0755783b2d4138460b944a304892a6a5fe09377d`. Prior approved asset pins and SERV evidence remain historical; the disposable customer regression pin requires reviewed maintenance in the later test phase.

## Deferred verification and material limits

See ../../test-debt.md and HIST-001-TEST/REM-001-TEST. In particular: transactional index rollback/replay/concurrency/cache behavior, malformed projections, count/page drift under concurrent writes, role/privacy denial cases, unit/locale/date boundaries, business validation of G-07, responsive/RTL/accessibility, all prior regression suites and MVP package/performance acceptance. A date/distance queue implementation does not establish its accuracy or speed on the G-08 dataset. Structural preflights and derived refresh scan costs remain unbenchmarked. No production-ready claim is made.

## Workspace and cleanup

Existing unrelated dirty files were preserved. No scratch fixture, temporary test environment, live seed, background process, dependency, commit, push, deployment or release was created in this phase. Only required implementation/documentation/generated artifacts and retained evidence were added. Final git status was inspected; no owned scratch files require deletion.

## Next action

Developer retains the consolidated test/fix debt as DEFERRED until Product Owner resumes that phase. The documented MVP application code queue has no further unimplemented item; MVP-001 verification/release acceptance remains debt. Follow CODE-001's current handoff prompt.
