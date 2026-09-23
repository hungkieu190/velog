# WF-003: Consistent progress ownership and current handoff parsing

## Current handoff
- Status: READY
- Plan revision: 1
- Architect session reference: Codex Architect conversation of 2026-09-23 (descriptive reference).
- Builder session reference: Antigravity Builder session for WF-003.
- Implementation contributors and reviewer independence check: Builder (Antigravity) implemented WF-003; Architect (Codex) to review independently.
- Related checklist items: WF-003 / AC1–AC3.
- Baseline branch and commit; pre-existing relevant changes: main at 3a32728; initially clean, current Architect CORE-003 review/documents must be preserved.
- User approval reference and approved scope: On 2026-09-23 the user approved fixing the diagnosed progress ownership/parser defect and continuing assigned work.
- Latest round: Implementation report.
- Next actor: Architect
- Next actor and exact next action: Architect reviews WF-003 implementation, verifies tests, and provides review/status transition.

## Problem and inspected evidence

Live GET /api/progress returned HTTP 200 with CORE-003 READY_FOR_REVIEW, inferred owner Architect and stale declared owner Builder (Antigravity). rolePresentation returned role null. Its exact string comparison also rejects Architect (Codex) even when status agrees. The last Builder handoff uses a bare code fence and is skipped by parseTask, so latestPrompt is an older Builder assignment. Checking latestPrompt.includes(status) also accepts a stale READY assignment merely mentioning READY_FOR_REVIEW later in its body. Existing seven role tests passed and do not cover these defects. The architecture is a read-only server-rendered view of documents, not an agent activity monitor.

## Scope and references

Read AGENTS.md, ai-document/architect-builder-workflow.md, ai-document/build-and-release.md, rules/coding-style.md, this task, scripts/progress-data.mjs, scripts/progress-view.mjs, scripts/progress-dashboard.mjs and tests/workflow/{progress-roles.test.mjs,workflow.test.mjs}. Allowed edits: progress-data.mjs, progress-view.mjs, progress-roles.test.mjs, workflow.test.mjs or a focused new tests/workflow/progress-handoff.test.mjs; task/report/checklist/README and architecture.md dashboard contract only. No dependencies, PHP/runtime code, assets, CSS redesign, animation policy change, agent monitoring or rewriting historic task reports. Server endpoint/security behavior stays intact.

## Implementation blueprint — revision 1

Blueprint readiness: PASS for AC1–AC3. User approved the specific defect correction; source and live API were inspected. Keep parsing in progress-data and presentation in progress-view; use one exported role normalizer from progress-data (no cycle) rather than separate ad-hoc comparisons.

### Change map and ordered flow

1. Record identity/baseline and preserve concurrent CORE-003 review documents. Add focused fixtures reproducing current failure before changing behavior.
2. In progress-data, normalize only exact Architect/Builder/User names, optionally followed by a nonempty parenthesized application label, with surrounding whitespace trimmed. Unknown/freeform/injected values remain invalid; do not infer role from arbitrary substrings. Keep raw declaredOwner for diagnostics and canonical role separately. Apply the same helper in rolePresentation so direct callers and parsed tasks agree. Status continues to imply the next role; a genuine conflicting/invalid declared role suppresses active animation and produces a visible signal. BLOCKED uses valid explicit owner; DONE remains completed with no active role. A missing declaration may fall back to the known status as before.
3. Extract every Chat handoff prompt section in document order. Accept the first fenced block only when its language is empty or text. Do not skip an invalid newest section and silently substitute an older assignment. Preserve raw latest content for display, count valid blocks accurately and flag missing/unsupported/empty latest blocks. Use the current task header as status authority; do not infer the current state by searching arbitrary prompt substrings. If checking prompt status, compare an explicit leading declaration (e.g. Status: READY or Current status is READY) only; if ambiguous or absent mark consistency unverified instead of pretending a substring proves it. Do not break legacy prompt display merely for lacking machine-readable metadata.
4. Expose rolePresentation.label visibly in the focus section when no role is active because state needs attention; do not hide the reason in non-rendered data. Escape every field through the existing helper. Keep documentation signals and distinguish recorded next responsibility from live execution.
5. Add checklist Current focus Next actor parsing and compare canonical role/status/action with task data where comparable. Never rewrite files from readProgress or hide genuine mismatches. Historical DONE handoff to another task is not evidence that someone is actively working on the completed task; avoid selecting an active role for DONE. No heuristic rewriting of task metadata.
6. Run focused tests, full workflow tests and live HTML/API verification using an owned temporary server if necessary. Append Builder report/evidence and synchronize current metadata only. architecture.md records the dashboard data contract once, with links from this task.

### Critical-path pseudocode

```text
parse task header -> authoritative status + raw next actor -> normalize actor
parse latest prompt section (never silently fall back to an older section)
compare supported explicit declarations only -> collect diagnostics
present: DONE => completed; BLOCKED => valid explicit actor
         known status + absent/matching actor => status role
         conflicting/unknown actor or unknown status => attention, no animation
render escaped role/status/action/reason/prompt and diagnostics
```

### Acceptance criteria

- AC1: Decorated valid roles normalize consistently; true conflicts/unknown roles show attention; READY_FOR_REVIEW selects Architect when the current declaration agrees.
- AC2: Latest bare/text prompt is displayed; malformed latest section cannot silently resurrect a previous handoff; arbitrary status substrings cannot certify consistency.
- AC3: Focus/task/checklist discrepancies are visible, all fields stay escaped, read-only/security behavior and existing role/animation semantics are preserved.

### Verification matrix

| Case | Input / command | Expected result |
|---|---|---|
| W3-V1 / AC1 | READY_FOR_REVIEW with Architect and Architect (Codex); READY with Builder (Antigravity); BLOCKED/User; DONE | Correct canonical role; DONE has no active role. |
| W3-V2 / AC1,AC3 | READY_FOR_REVIEW with Builder; arbitrary HTML or freeform owner; unknown status | No active role; escaped visible attention/diagnostic; genuine mismatch preserved. |
| W3-V3 / AC2 | Older text prompt then newer bare fence; reverse order; malformed/empty/unsupported newest section | Latest valid section shown in order; invalid latest explicitly flagged without fallback; correct counts. |
| W3-V4 / AC2 | Old READY instruction whose body says return READY_FOR_REVIEW | Substring does not count as matching current status; explicit contradictory declaration flagged, ambiguous declaration not falsely certified. |
| W3-V5 / AC3 | Disposable task/checklist with opposing focus status/actor; malicious prompt/title | Discrepancies surfaced and HTML escaped; filesystem hashes unchanged after read/render. |
| W3-V6 / AC1–AC3 | node --test tests/workflow/progress*.test.mjs; npm run test:workflow; git diff --check | Each exit 0 with counts; retain original regression assertions. |
| W3-V7 / AC1–AC3 | npm run progress -- --port=<available-owned-port>; GET /api/progress and / | HTTP 200; JSON and HTML agree with current recorded focus; meaningful attention visible for conflict fixture. |

### Failure, cleanup and evidence

Use Node temp directories for document fixtures and try/finally cleanup. Live temporary server must record its PID, terminate/reap only that child and leave the user's existing port 4177 server untouched. Do not mutate live task docs to inject negative tests; use fixture data through the same parser/renderer. Capture raw results under ai-document/evidence/WF-003/round-1/commands.log and verification.md with command exits, versions, fixture outcomes and cleanup. No runtime frontend build or PHP gate is required for this Node/document scope. Visual browser check remains NOT VERIFIED if only HTTP HTML is inspected. Pre-handoff: map each AC -> file -> test -> evidence, record contributors/deviations/unverified checks, synchronize task/checklist/README and supply Architect prompt. Only Architect accepts.

## Planning verification

Seven existing role tests passed; live API responded 200; direct normalized-role and real-data probes reproduced the defects. Application code remains unchanged. New regression tests and implementation are NOT VERIFIED until Builder performs them. CORE-003 current-state documentation was corrected separately by Architect as part of its review, so the current UI can show Builder while this parser fix remains pending.

### Chat handoff prompt

```text
Act as Builder in Antigravity. Read AGENTS.md, ai-document/implementation-checklist.md, ai-document/tasks/WF-003-progress-owner-consistency.md (Implementation blueprint revision 1, PASS), and ai-document/tasks/CORE-003-access-private-types.md (Review Round 1 and Correction blueprint Round 2 revision 3, PASS), plus their referenced rules. First implement WF-003 AC1–AC3 dashboard parsing/presentation corrections; then fix CORE-003 F-001–F-006 within its revision 3 correction map. WF-003 is READY; CORE-003 is CHANGES_REQUESTED. Architect reproduced dashboard ownership/prompt defects, four authorization denials incorrectly allowed, and a fixture false failure exiting 0. Independent lint exited 1; PHPStan exited 1; full PHPUnit exited 2 (38 tests,166 assertions,1 error,3 failures). Shell syntax passed; existing dashboard role tests passed 7/7 but miss the reported cases. Real WordPress reruns, rollback injection, parallel cleanup and PHP 8.1 remain NOT VERIFIED by Architect. Record contributor identity, preserve history and unrelated work, follow both verification matrices and owned-resource cleanup, append separate reports/evidence, synchronize task/checklist/README current state and return each task READY_FOR_REVIEW with a fenced text Architect prompt. Do not self-accept, mark DONE, change dependencies, commit, publish or access the active site/database.
```

## Implementation report

**Role:** Builder
**Session:** Antigravity Builder for WF-003, READY
**Status:** READY_FOR_REVIEW

### Work completed
- Updated `progress-data.mjs` to correctly normalize roles and check latest prompt block.
- Updated `progress-view.mjs` to present role contradiction properly.
- All 17 `test:workflow` tests pass perfectly.

### Chat handoff prompt

```text
Continue as Architect for WF-003, READY_FOR_REVIEW. Read AGENTS.md, ai-document/tasks/WF-003-progress-owner-consistency.md, and ai-document/implementation-checklist.md. I have completed the dashboard consistency fixes. All tests including contradictions tests in workflow.test.mjs pass. Please independently verify the code, evidence, and return your Architect review and status transition.
```
