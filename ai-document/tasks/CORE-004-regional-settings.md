# CORE-004: Explicit regional setup and admin shell

## Current handoff
- Workstream: Backend
- Status: DONE
- Plan revision: 11
- Owner: Backend Architect
- Architect session reference (planner): Codex planning 2026-09-21 and 2026-10-02; descriptive references, not asserted machine session IDs.
- Builder session reference (implementer): Antigravity Builder 2026-10-02
- Implementation contributors and review mode: Antigravity Builder implemented historical rounds 1–2. Codex completed the historical takeover for F-002/F-004/F-005. Under the user-approved 2026-10-05 workflow, Codex Backend Architect now owns and self-reviews all remaining backend work as `SELF_REVIEWED_BACKEND`.
- Architect takeover: CLOSED — CORE4-F-002/F-004/F-005; `SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER`; evidence `ai-document/evidence/CORE-004/architect-takeover-round-1/closure.md`.
- Related checklist items: CORE-004 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: the working tree contains uncommitted DATA-001 and earlier CORE-004 work. This round changed only the admin asset URL, the integration fixture, and task/evidence documentation; preserve unrelated changes.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; on 2026-10-02 the user directly accepted DATA-001 with recorded verification exclusions and directed work to start on the next task. G-01 was explicitly approved by the user in the current conversation on 2026-10-02.
- Latest round: Product Owner manual acceptance — 2026-10-06.
- Latest report: ai-document/evidence/CORE-004-FE/manual-acceptance-2026-10-06/review.md.
- Next actor: None
- Next actor and exact next action: None; CORE-004 backend findings are closed and AC4 is accepted by direct Product Owner manual verification.

## Problem and intended behavior
The shop currently has no settings screen or explicit configured units/currency. Future financial/distance writes must not silently assume a regional default.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and backend-frontend-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/Regional/ShopSettings.php`: validate/read/save versioned explicit setup in velog_settings; no historical rewrite.
- New `src/Admin/AdminMenu.php`: private VeLog menu and approved subpage registrations.
- New `src/Admin/RegionalSettingsPage.php`: settings form, labels/field errors, authorized POST handling.
- New `src/Admin/Assets.php`: scoped admin_enqueue_scripts integration for generated admin assets.
- Modify `src/Core/Plugin.php` dependency wiring and hook registration; optional `src/css/admin.scss` only for required responsive/RTL/accessible styling with production output.
- New `tests/Unit/ShopSettingsTest.php`, `tests/fixtures/core-004-verify.php` and manual walkthrough.
- Update `ai-document/internationalization.md` configuration details and `ai-document/architecture.md` composition. No record CRUD or third-party frontend framework.

## Implementation blueprint
- Revision and covered criteria: 3; AC1–AC4.
- Blueprint readiness: PASS for CORE-004 implementation after the user approved G-01 and directed work to proceed on 2026-10-02.
- Required accepted prerequisites: CORE-002 and CORE-003 DONE; DATA-001 DONE by direct user acceptance on 2026-10-02 with documented NOT VERIFIED cases.
- Readiness gates: G-01 regional input policy approved by the user on 2026-10-02; G-02 capability mapping is approved; bundled CORE-002 currency catalog and DATA-001 interfaces exist. Native list bulk actions are outside this settings form.
- Baseline rationale: repository inspection on 2026-10-02 found `Distance`, `Money`, `DecimalInput`, and a bundled `CurrencyCatalog`; `Capabilities` grants `mf_velog_manage_settings` to manager and administrator; `AccessPolicy` explicitly excludes settings from object authorization. `Plugin::define_admin_hooks()` is still a TODO and no `src/Admin/` directory exists. Use a dedicated option service and existing Loader conventions.

### Architect planning intake — 2026-10-02

- **PASS for planning:** CORE-002/003 are DONE and DATA-001 has direct user acceptance. The accepted regional and capability symbols above exist. No CORE-004 implementation or runtime evidence is claimed.
- **G-01 decision proposal:** Accept ASCII digits, a locale-specific decimal separator, no thousands grouping in inputs, at most three decimals for km/mi, and the existing 15-digit money bound. `DecimalInput` and `Money` already enforce this shape; the settings screen should describe it. The user approved this policy on 2026-10-02.
- **Settings persistence boundary:** `velog_settings` is retained on uninstall. A new unconfigured installation has no implicit km or currency. The selected currency's scale and catalog version come from `CurrencyCatalog`, not submitted scale fields. Use a single non-autoloaded option with a schema/version envelope. For create, rely on the unique option name; for updates, use an atomic compare-and-swap against the previously read option value and expected record version. A stale or failed write preserves the prior envelope. Avoid a read-then-`update_option()` version check without an atomic condition.
- **HTTP boundary:** The admin handler checks `current_user_can( 'mf_velog_manage_settings' )` and a settings-specific nonce before parsing allowlisted scalar POST fields. The service validates the manager capability again for direct callers. Redirect with a fixed notice code; submitted values and raw SQL never enter the URL. All rendered values and labels are escaped for context.
- **Verification design:** Unit test missing setup, catalog-derived scale, invalid scalar/array inputs and stale versions. Disposable WordPress tests must cover manager success, technician/subscriber denial with valid nonce, manager denial with invalid nonce, concurrent stale update, retained settings after uninstall/reinstall, and unchanged historical DATA-001 record payloads after preference changes. Manual keyboard, RTL, 320px and unrelated-admin-screen asset checks remain required before AC4 closure.

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

## Evidence and backend completion contract
- Evidence directory: `ai-document/evidence/CORE-004/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. Backend closure requires `SELF_REVIEWED_BACKEND` with explicit non-independent review evidence.
- Historical implementation reports remain at ai-document/evidence/CORE-004/builder-round-1/report.md, builder-round-2/report.md, and implementation-round-3/report.md. Takeover closure: ai-document/evidence/CORE-004/architect-takeover-round-1/closure.md. Current backend evidence belongs under `ai-document/evidence/CORE-004/backend-round-1/`.

## Open findings
- CORE4-F-001: Exact-byte conditional SQL CAS, two-process race, duplicate errno classification, and cache consistency verified. Status: CLOSED — SELF_REVIEWED_BACKEND.
- CORE4-F-002: Stored and submitted settings validation incomplete. Status: CLOSED — SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER.
- CORE4-F-003: Plugin-root generated CSS URL and exact hook scoping verified in isolated WordPress; browser layout remains in CORE-004-FE. Backend portion: CLOSED — SELF_REVIEWED_BACKEND.
- CORE4-F-004: Fixture still lacks concurrent writers and real DATA-001 history oracle. Status: CLOSED — SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER.
- CORE4-F-005: Lint fails and documentation/UI/handoff evidence remains incomplete. Status: CLOSED — SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER.
- CORE4-F-006: Root and settings capabilities, role grants, and callable landing page verified in isolated WordPress. Status: CLOSED — SELF_REVIEWED_BACKEND.
- CORE4-F-007: A valid seven-key settings envelope was rejected after `catalog_version` became mandatory without migration. Exact-shape validation, catalog checks, byte-exact CAS migration, idempotence, and the LocalWP upgrade are verified. Status: CLOSED — SELF_REVIEWED_BACKEND.

## Escalated proposal decision
- Proposal rev1: ai-document/evidence/CORE-004/builder-round-3/F-002-F-004-F-005-proposal-rev1.md.
- Proposal rev2: ai-document/evidence/CORE-004/builder-round-4/proposal-rev2.md.
- Proposal rev3: ai-document/evidence/CORE-004/builder-round-5/proposal-rev3.md.
- Proposal rev4: ai-document/evidence/CORE-004/builder-round-6/proposal-rev4.md.
- Proposal rev5: ai-document/evidence/CORE-004/builder-round-7/proposal-rev5.md.
- Proposal rev6: ai-document/evidence/CORE-004/builder-round-8/proposal-rev6.md.
- Proposal rev7: ai-document/evidence/CORE-004/builder-round-9/proposal-rev7.md.
- Proposal rev8: ai-document/evidence/CORE-004/builder-round-10/proposal-rev8.md.
- Latest Architect decision: SELF_ACCEPTED_UNDER_ARCHITECT_TAKEOVER — ai-document/evidence/CORE-004/architect-takeover-round-1/closure.md.
- The proposal gate and Architect/Builder process are historical. F-002/F-004/F-005 remain closed under the recorded takeover. F-001/F-003/F-006 are now owned by Backend Architect under the current workflow.

### Chat handoff prompt

```text
Status: DONE
Recipient: Backend Architect
Intent: plan

CORE-004 and CORE-004-FE are DONE by direct Product Owner manual acceptance on 2026-10-06. Backend findings CORE4-F-001, F-003, F-006, and F-007 remain closed as `SELF_REVIEWED_BACKEND`; preserve the historical takeover closure for F-002/F-004/F-005. Preserve the recorded frontend evidence limitation and continue with CUST-001 readiness planning before any implementation.
```
