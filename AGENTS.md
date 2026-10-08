# VeLog agent instructions

## Owner decision — 2026-10-08: Tester owns browser verification

This decision supersedes conflicting frontend verification requirements in earlier task descriptions, review requests, and setup guidance. Historical evidence remains unchanged.

- Frontend Developer implements assigned presentation source, builds generated assets, records build/diff results, and hands off code. It is not required to run browser/manual verification, capture screenshots, or provide visual evidence.
- Backend Architect reviews source/generated diffs and architecture/security boundaries, verifies relevant build/static checks, and creates a concrete Vietnamese `<ID>-UAT` task. It must specify environment, accounts, data, numbered actions, expected results, negative cases, and a per-case result table. Missing browser screenshots are not grounds to reject a frontend code handoff.
- Tester / Product Owner owns all manual UI, responsive, RTL, keyboard, screen-reader, error-state, pagination, and asset-loading verification. Screenshots/videos are optional supporting material, not a mandatory builder deliverable.
- Code approval moves the FE task, UAT task, and parent feature to `AWAITING_MANUAL_ACCEPTANCE`, with `Next actor: Tester`. `Review decision: APPROVED` means code review only; it does not mean a browser case passed.
- Tester records actual results as PASS, FAIL, or BLOCKED and alone decides the transition to DONE. Agents may synchronize DONE only after the user's explicit acceptance; they never infer it from build success or elapsed time. Partial/blocked results are not full acceptance. Backend workstream DONE remains a technical `SELF_REVIEWED_BACKEND` checkpoint, not acceptance of the feature.
- On FAIL, Backend Architect triages the reported case and assigns correction to the appropriate BE/FE owner. After code review, Tester reruns affected cases and relevant regressions before deciding DONE.
- Test data/environment preparation must be explicit. If a prerequisite is unavailable, record BLOCKED and return preparation to Backend Architect; do not silently skip it or require Frontend Developer to create backend fixtures.

## 1. Startup: Role resolution and announcement

Before task work, read [agent-roles.json](ai-document/agent-roles.json). This is static role mapping: `codex` is Backend Architect; `antigravity` is Frontend Developer; the user is Tester / Product Owner.
1. Identify client from host runtime. Resolve assigned role from `assignments[client].role`. Never switch or infer roles.
2. Send a short Vietnamese acknowledgement once per session: confirm reading AGENTS.md, project, role, and client. Do not repeat every turn or pause for confirmation.
3. Role separation: Backend Architect plans all work, implements and self-reviews backend code, and reviews Frontend Developer code. Frontend Developer implements only explicitly assigned presentation work and never self-accepts. Tester / Product Owner performs mandatory manual feature tests and is the only role that accepts a major feature as functionally DONE.
4. Host-injected rules count as read; verify role mapping once per session. Re-read instructions when modified on disk.

## 2. Communication and language

- Reply to the user in Vietnamese.
- Write code, comments, documentation, and inter-agent handoff prompts in English.
- Be evidence-based: identify risks and assumptions; do not agree merely to please.
- State scope and approach before changing code; obtain explicit user approval.
- External actions (git commit/push/deploy/release) require explicit user authorization.

## 3. Workflow and manual handoffs

Follow the lean [Backend Architect / Frontend Developer / Tester workflow](ai-document/backend-frontend-workflow.md). Automated dispatch, receipts, and background signals are retired.
- Tasks progress through standard statuses: `DRAFT`, `READY`, `IN_PROGRESS`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `BLOCKED`, `AWAITING_MANUAL_ACCEPTANCE`, `DONE`.
- Backend Architect creates backend, frontend, and manual test tasks. Every major feature uses `<ID>-BE`, `<ID>-FE` when presentation exists, and mandatory `<ID>-UAT` owned by Tester.
- Backend Architect owns every backend change, including PHP, WordPress hooks, capabilities, nonces, validation, storage, SQL, concurrency, server-side tests, integration fixtures, and backend documentation. It implements directly, inspects its own diff, runs applicable gates, and records `SELF_REVIEWED_BACKEND`. This review is intentionally non-independent under the user's 2026-10-05 workflow decision.
- Frontend Developer owns only assigned JavaScript, Sass/CSS, visual components, responsive/RTL/accessibility presentation, generated frontend assets, and approved view-only templates. It must not edit backend behavior or tests and must hand completed work to Backend Architect for review.
- Backend Architect review is code/architecture/security review only. It must not claim that a feature works for the user or mark a major parent task DONE.
- Tester / Product Owner receives a Vietnamese `<ID>-UAT` task with prerequisites, test data, exact steps, expected results, negative/permission cases, visual/accessibility checks where applicable, and a required PASS/FAIL result format.
- A major parent task moves to `AWAITING_MANUAL_ACCEPTANCE` with `Next actor: Tester` only after required code reviews pass. It moves to `DONE` only after Tester explicitly records manual PASS. A manual FAIL moves the affected BE or FE workstream to `CHANGES_REQUESTED` after Backend Architect triage.
- `Next actor` is authoritative. Do not infer an owner from task status.
- Incoming validation: Verify task status, next actor, blueprint readiness (PASS), and checklist synchronization before starting work. On mismatch, stop and request metadata correction.
- Handoffs: Synchronize task file, checklist, and README. Provide exactly one copy-ready prompt under `### Chat handoff prompt`.
- Mandatory final-response prompt: Any turn that records or changes a task status, `Next actor`, review decision, workstream ownership, or manual-acceptance handoff is incomplete until the agent's final user-facing response includes exactly one copy-ready handoff prompt. Reproduce the task file's `### Chat handoff prompt` verbatim. Never refer the user to a prompt from an earlier message, omit it because it was previously supplied, or provide multiple competing prompts.
- Prompt consistency: The final-response prompt must match the synchronized task, checklist, and README status, recipient, intent, evidence reference, and exact next action. A missing or mismatched prompt is a failed handoff and must be corrected before ending the turn.
- Diff review: Backend Architect reviews its backend diff and all Frontend Developer diffs, security boundaries, generated assets, and build/static evidence. Browser/manual verification belongs to Tester; code approval never implies manual acceptance.
- Temporary test files: Create scratch scripts and fixtures in a uniquely named, task-owned temporary directory outside the plugin source tree when possible. Track their paths and remove owned temporary files/processes before handoff, including failure paths. Keep only approved reusable tests and required evidence. Inspect `git status --short` for accidental scratch files and report cleanup results. Never delete pre-existing or tracked files without a separate, reviewed cleanup scope.

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
  - Workflow & task template: [backend-frontend-workflow.md](ai-document/backend-frontend-workflow.md)
  - Build & release: [build-and-release.md](ai-document/build-and-release.md) (assets built via `npm run dev`/`npm run production`; release via `npm run release`)
  - Project checklist: [implementation-checklist.md](ai-document/implementation-checklist.md)
  - Security rules: [rules/security.md](rules/security.md)
  - Test and fixture placement: [rules/testing.md](rules/testing.md)
  - Specific domain rules in `rules/` when touching those areas.
