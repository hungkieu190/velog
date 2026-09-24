# Architect and Builder workflow

## 1. Principles and role separation

VeLog uses an independent pair-programming model between Architect and Builder:
- **Architect (Codex)**: Investigates repository state, writes tasks and blueprints, reviews diffs and test evidence, decides acceptance, and manages project documentation. Architect never writes application fixes.
- **Builder (Antigravity)**: Implements approved blueprints, executes tests, records evidence, and reports results. Builder never acts as Architect, marks tasks DONE, or accepts criteria.
- **Role separation**: Separate sessions with fixed roles. A former implementer or contributor cannot independently review or accept the same work.
- **User authority**: User approves product scope, master plans, and performs manual acceptance.

## 2. Task lifecycle and statuses

Tasks transition strictly through defined statuses:
- `DRAFT`: Under planning or revision; unapproved.
- `READY`: Blueprint approved (readiness PASS); assigned to Builder.
- `IN_PROGRESS`: Active implementation by Builder.
- `READY_FOR_REVIEW`: Implementation and pre-handoff evidence complete; assigned to Architect.
- `CHANGES_REQUESTED`: Review returned with findings; assigned to Builder for fixes.
- `BLOCKED`: Work paused; must name declared owner and blocker.
- `AWAITING_MANUAL_ACCEPTANCE`: Implementation verified; awaiting user decision.
- `DONE`: Independently accepted by Architect or User. Checklist updated.

```text
DRAFT -> READY -> IN_PROGRESS -> READY_FOR_REVIEW -> CHANGES_REQUESTED -> IN_PROGRESS ... -> DONE
```

## 3. Scalable planning and blueprints

Architect provides an actionable implementation blueprint before dispatching to Builder:
1. **Outcome and evidence**: Current vs. expected behavior, addressed ACs/findings.
2. **Change map**: Exact files, functions/hooks, and components to change; explicit exclusions.
3. **Boundaries and risks**: Lifecycle, security, or concurrency risks. Reserve pseudocode and fault matrices for complex multi-process or critical-path logic.
4. **Verification matrix**: Expected commands, positive/negative controls, exit codes, and evidence paths.
5. **Blueprint readiness**: Architect marks `PASS` before setting `READY`. Builder reports design gaps if incomplete.

## 4. Manual handoff workflow

Handoffs between Architect and Builder are manual via synchronized documents and copy-ready chat prompts. Automated dispatch, JSON signals, and receipts are retired.

### Finalization
1. Finish code, tests, and evidence logs.
2. Append report to task file; preserve historical reports or link to archived rounds under `ai-document/history/<TASK-ID>/`.
3. Synchronize task file, `ai-document/implementation-checklist.md`, and `ai-document/README.md`.
4. Provide exactly one copy-ready prompt under `### Chat handoff prompt`. Format:
```text
Status: <STATUS>
Recipient: <Architect|Builder>
Intent: <work|review>

<Summary of changes, criteria, evidence path, next action>
```

### Mandatory incoming validation
Before starting received work, the receiving agent must validate:
- Role authority, assigned client, and reviewer independence.
- Task status, revision, round, next actor, and blueprint readiness (`PASS`).
- Task matches checklist focus/item status and README.
- Referenced reports and evidence exist.
- Record `PASS` or `FAIL` in an intake note. On `FAIL`, stop and return a correction prompt; do not proceed.

## 5. Review and verification contract

- **Diff-based review**: Architect reviews the git diff first, verifying affected callers, security boundaries, and negative controls.
- **Verification execution**: Reviewer verifies evidence and runs relevant gates:
  - PHP: `composer run lint` (PHPCS + PHPStan), `composer run test` (PHPUnit).
  - Workflow/tooling: `npm run test:workflow` (Node 24).
  - Frontend: `npm run build` / `npm run production` if sources changed.
- **Findings**: Report findings with stable IDs (`F-001`, `F-002`), file/line references, and verification requirements. Do not close findings without verified fixes.

## 6. Required lean Task Template

All new tasks follow this compact structure (target <=12,000 characters):

````markdown
# <TASK-ID>: <Title>

## Current handoff
- Status: <DRAFT|READY|IN_PROGRESS|READY_FOR_REVIEW|CHANGES_REQUESTED|DONE>
- Plan revision: <N>
- Implementation round: <N>
- Blueprint readiness: <PASS|INCOMPLETE>
- Architect session reference: <session-id-or-date>
- Builder session reference: <session-id-or-date>
- Implementation contributors and reviewer independence check:
- Related checklist items: <TASK-ID / AC1–ACN>
- User approval reference and approved scope:
- Latest round:
- Latest report: <evidence/path>
- Evidence: <evidence/path>
- Next actor: <Architect|Builder|User>
- Next actor and exact next action:

## Problem and intended behavior
- Current behavior, defect or feature description, and business rules.

## Approved scope and blueprint
- Allowed files and components to change.
- Step-by-step implementation plan and architectural invariants.
- Verification matrix (cases, commands, expected results).

## Acceptance criteria
- [ ] <TASK-ID> / AC1: <Description>. Status: <STATUS>.
- [ ] <TASK-ID> / AC2: <Description>. Status: <STATUS>.

## Open findings
- None recorded (or list F-001 with status and fix criteria).

## History and archives
- Prior rounds archived at: [ai-document/history/<TASK-ID>/pre-lean.md](ai-document/history/<TASK-ID>/pre-lean.md) (or inline for small tasks).

### Chat handoff prompt

```text
Status: <STATUS>
Recipient: <Architect|Builder>
Intent: <work|review>

<Concise handoff summary>
```
````
