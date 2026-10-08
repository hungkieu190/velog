# Decision log

## Approved bootstrap decisions — user approval on 2026-09-17

- D1: Adopt ai-document/implementation-checklist.md and tasks/ as workflow sources of truth. Preserve legacy product plans and rules; reconcile conflicting workflow instructions during implementation.
- D2: Keep existing Sass and esbuild. Move JS to src/js/ and SCSS to src/css/ with its partials; SCSS is the documented CSS-preprocessor variation. Preserve compiled filenames and behavior.
- D3: Keep PHP classes in src/Core/ and the existing namespace. Do not exclude all src/ from releases: allowlist runtime PHP directories explicitly.
- D4: Track production assets and both dependency lockfiles. Clean checkouts require composer install to supply the current runtime autoloader; packaged installations require no Node or Composer.
- D5: velog.php plugin header is the version authority; validate VELOG_VERSION, package.json, and README.md Stable tag against it. composer.json currently has no version: do not invent one or automatically bump versions.
- D6: Generate a production-only Composer autoloader in isolated staging using the existing lockfile and no network installation during release. Do not copy development vendor wholesale. Fail when prerequisites cannot be satisfied.
- D7: Preserve npm run build as a production compatibility alias; migrate legacy bin entry points to the same implementation. Correct check.sh exit-code handling so tool failures cannot pass.
- D8: Keep existing dependencies; adding a ZIP library requires a specifically recorded scope approval. Prefer built-in Node packaging if practical. Selected Node 24 LTS; verified Node 24.21.0/npm 11.19.0. Use PHP ZipArchive for packaging with no new npm dependency.
- D9: No feature implementation, deployment, tag, commit, push, publication, or changes to the active site's database are included.

## Implementation clarifications

- Tooling-only dashboard CSS is read from src/css/progress.css and never packaged with the plugin.
- Frontend scaffold callbacks were named and formatted to satisfy the existing WordPress PHPCS rules; no application PHP or behavior changed.
- rules/coding-style.md source path/indentation directions were reconciled to avoid contradictory enforcement after migration.
- Historical WF-001 exception (no longer applicable): acceptance was initially reserved for a separate reviewer; the user then reassigned that session to Architect for self-review. The historical evidence remains recorded in WF-001. This is not a precedent: GOV-001 below now prohibits dual-role operation.

## Proposed product decisions — PLAN-001 revision 2

Historical revision 2 proposal; superseded by the approval/amendment below. Preserve this table as proposal history, not current defaults.

| ID | Proposed decision | Alternative / consequence |
|---|---|---|
| P-001 | One shop per WP installation; private WP admin MVP includes customers/vehicles, services and internal maintenance queue | Multi-shop, customer portal, photos or email at first launch require revised scope and architecture |
| P-002 | Manager controls configuration and historical corrections; technician reads shop history and creates/finalizes own drafts | Broader or restricted staff access requires an explicit permission matrix |
| P-003 | Customers are private records without WP accounts; archive linked records and preserve history; retain on uninstall | Customer login, automatic erasure or purge needs separate lifecycle and privacy decisions |
| P-004 | Plate or VIN required, allow nonstandard/missing VIN; km and VND; configurable service types; optional cost without WooCommerce dependency | Other markets, currencies/units or mandatory standard VIN change validation; exact duplicate rules remain P1 gate |
| P-005 | Finalized service corrections are manager-only with recorded reason/actor/time; odometer corrections are explicit | Freely editable history weakens traceability; full immutable audit ledger is a separate scope |
| P-006 | MVP reminders are manually configured thresholds, due when either date or recorded odometer threshold is met; snooze suppresses due status until its date | Automated recurring intervals/email need additional scheduling, recipient, retry and duplicate-send policies |

## Product approval — PLAN-001 revision 3, 2026-09-18

The user stated that the product serves international markets and must configure km/miles and relevant units, then approved the remaining plan and authorized starting.

- P-001, P-002, P-003, P-005 and P-006: APPROVED as proposed in revision 2.
- P-004: APPROVED WITH AMENDMENT. Remove fixed km/VND assumptions. Require international unit/currency configuration, locale-aware input/display, timezone-aware dates, Unicode identifiers and translation/RTL readiness. The approved identifier flexibility, configurable service types and WooCommerce independence remain.
- Follow internationalization.md for engineering constraints and staged verification. km/mi and currency/date/number behavior are MVP requirements; additional measurement dimensions apply when corresponding fields enter scope. Existing records must retain original unit/currency meaning when preferences change.
- This approval authorizes Architect to create bounded Builder assignments, beginning with CORE-001. It does not reassign Architect to application implementation or authorize commit, publication, external messaging or active-site database changes.
- PLAN-F-001 and PLAN-F-003: planning scope resolved by private MVP and deferred sharing/photos. PLAN-F-002: role direction approved; detailed mapping is gated by its future task. PLAN-F-005: fixed-precision regional costs and internal thresholds approved; details remain in owning tasks. PLAN-F-004: transferred to CORE-001, unresolved until implementation is verified.

## GOV-001 — Mandatory separate Architect and Builder roles (2026-09-18)

Approved directly by the user: update the workflow to prohibit one person/agent from holding both roles and prevent Builder from asking to act as Architect and complete acceptance itself.

- Separate agents/operators and sessions; fixed role per session; no proposing or requesting role-combination/self-acceptance exceptions.
- Architect owns planning, independent review and acceptance; Builder owns code implementation and fixes. All implementation contributors are ineligible to accept their own task.
- Role-conflicting requests are handed off to an independent eligible session. Contributor/reviewer identities are recorded and checked before acceptance.
- Supersedes historical reassignment/self-review exceptions prospectively; preserves prior evidence and does not automatically reopen historical tasks.
- This change governs agent behavior and review procedure. No runtime code or automated access-control enforcement is included.

## GOV-002 — Mandatory implementation guidance for every handoff

User instruction: incorporate detailed Architect guidance into the workflow itself, not just the current task. Effective immediately for new work and the next handoff of ongoing work.

Architect must provide a reviewable implementation/correction blueprint, ordered steps, applicable pseudocode, a verification matrix, failure/cleanup rules and evidence expectations. Record assignment-readiness before dispatch. Builder must preflight those instructions and map implementation/results to them before review handoff. Missing guidance is returned to Architect; role separation and acceptance authority remain unchanged.

Templates and gates are defined in architect-builder-workflow.md and referenced by AGENTS.md. This documentation change adds no automated tooling enforcement or product scope. Historical task reports remain unchanged.

## GOV-003 — Consolidate maintained documentation

User approved removing the empty docs/ scaffold. Its ten .gitkeep placeholders were removed after verifying there were no content files. Maintained technical/workflow documentation belongs in ai-document/; architecture.md and decisions.md remain authoritative. Add specialist subdirectories only for actual content, and require each task to identify documentation updates or justify N/A. Active references were updated; historical evidence is preserved. plans/ remains unchanged as draft product input pending any separate user decision.

## GOV-004 — Move product inputs into the documentation tree

User approved moving the four product drafts to ai-document/product-inputs/ and removing plans/. Draft bytes and status were preserved; empty ideas/backlog/completed placeholders were removed. Active references and task reading paths were updated without changing historical task decisions. Historical evidence files and accepted-file manifests retain their original paths as immutable snapshot records; former plans/current/<filename> maps to ai-document/product-inputs/<filename>. These inputs are not approval authority; product-plan.md, decisions.md and assigned tasks govern implementation.

## GOV-005 — Application-specific startup roles

User approved a static project JSON mapping (Codex → Architect, Antigravity → Builder) and mandatory immediate Vietnamese role acknowledgement after reading AGENTS.md. Implemented in ai-document/agent-roles.json, root startup instructions and an Antigravity workspace rule. No per-session writeback or shared current_role field. Unknown application identities must be clarified, not guessed. Model names do not identify the host. Existing fixed-role/contributor separation remains binding.

Configuration is project-scoped; global user files are unchanged. JSON and instruction consistency are verified locally; fresh-session loading in the two applications is not yet independently tested. This is automatic role guidance, not tamper-proof access control.

## PLAN-002 — Continuous planning authorization (2026-09-21)

The user requested successive plans for multiple tasks and explicitly placed Builder implementation later. This authorizes Architect to prepare the entire remaining P0–P4 draft queue now, without a confirmation between each planning document. It does not approve unconfirmed business rules or dispatch Builder. PLAN-002 owns proposed G-01–G-08 and each task owns its technical readiness gates; all remain pending until actual decisions are recorded. Existing PLAN-001 approval, CORE-001 findings and role separation are unchanged. No product or runtime verification is claimed by writing the plans.

## G-02 — Staff capability policy (2026-09-22)

APPROVED. The user instructed Architect to begin CORE-003 and hand implementation to Builder after reviewing the proposed next work. Create `mf_velog_manager` and `mf_velog_technician`; administrators receive the complete explicit VeLog capability set. Managers can configure the plugin, manage customers and vehicles, correct all service records and manage reminders. Technicians can read operational records and create, edit and finalize only their own service drafts. Customer contact details remain manager/admin only. Existing editors, authors and other WordPress roles receive no VeLog capabilities automatically. WordPress user administration remains the assignment mechanism.

This decision authorizes the bounded CORE-003 capability lifecycle and private record-type implementation after its executable blueprint reaches PASS. It does not authorize product CRUD screens, repository/storage behavior, automatic user-role migration, commit, deployment or active-site database access.

## G-08 — Storage planning and acceptance benchmark (2026-09-25)

The user approved the proposed DATA-001 planning/verification approach. The benchmark target is 1,000 customers, 2,000 vehicles, 20,000 services and 5,000 reminders, with warm p95 <= 2 seconds for specified operational list/search queries on a recorded isolated environment. Operational data and configuration are retained on uninstall. This approves the target and disposable design investigation, not a claim that the benchmark has passed or that the DATA-001 blueprint is READY. Application implementation, active-site database changes, commit, push and deployment remain outside this authorization.

## G-03 — Customer archive and restore policy (2026-10-06)

APPROVED. A customer may be archived only when no active vehicle currently links to that customer. An active link requires vehicle reassignment or vehicle archive first, and a rejected customer archive changes neither record. Archive and restore require manager authorization, action-specific nonce validation at the HTTP boundary, exact expected-version checks, unique request IDs, version increments, and audit entries. Restore is allowed for a valid archived customer. There is no hard delete. Bulk transitions authorize and report each customer independently rather than claiming a cross-customer transaction.

This decision approves the CUST-001 product rule and blueprint completion. It does not by itself authorize source implementation, commit, deployment, or active-site database changes.

## G-04 — Vehicle identifier and uniqueness policy (2026-10-08)

APPROVED. A vehicle may be registered when it has at least one identifier: a plate together with its jurisdiction, or a VIN. Plate uniqueness is the pair of jurisdiction and normalized plate; VIN is independently unique. The same normalized identifier remains reserved across both active and archived vehicles. VIN input is bounded to 64 characters. Vehicle year is optional and, when supplied, must be an integer from 1886 through the current calendar year plus one. An edit may retain its own identifiers but cannot collide with another vehicle.

This decision authorizes VEH-001 backend planning against the accepted CUST-001-BE relation contract. It does not settle G-05 odometer/backdating rules, authorize source implementation that depends on those rules, or authorize commit, deployment, or active-site database access.

## G-05 — Odometer chronology and backdated-service policy (2026-10-08)

APPROVED. A vehicle's initial odometer may be unknown. A finalized service requires both a reading and a date. Known readings are ordered by service date descending and then stable service ID descending, with an initial baseline retaining its own reading date. A reading that decreases relative to its known chronological neighbours is rejected unless a manager supplies a nonempty reason recorded in the audit trail. A backdated service is validated against its neighbours but does not automatically replace the current reading. Physical odometer replacement or reset is excluded until a separate policy is approved.

This decision authorizes the VEH-001 backend blueprint together with G-04 and the accepted CUST-001-BE contract. It does not authorize commit, deployment, release, or active-site database access.

## Owner decision — 2026-10-08: Tester owns browser verification

This decision supersedes conflicting frontend verification requirements in earlier task descriptions, review requests, and setup guidance. Historical evidence remains unchanged.

- Frontend Developer implements assigned presentation source, builds generated assets, records build/diff results, and hands off code. It is not required to run browser/manual verification, capture screenshots, or provide visual evidence.
- Backend Architect reviews source/generated diffs and architecture/security boundaries, verifies relevant build/static checks, and creates a concrete Vietnamese `<ID>-UAT` task. It must specify environment, accounts, data, numbered actions, expected results, negative cases, and a per-case result table. Missing browser screenshots are not grounds to reject a frontend code handoff.
- Tester / Product Owner owns all manual UI, responsive, RTL, keyboard, screen-reader, error-state, pagination, and asset-loading verification. Screenshots/videos are optional supporting material, not a mandatory builder deliverable.
- Code approval moves the FE task, UAT task, and parent feature to `AWAITING_MANUAL_ACCEPTANCE`, with `Next actor: Tester`. `Review decision: APPROVED` means code review only; it does not mean a browser case passed.
- Tester records actual results as PASS, FAIL, or BLOCKED and alone decides the transition to DONE. Agents may synchronize DONE only after the user's explicit acceptance; they never infer it from build success or elapsed time. Partial/blocked results are not full acceptance. Backend workstream DONE remains a technical `SELF_REVIEWED_BACKEND` checkpoint, not acceptance of the feature.
- On FAIL, Backend Architect triages the reported case and assigns correction to the appropriate BE/FE owner. After code review, Tester reruns affected cases and relevant regressions before deciding DONE.
- Test data/environment preparation must be explicit. If a prerequisite is unavailable, record BLOCKED and return preparation to Backend Architect; do not silently skip it or require Frontend Developer to create backend fixtures.
