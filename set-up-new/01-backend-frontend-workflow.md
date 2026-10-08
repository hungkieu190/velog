# Backend Architect and Frontend Developer workflow

## Owner decision — 2026-10-08: Tester owns browser verification

This decision supersedes conflicting frontend verification requirements in earlier task descriptions, review requests, and setup guidance. Historical evidence remains unchanged.

- Frontend Developer implements assigned presentation source, builds generated assets, records build/diff results, and hands off code. It is not required to run browser/manual verification, capture screenshots, or provide visual evidence.
- Backend Architect reviews source/generated diffs and architecture/security boundaries, verifies relevant build/static checks, and creates a concrete Vietnamese `<ID>-UAT` task. It must specify environment, accounts, data, numbered actions, expected results, negative cases, and a per-case result table. Missing browser screenshots are not grounds to reject a frontend code handoff.
- Tester / Product Owner owns all manual UI, responsive, RTL, keyboard, screen-reader, error-state, pagination, and asset-loading verification. Screenshots/videos are optional supporting material, not a mandatory builder deliverable.
- Code approval moves the FE task, UAT task, and parent feature to `AWAITING_MANUAL_ACCEPTANCE`, with `Next actor: Tester`. `Review decision: APPROVED` means code review only; it does not mean a browser case passed.
- Tester records actual results as PASS, FAIL, or BLOCKED and alone decides the transition to DONE. Agents may synchronize DONE only after the user's explicit acceptance; they never infer it from build success or elapsed time. Partial/blocked results are not full acceptance. Backend workstream DONE remains a technical `SELF_REVIEWED_BACKEND` checkpoint, not acceptance of the feature.
- On FAIL, Backend Architect triages the reported case and assigns correction to the appropriate BE/FE owner. After code review, Tester reruns affected cases and relevant regressions before deciding DONE.
- Test data/environment preparation must be explicit. If a prerequisite is unavailable, record BLOCKED and return preparation to Backend Architect; do not silently skip it or require Frontend Developer to create backend fixtures.

## Fixed roles

- **Backend Architect (Codex):** plans all tasks and owns architecture, PHP/backend logic, WordPress hooks, authorization, validation, persistence, SQL, concurrency, server-side tests, integration fixtures, backend documentation, and release decisions. It implements and self-reviews backend work.
- **Frontend Developer (Antigravity):** owns only explicitly assigned JavaScript, Sass/CSS, visual components, responsive/RTL/accessibility presentation, generated frontend assets, and approved view-only templates. It never edits backend behavior and never self-accepts.
- **User:** owns product scope, business decisions, destructive or external authorization, and required manual product acceptance.

Store this static mapping in `ai-document/agent-roles.json`. Do not switch roles by inference.

## Task ownership and review

Split mixed features into `<ID>-BE`, `<ID>-FE`, and mandatory Vietnamese `<ID>-UAT` tasks authored by Backend Architect and executed by Tester. Every task declares `Workstream`, allowed files, prohibited boundaries, acceptance criteria, evidence path, owner, and authoritative `Next actor`.

Backend Architect implements backend work, inspects its own diff, runs applicable security, lint, unit, integration, runtime, build, and cleanup gates, and records `SELF_REVIEWED_BACKEND`. This is an intentional non-independent review.

Frontend Developer sends completed work to Backend Architect as `READY_FOR_REVIEW`. Backend Architect reviews source/generated diffs, build/static results, accessibility/responsive/RTL implementation, and boundary compliance. Only Backend Architect may approve frontend work.

If frontend work requires a missing backend contract, record the dependency and return it to Backend Architect. Frontend Developer must not implement a backend workaround.

## Statuses

Use `DRAFT`, `READY`, `IN_PROGRESS`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `BLOCKED`, `AWAITING_MANUAL_ACCEPTANCE`, and `DONE`. Status does not imply the owner; `Next actor` is authoritative.

## Handoffs

Synchronize the task, checklist, and README. Keep exactly one current English prompt under `### Chat handoff prompt`:

```text
Status: <STATUS>
Recipient: <Backend Architect|Frontend Developer|User>
Intent: <work|review|accept>

<Scope, evidence, decision, and exact next action>
```

Any final response that changes task status, next actor, review decision, workstream ownership, or manual acceptance must reproduce that prompt verbatim exactly once.

## Cleanup and evidence

Evidence records actual commands, versions, exits, assertions, browser observations, and limitations. Remove task-owned temporary files, databases, processes, and tabs on success and failure. Inspect `git status --short` and `git diff --check`; preserve unrelated work.

## Test and fixture placement

- Put reusable PHP unit tests in `tests/Unit/`, PHP integration tests in `tests/Integration/`, PHP fixtures in `tests/fixtures/`, and Node workflow tests in `tests/workflow/`.
- Do not create tracked test code in runtime source, the project root, scripts, assets, documentation, or feature directories. Runtime code must not contain test-only switches or fixtures.
- Put ad-hoc probes, generated scripts, databases, logs, and downloads in a unique task-owned directory outside the project source whenever practical; remove owned temporary resources on every exit path.
- Do not delete reusable tracked tests merely to reduce file count. Retiring a test requires a separate approved cleanup scope, reference audit, and replacement or retirement rationale.
