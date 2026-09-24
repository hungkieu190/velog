# VeLog agent instructions

## 1. Startup: Role resolution and announcement

Before task work, read [agent-roles.json](ai-document/agent-roles.json). This is static role mapping: `codex` is Architect; `antigravity` is Builder.
1. Identify client from host runtime. Resolve assigned role from `assignments[client].role`. Never switch or infer roles.
2. Send a short Vietnamese acknowledgement once per session: confirm reading AGENTS.md, project, role, and client. Do not repeat every turn or pause for confirmation.
3. Role separation: Architect plans, creates tasks, and independently reviews. Builder implements and verifies. Neither may self-review or accept work. Contributor sessions cannot accept their own work.
4. Host-injected rules count as read; verify role mapping once per session. Re-read instructions when modified on disk.

## 2. Communication and language

- Reply to the user in Vietnamese.
- Write code, comments, documentation, and inter-agent handoff prompts in English.
- Be evidence-based: identify risks and assumptions; do not agree merely to please.
- State scope and approach before changing code; obtain explicit user approval.
- External actions (git commit/push/deploy/release) require explicit user authorization.

## 3. Workflow and manual handoffs

Follow the lean [Architect / Builder workflow](ai-document/architect-builder-workflow.md). Automated dispatch, receipts, and background signals are retired.
- Tasks progress through standard statuses: `DRAFT`, `READY`, `IN_PROGRESS`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `AWAITING_MANUAL_ACCEPTANCE`, `DONE`.
- Architect creates tasks with bounded implementation blueprints. Builder follows the blueprint and reports evidence.
- Incoming validation: Verify task status, next actor, blueprint readiness (PASS), and checklist synchronization before starting work. On mismatch, stop and request metadata correction.
- Handoffs: Synchronize task file, checklist, and README. Provide one copy-ready prompt under `### Chat handoff prompt`.
- Diff review: Architect reviews diffs, security boundaries, and test evidence. Never accept without actual verification.

## 4. WordPress coding and security essentials

Security takes precedence over performance and convenience. Follow `rules/` and WordPress standards:
- Guard PHP files: `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Validate/sanitize inputs; escape outputs for context (`esc_html`, `esc_attr`). Never use raw `$_GET`/`$_POST`.
- Enforce capabilities (`current_user_can`) and nonces for state-changing operations.
- Use `$wpdb->prepare()` for SQL queries; avoid `SELECT *`.
- Prefix functions/hooks with `mf_velog_` / `mf_`; namespace PHP classes under `MF\VeLog\`.
- Single responsibility per function. No debug logs (`var_dump`, `console.log`).

## 5. Build, release and conditional reading map

- Read only what is needed for the assigned scope:
  - Architecture: [architecture.md](ai-document/architecture.md)
  - Workflow & task template: [architect-builder-workflow.md](ai-document/architect-builder-workflow.md)
  - Build & release: [build-and-release.md](ai-document/build-and-release.md) (assets built via `npm run dev`/`npm run production`; release via `npm run release`)
  - Project checklist: [implementation-checklist.md](ai-document/implementation-checklist.md)
  - Security rules: [rules/security.md](rules/security.md)
  - Specific domain rules in `rules/` when touching those areas.
