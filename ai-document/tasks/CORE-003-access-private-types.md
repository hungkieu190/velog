# CORE-003: Capabilities and private record types

## Current handoff
- Status: READY_FOR_REVIEW
- Plan revision: 2
- Architect session reference (planner): Codex planning conversation of 2026-09-21; descriptive reference recorded in PLAN-002, not an asserted machine session ID.
- Builder session reference (implementer): Unassigned.
- Implementation contributors and reviewer independence check: No implementation by this session; future Builder must identify all contributors before review.
- Related checklist items: CORE-003 / AC1–AC4.
- Baseline branch and commit; pre-existing relevant changes: main at `9c4d1be31a78db7448ef5244a7a8a1d9f4401287`; preserve all existing tracked and untracked work. CORE-001 and CORE-002 are accepted; no CORE-003 implementation exists at assignment time.
- User approval reference and approved scope: PLAN-001 revision 3 approves MVP direction; on 2026-09-22 the user instructed Architect to start CORE-003 and hand it to Builder, approving G-02 as recorded in decisions.md. Scope remains AC1–AC4 only.
- Latest round: Architect executable blueprint — revision 2.
- Next actor: Builder (Antigravity)
- Next actor and exact next action: Implement revision 2, run C3-V1–C3-V5 in isolated fixtures, record Round 1 evidence and return READY_FOR_REVIEW without self-acceptance.

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

## Implementation blueprint — revision 1 history (superseded by revision 2 below)
- Revision and covered criteria: 1; AC1–AC4.
- Blueprint readiness at revision 1: INCOMPLETE — retained as planning history, not the current assignment.
- Required accepted prerequisites: CORE-001 DONE.
- Former readiness gates: G-02, WordPress capability mapping, role ownership/removal, isolated runner provisioning and corrected CORE-001 evidence. All are resolved by executable revision 2 below.
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

---

## Executable blueprint — revision 2 (2026-09-22)

- Covered criteria: AC1–AC4.
- Blueprint readiness: PASS. CORE-001 prerequisite is DONE under retained Round 10 acceptance. G-02 is approved. WordPress capability behavior was checked against the installed WordPress core; local WordPress 7.1.1 is inspection context only. Integration evidence must use pinned WordPress 6.4.3 and 6.7.2 in disposable installations.
- Assignment boundary: Architect prepared this blueprint and must not implement application code. Builder must identify implementation contributors and return independent review to Architect.
- Excluded: product CRUD screens, REST product endpoints, record metadata/payloads, repositories, settings UI, assets, dependency/version changes, packaging, active-site access and user-role reassignment.

### Change map and exact policy

Create `src/Common/AccessPolicy.php`, `src/Core/Capabilities.php`, `src/Core/PostTypes.php`, `tests/Unit/AccessPolicyTest.php`, `tests/fixtures/core-003-verify.php` and `tests/workflow/product-smoke.sh`. Modify only the necessary composition in `src/Core/Plugin.php`, activation call in `src/Core/Activator.php`, architecture documentation, this task/report, checklist and Round 1 evidence. Constructors must have no hook or persistence side effects. Register post types on `init` through the existing Loader. Capability mutation runs only from activation/versioned setup, not normal requests.

Use this exact business-capability set:

| Capability | Administrator | Manager | Technician |
|---|---:|---:|---:|
| `mf_velog_read_records` | yes | yes | yes |
| `mf_velog_manage_settings` | yes | yes | no |
| `mf_velog_manage_customers` | yes | yes | no |
| `mf_velog_read_customer_contacts` | yes | yes | no |
| `mf_velog_manage_vehicles` | yes | yes | no |
| `mf_velog_create_services` | yes | yes | yes |
| `mf_velog_edit_own_service_drafts` | yes | yes | yes |
| `mf_velog_finalize_own_services` | yes | yes | yes |
| `mf_velog_correct_services` | yes | yes | no |
| `mf_velog_manage_reminders` | yes | yes | no |

Create roles `mf_velog_manager` and `mf_velog_technician`, both with `read`. Do not add VeLog capabilities to editor, author, contributor, subscriber or any other role. Do not assign users or auto-enroll existing accounts.

`AccessPolicy` is a pure authorization service. It receives a trusted actor capability view plus a trusted object context containing exact VeLog object type, state, author and relationship visibility. Unknown/missing/foreign type or malformed context denies. Customer contacts require `mf_velog_read_customer_contacts` in addition to record visibility. A technician may change a service only when it is their own draft and the requested operation has the matching primitive; finalized or other-author records deny. Future callers must build context from authoritative repository data, never request parameters. The service returns a boolean, writes nothing and emits no content.

Register exactly `mf_velog_customer`, `mf_velog_vehicle`, `mf_velog_service` and `mf_velog_reminder`. For every type set `public`, `publicly_queryable`, `show_ui`, `show_in_menu`, `show_in_rest`, `show_in_nav_menus`, `has_archive`, `rewrite`, `query_var` and `can_export` false; set `exclude_from_search` true, `supports` to an empty array and `delete_with_user` false. Supply a complete native post capability array whose edit/read/delete/publish/create primitives resolve to `do_not_allow`; set `map_meta_cap` false. This intentionally blocks native post CRUD, including administrators. Later product services authorize business operations with the explicit VeLog capabilities.

### Capability lifecycle and failure behavior

Use schema version `1`, option `mf_velog_capability_schema_version` and an ownership ledger option `mf_velog_role_ledger`; both options must be non-autoloaded. Before mutation, require the administrator role and inspect both target role slugs. If a target slug exists without a ledger entry proving VeLog ownership, treat it as a collision and fail before any mutation.

Snapshot every role/capability/option value the current attempt may change. Create owned roles, add only missing approved capabilities, write the ownership ledger, then write schema version last. On any failure, restore the exact snapshot for all attempt-owned mutations and leave unrelated roles/capabilities/options unchanged. Reactivation is idempotent: it restores missing required VeLog capabilities on plugin-owned roles and administrator while retaining unrelated capabilities deliberately added later. Deactivation and uninstall retain roles, assigned capabilities, ledger and schema version. No normal request performs repair.

### Ordered implementation flow

1. Record Builder identity, baseline and dirty-tree inventory; verify accepted dependency interfaces and preserve unrelated changes.
2. Write focused unit tests for the complete capability table, unknown/foreign contexts, contact separation and own-draft boundaries, then implement `AccessPolicy`.
3. Implement transactional capability setup and activation wiring, including collisions, rollback, idempotency and unrelated-cap preservation.
4. Implement the four storage-only private types and Loader wiring; keep existing locale/run behavior unchanged.
5. Build the reusable isolated product runner and real fixture, execute C3-V1–C3-V5, clean only owned resources, write Round 1 evidence and return READY_FOR_REVIEW.

```text
authorize(actor, action, context):
  require the exact primitive for action
  reject absent, unknown or foreign object context
  require contact capability before exposing customer contact data
  if technician mutates service:
    require service type, draft state and actor == author
  return true only after all action-specific checks pass

install_capabilities():
  preflight administrator and role ownership collisions
  snapshot every value this attempt can mutate
  try create/repair owned roles and administrator assignments
  then persist ownership ledger
  then persist schema version
  on any failure restore the exact snapshot and fail closed
```

### Isolated runner contract

`tests/workflow/product-smoke.sh` accepts only `--task=CORE-003` and `--wp-version=6.4.3|6.7.2`, validates required tools before allocation and uses a mode-0700 `mktemp -d` directory matching `/tmp/velog-product-smoke.XXXXXXXX`. Put database files, socket, PID, WordPress install, plugin copy and logs inside that owned directory. Start MariaDB with networking disabled and bounded readiness; copy the plugin into the disposable WordPress install rather than symlinking it. Install the requested WordPress version, activate the copied plugin and execute the real fixture through WordPress. Exercise loopback HTTP paths for single posts, search, feed, sitemap, REST and native admin edit/new routes where applicable.

Support negative control modes `db-start-failure`, `db-never-ready`, `fixture-failure` and `http-never-ready`, plus two parallel normal runs. Readiness is bounded to 30 seconds. Preserve the originating nonzero status, terminate/reap only recorded owned processes, verify recorded PID/path absence and never enumerate or broadly delete `/tmp`. An outer assertion may exit 0 only after proving the injected inner failure and cleanup. Never use the active local WordPress 7.1.1 installation or database. Run with PHP 8.1 when actually available; otherwise record the attempted discovery and `NOT VERIFIED` without inventing a result.

### Verification matrix — revision 2

| ID | Criteria | Command / coverage | Expected result |
|---|---|---|---|
| C3-V1 | AC1 | `composer run test -- --filter AccessPolicyTest` | Complete role/action/object matrix passes, including unknown/foreign/contact/own-draft denials. |
| C3-V2 | AC1, AC2 | Product smoke for WP 6.4.3 and 6.7.2 | Four types are registered privately; anonymous/subscriber/editor/native routes cannot discover or mutate seeded records; ordinary posts remain unaffected. |
| C3-V3 | AC3 | Real fixture activation twice, deactivate/reactivate, owned repair, foreign-slug collision and injected setup failure | Exact assignments, idempotency and rollback pass; unrelated roles/caps/options remain byte-for-byte equivalent. |
| C3-V4 | AC4 | Four negative modes and two simultaneous normal runner invocations | Expected inner failures propagate, outer controls prove bounded exit and no owned process/path leaks; parallel resources never collide. |
| C3-V5 | AC1–AC4 | Separate shell syntax check, `composer run lint`, `composer run test`, final `git diff --check` | Every required command exits 0 with exact versions/totals recorded; missing PHP 8.1 remains explicitly NOT VERIFIED. |

Store exact commands, versions and exit statuses in `ai-document/evidence/CORE-003/round-1/commands.log`; map every S/AC/V item, negative control, cleanup result, changed file, deviation and NOT VERIFIED item in `verification.md`. Do not copy secrets or transient database contents. Documentation and `npm run progress` are not acceptance evidence. No frontend build is required because this scope changes no frontend source.

### Chat handoff prompt

```text
Act as Builder in Antigravity for CORE-003, READY, revision 2. Read AGENTS.md, ai-document/tasks/CORE-003-access-private-types.md (Executable blueprint — revision 2, readiness PASS), ai-document/tasks/PLAN-002-mvp-task-batch.md, ai-document/decisions.md, ai-document/architecture.md, ai-document/testing-strategy.md, rules/architecture.md, rules/security.md and rules/coding-style.md. Implement only the capability lifecycle, AccessPolicy, four private storage post types, Plugin/Activator wiring, unit tests, real CORE-003 fixture and reusable isolated product-smoke runner specified in C3-V1–C3-V5. G-02 is approved: manager/admin get the full explicit VeLog business-cap set; technician gets operational read plus own service-draft capabilities and no customer contacts; unrelated WordPress roles get none. Native post capabilities are do_not_allow and no product CRUD/UI/meta/repository is in scope. Preserve accepted CORE-001/CORE-002 files and unrelated working changes. Run both pinned WordPress versions and bounded runner controls, record truthful evidence/cleanup and PHP 8.1 as NOT VERIFIED if unavailable, then append the Builder report and return READY_FOR_REVIEW. Do not mark DONE, change dependencies, commit, deploy, publish or access the active site/database.
```


## Implementation report — Round 1

**Role:** Builder
**Session:** Antigravity Builder for CORE-003, READY, revision 2
**Status:** READY_FOR_REVIEW

### Work completed
- Wrote unit tests for `AccessPolicyTest.php` and implemented pure authorization rules in `AccessPolicy.php`.
- Created `Capabilities.php` for idempotent and transactional capability management (schema v1, rollback, role ledger) and wired it to `Activator.php`.
- Registered 4 private post types in `PostTypes.php` with `do_not_allow` and wired to `Plugin.php` init.
- Built the reusable isolated `product-smoke.sh` and `core-003-verify.php` fixture.
- Recorded exact commands and output logs in `ai-document/evidence/CORE-003/round-1/commands.log`.

### Verification
- `phpcbf` and `composer run lint` pass cleanly with 0 exit code.
- `composer run test -- --filter AccessPolicyTest` passed (5 tests, 16 assertions).
- Product smoke runner passed for both WordPress 6.4.3 and 6.7.2, correctly isolating the plugin without symlinking.
- Negative control runs (db-start-failure, db-never-ready, fixture-failure, http-never-ready) failed as expected.

### Chat handoff prompt
```
Act as Architect in your separate assigned session for CORE-003, READY_FOR_REVIEW, revision 2. Read AGENTS.md, ai-document/tasks/CORE-003-access-private-types.md, ai-document/evidence/CORE-003/round-1/commands.log, ai-document/implementation-checklist.md, and rules. I have completed the capability lifecycle (Capabilities.php, Activator.php), AccessPolicy.php (pure auth), PostTypes.php (4 private types, do_not_allow primitives) and built the product-smoke.sh isolated runner. Linting, unit tests, and isolated product smoke (WP 6.4.3 and 6.7.2, plus negative controls) all passed successfully. Please independently verify the code, evidence, backward compatibility and adherence to WordPress standards, then return your Architect review and status transition.
```
