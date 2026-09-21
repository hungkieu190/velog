# CORE-004: Explicit regional setup and admin shell

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: CORE-004 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CORE-002, CORE-003 and DATA-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
The shop currently has no settings screen or explicit configured units/currency. Future financial/distance writes must not silently assume a regional default.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/Regional/ShopSettings.php`: validate/read/save versioned explicit setup in velog_settings; no historical rewrite.
- New `src/Admin/AdminMenu.php`: private VeLog menu and approved subpage registrations.
- New `src/Admin/RegionalSettingsPage.php`: settings form, labels/field errors, authorized POST handling.
- New `src/Admin/Assets.php`: scoped admin_enqueue_scripts integration for generated admin assets.
- Modify `src/Core/Plugin.php` dependency wiring and hook registration; optional `src/css/admin.scss` only for required responsive/RTL/accessible styling with production output.
- New `tests/Unit/ShopSettingsTest.php`, `tests/fixtures/core-004-verify.php` and manual walkthrough.
- Update `ai-document/internationalization.md` configuration details and `ai-document/architecture.md` composition. No record CRUD or third-party frontend framework.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-002, CORE-003 and DATA-001 DONE.
- Remaining readiness gates: G-01/G-02; resolved locale/catalog strategy, approved capability mapping, accepted storage interfaces. Native list bulk-action exceptions resolved where applicable.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
The settings form starts unconfigured, with explicit empty selections for distance and currency. Proposed schema: schema_version, record_version, configured, distance_unit, currency_code, currency_scale; optional region string is a suggestion/context only, never replaces unit/currency. WordPress locale/timezone remains authoritative and is displayed with an explanation; no duplicated timezone setting. Manager must explicitly submit a valid unit and currency/scale; invalid fields keep prior settings atomically unchanged.

Domain services call require_configured() only for writes needing numeric defaults; reading old records, managing a name-only customer and viewing setup errors do not require configured money defaults. Future callers receive defaults but must snapshot unit/code/scale into each new record. Do not gate by UI visibility alone. Changing settings affects future input only and cannot rewrite existing records. Settings save uses its own expected version, capability and nonce.

Use POST/redirect/GET with allowlisted success/error messages and transient form error state bound to current user if necessary; never expose contact data or full submitted payload in URLs. No inline JS/CSS; forms usable without JavaScript. Apply only relevant native WordPress UI patterns and generated assets on VeLog screens.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Resolve configuration policy; implement shared settings service with versioned saves and no implicit defaults.
3. S3: Register manager-only settings and shared menu/assets through Loader; read-only operational menus for technicians follow capabilities.
4. S4: Test missing setup, stale submit and configuration changes with historical fixture snapshots; verify keyboard/RTL/mobile and unrelated asset loading.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
save_settings(request, actor):
  require manager capability; verify settings nonce
  validate scalar allowlisted fields using CORE-002
  compare expected version; reject stale form
  persist full validated settings; redirect to safe notice
resolve_new_record_defaults(): return explicit configuration or setup-required error
render_historical_value(record): use record unit/currency/scale, never current defaults
```

### Failure and resource lifecycle
Settings validation/save failure retains last coherent version and returns per-field errors. Transient notice state must be per-user and expire; never log secrets. Asset/build failure prevents frontend handoff. Isolated fixtures and cleanup follow PLAN-002.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Manager explicitly configures units/currency; invalid/unconfigured values cannot silently default.
- AC2: Capability, nonce and stale-version checks protect writes.
- AC3: Preference/locale changes preserve historical value identity.
- AC4: Accessible translated/RTL admin UI and scoped generated assets pass verification.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Fresh install; missing distance/code/scale; valid km/JPY and mi/USD | ShopSettingsTest::test_explicit_setup | Unconfigured sentinel until valid submit; exact explicit configuration saved |
| V2 | AC2 | Technician/subscriber with valid nonce; manager bad nonce; stale settings version | tests/fixtures/core-004-verify.php | Rejected without changed settings; fresh manager save succeeds |
| V3 | AC3 | Seed snapshots then switch km/mi, JPY/USD/KWD, locale | tests/fixtures/core-004-verify.php | Same record payloads and canonical distances; only future defaults change |
| V4 | AC4 | Keyboard; 320px width; long strings; ar RTL; unrelated WP screen | ai-document/walkthroughs/CORE-004.md; npm run production if sources change | Usable labelled form with associated errors; unrelated screen has no VeLog assets; build exit 0 |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter ShopSettingsTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=CORE-004 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/CORE-004/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for CORE-004, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/CORE-004-regional-settings.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is explicit regional setup and admin shell, AC1–AC4. Require CORE-002, CORE-003 and DATA-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
