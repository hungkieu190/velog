# PLAN-002: Prepare the complete MVP implementation queue

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference (planner): Codex conversation started 2026-09-21 with the request to read AGENTS.md and then plan successive tasks. This is a descriptive reference, not an asserted machine session ID.
- Builder session reference (implementer): CORE-003 assigned to Antigravity Builder under its task revision 2; the remaining draft tasks are unassigned.
- Implementation contributors and reviewer independence check: This session changes planning documents only. Record all future implementers separately before independent review.
- Related checklist items: PLAN-002 / AC1–AC3; CORE-002 through MVP-001 below.
- Baseline branch and commit; pre-existing changes: main at 2aa3b8d; git status --short was empty before this planning pass.
- User approval reference and approved scope: On 2026-09-21 the user explicitly requested continuous planning. On 2026-09-22 the user instructed Architect to start CORE-003 and hand it to Builder, approving G-02 for that bounded task. Other proposed business rules remain pending.
- Latest round: CORE-003 dispatch update — 2026-09-22.
- Next actor: Architect
- Next actor and exact next action: Builder implements CORE-003 revision 2. Architect maintains the remaining draft queue and resolves G-03–G-08 before their future assignments.

## Problem and intended behavior

PLAN-001 approves the international private single-shop MVP. CORE-001 and CORE-002 are accepted; CORE-003 is the next bounded implementation assignment. The user wants the complete sequence planned ahead, instead of alternating a single plan with each implementation. This batch supplies ten bounded blueprints across P0–P4. Drafting can proceed across dependencies; execution and acceptance cannot skip them.

Inspected source: velog.php, src/Core/{Plugin,Loader,Activator,Deactivator}.php, uninstall.php, composer.json, package.json, tests/{bootstrap.php,phpunit.xml}, tests/workflow/core001-smoke.sh and scripts/release.config.mjs. Plugin now has once-only run() and init translation wiring; this does not close CORE-001's remaining verification findings. Product repositories, CPTs, settings UI and product tests are absent. uninstall.php currently deletes velog_settings, which must be reconciled with retention before storing operational configuration there. Existing PHPUnit uses a mocked WordPress environment, so a file under tests/Integration is not by itself proof of real WordPress integration.

## Scope and references

Planning documents only. Read AGENTS.md, architect-builder-workflow.md, product-plan.md, internationalization.md, decisions.md, architecture.md, testing-strategy.md, build-and-release.md and the existing CORE-001 task. Each new task links this shared contract and supplies its own files, algorithm, criteria and test cases. No runtime code, tests, generated assets, dependencies or database data changed during planning.

## Dependency queue

| Order | Task | Accepted predecessors required before implementation | Outcome |
|---|---|---|---|
| 0 | [CORE-001](CORE-001-bootstrap-i18n.md) | Existing approved scope | Close remaining smoke/isolation findings; preserve review history |
| 1 | [CORE-002](CORE-002-regional-primitives.md) | CORE-001 | Exact distance/money/date/input primitives |
| 2 | [CORE-003](CORE-003-access-private-types.md) | CORE-001 | Capability lifecycle and private CPT boundary |
| 3 | [DATA-001](DATA-001-record-storage.md) | CORE-002, CORE-003 | Versioned record storage, concurrency and retention |
| 4 | [CORE-004](CORE-004-regional-settings.md) | CORE-002, CORE-003, DATA-001 | Explicit setup and shared admin shell |
| 5 | [CUST-001](CUST-001-customer-records.md) | CORE-004, DATA-001 | Customer create/edit/search/archive |
| 6 | [VEH-001](VEH-001-vehicle-records.md) | CUST-001 | Vehicle identity, owner, baseline odometer |
| 7 | [SERV-001](SERV-001-service-workflow.md) | VEH-001 | Draft/finalized services and attributed corrections |
| 8 | [HIST-001](HIST-001-service-timeline.md) | SERV-001 | Searchable, private, deterministic service timeline |
| 9 | [REM-001](REM-001-maintenance-queue.md) | HIST-001 | Manual date/distance maintenance queue |
| 10 | [MVP-001](MVP-001-acceptance-package.md) | All preceding tasks | End-to-end, performance, privacy and local ZIP acceptance |

CORE-002 and CORE-003 are logically independent after CORE-001; this is a dependency observation, not authorization to start parallel agents. Default execution follows the order above to minimize shared Plugin.php changes. Each task must return evidence for independent review before dependent execution. Plans can be revised in batches without implementing anything. Photos, public sharing, QR/PDF, notifications, recurrence and multi-shop remain deferred; no speculative implementation tasks are created for them.

## Consolidated proposed product decisions

All rows below are PENDING, not amendments to the approved master plan. User may approve the group with exceptions; Architect records the actual response once and propagates it to owning tasks. Technical gates remain Architect work even after product approval.

| Gate | Proposed rule for approval | Owners / impact |
|---|---|---|
| G-01 | Regional input: localized decimal separator, no grouping in editable numeric fields, ASCII digits initially with explicit feedback for unsupported digits; km/mi at most 3 decimals, values 0–999999999.999; money nonnegative, at most 15 minor-unit digits. If no versioned catalog is selected, manager explicitly enters uppercase 3-letter currency code plus scale 0–3; clearly label this as shop configuration, not certified ISO membership. | CORE-002/004; catalog/source or this validated configuration must be resolved before READY. |
| G-02 — APPROVED 2026-09-22 | Add mf_velog_manager and mf_velog_technician roles; give plugin capabilities to administrators. Manager handles configuration, customer/vehicle changes, all service corrections and reminders. Technician reads operational records, creates/finalizes own service drafts and sees customer name only; customer contact details are manager-only. WordPress user administration assigns roles. No auto-enrollment of existing editors/authors. | CORE-003, CUST-001, SERV-001; role lifecycle specification below. |
| G-03 | Customer name 1–200 characters, optional phone up to 64/email up to 254; plain text; duplicate names/contact details allowed. Active vehicles must be reassigned or archived before archiving their current customer. Archive may be reversed by manager, with an audit entry; no hard-delete UI. | CUST-001 and VEH-001. |
| G-04 | Plate OR VIN required. Plate uniqueness uses country/region jurisdiction plus Unicode-preserving normalized plate; require jurisdiction when plate exists. Trim/collapse whitespace and uppercase ASCII letters only; preserve punctuation/non-ASCII. VIN may be nonstandard; trim, uppercase ASCII, preserve other characters, reject controls, max 64 characters. Plate max 64; jurisdiction max 100. Block exact normalized duplicates including archived vehicles; edit-self is allowed; no automatic fuzzy merge. Proposed optional year range is 1886 through local current year + 1. | VEH-001. Conservative policy may miss differently punctuated variants; show guidance instead of claiming global identifier validation. |
| G-05 | Initial odometer may be unknown; service finalization requires a reading/date. Choose current reading by service date descending then stable service ID descending, including an initial baseline with its own reading date. Reject a decrease relative to neighboring dated readings unless a manager supplies a reason. Backdated insertion does not automatically replace the current reading. Physical odometer replacement/reset is excluded until a separate policy exists. | VEH-001, SERV-001, REM-001. |
| G-06 | Draft editable by creator or manager; finalization/correction uses optimistic version checks. Finalized fields can be corrected by manager only with nonempty reason; append actor/UTC time/full before-and-after snapshots. Save customer association and minimal customer-name snapshot when finalizing; later owner changes do not rewrite history. Taxonomy names are snapshotted too. Future service dates disallowed; no deletion of finalized records. Proposed limits: notes 10000 characters, correction reason 1–1000; one manager-configured active service type required at finalization, no invented default types; referenced types may be deactivated but not deleted. Archived vehicles block draft writes; manager may correct existing finalized history without restoring the vehicle. | SERV-001/HIST-001; operational audit, not tamper-proof proof. |
| G-07 | Reminder requires date and/or distance; due when either is reached. Snooze hides due status until the chosen future local calendar date; then reevaluate original thresholds. Completed is terminal in MVP; no reopen, recurring rule, automatic service or notification. Archive suspends active reminders from the actionable queue, preserves them in history; restore reevaluates thresholds. | REM-001. |
| G-08 | Acceptance baseline: synthetic 1000 customers, 2000 vehicles, 20000 finalized services, 5000 reminders; 50 rows/page; warm p95 <=2 seconds for vehicle search, timeline and due queue on an explicitly recorded reference machine, 30 timed requests after 5 warmups. Retain operational data/configuration on uninstall; no built-in erasure/export in MVP, so production rollout remains blocked until the owner approves an operational privacy/retention procedure. | DATA-001/MVP-001. Performance target and production policy require explicit agreement. |

## Shared blueprint contract — revision 1

These sections are incorporated by reference by all ten implementation drafts. A task's own detailed rules override only where explicitly stated and approved. Blueprint readiness is INCOMPLETE for the batch: product gates above and future accepted-source reinspection remain. Do not mistake the amount of draft detail for permission to mark READY.

### Architecture and security

Use existing Core bootstrap/Loader composition; named callbacks registered through define_*_hooks(), no change to existing callback priorities or public signatures. New Common services own validation/policy/storage; Admin owns forms/tables and calls Common services with an explicit actor. No business logic in templates or velog.php. Use private CPTs and registered protected metadata; no public or REST routes, no custom tables, no runtime dependencies. Proposed new files do not exist yet and must be inspected against the then-current checkout before creation.

Browser writes use POST, action/object-specific nonce, capability and object checks, strict scalar input validation after unslashing, then domain validation. Reject unexpected arrays, unknown fields, missing/foreign IDs and stale versions; do not coerce malformed values into valid zero/IDs. Read authorization must apply to query and detail paths. Unauthorized requests must neither return protected fields nor create partial records. Escape values at their output context. Sanitization must not silently transform an invalid financial amount, identity or date into a different valid record.

Use dedicated admin-post callbacks per resource action, native WordPress admin layout and WP_List_Table for lists. Whitelist sorting/filter keys; paginate and bound search lengths. Bulk operations only when semantically safe (archive/restore where approved), checking each object and reporting each result; no bulk finalization/correction. Other lists explain why bulk mutation is unavailable. This is a proposed scoped exception to rules/ui-ux.md's blanket bulk-action requirement and must be resolved by Architect before READY. Keyboard, visible labels, error association/focus, narrow viewports, long strings and RTL are required. If sources in src/css/admin.scss or src/js/admin.js change, produce and inspect matching assets with npm run production; use scoped admin_enqueue_scripts and record that unrelated WP screens load no VeLog assets.

### Verification environments and commands

Every task runs composer run lint (PHPCS and PHPStan) and composer run test, expected exit 0 and actual totals recorded, plus its named targeted PHPUnit filter. These names describe tests to be added, not currently available passing tests. Mocked tests verify domain/adapter behavior; real WordPress cases live in tests/fixtures/<task-lowercase>-verify.php and run through the owned isolated runner below. Do not modify tests/phpunit.xml to hide warnings or remove existing tests. git diff --check must exit 0. For changed runtime frontend sources, npm run production must exit 0. Placeholder lint:js/lint:css are not checks.

CORE-003 establishes tests/workflow/product-smoke.sh with explicit arguments --task=<ID> --wp-version=<pinned-version>. CORE-002 uses pure tests plus the accepted bootstrap smoke; DATA-001 extends the runner's persistence fixtures. Run task fixtures at WP 6.4.3 and 6.7.2, PHP 8.1 and the available current local PHP runtime, recording exact versions (these are reproducibility baselines, not claims of current release support). If a version or dependency is unavailable, preserve NOT VERIFIED with attempted command/error. No real WP fixture may run against the working site. Before READY, CORE-003 must specify exact provisioning commands and artifact checksums based on the then-available local toolchain; the current defective CORE-001 smoke is not a safe reusable harness.

### Isolation, failure and cleanup

Runner atomically owns a private mktemp directory, plugin copy, separate WordPress tree and dedicated database process/socket. Resolve repository root from script location; never hard-code this workstation path. Track only launched PIDs. Database readiness is capped at 30 seconds with child-liveness checks; HTTP startup at 15 seconds; each fixture at 120 seconds and total run at 600 seconds (performance fixture may use an explicitly documented larger bound). Timeout/fatal/setup failure returns nonzero. On EXIT/INT/TERM preserve original error status, stop HTTP child, TERM database child, wait up to 10 seconds then kill only that owned child if still alive and reap it; remove only the atomically reserved directory after children stop. Preserve sanitized logs outside temporary tree. No broad pkill, fixed shared datadir, active database credentials or sudo-based workaround. If successful test cleanup fails, return nonzero and retain diagnostic ownership information.

Positive and negative controls invoke the same domain validator/request handler. Expected invalid input produces WP_Error or HTTP rejection without writes; outer test runner exits 0 only when that rejection is asserted. Unexpected acceptance must fail the test nonzero. For authorization, replay identical input with manager, permitted technician, other technician, subscriber and anonymous actor; distinguish capability rejection from nonce rejection by separately testing a valid nonce without capability and capability with invalid nonce.

### Storage and failure contract

DATA-001 owns versioned snapshots and concurrent-write behavior. Feature tasks do not invent alternative meta keys, transaction/lock schemes or repair logic. A service-finalization or vehicle-creation operation spanning records must either commit coherently or report failure without exposing a successful partial state. Tests inject each persistence failure and interleave concurrent requests. DATA-001 remains DRAFT until exact supported database/storage-engine behavior, duplicate uniqueness serialization and crash recovery are designed and verified in the disposable environment. Do not label ordinary update_post_meta sequences transactional or rely solely on a disabled submit button.

### Evidence and completion

Each implementation writes ai-document/evidence/<TASK-ID>/round-1/{commands.log,verification.md} and task-specific artifacts indicated in its matrix. Record baseline commit/diff, implementer identity, versions, fixture IDs, command exit codes/test totals, expected negative rejections, actual HTTP/data observations, cleanup and limitations. Use synthetic personal data only; no passwords, cookies or nonces in retained evidence. Append later rounds rather than overwriting logs. Manual procedures/results go in ai-document/walkthroughs/<TASK-ID>.md.

Builder pre-handoff checklist: map every step/AC to changed files and evidence; report all deviations and NOT VERIFIED checks; record resources/cleanup and required builds; synchronize task/checklist current handoff and append copy-ready independent Architect prompt. Never check acceptance boxes or mark DONE. Architect reruns relevant tests, verifies contributor independence and either accepts, requests concrete corrections or records required manual acceptance. No commits, pushes, version bumps, deployments, publication or external messages are authorized.

## Acceptance criteria for the planning batch

- AC1: Ten bounded draft tasks cover the approved P0–P4 journey, with explicit dependencies, allowed files, pseudocode and verification expectations.
- AC2: Pending product choices and technical gates are visible, with no invented approval or runtime verification claims.
- AC3: User decisions are recorded; each task promoted later only after its individual readiness gate, source reinspection and prerequisite acceptance. Pending; this planning request alone does not satisfy AC3.

## Planning verification

Read-only repository inspection performed on 2026-09-21. No runtime, PHP test, integration, performance, manual UI or package command was run for this batch; all product results remain NOT VERIFIED. Documentation verification: existing readProgress parser returned all 11 new planning/task documents as DRAFT, owned by Architect, with one valid prompt each; exit 0. A temporary read-only document check covered 17 changed/new documents and 56 local links, required blueprint headings, AC1–AC4 coverage, balanced fences and trailing whitespace; exit 0. git diff --check passed exit 0. These are planning consistency checks only. The parser also reports a pre-existing PLAN-001 DONE/Next actor Builder mismatch; its accepted historical handoff was not rewritten in this batch. Existing accepted PLAN-001/WF-001 history and CORE-001 findings are preserved.

### Chat handoff prompt

```text
Continue as Architect for PLAN-002, DRAFT revision 1. Read AGENTS.md, ai-document/tasks/PLAN-002-mvp-task-batch.md, ai-document/implementation-checklist.md and all ten task links in the Dependency queue. The user authorized continuous planning on 2026-09-21 and wants Builder execution later. Draft scope covers CORE-002, CORE-003, DATA-001, CORE-004, CUST-001, VEH-001, SERV-001, HIST-001, REM-001 and MVP-001, using Shared blueprint contract revision 1 and each task's Implementation blueprint revision 1. CORE-001 remains CHANGES_REQUESTED with F-003/F-004 open; its next correction handoff needs the mandatory readiness audit before redispatch. Repository inspection and documentation checks are the only planning evidence; all new product runtime, integration, performance and manual checks are NOT VERIFIED. Next: resolve G-01–G-08 as a consolidated decision set, finish each listed technical gate and reinspect accepted dependencies before any READY assignment. Preserve role separation, existing review history and checklist acceptance state. Do not implement application code, start Builder, commit, deploy or touch the active database.
```
