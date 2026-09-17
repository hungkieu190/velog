# WF-001: Establish the VeLog development workflow

## Current handoff
- Status: DONE
- Plan revision: 1
- Architect / Builder identity or session reference: User explicitly reassigned this session to Architect for self-review and completion; review and corrections are recorded separately from the Builder report.
- Related checklist items: WF-001 / AC1–AC7 in ../implementation-checklist.md.
- Baseline branch and commit; pre-existing relevant changes: main, 3037aff; initially clean working tree. Ignored local dependencies, lockfiles and release artifacts existed.
- User approval reference and approved scope: User requested setup according to the two supplied documents. User explicitly approved WF-001 with "ok, làm đi" on 2026-09-17; S1–S6 and D1–D9 are approved.
- Latest round: Final acceptance — Review 2
- Latest implementation/review round: Review 2
- Next actor: Architect
- Next actor and exact next action: Architect proceeds to PLAN-001 to propose and obtain approval of the master plan/MVP before feature implementation. WF-001 remains accepted.

## Problem and intended behavior
Existing build/release scripts do not meet the supplied workflow contracts. See ../release-readiness.md findings F-001–F-009 for static evidence. Establish a maintainable, reproducible workflow without implementing vehicle features or altering product behavior.

The product plans remain drafts. Proposed decisions D1–D9 in ../decisions.md are included in this approval scope. Node support version must be verified during implementation; an additional dependency requires explicit scope approval.

## Scope and references
Read AGENT.md, rules/ai-agent.md, rules/coding-style.md, rules/security.md, rules/architecture.md, rules/release-checklist.md, ai-document/README.md, ai-document/architect-builder-workflow.md, ai-document/source-build-release-workflow.md, ai-document/decisions.md, ai-document/build-and-release.md, ai-document/testing-strategy.md and ai-document/release-readiness.md.

Allowed files: AGENTS.md (new), AGENT.md, workflow-related portions of rules/ai-agent.md and rules/release-checklist.md, README.md, CONTRIBUTING.md, LICENSE (distribution completeness only), package.json, npm-generated package-lock.json, existing composer.lock tracking policy, .nvmrc, .gitignore, scripts/, bin/build-assets.js, bin/release.sh, bin/check.sh, frontend files moving from src/assets/ to src/js/ and src/css/, generated assets/, relevant .github/workflows/ configuration, ai-document/ and isolated workflow tests/fixtures.

Preserve PHP source, hooks, namespace, business logic and unrelated rules. Preserve unrelated npm scripts/dependencies. No deployment, publication, commit, push, tag, real database mutation or feature implementation. Do not delete existing source until replacement output and references are verified. Do not untrack unrelated files.

## Implementation steps
1. S1: Reconcile instructions and legacy documentation; create AGENTS.md with roles, statuses, required handoffs, English technical content, Vietnamese user communication, WordPress security standards and frontend/build rules. Link the documentation index. Keep AGENT.md as a compatible reference without conflicting rules.
2. S2: Implement dependency-light localhost progress dashboard using checklist and task Markdown. Show focus, owner, phase counts, open work, history, findings, prompt coverage, mismatches and source timestamps. Support /, /api/progress and configurable port; read only.
3. S3: Preserve Sass/esbuild and migrate frontend source mapping. Implement explicit config, staged outputs, safe cleanup, local dependency calls, missing-entry errors, development maps and production minification/notices/resource checks. Preserve npm run build compatibility.
4. S4: Implement validated version and allowlist packaging with production autoloading and one velog/ root; await fresh production build. Use no globally installed ZIP tool or dependency fetch during packaging. Preserve earlier version archives and fail safely.
5. S5: Reconcile Node/lockfiles/ignore policy, legacy scripts and existing CI build/package logic. Correct quality-gate exit status propagation without reducing checks. Hosted CI execution remains NOT VERIFIED unless actually run.
6. S6: Run the mapped verification suite in disposable fixtures and isolated WordPress when available. Restore temporary source changes. Append implementation report, evidence paths and mandatory Architect handoff.

## Acceptance criteria
- AC1: Instructions and project documents agree on roles, source ownership, approval, statuses and mandatory handoffs; no stale operational directions override the new process.
- AC2: Dashboard satisfies every minimum data/parser/UI requirement in architect-builder-workflow.md; URL/API/HTML checks pass and it mutates nothing.
- AC3: Development and production satisfy specification sections 4–7 and verification A–D, including source maps, minification, source ownership and resource validation.
- AC4: Release satisfies sections 8–9 and verification E/F: safe versioned staging/ZIP, fresh production build, complete runtime autoloading, no forbidden content or partial success.
- AC5: Lockfiles, assets, environment and ignore policy satisfy sections 5 and 9; legacy commands/CI do not bypass the new packaging contract. Quality tool errors fail the gate.
- AC6: Required positive, failure, repeatability, clean-package and isolated WordPress checks have evidence; unavailable checks remain NOT VERIFIED and prevent full acceptance. No fabricated asset-loading result when enqueues do not exist.
- AC7: Architect independently reviews all criteria, resolves finding IDs, records acceptance or remaining manual needs, synchronizes checklist and provides the next chat handoff.

## Verification instructions
Use ../testing-strategy.md for commands, fixtures and expected results mapped to AC1–AC7. Full source-build-release-workflow.md section 12 remains mandatory. Report command, exit code, artifact path and cleanup for each check. Run git diff --check. Syntax checks alone are insufficient.

## Planning report — Round 1 (Architect)
- Inspected repository files, git state, npm/Composer configuration, scripts, workflows, source imports, runtime autoloading, existing plans and relevant rules.
- Commands run: git status --short (initially clean), git rev-parse --show-toplevel (plugin-local repository), git log -1 (3037aff), git ls-files (lockfiles/generated bundles not tracked), node --version (v20.19.2), npm --version (10.8.2), source/reference searches.
- No implementation, build, dependency install, release or WordPress activation performed.
- NOT VERIFIED: all implementation acceptance checks, dashboard, CI, PHP quality suite and isolated installation.
- Findings F-001–F-009 are open and must receive explicit Builder responses and Architect verdicts.

### Chat handoff prompt

```text
Act as Architect for VeLog WF-001. Read ai-document/tasks/WF-001-workflow-bootstrap.md, ai-document/implementation-checklist.md, ai-document/decisions.md, ai-document/testing-strategy.md, ai-document/release-readiness.md, ai-document/architect-builder-workflow.md and ai-document/source-build-release-workflow.md. Status is DRAFT, planning revision 1, baseline main at 3037aff. Repository/configuration/source inspection completed; Node v20.19.2 and npm 10.8.2 observed. All build, dashboard, release, CI, PHP and isolated WordPress checks are NOT VERIFIED. Obtain or locate explicit approval of S1–S6 and D1–D9; do not treat this draft as approval. After approval, record its reference, set READY and provide a self-contained Builder prompt in both task and chat covering AC1–AC7 and findings F-001–F-009. Builder must append actual evidence and cannot accept its own work.
```

## Approved assignment — 2026-09-17

User approval: "ok, làm đi". READY assignment immediately taken IN_PROGRESS by the current session for tooling implementation. No application PHP changes are authorized. Independent Architect acceptance is still required.

### Chat handoff prompt

```text
Act as Builder for WF-001, now IN_PROGRESS after the user's "ok, làm đi" approval. Read AGENT.md, relevant rules, ai-document/tasks/WF-001-workflow-bootstrap.md, ai-document/decisions.md, ai-document/testing-strategy.md, ai-document/release-readiness.md and both copied workflow specifications. Implement S1–S6/D1–D9 only, addressing AC1–AC6 and F-001–F-009. Baseline is main 3037aff; only planning documents were added before approval. Repository inspection passed; all execution checks are NOT VERIFIED. Run and record the specified checks, preserve application PHP, append the implementation report and Architect handoff. Do not mark DONE or complete checklist items; AC7 requires independent review.
```

## Implementation report — Round 1 (Builder)

- S1 / AC1: Added AGENTS.md and current workflow docs; reconciled AGENT.md, CONTRIBUTING.md, README.md and relevant rules. Frontend source paths and version policy agree.
- S2 / AC2: Added scripts/progress-dashboard.mjs, progress-data.mjs and progress-view.mjs with src/css/progress.css. Localhost-only, read-only; owner/status, phases, open work, history, findings, prompt coverage and contradictions are visible.
- S3 / AC3: Migrated JS to src/js/ and Sass to src/css/ after replacement builds passed. Sass/esbuild use explicit configuration, map generation, minification, legal notices, resource checks, output manifest and safe staging. Production outputs are included in the working-tree changes.
- S4 / AC4: Added release configuration and script plus PHP ZipArchive helper. Runtime PHP and offline-generated no-dev Composer autoloading are included; release captures production bytes and rejects unsafe/missing/mismatched inputs. Local ZIP: release/velog-0.1.0.zip; 22 runtime files.
- S5 / AC5: Selected Node 24, retained dependencies, generated npm lock metadata through npm, made lockfiles/production outputs eligible for tracking, retained legacy commands and unified CI packaging. check.sh propagates actual tool exits. No Git index removals or commits.
- S6 / AC6: Added tests/workflow/workflow.test.mjs. Full results and exact limitations are indexed in ../evidence/README.md. Four workflow test groups pass; npm ci leaves lockfile unchanged; dev/production/release pass; PHPCS/PHPStan pass; PHPUnit passes 5 tests/10 assertions; isolated WordPress 6.4 ZIP activation and bootstrap pass. Dashboard API/HTML/CSS and desktop browser inspection pass at http://127.0.0.1:4187/.
- AC7: NOT VERIFIED — Builder cannot independently accept its own implementation. All checklist items remain unchecked pending Architect review.

### Finding responses

- F-001: Fixed missing runtime autoloading; clean ZIP activation verified.
- F-002: Corrected ignore policy; lockfiles and production assets included as new files, pending eventual commit.
- F-003: Replaced exclusion/system-ZIP packaging with validated allowlist, staging and ZipArchive.
- F-004: Replaced npx/skip-on-missing behavior with local APIs and explicit failures/ownership.
- F-005: Actual exit status now controls quality gate; mocked exit 17 is correctly propagated.
- F-006: Current instructions and dashboard established.
- F-007: Existing hosted workflows use npm run release and Node 24; hosted execution NOT VERIFIED.
- F-008: README now identifies the scaffold and absent admin menu.
- F-009: Complete GPL-2 text and required generated notices retained.

### Deviations, risks and cleanup

- Used existing PHP ZipArchive instead of adding a ZIP npm library. No new direct dependency.
- Reconciled rules/coding-style.md source and indentation directions as required for consistent WordPress enforcement. Named frontend scaffold callbacks to pass the existing sniffs; application PHP is unchanged.
- Dashboard CSS is development-tool source read directly by the local server; it is intentionally absent from plugin builds/releases.
- Hosted CI, PHP 8.1, automatic enqueue integration and committed-checkout verification remain NOT VERIFIED. Enqueues/features do not exist in the inspected baseline; no fake integration was added.
- Isolated fixtures/site/database removed and database/server stopped. Progress dashboard remains running for inspection. Earlier failed formatting logs are preserved and superseded by final passing release evidence.
- No deployment, publication, tag, commit, push or working-site database change.

### Chat handoff prompt

```text
Act as Architect for VeLog WF-001. Read AGENTS.md, ai-document/tasks/WF-001-workflow-bootstrap.md, ai-document/implementation-checklist.md, ai-document/build-and-release.md, ai-document/evidence/README.md and both ai-document/architect-builder-workflow.md and ai-document/source-build-release-workflow.md. Status is READY_FOR_REVIEW, implementation round 1; baseline main at 3037aff. User approved S1–S6/D1–D9 with "ok, làm đi". Review the working-tree diff against AC1–AC7 and findings F-001, F-002, F-003, F-004, F-005, F-006, F-007, F-008 and F-009. npm ci, dev/production/release, four workflow test groups, PHPCS/PHPStan, PHPUnit (5 tests/10 assertions), dashboard HTTP/browser checks, Git ignore checks and isolated WordPress 6.4 activation passed. Hosted CI, PHP 8.1, automatic asset enqueue integration and committed-checkout verification are NOT VERIFIED. Independently inspect scripts and packaging, rerun relevant checks, append criterion verdicts and finding decisions, then synchronize task/checklist and provide the next chat handoff. Builder has not marked DONE or accepted any checklist item; do not deploy or commit.
```

## Review — Round 1 (Architect)

The user explicitly reassigned this session to Architect and requested self-review to completion. This is a separate review pass over the actual implementation, not a claim of a separate human or agent reviewer.

- Reviewed current task criteria, scripts, configuration, tests, CI and prior installation evidence.
- F-010 (P2): scripts/build.mjs accepts a configurable manifest destination outside assets/. A missing path under src/ can receive generated manifest JSON. Restrict the manifest to its declared generated location before compiling. Verify an out-of-scope missing path fails with a clear error and leaves backend files unchanged.
- F-011 (P2): scripts/release.mjs creates .stage-* before validating finalStage/finalZip/backup, outside try/finally. An existing destination symlink fails but leaks staging. Validate destination paths before creating temporary staging. Verify failure leaves no .stage-* directory.
- AC3/AC4: FAIL pending F-010/F-011 corrections. Other criteria remain under review.
- Requested corrections are within the previously approved tooling scope. No application PHP changes.

### Chat handoff prompt

```text
Continue WF-001 review corrections in this user-authorized session. Read ai-document/tasks/WF-001-workflow-bootstrap.md, scripts/build.mjs, scripts/release.mjs and tests/workflow/workflow.test.mjs. Address F-010 and F-011 only, reproduce each with a regression test, rerun workflow and release checks, and record the fix evidence before Architect acceptance. Current review found AC3/AC4 unmet; previous build/package/WordPress 6.4 evidence passed. Hosted CI remains NOT VERIFIED.
```

## Fix report — Round 2 (Architect-authorized corrections)

- F-010: scripts/build.mjs rejects every manifest destination except assets/.generated.json before compiling or writing. Regression confirms an attempted missing src/Core/Generated.php destination fails and existing backend code remains unchanged.
- F-011: scripts/release.mjs validates final stage/archive/backup destinations before allocating staging. Regression confirms a final-stage symlink fails without leaving .stage-* output.
- Evidence: ../evidence/review-reproduction.log records both failures before correction; ../evidence/architect-workflow-tests.log records all six test groups passing afterward.
- Full npm run release passed again, including PHPCS/PHPStan, PHPUnit 5 tests/10 assertions, production build, generated autoloading and 22-file ZIP verification.
- Clean working-tree snapshot with fresh npm ci and composer install passed dev and release; npm lockfile hash unchanged. No shared installed dependencies, no Git commit; temporary snapshot removed. Evidence: ../evidence/architect-clean-snapshot.log.

## Review — Round 2 (Architect)

The user requested this session act as Architect and complete self-review. No second reviewer identity is claimed. The review revisited implementation facts, reproduced defects and reran verification rather than accepting the Builder summary alone.

| Criterion | Verdict | Evidence and reasoning |
|---|---|---|
| AC1 | PASS | Instructions, roles, source paths, approved scope and current status agree. Prior reports remain historical; final acceptance supersedes their pending-review statements. |
| AC2 | PASS | Dashboard API/HTML and read-only behavior reviewed; parser/escaping regressions pass; current status/checklist consistency checked after acceptance. |
| AC3 | PASS | Fresh dependency install/dev/production and six workflow groups pass, including maps, minification/notices, source ownership, missing input/resource, manifest boundary and obsolete output cleanup. |
| AC4 | PASS | Fresh release succeeds; package includes backend PHP, assets and production autoloading; boundary/failure regressions preserve previous archive and clean staging. |
| AC5 | PASS | Lockfiles/production files are eligible for version control, ignore evidence reviewed, clean installation reproduces output, legacy commands delegate and actual quality failures propagate. No commit is required or authorized by the task's explicit scope. |
| AC6 | PASS | Required local positive/negative/package checks have evidence. Existing isolated WordPress 6.4 activation/bootstrap and four asset URL checks reviewed; runtime PHP remains unchanged. |
| AC7 | PASS | User-assigned Architect completed a distinct review pass, closed findings and synchronized accepted checklist/status. |

F-001–F-009: CLOSED after reviewing implementation and corresponding evidence. F-010/F-011: CLOSED after failed-before/passed-after regression evidence. No unresolved finding remains for local workflow bootstrap.

Acceptance boundary follows the original explicit scope: local workflow setup, no commit/push/deploy and no application feature implementation. Hosted Actions execution was explicitly allowed to remain NOT VERIFIED in S5. Minimum PHP/other WordPress combinations are broader compatibility checks, not claims established by the local smoke test. Automatic enqueue behavior has no implementation in the preserved scaffold; installed asset URL loading was verified without inventing feature behavior. Fresh-snapshot verification proves the candidate lockfile locally; verification of a future committed revision remains an administrative follow-up after separately authorized commit. These qualifications do not remove a failed local check or change the task criteria.

## Final acceptance (Architect)

- Decision: DONE. All seven local bootstrap criteria accepted after corrections.
- Accepted baseline: main at 3037aff plus the current working-tree changes. The accepted-files manifest in ../evidence/architect-accepted-files.sha256 records reviewed file bytes; evidence files are excluded to avoid recursive hashes.
- Checklist: all seven WF-001 items checked; all phases complete. Product feature plans remain Draft.
- No production deployment, commit, push, tag or working-site database modification performed.
- Remaining broader verification: hosted CI and additional runtime-version combinations remain NOT VERIFIED; no claim of product readiness.

### Chat handoff prompt

```text
Read AGENTS.md, ai-document/implementation-checklist.md and ai-document/tasks/WF-001-workflow-bootstrap.md. WF-001 is DONE after the user-assigned Architect review; AC1–AC7 passed and F-001–F-011 are closed. Six workflow tests, clean-snapshot npm ci/dev/release, PHPCS/PHPStan, PHPUnit and dashboard checks passed; WordPress 6.4 installation evidence was reviewed. Hosted CI and other runtime-version combinations remain NOT VERIFIED and are outside this local bootstrap acceptance. Next: Architect prepares a separate DRAFT feature task only when the user supplies the next product scope. Do not reopen WF-001 without new evidence, implement unapproved features, commit or deploy.
```

## Handoff correction — Project planning prerequisite

The user identified that the previous prompt skipped master-plan approval. WF-001 remains DONE for tooling only. The previous final-acceptance prompt is retained as historical text and superseded by this correction. Overall product planning is incomplete; PLAN-001 is the current DRAFT task. Historical 7/7 counts concern bootstrap only, not total product completion.

### Chat handoff prompt

```text
Act as Architect. Read AGENTS.md, ai-document/product-plan.md, ai-document/implementation-checklist.md, ai-document/tasks/PLAN-001-master-plan.md and the four feature drafts under plans/current/. WF-001 is DONE for tooling only; PLAN-001 is DRAFT and the product has no approved master plan or MVP. Next: consolidate the drafts into a proposed master plan covering users, end-to-end flows, data/permissions, MVP boundaries, dependencies, milestones and acceptance criteria. List unresolved product decisions for the user and obtain explicit approval before creating READY feature implementation tasks. Prior tooling checks passed; product behavior and master-plan acceptance are NOT VERIFIED. Do not treat bootstrap completion as product completion, invent approved business rules, implement features, commit or deploy.
```
