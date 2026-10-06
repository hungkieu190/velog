# Backend Architect and Frontend Developer workflow

## Fixed roles

- **Backend Architect (Codex):** plans all tasks and owns architecture, PHP/backend logic, WordPress hooks, authorization, validation, persistence, SQL, concurrency, server-side tests, integration fixtures, backend documentation, and release decisions. It implements and self-reviews backend work.
- **Frontend Developer (Antigravity):** owns only explicitly assigned JavaScript, Sass/CSS, visual components, responsive/RTL/accessibility presentation, generated frontend assets, and approved view-only templates. It never edits backend behavior and never self-accepts.
- **User:** owns product scope, business decisions, destructive or external authorization, and required manual product acceptance.

Store this static mapping in `ai-document/agent-roles.json`. Do not switch roles by inference.

## Task ownership and review

Split mixed features into `<ID>-BE` and `<ID>-FE` tasks. Every task declares `Workstream`, allowed files, prohibited boundaries, acceptance criteria, evidence path, owner, and authoritative `Next actor`.

Backend Architect implements backend work, inspects its own diff, runs applicable security, lint, unit, integration, runtime, build, and cleanup gates, and records `SELF_REVIEWED_BACKEND`. This is an intentional non-independent review.

Frontend Developer sends completed work to Backend Architect as `READY_FOR_REVIEW`. Backend Architect reviews source and generated diffs, browser/runtime evidence, accessibility, responsive and RTL behavior, and boundary compliance. Only Backend Architect may approve frontend work.

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
