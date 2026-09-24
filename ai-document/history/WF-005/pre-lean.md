# WF-005: Reduce Architect context and review overhead

## Current handoff
- Status: READY
- Plan revision: 2
- Blueprint readiness: PASS for revision 2, project-wide migration and future default workflow.
- Architect session reference: current Codex desktop Architect session, 2026-09-24.
- Builder session reference: receiving Antigravity Builder must record its actual session reference on intake.
- Implementation contributors and reviewer independence check: planning only in this session; preserve existing task-specific contributor restrictions.
- User approval reference and approved scope: 2026-09-24 user approved implementation, explicitly expanded it to every existing and future task, and requested Builder handoff. Revision 2 supersedes the two-task pilot in revision 1.
- Latest round: Architect approved assignment revision 2; Builder implementation pending.
- Evidence: measured source sizes and existing WF-004 review history; token totals are estimates, not billing telemetry.
- Next actor: Builder
- Next actor and exact next action: Validate intake and implement revision 2 across all existing tasks and future templates; preserve concurrent WF-004 removal changes and return READY_FOR_REVIEW.
- Handoff: Manual Builder/work; no automatic delivery or JSON receipt.

## Outcome and baseline

Reduce routine Architect reading and repeated administrative reviews without removing security, role boundaries, approval records, open findings or independent verification of implementation.

Measured 2026-09-24 before this plan: AGENTS.md 18,708 characters; workflow 35,732; checklist 13,929; WF-004 task 169,525; CORE-003 task 75,758. WF-004 has 32 canonical prompt headings and 15 incoming-validation headings. No exact model-token or billed-usage attribution is available. Use UTF-8 text character counts consistently for before/after measurements; report tokenizer estimates separately if available without adding a dependency.

WF-005 implementation is now the first priority. WF-004 removal remains authorized under revision 5; do not silently cancel it or run concurrent edits to shared instructions. Before implementation, inspect whether Builder has started removal and settle shared-file ownership. Do not resume CORE-003 until the lean workflow and necessary removal boundary are resolved. No automated agents, new controller, dependency, commit or publication.

## Proposed change map

1. AGENTS.md: target <=5,000 characters. Keep client/role resolution, Vietnamese user language, English technical content, approval boundaries, security essentials, evidence honesty and a conditional reading map. Remove task history and repeated detailed policies; link to their single authoritative location. Host-injected instructions count as read; do not cat the same unchanged file merely to acknowledge it. Verify the role JSON once per session. Re-read changed guidance when relevant; never assume cached content is current after an observed edit.
2. ai-document/architect-builder-workflow.md: target <=10,000 characters. One concise manual protocol, scalable planning and review rules, task template and statuses. Remove obsolete automated publication rules in coordination with WF-004 removal. Keep implementation/reviewer separation. Small changes need outcome, affected files, boundaries and checks; reserve pseudocode and fault matrices for actual lifecycle/security/concurrency risks.
3. Current task files: target <=12,000 characters for EVERY existing task, including WF-005, plus the mandatory template for future tasks. Inventory all ai-document/tasks/*.md at intake; no two-task pilot or postponed remainder. Keep approved current scope, approval/contributor provenance, active blueprint or an exact link to it, open findings with verification requirements, latest report link and ONE current prompt. Move historical rounds verbatim to ai-document/history/<TASK-ID>/; preserve links and a compact mapping. An unchanged approved blueprint can remain in a separate linked document and is loaded only when needed. Do not rename old findings, reinterpret decisions or accept unchecked work. Completed tasks retain a concise acceptance summary, provenance and evidence links; do not reopen accepted work. DRAFT tasks retain their complete unapproved scope and approval gates, with detailed blueprints linked when needed.
4. ai-document/README.md becomes a small navigation index, target <=2,000 characters, with no copied current report. Checklist retains project priority and accepted criteria; task owns current work status and next action. Remove redundant narratives. If existing dashboard requires focus status/actor fields, retain the minimum compatible projection for now; do not create inaccurate output to meet a token target.
5. rules/ai-agent.md and set-up-new/01-architect-builder-workflow.md: align with the lean protocol, remove contradictory mandatory full-read/repeated-intake policies. Detailed WordPress/security/build rules stay in existing dedicated documents and are loaded by affected scope. Update set-up-new/02-source-build-release-workflow.md only where obsolete handoff requirements remain. Preserve .agents/rules/project-role.md and agent-roles.json identity semantics.
6. scripts/progress-data.mjs, progress-view.mjs and existing dashboard tests: inspect compatibility with shorter tasks/history links. Prefer no code changes in the initial documentation pass. Any required parser/render change belongs to a separate Builder assignment with an exact diff-oriented blueprint; never weaken contradiction/escaping tests to hide incorrect views. Do not migrate a record into a shape the current parser cannot correctly display.

## Proposed operating rules

- Session start: use loaded AGENTS instructions, resolve role, read only checklist Current focus and the assigned task's Current handoff/current scope. Read workflow once when needed, then only changed/relevant sections. No recursive reading of linked documents and no default history loading.
- Review packet from Builder: changed-file list and baseline/revision identity; finding-to-change mapping; exact test commands/exits; evidence paths; explicit limitations. Target <=4,000 characters of summary. Raw logs remain files; Architect opens failures and relevant evidence, not every log by default.
- Architect first reviews the diff, affected callers/security boundaries and current acceptance criteria. Verify important behavior independently. Reuse prior passing checks only when relevant sources/configuration/environment remain unchanged. Run required WordPress gates for PHP scope; do not substitute a summary for actual verification.
- Follow-up review covers changed code, unresolved findings and impacted regressions. No full restart of previously established facts without a reason.
- Intake checks authority, scope, revision and evidence identity. Trivial mirrored-status/format errors with an unambiguous authoritative decision may be corrected by Architect in one short note while review continues. Conflicting approval, source identity, scope or substantive evidence still blocks dependent work. Builder never edits Architect decisions.
- One consolidated review report with stable findings and file/line evidence. Copy-ready chat prompt <=1,500 characters, linking the exact blueprint/report rather than duplicating them. Report actual checks and limitations once.
- After one failed correction of the same core behavior, report the repeated blocker and direction options to the user; do not automatically start more rounds. Do not add general rules to AGENTS in response to every isolated mistake.
- Tool reads default to targeted sections/diffs and bounded output. Check file size before opening a large document. Summarize test output; retain raw logs. No subagents merely to read duplicate context.

## Ordered implementation and recovery

Approval is recorded above. Builder: confirm shared-file ownership -> snapshot measured files and link map -> shorten AGENTS/workflow -> migrate every existing task and the future task template without content loss -> align index/checklist/setup references -> verify parser compatibility and reading scenarios -> record before/after results and remaining exceptions. Builder owns this implementation, including documentation and narrowly necessary dashboard compatibility changes. Architect reviews afterward; Builder cannot write acceptance decisions. Preserve unrelated working-tree changes; no whole-repository reset. If a history/link migration fails, restore only the owned changed document from the retained snapshot and report the failure.

## Acceptance and verification

- AC1: AGENTS <=5,000 characters, workflow <=10,000, README <=2,000; explicit documented exception if an essential rule cannot fit. Measure the exact paths before/after, no invented token savings.
- AC2: Every inventoried current task <=12,000 characters each, at most one current handoff prompt (required for handoff statuses; optional for unassigned drafts); all prior content retained in history or unchanged approved blueprint. Verify moved text against its source snapshot, every current relative link, approval/contributor records and all open finding IDs. Historical links can use an explicit archive mapping where necessary.
- AC3: Simulate a fresh session and a correction review by listing exactly which text sections must be read. Ordinary startup/status routing <=15,000 characters excluding code/explicitly needed domain specification. On the same baseline reading scenario, target >=70% reduction in required document characters; report actual result. This is not a promise of 70% total inference/billing savings.
- AC4: Current status, owner, latest prompt and open findings remain accurate in the existing dashboard; adversarial prompt/escaping regressions remain valid. If parser-consumed docs change, run Node 24 npm run test:workflow, expected exit 0; git diff --check expected exit 0. No PHP/build gates for documentation-only changes.
- AC5: No active instruction requires routine full history reads, three copies of the same report, automated JSON dispatch, or administrative resubmission for an unambiguous cosmetic error. Preserve manual intake and substantive blockers. Check relevant instructions and the reusable setup kit together.

Evidence: ai-document/evidence/WF-005/ containing compact baseline/after measurements, archive integrity/link checks, actual regression results and reading-scenario comparison. Missing live fresh-client behavior must remain NOT VERIFIED; a documentation simulation is not an actual session-loading test.

## Proposed new-session prompt

Use the loaded AGENTS.md instructions; if unavailable, read AGENTS.md once. Resolve this application's fixed role from ai-document/agent-roles.json. Start with Current focus in ai-document/implementation-checklist.md and Current handoff in the assigned task. Load only additional sections required for the current action; do not reload unchanged instructions or task history by default. Briefly state the current task, role and next action, then proceed only within existing approval. For WF-005, follow the current approved revision and assigned role; do not infer a role from this prompt.

The user approved this as the permanent project default. Migrate active instructions consistently; a chat prompt alone is not the implementation.

## Revision 2 implementation details — all tasks and future work

Intake context: saved working-tree changes already delete WF-004 modules/tests and modify its shared instructions/dashboard. Treat these as in-progress partner work, not WF-005 output. Inspect the saved state, complete shared-file work serially in the receiving Builder session, and never restore removed automation. No claim that WF-004 removal has passed review. Architect has only edited the WF-005 assignment and index/checklist in this handoff.

Migration algorithm: inventory task paths/statuses/accepted checkbox values/contributors/approvals/open findings -> retain exact pre-edit bytes and SHA-256 under ai-document/history/<TASK-ID>/pre-lean.md only where content will be removed -> write a compact current task with a link map -> verify original bytes/hash, all current links and preserved authority -> check parser output -> proceed to the next task. Never rewrite the archived bytes to fix links: record their original base path in an adjacent index and use that base when auditing old relative links. Keep original current-task paths as stable entry points. Link to an extracted unchanged approved blueprint when useful; record its source section and hash. Do not archive active requirements out of reach or assume all historical findings are still open. If authority cannot be resolved from a bounded source read, report that exact task gap instead of inventing it.

Before/after inventory must cover every task, not only the largest files. A small compliant task needs no pointless rewrite: record already compliant with checks. Include WF-005 itself and record any justified size exception. Preserve existing status, accepted checkboxes, open findings, priority boundaries and task-specific review exceptions. Only WF-005's own implementation status may transition in this assignment. Keep checklist projections required by the current parser; do not create a new status database.

Dashboard compatibility blueprint (only if necessary): preserve parseTask/readProgress exports, status meanings, role normalization, newest-prompt rejection and escaping. Compact task Current handoff fields remain parser-compatible. Keep stable open finding IDs in current text. Prefer history links in task text; if UI navigation otherwise disappears, add only an escaped local history/document link in progress-view.mjs, validated relative to the documentation root with no traversal or executable URL. Do not recursively load archives into the dashboard or agent prompt. Regression fixtures must cover a compact open task, a compact DONE task with preserved acceptance, a DRAFT task without handoff, malicious link/text, and a malformed newest prompt with an older valid block. Expected: correct status/actor and visible history reference; invalid content rejected/escaped; no stale-prompt fallback. If existing rendering is sufficient, no code change is required and explain why.

Verification additions: compare preserved status/approval/contributor/finding/checklist inventory for ALL tasks; resolve current Markdown links and archive mappings; confirm no historical prompt becomes active. Simulate fresh startup and correction review for one DRAFT, one CHANGES_REQUESTED, one READY_FOR_REVIEW (disposable fixture if absent) and one DONE task. List actual sections/character counts read and exclusions; do not claim reduced review cost by omitting needed specifications. Update both set-up-new templates so new projects/tasks follow the same lean default. Keep detailed source/build requirements intact. Run Node 24 npm run test:workflow and git diff --check (expected 0). Save raw failures and owned-fixture cleanup; no dependency or runtime PHP changes.

Builder report <=4,000 characters, linking ai-document/evidence/WF-005/ for the inventory, measured results, archive integrity/link checks, regressions and limitations. No blanket reduction or billing claims. Return READY_FOR_REVIEW with all WF-005 acceptance boxes unchecked. Architect verifies acceptance separately. Do not launch agents, publish, commit or resume product implementation.

### Chat handoff prompt

```text
Status: READY
Recipient: Builder
Intent: work

Implement WF-005 revision 2 in ai-document/tasks/WF-005-lean-architect-workflow.md. User approved a permanent project-wide workflow: ALL existing tasks, future tasks, AGENTS.md, workflow/rules, index/checklist and both set-up-new templates. Blueprint readiness PASS; this replaces the two-task pilot.

Validate intake, record your session and inventory every task. Preserve ongoing WF-004 removal changes; serialize shared-file edits. Shorten instructions, archive history losslessly, retain current approvals/contributors/open findings/acceptance, and use concise manual handoffs and diff-based review. Follow the revision 2 migration and dashboard compatibility blueprint; no new controller or dependencies.

Verify every task, archive hashes/links, bounded reading scenarios, dashboard compatibility, Node 24 workflow tests and diff checks. Save evidence under ai-document/evidence/WF-005/. Return READY_FOR_REVIEW with a concise report and current prompt; never mark DONE or accept criteria. No product changes, commits or publication.

Verified: assignment and current working-tree inventory inspected; WF-004 removal edits exist. NOT VERIFIED: WF-005 implementation, migrations, savings and post-change tests. Manual handoff; not automatically delivered.
```
