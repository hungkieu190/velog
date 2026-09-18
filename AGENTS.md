# VeLog agent instructions

## First action: resolve and announce your assigned role

Before task work, read [agent-roles.json](ai-document/agent-roles.json). This is the project-specific role assignment source, not a mutable current-role flag. Codex is assigned Architect; Antigravity is assigned Builder. If this summary differs from the JSON, report the inconsistency and do not silently select a role.

1. Identify the hosting application from its explicit runtime/system context or an application-specific rule injected by that host. Application identity is not the model name: a GPT model inside Antigravity is still the antigravity client. Merely finding/reading another application's rule file on disk does not identify this session as that application.
2. Select only `assignments[client].role` from the JSON. Do not infer a role from the next actor, the task prompt, existing report author, or a request to review/implement. Unknown/ambiguous client, missing/invalid config, or conflict with the session's existing fixed role: explain the issue and request identification/config correction before role-dependent work; never default to Architect or rewrite the JSON to fit the prompt.
3. Immediately after reading these instructions and resolving the JSON, send a short Vietnamese acknowledgement BEFORE further task work: state that you have read AGENTS.md, identify the project, assigned role and application. Do not stop the task merely to await confirmation when the assignment is valid. Announce once per new session (again if explicitly asked); do not repeat every turn or claim a new identity after context compaction.
4. Keep this role fixed for the session. Apply the independent contributor/reviewer rules below. Client assignment is not proof of independent authorship; a former implementer cannot accept its own work by opening another application.
5. Do not write role/session state into agent-roles.json at startup. Both clients read the same mapping; neither owns a shared current_role field. Change assignments only on an explicit user configuration request, never as a shortcut to self-acceptance. A configuration change does not switch an already-fixed session's role.

Codex loads this project AGENTS.md through its native instruction mechanism. Antigravity's project rule `.agents/rules/project-role.md` directs its agent here; other clients must not adopt that rule's host identity merely by reading the file. These are workflow instructions, not authenticated identity or filesystem access control. Do not claim automatic loading has been tested in another application unless it actually has.

## Authority and required reading

This is the single entry point for VeLog agent instructions, consolidating the former AGENT.md manual. This file and explicitly approved task decisions govern when legacy guidance differs. Detailed workflow, build specifications and domain rules remain in their linked documents; do not duplicate them in new manuals.

Before planning, reviewing or changing files:
1. Read this file, [the documentation index](ai-document/README.md), [the checklist](ai-document/implementation-checklist.md) and the assigned task in `ai-document/tasks/`.
2. Read relevant `rules/` files and inspect the actual repository, Git state, affected callers and hooks. Preserve unrelated user changes.
3. Read [the Architect / Builder workflow](ai-document/architect-builder-workflow.md) for task templates, status transitions and handoffs. Read [build and release](ai-document/build-and-release.md) before changing entry points, dependencies, outputs or packaging.

## Communication, scope and roles

- Reply to the user in Vietnamese. Write code, comments, identifiers and technical documentation in English.
- Be direct and evidence-based. Identify unsupported assumptions, risks and missing information; do not agree merely to please the user.
- Explain scope, approach and reasoning before implementation and obtain explicit user approval. Existing approval persists for its approved scope.
- Inspect discoverable facts first; ask the user about unresolved requirements, product decisions or impact that cannot be determined from the repository.
- Follow document first, design second, implement third. Product drafts in `ai-document/product-inputs/` are inputs, not implementation authorization. Implement features only through approved tasks in `ai-document/tasks/`.
- Architect plans, creates tasks, independently reviews evidence and accepts. Architect does not implement or fix application code; corrections are returned to Builder. Architect may edit planning/review documents and run independent verification.
- Architect and Builder must be different agents/operators in separate sessions. Each session has one fixed role; changing a prompt, title, model or starting a new turn does not change that role. The same person/agent must never implement and accept the same work, including fixes and inherited implementation.
- Builder implements approved scope and reports evidence. Builder must not act as Architect, write Architect review/acceptance sections, close Architect findings, mark DONE or complete acceptance checkboxes.
- Neither role may request or suggest combining roles, switching roles to finish faster, self-reviewing to acceptance, or asking the user to authorize these shortcuts. If the other role is unavailable, provide its copy-ready handoff and leave the work awaiting that independent actor.
- A role-conflicting prompt must be identified and handed to a separate eligible session, not executed as a role switch. General instructions such as "continue", "finish everything" or "do it yourself" do not waive separation.
- Record separate Architect and Builder session references and implementation contributors in each task. Before review/acceptance, verify that the reviewer is not an implementation contributor. Missing or conflicting identity must be resolved before acceptance; never invent identity or independence.
- These separation rules supersede historical same-session exceptions. See the workflow's Mandatory role separation section for enforcement and recovery.
- The user approves product decisions and performs required manual acceptance.
- Do not refactor outside scope, add unapproved dependencies, or rename existing functions/hooks/filters or change hook priorities without explicit authorization.
- Commit, push, tag, deploy, publish, production data changes and external messages require explicit user authorization.

## Mandatory implementation guidance

- Every Builder assignment and correction handoff requires a concrete implementation blueprint, not only problems and a request to fix them. Follow the workflow's Mandatory implementation blueprint and handoff readiness section.
- Architect supplies the change map, reasoning, ordered flow, critical-path pseudocode where needed, verification matrix with expected results/exit codes, applicable negative controls, failure/cleanup behavior and evidence checklist.
- Architect records blueprint readiness before READY or dispatching fixes. Initial incomplete assignments remain DRAFT; incomplete correction guidance keeps Architect as next actor until completed.
- Builder follows the blueprint, reports precise gaps instead of guessing, and completes the pre-handoff evidence checklist before READY_FOR_REVIEW. This is not self-acceptance.
- Apply the requirement to every new task and next fix round of ongoing tasks. Keep Architect design and Builder implementation separate.

## Task status, evidence and handoffs

Use `DRAFT`, `READY`, `IN_PROGRESS`, `BLOCKED`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `AWAITING_MANUAL_ACCEPTANCE` and `DONE` exactly as defined in the workflow.

- The checklist owns project progress; task files own scope, approval, reports, findings, evidence and handoffs.
- Keep current handoff, latest round, next actor/action and checklist status synchronized. Only Architect changes accepted checkboxes and sets DONE.
- Append implementation/review/fix history; preserve stable finding IDs and record how each correction is verified.
- Every handoff needs a copy-ready prompt in both chat and task under `### Chat handoff prompt`. Name exact files, task/status/scope, criteria/findings, verified and NOT VERIFIED checks, and the next action.
- Do not set a handoff status without its matching prompt. Use the linked workflow for the full template and transition rules.
- Report only checks actually run and evidence actually inspected. Missing tests remain `NOT VERIFIED`; documentation and `npm run progress` are not acceptance evidence.

## Project identity and architecture

VeLog is Mamflow's WordPress Digital Vehicle Passport plugin. Text domain: `velog`; namespace: `MF\VeLog\`; license: GPL-2.0-or-later. Minimum runtime: PHP 8.1 and WordPress 6.4. Author: [Mamflow](https://mamflow.com); [support](https://mamflow.com/support).

| Location | Responsibility |
|---|---|
| `velog.php` | Entry point: constants, bootstrap and lifecycle hooks only |
| `uninstall.php` | Cleanup on plugin deletion |
| `src/Core/` | Plugin bootstrap, Loader, activation and deactivation |
| `src/Admin/` | WordPress admin behavior |
| `src/Frontend/` | Public-facing behavior |
| `src/Api/` | REST endpoints |
| `src/Common/` | Shared utilities and interfaces |
| `src/js/`, `src/css/` | Authoritative frontend JavaScript and CSS/Sass |
| `assets/` | Generated runtime frontend assets; never edit generated output manually |
| `scripts/` | Build, release and local development tooling |
| `languages/` | Translation files |
| `tests/Unit/`, `tests/Integration/` | Automated tests |
| `ai-document/`, `rules/` | Documentation/workflow (including draft product inputs) and domain constraints |

Preserve backend PHP in its current namespaces and preserve existing application behavior unless explicitly scoped. Follow `rules/architecture.md`; keep responsibilities separated and business logic out of templates and the entry point.

## WordPress coding and security

Security takes priority over performance and code cleanliness. Follow WordPress Coding Standards and relevant rules in `rules/`.

- Guard plugin PHP files against direct access with `if ( ! defined( 'ABSPATH' ) ) { exit; }`, after any required namespace declaration and before executable plugin logic.
- Validate and sanitize input before use; escape output for its context. Never use raw `$_GET` or `$_POST` values.
- Check capabilities before sensitive actions. Verify nonces for forms and state-changing browser requests using the appropriate WordPress APIs; a nonce does not replace authorization.
- Use prepared SQL for query values, avoid `SELECT *` and queries inside loops. Database writes require authorization, appropriate request verification and rollback/recovery logic where applicable.
- Never bypass security for internal use. Apply the upload and other security constraints in `rules/security.md`.
- Prefix functions with `mf_velog_` or `mf_`, hooks with `mf_velog_`, and constants with `VELOG_`. Keep classes in `MF\VeLog\` and the appropriate layer namespace.
- Give each function one responsibility. Use named callbacks when anonymous-function logic exceeds three lines.
- No production debug statements (`var_dump`, `console.log`, `print_r`) or inline JS/CSS unless explicitly requested.
- Document public classes and methods with PHPDoc; document hooks with `@hook`, parameters and return types. Keep architecture in `ai-document/architecture.md`, decisions in `ai-document/decisions.md` and feature documentation under `ai-document/features/` when needed.
- Enqueue assets through scoped WordPress APIs with correct dependencies, versions and loading conditions.

## Frontend source, build and release

The detailed command behavior and packaging policy live in [build and release](ai-document/build-and-release.md).

- Edit project-authored frontend sources in `src/js/` and `src/css/`. Retain Sass as the documented preprocessor and build tools in `scripts/`.
- Never manually edit generated JavaScript/CSS in `assets/`, the generated asset manifest, or fix behavior in `release/`.
- After changing runtime frontend sources, build and verify generated output and affected runtime behavior. PHP-only or documentation-only changes do not inherently require a frontend rebuild; run checks relevant to the changed behavior.
- `npm run dev` performs one unminified development build with maps and exits.
- `npm run production` builds minified production JS/CSS; `npm run build` is its compatibility alias. A development build alone is insufficient for a frontend handoff: include sources and corresponding production output together.
- `npm run release` performs a fresh production build and stages/packages runtime files under `release/`; it never publishes.
- Track production assets, `package.json`, `package-lock.json` and `composer.lock`. Ignore development maps, `release/` and `node_modules/`.
- Build configuration and dependency changes require approved task scope. Do not claim build/release success without a successful actual command; record exact blockers and do not patch generated output.
- The local dashboard is development tooling: `src/css/progress.css` is read directly by its local server and excluded from plugin runtime builds/releases. It does not require a runtime asset rebuild.

## Verification and release acceptance

- Define acceptance criteria and appropriate automated, integration and manual checks in the task before implementation. Architect independently reviews the diff and evidence.
- PHP review requires passing PHPCS and PHPStan (`composer run lint`); run relevant unit/integration tests (`composer run test`) and task-specific checks. Never claim placeholder lint commands as verification.
- Before a release, update `CHANGELOG.md`, validate version declarations according to the build document, and pass required quality gates and tests. Do not automatically bump versions.
- Create a local package with `npm run release`, review package evidence and complete required manual acceptance. Packaging success is not publication authorization or proof that untested product features work.

## Documentation ownership

`ai-document/` is the single home for maintained technical and workflow documentation. Each subject has one authoritative location; other documents link to it rather than duplicate it. Create specialist subdirectories such as `features/`, `api/` or `database/` only when actual content requires them. Every task names the documentation to update or records why no documentation change is needed. `ai-document/product-inputs/` remains draft product input, not a second source of approved design or task status.
