# MVP-001: MVP integration, performance and local package acceptance

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: MVP-001 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CORE-001/002/003/004, DATA-001, CUST-001, VEH-001, SERV-001, HIST-001 and REM-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
Passing individual task tests does not establish the complete customer-to-service-to-reminder journey or a runtime-complete installable package. The approved MVP needs end-to-end, privacy, performance and user evidence.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `tests/fixtures/mvp-001-verify.php`: deterministic synthetic journey, cross-feature permission/retention checks and dataset seed/query timings in isolated WP only.
- Extend `tests/workflow/product-smoke.sh` MVP mode and its bounded performance timeout, without weakening cleanup/error handling.
- New `ai-document/walkthroughs/MVP-001.md`; task evidence under `ai-document/evidence/MVP-001/`.
- Update `ai-document/release-readiness.md`, `ai-document/testing-strategy.md`, `CHANGELOG.md`, root `README.md` feature/installation truthfulness and `ai-document/architecture.md` only if final integration facts require it.
- Use existing release/build scripts unchanged; generated outputs may be refreshed only by approved commands. Runtime defects return to owning Builder task with Architect correction blueprint; no opportunistic application fixes here.
- No new dependency, automatic version increment, git commit/tag/push, upload/deployment or production data change.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-001/002/003/004, DATA-001, CUST-001, VEH-001, SERV-001, HIST-001 and REM-001 DONE.
- Remaining readiness gates: G-08 dataset/reference-machine target and operational privacy gate; user manual acceptance remains required. Freeze acceptance matrix/package baseline after predecessor acceptance; no version bump or publication inferred.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Use a new isolated site installed from the actual ZIP with no development vendor or source-tree autoloader fallback. Record ZIP SHA-256, file manifest, plugin version consistency and exact WP/PHP/DB/node/npm versions. Cover minimum PHP 8.1 plus available local PHP, pinned WP 6.4.3 and 6.7.2; broader current-version support must be explicitly scoped and verified before claiming it.

Journey: manager configures explicit units/currency, creates customer and vehicle; technician creates/finalizes own service with exact cost; manager records reasoned correction, reassigns owner and retrieves immutable history; creates date/distance reminders, snoozes/completes; archives/restores and verifies retained records across deactivate/reactivate/uninstall/reinstall. Test unauthorized variants with valid nonce and wrong capability separately from invalid nonce. Repeat locale/unit/currency fixtures from internationalization.md, including RTL and keyboard.

Performance dataset/target are G-08 proposal. Run after 5 warmups, record all 30 timings per operation, p95 calculation, query counts, reference hardware/cache settings and seed distribution (including due rows past early pages). A failed target triggers a bounded design correction, not silent reductions to fixture size/target or addition of custom tables.

Retention/export/erasure procedure is a product-owner production gate, not a legal compliance claim. Local technical acceptance may be recorded with a clearly separated production hold; no task should advertise worldwide compliance. Manual user verdict is required before DONE. If all automated checks pass, Architect may set AWAITING_MANUAL_ACCEPTANCE with the exact walkthrough and matching handoff.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Verify predecessor acceptance and contributor independence; pin matrix, synthetic data and required user walkthrough before work.
3. S3: Run lint/test/workflow/build/release commands with exact exits; install the produced ZIP in owned isolated sites and run complete positive/negative/retention journeys.
4. S4: Measure agreed dataset, perform regional/accessibility walkthrough and record limitations; Architect reviews then requests user manual acceptance, preserving any production hold.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
verify_package():
  run required gates; abort on any nonzero
  package through npm run release; hash/list actual ZIP
  install ZIP in new isolated WP, activate, execute journey + negative matrix
  seed agreed performance dataset; warm up; time 30 requests; report p95 and query counts
  clean owned resources; preserve logs/hash/manual checklist
  Builder reports READY_FOR_REVIEW, never DONE
accept(): Architect verifies independence/evidence; user completes required walkthrough
```

### Failure and resource lifecycle
Any quality/runtime/package check failure prevents acceptance; preserve failed logs and route to owning task with stable finding IDs and correction blueprint. Do not publish or tag a ZIP. Cleanup must preserve the verification failure exit code; performance mode may raise total timeout only with an explicit value recorded before running. Manual absence is not permission to self-accept.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Actual local ZIP contains complete runtime and installs/runs without development environment fallback.
- AC2: Complete international staff journey and negative authorization/retention cases pass across required runtime matrix.
- AC3: Agreed performance dataset/targets and query correctness are evidenced without hidden shortcuts.
- AC4: User manual acceptance and production limitations are explicitly recorded before final DONE decision.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Clean build environment and existing version declarations | composer run lint; composer run test; npm run test:workflow; npm run release | All exit 0; ZIP manifest/hash and runtime autoload verified; failures prevent new success artifact |
| V2 | AC2 | Fresh ZIP-only site; manager/technician/subscriber; locale/unit/currency matrix | tests/fixtures/mvp-001-verify.php via product runner | End-to-end journey passes; denied mutations do not alter data; retain snapshots across preference/owner/lifecycle changes |
| V3 | AC3 | G-08 seeded 1000/2000/20000/5000 records; 5 warmup +30 samples each | tests/fixtures/mvp-001-verify.php performance mode, exact command fixed at readiness | Correct pages/totals and warm p95 <=2s on agreed machine; raw timings/query counts retained |
| V4 | AC4 | User performs independent setup/customer/vehicle/service/reminder/history journey | ai-document/walkthroughs/MVP-001.md | Dated explicit user verdict; missing manual evidence stays NOT VERIFIED and prevents DONE |

## Verification instructions
- Targeted test command after implementation: `composer run test`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=MVP-001 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/MVP-001/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for MVP-001, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/MVP-001-acceptance-package.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is mvp integration, performance and local package acceptance, AC1–AC4. Require CORE-001/002/003/004, DATA-001, CUST-001, VEH-001, SERV-001, HIST-001 and REM-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
