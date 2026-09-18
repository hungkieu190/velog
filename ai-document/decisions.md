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
