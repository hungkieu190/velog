# Backend Architect, Frontend Developer, and Tester workflow

## Owner decision — 2026-10-08: Tester owns browser verification

This decision supersedes conflicting frontend verification requirements in earlier task descriptions, review requests, and setup guidance. Historical evidence remains unchanged.

- Frontend Developer implements assigned presentation source, builds generated assets, records build/diff results, and hands off code. It is not required to run browser/manual verification, capture screenshots, or provide visual evidence.
- Backend Architect reviews source/generated diffs and architecture/security boundaries, verifies relevant build/static checks, and creates a concrete Vietnamese `<ID>-UAT` task. It must specify environment, accounts, data, numbered actions, expected results, negative cases, and a per-case result table. Missing browser screenshots are not grounds to reject a frontend code handoff.
- Tester / Product Owner owns all manual UI, responsive, RTL, keyboard, screen-reader, error-state, pagination, and asset-loading verification. Screenshots/videos are optional supporting material, not a mandatory builder deliverable.
- Code approval moves the FE task, UAT task, and parent feature to `AWAITING_MANUAL_ACCEPTANCE`, with `Next actor: Tester`. `Review decision: APPROVED` means code review only; it does not mean a browser case passed.
- Tester records actual results as PASS, FAIL, or BLOCKED and alone decides the transition to DONE. Agents may synchronize DONE only after the user's explicit acceptance; they never infer it from build success or elapsed time. Partial/blocked results are not full acceptance. Backend workstream DONE remains a technical `SELF_REVIEWED_BACKEND` checkpoint, not acceptance of the feature.
- On FAIL, Backend Architect triages the reported case and assigns correction to the appropriate BE/FE owner. After code review, Tester reruns affected cases and relevant regressions before deciding DONE.
- Test data/environment preparation must be explicit. If a prerequisite is unavailable, record BLOCKED and return preparation to Backend Architect; do not silently skip it or require Frontend Developer to create backend fixtures.

## 1. Fixed roles

- **Backend Architect (Codex):** owns architecture, task decomposition, all PHP/backend application logic, WordPress hooks, capabilities, nonces, validation, persistence, SQL, concurrency, server-side tests, integration fixtures, technical documentation, and release decisions. Codex implements and self-reviews backend work.
- **Frontend Developer (Antigravity):** owns only explicitly assigned presentation work: frontend JavaScript, Sass/CSS, visual components, responsive behavior, RTL presentation, accessibility presentation, and approved view-only templates. Antigravity cannot edit persistence, authorization, validation, SQL, service classes, lifecycle hooks, or backend test logic.
- **Tester / Product Owner (User):** owns product scope, performs mandatory manual tests for every major feature, records observed results, and provides final functional acceptance.

Roles are resolved only from `ai-document/agent-roles.json`. Role switching and mixed ownership inside one task are prohibited.

## 2. Review authority

Backend work is implemented and reviewed by Backend Architect. Closure must be labeled `SELF_REVIEWED_BACKEND` and must include diff inspection plus all applicable security, lint, unit, integration, runtime, build, and cleanup evidence. This review is intentionally non-independent under the user's 2026-10-05 workflow decision.

Frontend Developer never self-accepts. Frontend work moves to `READY_FOR_REVIEW` and Backend Architect reviews source/generated diffs, accessibility and responsive implementation, build results, and boundary compliance. Backend Architect records `APPROVED` or `CHANGES_REQUESTED`.

If frontend work requires backend behavior, Frontend Developer records the dependency and stops that part. Backend Architect creates or updates a backend task; Antigravity must not implement around a missing backend contract.

## 3. Task model

Every major feature is split into separately owned workstreams:

- `<ID>-BE`: backend contract, application logic, security, storage, APIs/hooks, backend tests, and fixtures. Owner: Backend Architect.
- `<ID>-FE`: presentation, assets, interaction, responsive/RTL/accessibility behavior, and build results. Owner: Frontend Developer.
- `<ID>-UAT`: Vietnamese manual test instructions, observed results, and Product Owner acceptance. Owner: Tester.

The backend task defines stable data/view contracts before dependent frontend implementation begins. Tasks may run in parallel only when the frontend contract is already decision-complete.

Every task must declare:

- `Workstream: Backend` or `Workstream: Frontend`.
- Exact allowed files and prohibited boundaries.
- `Next actor: Backend Architect`, `Frontend Developer`, or `Tester`.
- Acceptance criteria and verification commands.
- One evidence directory and one current handoff prompt.

## 4. Lifecycle

Statuses remain:

- `DRAFT`: Backend Architect is defining scope and contract.
- `READY`: task is decision-complete and assigned to its declared owner.
- `IN_PROGRESS`: declared owner is implementing or verifying.
- `READY_FOR_REVIEW`: Frontend Developer completed frontend work for Backend Architect review. Backend tasks may use this only for user/manual review; they do not invent an independent reviewer.
- `CHANGES_REQUESTED`: Backend Architect returned frontend findings, or backend self-review found unresolved defects.
- `BLOCKED`: concrete blocker and owner are recorded.
- `AWAITING_MANUAL_ACCEPTANCE`: code review has passed and Tester must execute the linked Vietnamese UAT task.
- `DONE`: backend is `SELF_REVIEWED_BACKEND`, frontend is approved when applicable, and the linked UAT task has an explicit Tester `PASS`.

`Next actor` is authoritative. Status alone must never infer Backend versus Frontend ownership.

## 5. Backend execution contract

Backend Architect:

1. Inspects repository state and writes a bounded backend task.
2. Implements backend code directly after readiness is PASS and user scope is authorized.
3. Reviews its own diff against WordPress security, data integrity, performance, failure behavior, and compatibility requirements.
4. Runs required positive and negative tests, lint/static analysis, real WordPress/runtime probes, build gates where affected, and cleanup checks.
5. Records `SELF_REVIEWED_BACKEND`, including limitations and any `NOT VERIFIED` cases. Failed gates keep the task open.
6. Synchronizes task, checklist, README, and the next task prompt.

Backend Architect does not delegate backend correction attempts to Frontend Developer.

After all required code reviews pass, Backend Architect sets the parent task to `AWAITING_MANUAL_ACCEPTANCE`, assigns `Next actor: Tester`, and provides the exact `<ID>-UAT` instructions. Backend Architect must not perform or pre-approve the manual test on Tester's behalf.

## 6. Frontend execution and review contract

Frontend Developer:

1. Validates task status, workstream, allowed file list, stable backend contract, and evidence path.
2. Changes only approved presentation files.
3. Builds generated assets from source and records build/diff results. Browser checks and screenshots belong to Tester, not Frontend Developer.
4. Runs assigned non-browser build/static checks and cleanup.
5. Sets `READY_FOR_REVIEW`, synchronizes documentation, and hands off to Backend Architect. It never marks its own task DONE.

Backend Architect reviews frontend work by inspecting source and generated diffs, confirming no backend/security boundary was crossed, rerunning relevant build/static checks, and recording an approval or stable findings. This approval covers code quality and technical evidence only; it is not functional acceptance.

## 7. Tester execution and functional acceptance

Every major feature has one `<ID>-UAT` task written in Vietnamese. The task must contain:

- test environment and prerequisites;
- required WordPress account/role;
- concrete test data;
- numbered user actions;
- expected result for every action;
- invalid-input, permission, stale/concurrent, and recovery cases relevant to the feature;
- responsive, RTL, keyboard, focus, long-content, and asset-scope checks when the feature has UI;
- a result table for actual observations;
- the exact response format: `PASS`, or `FAIL` with failed step, actual result, expected result, and screenshot when useful.

Tester executes the feature through the real product UI. Automated checks support code review; Tester owns browser observations and optional screenshots. On `PASS`, Tester may approve the UAT and parent feature as `DONE`. On `FAIL`, Backend Architect records the finding, assigns it to the correct BE or FE workstream, and keeps the parent open.

## 8. Handoff and prompt rule

Synchronize the task file, `ai-document/implementation-checklist.md`, and `ai-document/README.md` before every handoff. Keep exactly one prompt under `### Chat handoff prompt`:

```text
Status: <STATUS>
Recipient: <Backend Architect|Frontend Developer|Tester>
Intent: <work|review|accept>

<Scope, evidence, decision, and exact next action>
```

Any turn that changes task status, next actor, review decision, workstream ownership, or manual-acceptance state is incomplete until the final user-facing response reproduces that prompt verbatim exactly once.

## 9. Evidence and cleanup

- Build evidence contains actual commands, versions, exits, assertions, and limitations. Manual browser observations are recorded by Tester in UAT.
- Temporary scripts, databases, processes, browser tabs, and task-owned directories must be removed on success and failure.
- Reusable tests belong in the approved change map. Scratch files stay outside the plugin tree where practical.
- Inspect `git status --short` and `git diff --check` before handoff. Preserve unrelated work.

## 10. Lean task template

````markdown
# <TASK-ID>: <Title>

## Current handoff
- Workstream: <Backend|Frontend>
- Status: <STATUS>
- Plan revision: <N>
- Implementation round: <N>
- Blueprint readiness: <PASS|INCOMPLETE>
- Owner: <Backend Architect|Frontend Developer|Tester>
- Contributors and review mode: <SELF_REVIEWED_BACKEND|Frontend reviewed by Backend Architect|pending>
- Related checklist items:
- Latest report:
- Evidence:
- Temporary resource cleanup:
- Next actor: <Backend Architect|Frontend Developer|Tester>
- Next actor and exact next action:

## Problem and intended behavior

## Approved scope and boundaries

## Verification matrix

## Acceptance criteria

## Open findings

### Chat handoff prompt

```text
Status: <STATUS>
Recipient: <Backend Architect|Frontend Developer|Tester>
Intent: <work|review|accept>

<Concise handoff summary>
```
````
