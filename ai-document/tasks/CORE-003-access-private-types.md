# CORE-003: Capabilities and private record types

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: CORE-003 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at 2aa3b8d, clean before PLAN-002 documentation work; all new classes below are proposed, not inspected existing implementations.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; 2026-09-21 user authorizes batch planning only. Detailed pending proposals are not approved implementation.
- Latest round: Architect draft blueprint — Round 1.
- Next actor: Architect
- Next actor and exact next action: Resolve readiness gates below; require CORE-001 DONE; reinspect accepted files/interfaces and record Blueprint readiness PASS before considering READY. Builder execution is deferred by user request.

## Problem and intended behavior
No product CPTs or dedicated capabilities exist. Login alone cannot grant operational access; generic post editing, REST, search and direct URLs must not expose these records.

## Scope and references
Read AGENTS.md; [PLAN-002 Shared blueprint contract — revision 1](PLAN-002-mvp-task-batch.md#shared-blueprint-contract--revision-1); product-plan.md, internationalization.md, architecture.md, testing-strategy.md and architect-builder-workflow.md under ai-document/. Apply rules/architecture.md, security.md, coding-style.md and UI/accessibility rules when screens change. Read build-and-release.md for lifecycle, asset or packaging changes. The shared contract supplies required commands, isolation, evidence and pre-handoff gates; the scope below is task-specific.

- New `src/Common/AccessPolicy.php`: explicit primitive/object capability policy; no dependency on Admin.
- New `src/Core/Capabilities.php`: idempotent capability installation and plugin-owned role ledger.
- New `src/Core/PostTypes.php`: register mf_velog_customer, mf_velog_vehicle, mf_velog_service and mf_velog_reminder on init through Loader.
- Modify `src/Core/Plugin.php` composition and define_*_hooks; `src/Core/Activator.php` invokes capability setup. Preserve bootstrap guard and locale timing.
- New `tests/Unit/AccessPolicyTest.php`, `tests/fixtures/core-003-verify.php`, `tests/workflow/product-smoke.sh` (owned isolated runner from PLAN-002).
- Update `ai-document/architecture.md`; record authoritative capability table there after approval. No product CRUD or customer data screens.

## Implementation blueprint
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness: INCOMPLETE — concrete draft, not executable authorization or product acceptance.
- Required accepted prerequisites: CORE-001 DONE.
- Remaining readiness gates: G-02; validate WordPress capability mapping against actual installed core, decide exact role ownership/removal policy, pin/provision isolated runner and inspect corrected CORE-001 harness.
- Baseline rationale: inspected repository has Core bootstrap scaffolding and no product services; reuse WordPress/private CPT architecture and existing Composer/Loader conventions rather than adding a framework. Future dependency interfaces are proposals until their tasks are accepted.

### Required design and interfaces
Proposed primitives: mf_velog_read_records, mf_velog_manage_settings, mf_velog_manage_customers, mf_velog_read_customer_contacts, mf_velog_manage_vehicles, mf_velog_create_services, mf_velog_edit_own_service_drafts, mf_velog_finalize_own_services, mf_velog_correct_services, mf_velog_manage_reminders. Manager/admin receive all; technician receives read_records, create_services, edit_own_service_drafts and finalize_own_services. Object checks inspect actual type, state, author and associated vehicle visibility; unknown object returns deny. A raw primitive cannot substitute for ownership/state checks.

CPT flags: public/publicly_queryable/show_ui/show_in_rest/show_in_nav_menus/has_archive/rewrite/query_var false, exclude_from_search true, supports empty. Domain state lives in the DATA-001 payload; post status stays private. Later custom admin screens own all supported edits. Define complete per-type capability mappings and deny native edit/delete/publish paths rather than leaking WordPress default post permissions. Exact map_meta_cap behavior and supported native read paths must be pinned in readiness review; hiding UI alone is not authorization.

Proposed lifecycle: first activation records whether each role/capability pre-existed, adds missing approved assignments, never overwrites an unrelated role with a matching slug. Existing slug with incompatible definition is a setup error. Deactivation retains roles/caps/data for predictable reactivation. Uninstall retains operational roles with assigned users and their plugin caps under G-08 until an explicit safe removal procedure exists; plugin-inactive private types remain unregistered. Do not remove entire shared roles or unrelated caps. Upgrade setup is versioned/idempotent, never reset deliberately customized assignments on every request. Staff assignment stays in WordPress Users.

### Ordered implementation steps
1. S1: Builder preflight only after READY: read accepted dependencies, record identity/baseline, inspect callers/hooks and preserve unrelated changes. Return precise gaps to Architect rather than guessing.
2. S2: Resolve role/contact policy and enumerate complete CPT/object capability mapping; define isolated provisioning and cleanup commands before READY.
3. S3: Add capability lifecycle and private types through existing composition/Loader; no eager translation in constructors.
4. S4: Create isolated runner and assert real request paths under each actor; add lifecycle collision/idempotency regressions and evidence.
5. S5: Run the matrix and shared quality gates; preserve failures and NOT VERIFIED items, clean owned resources, then append implementation report and independent Architect handoff.

### Critical-path pseudocode
```text
authorize(actor, action, object_id):
  reject missing primitive capability
  if object operation: load expected type and authoritative state; reject invalid relation
  technician service change: require own author + draft + permitted action
  reject generic edit/delete routes even if unrelated WP role edits posts
  return permission for requested operation only
activate:
  inspect role ownership; reject incompatible collision before mutation
  apply missing owned assignments; record schema version only after success
```

### Failure and resource lifecycle
Capability setup must preserve prior values and fail closed on partial setup; define rollback ledger during readiness review. Owned runner follows PLAN-002 exactly. Denied reads must not echo object titles/contact fields in notices/logs; record status/error category only.
External fixture resource ownership, bounded readiness/cleanup and error propagation follow PLAN-002. No active-site writes are allowed.

## Acceptance criteria
- AC1: Explicit manager/admin/technician policy is enforced at object and primitive levels.
- AC2: Anonymous, subscriber and unrelated editor cannot discover private records through public/native endpoints.
- AC3: Activation/reactivation and role collisions preserve unrelated roles/capabilities.
- AC4: Real WordPress permission/lifecycle evidence and runner failure/cleanup controls pass.

## Verification matrix
| Case | AC | Fixture/input | Command or test entry point | Expected result |
|---|---|---|---|---|
| V1 | AC1 | Manager, own/other technician, subscriber; draft/finalized/foreign-type IDs | AccessPolicyTest::test_object_matrix | Only approved action/actor/state combinations allowed |
| V2 | AC2 | Seed synthetic records; request ?p=ID, search, feeds, sitemap, REST collections, native post.php/post-new.php | tests/fixtures/core-003-verify.php via isolated runner | No private content/contacts; non-authorized mutation rejected; ordinary posts unaffected |
| V3 | AC3 | Activate twice, deactivate/reactivate, conflicting existing role and custom editor caps | tests/fixtures/core-003-verify.php via isolated runner | No duplicate/lost capabilities, collision fails without unrelated mutations |
| V4 | AC4 | Fail DB launch; never-ready DB; fixture failure; parallel isolated runs | tests/workflow/product-smoke.sh negative control modes specified at readiness | Expected inner nonzero, outer assertion succeeds; bounded exit and no orphan owned processes |

## Verification instructions
- Targeted test command after implementation: `composer run test -- --filter AccessPolicyTest`. Named new fixtures/tests are planned entry points, not currently existing passing checks.
- Every successful test/quality/runner/build command must exit 0. A required invalid input or unauthorized operation must be rejected (WP_Error or asserted HTTP 4xx) with no forbidden write; the outer assertion runner exits 0 only when rejection is proved. Unexpected acceptance is a failing test, nonzero. Do not confuse an expected inner failure with a failed outer suite.
- Run `composer run lint`, `composer run test` and `git diff --check`; record actual exits/totals. UI source changes also require `npm run production` and matching outputs. Real WP fixture invocation: `bash tests/workflow/product-smoke.sh --task=CORE-003 --wp-version=6.4.3`, repeated for 6.7.2 on required PHP runtimes, where applicable. CORE-002 uses pure tests and accepted bootstrap smoke instead; the product runner is created by CORE-003. Missing executable/environment remains NOT VERIFIED.
- Follow the same real handlers/validators for positive and negative cases; include valid nonce with denied actor, permitted actor with invalid nonce, stale version and malformed/foreign IDs where relevant. Mocked PHPUnit is not proof of real WordPress authorization/persistence.
- Manual UI checks must record observations and actual user verdict where required; screenshots or automated checks do not substitute for unperformed keyboard/screen-reader/manual acceptance.

## Evidence and Builder completion contract
- Evidence directory: `ai-document/evidence/CORE-003/round-1/`; commands.log for commands/exits/versions, verification.md mapping S/AC/V IDs to changed files and actual evidence; named manual walkthrough where applicable. Append later rounds without replacing old results.
- Complete the PLAN-002 pre-handoff checklist: identity/contributors, exact changes, test totals/negative controls, deviations, NOT VERIFIED checks, cleanup and matching task/checklist state. No self-acceptance.
- Implementation report: not started. Architect review: not performed. All runtime/integration/performance/manual acceptance results for this task are NOT VERIFIED.

### Chat handoff prompt

```text
Continue as Architect for CORE-003, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md (Shared blueprint contract revision 1), ai-document/tasks/CORE-003-access-private-types.md (Implementation blueprint revision 1), ai-document/implementation-checklist.md and the accepted dependency task files. Scope is capabilities and private record types, AC1–AC4. Require CORE-001 DONE and resolve Remaining readiness gates before a bounded READY assignment. The user requested batch planning and Builder execution later. Baseline source inspection and planning documentation checks are the only evidence; all new runtime, integration and manual checks are NOT VERIFIED. Reinspect concrete callers/interfaces, complete the verification setup and record Blueprint readiness PASS only when genuinely complete and approved. Do not implement, dispatch Builder, mark DONE, commit, deploy or use the active database.
```
