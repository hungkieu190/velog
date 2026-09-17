# Architect / Builder Workflow Bootstrap

Use this file with a separate product plan when starting or reorganizing a project. The goal is to make two AI agents work with a stable handoff loop:

- Architect plans, scopes, reviews, and accepts.
- Builder implements only the approved scope and reports evidence.
- The user coordinates handoff, approves product decisions, and performs manual acceptance when needed.

Documentation is not proof that the product works. Every claim must be tied to code, commands, screenshots, browser checks, sandbox evidence, or a clearly marked `NOT VERIFIED` item.

## Roles

### Architect

The coordinating AI acts as Architect unless the user explicitly assigns a different role. Architect must inspect the actual repository before planning or reviewing. Architect creates task files, links them from the project checklist, writes acceptance criteria, reviews Builder's implementation, records findings, and decides whether a task can be accepted.

Architect does not implement application code unless the user explicitly reassigns that role.

### Builder

Builder implements the exact approved task or review corrections. Builder must not mark its own work accepted, silently expand scope, mark project checklist items complete, or remove Architect's previous requirements and review history.

### User

The user approves scope, passes prompts between Architect and Builder, resolves product decisions, and performs manual acceptance where needed.

## Mandatory Chat Handoff Prompts

Every handoff must include a copy-ready prompt for the next agent. This is mandatory.

Architect must provide a prompt for Builder:

- after creating an approved task;
- after every review that requests changes;
- after every review or acceptance that gives Builder or the user a next action.

Builder must provide a prompt for Architect:

- after implementation;
- after fixes;
- after a blocked report;
- after any partial handoff.

The prompt must be self-contained enough for the receiving agent to resume without reading the previous chat first. It must name:

- exact files to read;
- current status;
- task ID and scope;
- finding IDs or acceptance criteria involved;
- checks already run and their results;
- checks not run, marked `NOT VERIFIED`;
- exact next action.

The same prompt must also be recorded in the shared task file under the current implementation report, fix report, review, or final acceptance section using this heading:

````markdown
### Chat handoff prompt

```text
Read <files>. Current status is <status>. <Exact next action...>
```
````

A handoff is incomplete until both pieces exist: the task-file update and the chat prompt. Do not mark `READY`, `READY_FOR_REVIEW`, `CHANGES_REQUESTED`, `AWAITING_MANUAL_ACCEPTANCE`, or `DONE` without the matching chat handoff prompt.

## Required Project Documents

Create only documents that are useful for the project. A normal project should have:

```text
AGENTS.md
ai-document/
  product-plan.md
  requirements.md
  decisions.md
  architecture.md
  implementation-checklist.md
  testing-strategy.md
  release-readiness.md
  tasks/
    <task-id>-<slug>.md
  walkthroughs/
  evidence/
```

The project checklist is the project-level source of truth. The task file is the detailed source of truth for assignment, reports, review history, evidence, and next handoff prompt.

## Progress Dashboard Command

For projects with ongoing Architect / Builder work, create a local progress dashboard command during bootstrap. The product owner should be able to run one command and immediately see project progress, current owner, task history, and open work without reading every Markdown file.

Required command:

```json
{
  "scripts": {
    "progress": "node scripts/progress-dashboard.mjs"
  }
}
```

If the project already has a `package.json`, preserve existing scripts and add only the `progress` script unless the user requests a broader build setup. If the project has no `package.json`, create a minimal private package file:

```json
{
  "name": "<project-slug>",
  "version": "0.0.0",
  "private": true,
  "scripts": {
    "progress": "node scripts/progress-dashboard.mjs"
  },
  "engines": {
    "node": ">=18"
  }
}
```

Expected behavior:

- `npm run progress` starts a local read-only web UI.
- The UI reads the project checklist and task Markdown files directly from the repository.
- It shows overall checklist progress, Phase progress, current owner, current task/status, open items, task history, finding IDs, and handoff prompt coverage.
- Architect, Builder, and User states should be visually distinct.
- The command must not mutate product code, database data, task status, checklist checkboxes, or release artifacts.
- The server should print the local URL and keep running until stopped with `Ctrl+C`.

Implementation guidance:

- Create `scripts/progress-dashboard.mjs`.
- Prefer a dependency-light Node script using built-in Node modules first. Do not add frontend dependencies unless the project already has a frontend toolchain or the user explicitly requests a richer app.
- Default host should be `127.0.0.1`; default port should be stable, for example `4177`. Support `--port=<number>` and/or `PROGRESS_PORT` to avoid conflicts.
- The script should expose at least:
  - `/` for the dashboard UI.
  - `/api/progress` for parsed JSON data.
- The script should print the URL when started, for example `Project progress dashboard is running at http://127.0.0.1:4177`.
- Keep the dashboard read-only. If it detects stale or contradictory documentation, show it as a status signal instead of rewriting files.
- Use the same status semantics as the task workflow: `READY_FOR_REVIEW` means Architect is next; `CHANGES_REQUESTED`, `READY`, and `IN_PROGRESS` mean Builder is next; `AWAITING_MANUAL_ACCEPTANCE` means User is next; `DONE` is accepted history.
- Include a friendly visual representation of the current owner, but keep the UI suitable for project operations.
- The dashboard is a visibility tool, not acceptance evidence. It must never replace task-file review, verification commands, or manual/sandbox acceptance.

Minimum data sources:

- `ai-document/implementation-checklist.md`
- `ai-document/tasks/*.md`
- Optional: `AGENTS.md`, release-readiness docs, walkthrough docs, and evidence manifests when useful.

Minimum parser rules:

- Count checklist items using `- [x]` and `- [ ]`.
- Parse each task's `## Current handoff` section for `Status`, `Latest round`, and `Next actor`.
- Derive current owner from status:
  - `READY_FOR_REVIEW`, `DRAFT`, and `DONE` usually point to Architect.
  - `READY`, `IN_PROGRESS`, and `CHANGES_REQUESTED` usually point to Builder.
  - `AWAITING_MANUAL_ACCEPTANCE` and unresolved product decisions point to User.
  - `BLOCKED` points to whoever can unblock the recorded dependency.
- Surface mismatches instead of hiding them, for example a checklist saying `CHANGES_REQUESTED` while a task says `DONE`.
- Count `Chat handoff prompt` headings so missing handoff prompts are visible.

Minimum UI sections:

- Current focus: task/checklist item, status, owner, and exact next action.
- Progress snapshot: checklist total/done/open and active phase progress.
- Architect / Builder / User owner display with distinct visual identity.
- Task history: task title, status, latest round, findings, and prompt coverage.
- Open work list: highest-priority unchecked checklist items.
- Last updated timestamp and source-file note.

Acceptance checks before handing off:

- Run `npm run progress -- --port=<free-port>` and confirm it prints a local URL.
- Fetch `/api/progress` and confirm it returns current focus, summary, and tasks.
- Open the UI or fetch `/` and confirm HTML renders.
- Verify `git diff --check` passes.
- Record the command and URL in the task report or project bootstrap notes.

## Task Statuses

Use these statuses consistently:

| Status | Meaning | Set by |
|---|---|---|
| `DRAFT` | Architect is preparing the task or awaiting user approval. | Architect |
| `READY` | The task is approved and ready for Builder. | Architect |
| `IN_PROGRESS` | Builder is implementing the approved scope. | Builder |
| `BLOCKED` | A concrete dependency prevents progress. | Either agent |
| `READY_FOR_REVIEW` | Builder recorded implementation and evidence. | Builder |
| `CHANGES_REQUESTED` | Architect found unmet criteria or defects. | Architect |
| `AWAITING_MANUAL_ACCEPTANCE` | Code review passed, but manual/user/sandbox evidence is still needed. | Architect |
| `DONE` | Architect accepted all task criteria. | Architect only |

Normal loop:

```text
DRAFT -> READY -> IN_PROGRESS -> READY_FOR_REVIEW -> CHANGES_REQUESTED -> IN_PROGRESS
```

Repeat until Architect can set `DONE`, or use `BLOCKED` / `AWAITING_MANUAL_ACCEPTANCE` when accurate.

## Required Task Template

````markdown
# <Task ID>: <Title>

## Current handoff
- Status:
- Plan revision:
- Architect / Builder identity or session reference:
- Related checklist items:
- Baseline branch and commit; pre-existing relevant changes:
- User approval reference and approved scope:
- Latest implementation/review round:
- Next actor and exact next action:

## Problem and intended behavior
- Current behavior, evidence, expected behavior, and business rules.
- Open assumptions or required product decisions.

## Scope and references
- Allowed files/classes/methods/hooks.
- Required reading and relevant APIs.
- Explicit exclusions.

## Implementation steps
1. S1:
2. S2:

## Acceptance criteria
- AC1:
- AC2:

## Verification instructions
- Map each AC to commands, setup, expected output, and cleanup.
- Separate automated, local integration, sandbox, and manual checks.
- Mark unavailable checks `NOT VERIFIED`.

## Implementation report — Round 1 (Builder)
- Changes by step/criterion and file/method.
- Checks actually run.
- Checks not run, failed checks, blockers, deviations, and risks.
- Test data created and cleanup status.

### Chat handoff prompt

```text
Read <task file and relevant docs>. Current status is READY_FOR_REVIEW. Review <summary> and verify <checks/findings>.
```

## Review — Round 1 (Architect)
- Diff and evidence reviewed.
- AC verdicts: PASS / FAIL / NOT VERIFIED.
- Findings with stable IDs, severity, location, evidence, impact, correction, and verification.
- Decision and next action.

### Chat handoff prompt

```text
Read <task file>. Current status is CHANGES_REQUESTED. Fix <finding IDs> only, rerun <checks>, and append Fix report — Round 2.
```

## Fix report — Round 2 (Builder)
- Each finding ID: correction, changed files, and verification evidence.
- Remaining blockers or unaddressed findings.

### Chat handoff prompt

```text
Read <task file>. Current status is READY_FOR_REVIEW. Review the Round 2 fixes for <finding IDs> and rerun <checks>.
```

## Final acceptance (Architect only)
- Accepted diff/commit.
- Criterion results and resolved findings.
- Remaining manual/sandbox/open follow-ups.
- Checklist updates.

### Chat handoff prompt

```text
Read <next task or checklist>. Current status is <next status>. Continue with <exact next action>.
```
````

## Execution Rules

Architect must inspect implementation facts before prescribing changes. Plans must be small enough to review and detailed enough that Builder does not need to invent business rules.

Builder must preserve unrelated changes, document deviations before expanding work, and never fabricate successful tests, gateway evidence, transaction evidence, or manual acceptance.

Every requested correction must have a stable finding ID and a concrete verification condition. Builder answers each ID in the next report. Architect verifies the fix and closes or reopens it in the next review.

Required tests that cannot be run remain `NOT VERIFIED`. Do not narrow acceptance criteria after the fact to claim completion.

Only Architect sets `DONE` and marks project checklist items complete.

Commit, push, deployment, production data repair, or external messages require explicit user authorization unless the user already authorized that specific action.

## First Response Pattern

When an AI reads this file and the product plan for a new project, it should:

1. Inspect the repository, existing docs, git state, and available test commands.
2. Create or update the required planning documents.
3. Create the first bounded task in `ai-document/tasks/`.
4. Link that task from `ai-document/implementation-checklist.md`.
5. Ask for approval only after the task is concrete and reviewable.
6. Provide the mandatory Builder handoff prompt once the task is approved.
