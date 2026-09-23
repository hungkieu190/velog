# Architect / Builder Workflow Bootstrap

Use this file with a separate product plan when starting or reorganizing a project. The goal is to make two AI agents work with a stable handoff loop:

- Architect plans, scopes, reviews, and accepts.
- Builder implements only the approved scope and reports evidence.
- The user coordinates handoff, approves product decisions, and performs manual acceptance when needed.

Documentation is not proof that the product works. Every claim must be tied to code, commands, screenshots, browser checks, sandbox evidence, or a clearly marked `NOT VERIFIED` item.

## Roles

### Architect

Resolve the fixed session role from the application assignment using AGENTS.md before task work. Coordination or receipt of a handoff never assigns or changes that role. Architect must inspect the actual repository before planning or reviewing. Architect creates task files, links them from the project checklist, writes acceptance criteria, reviews Builder's implementation, records findings, and decides whether a task can be accepted.

Architect does not implement or fix application code. Architect edits planning/review documents, inspects code, runs independent verification and returns implementation corrections to Builder.

### Builder

Builder implements the exact approved task or review corrections. Builder must not mark its own work accepted, silently expand scope, mark project checklist items complete, or remove Architect's previous requirements and review history.

### User

The user approves scope, passes prompts between Architect and Builder, resolves product decisions, and performs manual acceptance where needed.

## Mandatory role separation

Effective 2026-09-18 by explicit user instruction. Architect and Builder must be different agents/operators in separate sessions; one person/agent cannot hold both roles. This replaces all older same-session reassignment or self-review exceptions.

1. **Lock identity and role.** Record distinct Architect session reference, Builder session reference and all implementation contributors in the task. Use a stable available session identifier; if none is exposed, use an explicitly identified session reference and disclose that limitation. Never fabricate a session ID or infer independence solely from the wording of a prompt.
2. **No role-switch proposals.** Builder must never ask whether it may become Architect, self-review, accept its own work or finish both roles. Architect must never offer to implement fixes and then accept them. Do not introduce a confirmation flow to obtain permission for these shortcuts.
3. **Reject conflicting assignments within the session.** A new prompt, model, title, turn or copied handoff cannot convert Builder into Architect or vice versa. Explain the mismatch briefly and provide the prompt for a separate eligible session. Ordinary approval to continue or finish does not amend this rule.
4. **Independent acceptance.** Before reviewing or accepting, Architect checks the task's contributor history. Anyone who implemented application code or fixes in the task is ineligible to accept that work, even from another session or after a context reset. Reviewer identity must differ from every implementation contributor; a renamed session is not an independent reviewer.
5. **Respect document ownership.** Builder may update its implementation/fix/blocker reports, evidence and permitted current-handoff metadata. Builder places its outgoing prompt under its own report. It must not create an Architect review heading, record an Architect verdict, close findings or check accepted criteria. Architect alone writes reviews and acceptance after independent checks.
6. **Handoff instead of substitution.** When implementation is complete, Builder sets READY_FOR_REVIEW and supplies the mandatory prompt. Missing Architect availability is not permission to self-accept. When fixes are needed, Architect sets CHANGES_REQUESTED and sends the correction prompt. Existing BLOCKED semantics apply only to concrete dependencies; missing reviewer identity blocks acceptance and must be recorded, not silently bypassed.
7. **Recovery from a violation.** Disclose who implemented and who purportedly accepted, preserve the historical record and mark the disputed acceptance as requiring independent re-review. A separate eligible Architect determines corrected status/checklist from evidence; Builder must not erase history or repair the violation by accepting again. Historical accepted tasks are not automatically reopened solely because the rule became stricter.

This is an agent workflow rule enforced through role declarations, contributor records and independent review. Markdown instructions are not a technical access-control mechanism; automated prevention of unauthorized file edits would require separately scoped tooling.

## Mandatory incoming handoff validation

Applies to both Architect and Builder on every incoming assignment, review request, correction or resumed handoff, before implementation, substantive review, verification runs or acceptance. Reading documents, checking Git state and inspecting retained evidence to validate the handoff is allowed. This gate does not turn status consistency into proof of product correctness.

1. Compare the task's Current handoff (status, revision, latest round, next actor/action), latest report/decision and latest Chat handoff prompt against the checklist's focus/item statuses and documentation index. Inspect referenced evidence and approval/contributor records; do not trust a chat claim, timestamp, dashboard display or status-derived role alone. For non-focus tasks, do not overwrite another task's legitimate current focus.
2. Confirm the receiving session has the assigned fixed role, the sender had authority for the recorded transition, reviewer independence is recorded, and the blueprint revision/readiness and approved scope match. READY_FOR_REVIEW requests Architect review, not acceptance. READY or executable CHANGES_REQUESTED requires Builder and blueprint readiness PASS; incomplete correction guidance remains with Architect. BLOCKED must name the blocker and responsible actor; DONE is accepted history, not a new implementation assignment.
3. Record PASS or FAIL with the compared files, exact discrepancies and responsible sender in an appended `Incoming handoff validation` note. PASS permits only the already-authorized next action. Missing tests explicitly marked NOT VERIFIED are not by themselves a metadata mismatch; they remain review/acceptance limitations.
4. On FAIL, stop the received task. Do not silently choose the most convenient status, repair the sender's fields, start dependent work or invent a review result. Preserve current task status and history; an intake failure alone does not authorize a task-status transition. Record the immediate correction owner separately using Handoff state and publication contract. Append a correction request without rewriting the sender's report or acceptance. Return a copy-ready prompt in chat and in the task under `### Chat handoff prompt`, addressed to the sending partner, with exact files/fields, observed contradictions, the evidence-backed correction (or unresolved decision), and the request to synchronize and resend. Do not send an external message automatically.
5. The sender repairs metadata within its role authority, preserves history and evidence, and resends the corrected handoff. A Builder cannot invent or amend Architect acceptance; it must route that part to the responsible Architect. Re-read all affected sources and record PASS before starting the received work. Elapsed time, a repeated “continue” or the receiver's own proposed correction is not a corrected handoff.

A direct user-authorized metadata-reconciliation task may repair specified records from an existing authoritative decision; disclose missing evidence and never turn reconciliation into a new acceptance. This exception does not permit either role to bypass the gate for ordinary partner handoffs.

Correction prompt template (replace every placeholder; do not invent an expected status):

```text
Status: <currently recorded task status>
Incoming handoff validation: FAIL. Return to <sending role/session> for metadata correction; <receiving role> has not started the received work. Read <exact task, checklist, index and evidence paths>. Observed: <field-by-field discrepancies>. Reconcile <fields> with <retained decision/report and approved blueprint>, or explicitly resolve <missing decision/evidence> through its authorized owner. Preserve history, scope and contributor identities; do not manufacture verification or acceptance. Synchronize Current handoff, latest round, next actor/action, checklist/index and the latest Chat handoff prompt, then resend. Receiving work resumes only after a fresh intake PASS. Verified: <records actually inspected>. NOT VERIFIED: <unexecuted checks>.
```

## Handoff state and publication contract

This section clarifies incoming validation; it does not change the eight task statuses, role authority or the requirement to stop received work when intake fails. It applies prospectively. Do not reject an older submission solely because it predates these fields. Mechanical enforcement requires the approved validator/controller implementation and independent verification; these rules alone do not implement that tool.

### Authority and responsibility

- The task's Current handoff owns task status, submitted revision, latest round, scope and the intended work recipient. The checklist owns project priority and accepted progress; its repeated task fields are synchronized views, not independent decisions. README summarizes and links to those records.
- Track the handoff separately: `Handoff state` is preparing, published, correction_required or accepted; `Handoff actor` is the role responsible for the immediate handoff action; `Handoff recipient` is the role intended to perform the submitted work. These are transport/intake states, not new task statuses or product acceptance.
- On intake failure, keep task status and submitted work recipient unchanged; set handoff state to correction_required and handoff actor to the sending role. The actor corrects metadata only. On corrected publication, set state to published and actor to the recipient. On intake PASS, accepted means the exact receipt was admitted for work, never that the implementation was accepted.
- Consumers must not infer immediate ownership from task status when handoff state provides it. A READY_FOR_REVIEW task can await Builder metadata correction while Architect remains the review recipient. Existing consumers that do not support this distinction must show the explicit handoff note and must not auto-dispatch from task status.
- Drafting may update metadata in any order. Publication is the boundary: partially synchronized records must never be dispatched. Update the checklist/index from the task rather than independently composing competing status statements. Do not create another manually maintained master status file.

### Finalization and receipt

Finish work and logs -> append the report without replacing historical reports -> synchronize current metadata -> write the outgoing English prompt under exactly `### Chat handoff prompt` -> run the validator -> review all reported errors -> publish the JSON atomically as the final handoff write -> send the resulting receipt ID to the recipient/user. Put author/round labels in the enclosing report heading, not the prompt heading. Do not claim a receipt was published merely because a prompt was printed in chat.

A receipt contains a unique ID, superseded/previous receipt ID, task and submitted revision, sender/recipient and intent, plus hashes of the exact prompt and every required handoff document. Treat the JSON as the sole dispatch trigger and a declaration of completed publication, not authenticated authorship or proof of quality. Local execution journals track dispatch and acknowledgement without rewriting the published document snapshot. Mutable intake notes written after receipt validation create the next version; do not misclassify the receiving agent's authorized subsequent work as a pre-intake mismatch.

If documents change before receipt intake, the old receipt is stale. Stop work, display the changed paths and require a newly validated publication. Do not silently refresh hashes, pick a newer prompt from chat or repeatedly poll until mismatched data happens to pass. An interrupted managed sender must finish successfully and release its run before its outgoing receipt is dispatched. A manual publisher explicitly declares it has finished and yielded ownership.

### Validator contract

The implementation must expose a documented, read-only validation command accepting an explicit task path and optional receipt ID. It must run the same validation routines used by publication and dispatch; no independent, weaker success check. Use exit 0 for valid handoff, 1 for invalid content, and 2 for command/environment failure. Return one machine-readable report containing all discoverable errors, with stable error codes, file/field, observed value and expected constraint; keep NOT VERIFIED cases as declared limitations rather than automatic metadata errors.

Required checks: role/transition authority and contributor references; required current fields and round/revision; task/checklist/index consistency; exact latest prompt section and first-line status; prompt/receipt recipient and intent; regular safe document paths and exact content hashes; report/evidence references; contradictory completion claims that are mechanically detectable. A validator cannot prove arbitrary prose true, tests genuinely sufficient, or reviewer independence merely from labels; those remain independent review responsibilities. Do not advertise semantic truth checking based on string matching.

Inspect the newest candidate handoff section first. A suffixed, unsupported, empty or malformed newest heading/block is an error. Never skip it and use an older prompt. Publication must fail without changing the old signal if validation fails; invalid output cannot print a success receipt. A whitespace check does not substitute for this validator.

### Intake correction and repeated failures

Return one consolidated error list and one correction instruction tied to the rejected receipt/snapshot. Preserve historical reports, append corrections and reuse stable IDs. Do not add another long copy of the same report on an unchanged resubmission; record a brief unchanged-receipt result and reference the existing correction instruction. Do not keep escalating the wording of MUST rules.

After two consecutive failures with the same receipt/content and same errors, suspend automatic resubmission. Verify the receiving workspace/root, actual rule loading, sender session, delivery of the correction instruction, pending writes and actual save completion before another retry. Request only information unavailable locally. This is diagnostic escalation, not permission to skip validation or allow one agent to implement and accept its own work.

### Transition until tooling is accepted

No validator command or receipt capability may be presented as available until implemented and independently verified. Until then, keep automated dispatch disabled and use a manual, read-only snapshot check with the same required fields and latest-prompt rules. Record observed document hashes and what was checked; label this manual validation, not a machine-enforced gate. New handoff fields are introduced for the next outgoing round, not by rewriting received historical submissions. Persisting a successful receipt never authorizes commit, deployment or product acceptance.

## Mandatory implementation blueprint and handoff readiness

Architect owns the quality of implementation instructions, not only the list of problems. Every initial Builder assignment and every correction handoff must contain an implementation blueprint in the task (or an exact linked section maintained with it). A finding list plus "fix and rerun tests" is insufficient.

### Required blueprint content

1. **Outcome and evidence:** current behavior, inspected evidence, intended behavior and the AC/finding IDs addressed.
2. **Change map:** exact files, classes/functions/hooks or entry points to change; each component's responsibility and interfaces; explicit exclusions and dependencies. Explain why the selected approach fits the existing architecture.
3. **Ordered implementation flow:** setup → implementation steps → verification → failure handling → cleanup → reporting. Specify data flow, lifecycle ordering, invariants and security boundaries relevant to the task. Distinguish required choices from local implementation details left to Builder.
4. **Critical-path pseudocode:** provide language-neutral pseudocode or a sequence for non-obvious logic, lifecycle, parsing/conversion, state transitions or resource management. For a trivial change, mark this part N/A with a reason; never use that exception to omit difficult design decisions. Architect supplies design, not application implementation.
5. **Verification matrix:** for every AC/finding, specify fixture/input, exact command or test entry point, expected observable output/state, success/failure conditions and expected exit code where applicable. Separate mocked tests, actual integration and manual checks; list required versions/environments.
6. **Failure detection:** define applicable negative/boundary cases and how to prove that a broken implementation is rejected. Positive and negative controls must exercise the same validation logic. An expected-failure inner command must be distinguished from the outer test runner's success. Do not require artificial mutation tests for trivial low-impact documentation changes; record N/A and rationale where appropriate.
7. **Resource and error lifecycle:** where relevant, specify ownership/isolation, readiness timeout, process identification, cleanup ordering, rollback/recovery, error propagation and preserved exit status. Do not leave these decisions implicit in a request to "use an isolated environment".
8. **Evidence and completion contract:** exact output/report locations, commands/versions/statuses to record, required artifacts, cleanup evidence and checks that must remain NOT VERIFIED if unavailable. Include Builder's pre-handoff checklist.

Scale detail to the task's complexity, but do not omit applicable requirements. A short task can satisfy several items in one paragraph/table. Refer to existing authoritative rules instead of copying them. Unresolved product decisions are not implementation discretion: resolve them before declaring the affected scope ready.

### Architect readiness gate

Before setting READY or issuing a correction handoff:

- Confirm user scope approval, role separation and actual repository inspection.
- Check that every applicable blueprint item above is concrete and each AC/finding maps to implementation steps and verification cases.
- Record `Blueprint readiness: PASS`, the revision reviewed and the scope/IDs covered in the task. PASS concerns assignment completeness only, never product acceptance.
- Keep an initial incomplete assignment DRAFT. An existing task with requested changes stays CHANGES_REQUESTED, but record Architect as next actor until the correction blueprint is complete; do not dispatch it as executable work yet.
- Include the exact blueprint section reference in the chat handoff. The chat prompt summarizes the assignment; it does not replace the task's instructions.

For every fix round, append a **Correction blueprint — Round N** mapping each open finding to cause, allowed change, ordered correction, regression/negative check and required evidence. Reference unchanged parts of the original blueprint instead of rewriting history. Do not introduce new acceptance requirements silently during review: distinguish an existing unmet criterion, a newly discovered defect and a new scope request. New scope needs the appropriate approval; clarifying how to meet an existing criterion does not reopen approval by itself.

### Builder preflight and pre-handoff gates

After the incoming handoff validation passes, Builder reads the blueprint and confirms scope, dependencies and verification expectations in its report. If an applicable design decision is missing or contradictory, identify the precise gap and return it to Architect; do not start the received assignment until the partner corrects the handoff and intake passes. Do not invent business rules, reinterpret acceptance criteria or request to become Architect.

Before READY_FOR_REVIEW, Builder records:

- Each step/finding → changed files → verification case → actual result/evidence.
- Required commands with exit codes and test totals; positive/negative controls where applicable.
- Remaining NOT VERIFIED checks, exact attempted commands/blockers and their acceptance impact.
- Temporary resources created and confirmed cleanup, plus deviations from the blueprint.
- Matching task/header/checklist current-focus state, latest round, next actor and copy-ready Architect prompt.

Builder's checklist is a self-check of handoff completeness, not acceptance. Architect independently reviews code and evidence. Missing checks may be handed off for blocker assessment only when clearly declared; READY_FOR_REVIEW never means all criteria passed.

Applies to new assignments and the next handoff of ongoing tasks, including CORE-001. Preserve historical reports; do not retroactively claim old handoffs met this gate. This is a workflow requirement, not an implemented dashboard/parser validation feature.

## Mandatory Chat Handoff Prompts

### Agent-to-agent language

All Architect-to-Builder and Builder-to-Architect messages, handoff prompts, correction requests and resubmissions must be written in English. This includes copy-ready agent prompts shown in user-facing chat and their matching task-file copies. Explanations addressed to the user remain in Vietnamese.

This is a language requirement, not a brevity requirement. Preserve the full required scope, context, blueprint references, findings, evidence, limitations and next actions; do not shorten or omit information for token savings. Preserve exact identifiers, paths, commands and quoted source text when needed. Apply this policy to new messages without rewriting historical handoffs.

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
- exact next action;
- exact implementation/correction blueprint section and revision for any Builder assignment.

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
- Architect session reference (reviewer):
- Builder session reference (implementer):
- Implementation contributors and reviewer independence check:
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
- Authoritative documentation to update under ai-document/, or N/A with a reason; link rather than duplicate existing content.

## Implementation blueprint
- Revision and AC/finding IDs covered:
- Blueprint readiness: PASS / INCOMPLETE (Architect only; assignment completeness, not acceptance).
- Inspected baseline and reason for the approach:
- File/component change map and interfaces:
- Required design choices, invariants and allowed implementation discretion:

### Ordered implementation steps
1. S1: Setup and prerequisites.
2. S2: Implement the scoped behavior.
3. S3: Verify, clean up and report.

### Critical-path pseudocode
- State the relevant sequence/algorithm; N/A with a reason only when genuinely trivial.

### Failure and resource lifecycle
- Isolation/ownership, timeouts, cleanup order, recovery and exit propagation; N/A with reason if inapplicable.

## Acceptance criteria
- AC1:
- AC2:

## Verification matrix
| Case | AC/finding | Setup/input | Command/test entry | Expected observable result | Expected exit / failure condition | Evidence path |
|---|---|---|---|---|---|---|
| V1 | AC1 | | | | | |
| V2 | AC1 negative/boundary, if applicable | | | | | |

## Verification instructions
- Specify how the negative control proves the same validator rejects the defect.
- Map each AC to commands, setup, expected output, and cleanup.
- Separate automated, local integration, sandbox, and manual checks.
- Mark unavailable checks `NOT VERIFIED`.

## Incoming handoff validation
- Receiving role/session, sender, task/revision and inspected records:
- Result: PASS / FAIL; discrepancies and correction request if any:
- Authorized next action after PASS; received work paused on FAIL:

## Implementation report — Round 1 (Builder)
- Changes by step/criterion and file/method.
- Checks actually run.
- Checks not run, failed checks, blockers, deviations, and risks.
- Test data created and cleanup status.
- Completed Builder pre-handoff checklist; blueprint deviations and unresolved gaps.

### Chat handoff prompt

```text
Read <task file and relevant docs>. Current status is READY_FOR_REVIEW. Review <summary> and verify <checks/findings>.
```

## Review — Round 1 (Architect)
- Diff and evidence reviewed.
- AC verdicts: PASS / FAIL / NOT VERIFIED.
- Findings with stable IDs, severity, location, evidence, impact, correction, and verification.
- Decision and next action.

### Correction blueprint — Round 2
- Blueprint revision/readiness and finding IDs covered:
- Per finding: cause → exact change map → ordered correction → regression/negative case → evidence.
- References to unchanged implementation blueprint sections:
- Next actor is Architect until this correction blueprint is complete.

### Chat handoff prompt

```text
Read <task file>. Current status is CHANGES_REQUESTED. Follow Correction blueprint — Round 2 (revision <revision>) for <finding IDs> only, run its verification matrix, and append Fix report — Round 2.
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
