# Implementation checklist

## Current focus

- Task: QA-001
- Workstream: Development
- Status: IN_PROGRESS
- Scheduling: PAUSED_BY_OWNER
- Next actor: Developer
- Exact next action: Resume the reminder benchmark diagnosis from evidence/QA-001/round-1/report.md, rerun G-08 and affected regressions, then complete ZIP retention verification.
- Authority: Product Owner requested saving progress for the next session and committing/pushing this checkpoint.
- Evidence: [QA-001 report](evidence/QA-001/round-1/report.md); [test debt](test-debt.md).


## Project status

Tooling bootstrap and master-plan revision 3 are accepted. CORE-001, CORE-002, CORE-003 and DATA-001 are DONE. DATA-001 was directly accepted by the user on 2026-10-02 without independent review of Codex's final cleanup implementation. Its live-handle COMMIT failure, coordinator 1213 deadlock and persistent-cache compatibility remain NOT VERIFIED; full G-08 product performance belongs to MVP-001 and is NOT VERIFIED. The 2026-10-21 owner decision supersedes role separation: Codex is the sole Developer for backend/frontend and technical self-review; Tester / Product Owner alone performs manual feature acceptance.

## Accepted product master plan and MVP

- [x] PLAN-001 / AC1: Consolidate product goals, users, journeys, data/permissions and MVP boundaries. Status: DONE.
- [x] PLAN-001 / AC2: Define dependency-ordered delivery phases and acceptance criteria. Status: DONE.
- [x] PLAN-001 / AC3: Record explicit user approval of master plan/MVP before feature implementation. Status: DONE.

Task: [PLAN-001](tasks/PLAN-001-master-plan.md).

## Product P0 — Foundation

- [x] CORE-001 / AC1: Real bootstrap runs Loader once. Status: DONE.
- [x] CORE-001 / AC2: Correct translation lifecycle and fixture translation. Status: DONE.
- [x] CORE-001 / AC3: Compatibility and PHP quality/test gates. Status: DONE.
- [x] CORE-001 / AC4: Isolated WordPress evidence and independent review. Status: DONE.

Task: [CORE-001](tasks/CORE-001-bootstrap-i18n.md). Accepted in independent Architect Round 10, revision 2; retained evidence is under `ai-document/evidence/CORE-001/architect-round-10/`.

## Continuous MVP planning — PLAN-002

- [ ] PLAN-002 / AC1: Complete dependency-ordered draft blueprints for P0–P4. Status: DRAFT.
- [ ] PLAN-002 / AC2: Consolidated product/technical gates without invented approval or runtime evidence. Status: DRAFT.
- [ ] PLAN-002 / AC3: Record product decisions and individual readiness before later dispatch. Status: DRAFT.

Task: [PLAN-002](tasks/PLAN-002-mvp-task-batch.md). Draft documents are written; unchecked items remain pending the planning review/decision gates. These entries measure recorded acceptance, not implementation completion. Approved master-plan direction remains unchanged.

## Product implementation queue

### CORE-002 — Exact regional value primitives

- [x] CORE-002 / AC1: Locale parsing rejects ambiguous/invalid values and preserves unknown versus zero. Status: DONE.
- [x] CORE-002 / AC2: Exact km/mi conversion, bounds and half-up behavior are proved without persisted binary floats. Status: DONE.
- [x] CORE-002 / AC3: Money identity/scale and date-only/UTC distinctions survive preference changes. Status: DONE.
- [x] CORE-002 / AC4: Unit fixtures and full quality checks pass; no settings or data writes introduced. Status: DONE.

Task: [CORE-002](tasks/CORE-002-regional-primitives.md). Prerequisites: CORE-001 DONE.

### CORE-003 — Capabilities and private record types

- [x] CORE-003 / AC1: Explicit manager/admin/technician policy is enforced at object and primitive levels. Status: DONE.
- [x] CORE-003 / AC2: Anonymous, subscriber and unrelated editor cannot discover private records through public/native endpoints. Status: DONE.
- [x] CORE-003 / AC3: Activation/reactivation and role collisions preserve unrelated roles/capabilities. Status: DONE.
- [x] CORE-003 / AC4: Real WordPress permission/lifecycle evidence and runner failure/cleanup controls pass. Status: DONE (fully verified on WP 6.4.3 and 6.7.2 + negative controls).

Task: [CORE-003](tasks/CORE-003-access-private-types.md). Prerequisites: CORE-001 DONE.

### DATA-001 — Versioned private storage and retained data

- [x] DATA-001 / AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads. Status: DONE by direct user acceptance; V1 fixture passed.
- [x] DATA-001 / AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates. Status: DONE by direct user acceptance; coordinator 1213 deadlock NOT VERIFIED.
- [x] DATA-001 / AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge. Status: DONE by direct user acceptance; V3 fixture passed.
- [x] DATA-001 / AC4: Metadata privacy, real storage failure/recovery and cache consistency verified. Status: DONE by direct user acceptance; live-handle COMMIT failure and persistent-cache compatibility NOT VERIFIED.

Task: [DATA-001](tasks/DATA-001-record-storage.md). Prerequisites: CORE-002 and CORE-003 DONE.

### CORE-004 — Explicit regional setup and admin shell

- [x] CORE-004 / AC1: Manager explicitly configures units/currency; invalid/unconfigured values cannot silently default. Status: DONE.
- [x] CORE-004 / AC2: Capability, nonce and stale-version checks protect writes. Status: DONE.
- [x] CORE-004 / AC3: Preference/locale changes preserve historical value identity. Status: DONE.
- [x] CORE-004 / AC4: Accessible translated/RTL admin UI and scoped generated assets pass verification. Status: DONE by direct Product Owner manual acceptance on 2026-10-06; retained Round 3 screenshots do not independently demonstrate the rendered form state.

Task: [CORE-004](tasks/CORE-004-regional-settings.md). Prerequisites: CORE-002, CORE-003 and DATA-001 DONE.

- [x] CORE-004-FE: Regional settings responsive, RTL, keyboard, focus, long-content, and asset-scope presentation evidence. Status: DONE by direct Product Owner manual acceptance on 2026-10-06.

Task: [CORE-004-FE](tasks/CORE-004-FE-regional-settings-ui.md). Owner: Frontend Developer; reviewer: Backend Architect.

### CUST-001 — Private customer management (restored by G-10)

- [x] CUST-001 / AC1–AC4: Backend self-review, FE code approval and all manual UAT cases accepted. Status: DONE by explicit Tester confirmation on 2026-10-21; Next actor: Tester via VEH-001-UAT.
- [x] CUST-001-BE: Round 3 gates and UAT preparation verified. Status: DONE as SELF_REVIEWED_BACKEND; full feature acceptance is recorded separately in UAT.
- [x] CUST-001-FE: Architect round 3 review 2 APPROVED for code; FE-R3-01 through FE-R3-05 resolved. Status: DONE after explicit Tester acceptance of all UAT cases on 2026-10-21.
- [x] CUST-001-UAT: T01–T20 PASS as reported by Tester; historical T02 failure retained. Status: DONE by explicit Tester acceptance on 2026-10-21. No additional environment/tool details supplied with final confirmation.

Task: [CUST-001](tasks/CUST-001-customer-records.md); [UAT results and acceptance](tasks/CUST-001-UAT-manual-test.md); [backend evidence](evidence/CUST-001-BE/backend-round-3/report.md); [FE approval](evidence/CUST-001-FE/frontend-round-3/architect-review-r2.md); [preparation evidence](evidence/CUST-001-UAT/preparation-2026-10-15/report.md). G-10 preserves independent private record IDs, optional/non-unique contacts and G-03 archive policy.

### CUST-002 — Native WordPress customer identities (retired by G-10)

- [ ] CUST-002 / AC1–AC4: Withdrawn without acceptance. Status: BLOCKED; Next actor: Backend Architect for historical-document consistency only. Do not resume implementation or UAT.
- [ ] CUST-002-BE/FE/UAT: BLOCKED/retired. Native runtime and fixtures removed after backup; documents and evidence retained as history.

Task: [CUST-002](tasks/CUST-002-native-wordpress-customers.md). G-10 supersedes G-09 and its adapter approvals; CUST-002 is no longer a prerequisite for product work.

### VEH-001 — Vehicle identity and current customer

- [x] VEH-001 / AC1: Validated unique international identifiers. Status: DONE; Next actor: Developer via SERV-001.
- [x] VEH-001 / AC2: Private customer relation, permissions and archive/restore. Status: DONE; Next actor: Developer via SERV-001.
- [x] VEH-001 / AC3: Exact known/unknown readings and global system distance unit. Status: DONE; Next actor: Developer via SERV-001.
- [x] VEH-001 / AC4: Native Year dropdown, operations and UI/security/accessibility UAT accepted. Status: DONE; Next actor: Developer via SERV-001.

Task: [VEH-001](tasks/VEH-001-vehicle-records.md). Tester explicitly confirmed all test cases/environments passed on 2026-10-21 and requested further code. Revision 3 technical evidence remains preserved.

- [x] VEH-001-BE: Technical SELF_REVIEWED_DEVELOPMENT and parent manual acceptance complete. Status: DONE.
- [x] VEH-001-FE: Presentation and manual acceptance complete. Status: DONE.
- [x] VEH-001-UAT: Explicit Tester acceptance, T01–T15. Status: DONE.

### SERV-001 — Service drafts, finalization and corrections

- [ ] SERV-001 / AC1: Creator/manager draft and finalization rules enforce required relations, dates, reading and type. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] SERV-001 / AC2: Optional costs and original units/customer/type snapshots preserve historical meaning. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] SERV-001 / AC3: Corrections/decreases are manager-only, attributed/reasoned and cannot erase prior audit. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] SERV-001 / AC4: Backdating/ties/concurrent transitions and failures preserve coherent current reading and service state. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.

Task: [SERV-001](tasks/SERV-001-service-workflow.md). Prerequisites: VEH-001 DONE.


Tester explicitly accepted all VEH-001 cases/environments on 2026-10-21. SERV-001 G-06 and revision 2 implementation are approved; previous pending/manual-handoff wording above is historical and superseded by this decision.


SERV-001 implementation remains complete. [SERV-001-UAT](tasks/SERV-001-UAT-manual-test.md) is DRAFT / TEST_DEBT / DEFERRED; Next actor Developer. Prior login/CSS/prerequisite evidence is historical. No manual PASS is claimed and UAT does not block code.

### HIST-001 — Private service history and correction visibility

- [ ] HIST-001 / AC1: Authorized vehicle timeline/filter/search uses deterministic newest-first pagination. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] HIST-001 / AC2: Historical units/currencies/customer/type and visible corrections are accurate. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] HIST-001 / AC3: Private/draft/contact boundaries hold for direct/query/detail paths without per-row lookup growth. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] HIST-001 / AC4: Keyboard, long content, RTL and mobile retrieval journey is manually demonstrated. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.

Task: [HIST-001](tasks/HIST-001-service-timeline.md). Implementation complete under CODE-001; functional acceptance deferred. SERV-001 code completion satisfied the implementation dependency.

### REM-001 — Manual maintenance thresholds and queue

- [ ] REM-001 / AC1: Manual reminder validation requires valid vehicle/title and at least one trigger. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] REM-001 / AC2: OR due, unknown/zero, cross-unit, local-date and snooze rules are exact and stable. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] REM-001 / AC3: Manager transitions/archive effects are attributed, concurrent-safe and generate no notifications/services. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.
- [ ] REM-001 / AC4: Due queue filtering/count/paging and accessible manager workflow pass real WordPress checks. Status: AWAITING_MANUAL_ACCEPTANCE; Next actor: Developer.

Task: [REM-001](tasks/REM-001-maintenance-queue.md). Implementation complete under CODE-001; functional acceptance deferred. HIST-001 code completion satisfied the implementation dependency.

### MVP-001 — MVP integration, performance and local package acceptance

- [ ] MVP-001 / AC1: Actual local ZIP contains complete runtime and installs/runs without development environment fallback. Status: DRAFT.
- [ ] MVP-001 / AC2: Complete international staff journey and negative authorization/retention cases pass across required runtime matrix. Status: DRAFT.
- [ ] MVP-001 / AC3: Agreed performance dataset/targets and query correctness are evidenced without hidden shortcuts. Status: DRAFT.
- [ ] MVP-001 / AC4: User manual acceptance and production limitations are explicitly recorded before final DONE decision. Status: DRAFT.

MVP-001 is TEST_DEBT / DEFERRED, Next actor Developer when the Product Owner resumes testing.

Task: [MVP-001](tasks/MVP-001-acceptance-package.md). Historical acceptance prerequisites: CORE-001/002/003/004, DATA-001, CUST-001, VEH-001, SERV-001, HIST-001 and REM-001 DONE.

## Phase 1 — Workflow visibility

- [x] WF-001 / AC1: Reconcile AGENTS.md, AGENT.md, legacy rules, and operational documentation. Status: DONE.
- [x] WF-001 / AC2: Implement and verify the read-only progress dashboard. Status: DONE.

## Phase 2 — Source and release tooling

- [x] WF-001 / AC3: Migrate frontend sources and verify development/production builds. Status: DONE.
- [x] WF-001 / AC4: Implement safe runtime-complete local release packaging. Status: DONE.
- [x] WF-001 / AC5: Reconcile ignore policy, lockfiles, Node environment, legacy commands and CI packaging. Status: DONE.

## Phase 3 — Acceptance

- [x] WF-001 / AC6: Record positive/negative checks and isolated WordPress smoke evidence. Status: DONE.
- [x] WF-001 / AC7: Independent Architect review and acceptance. Status: DONE.

Task: [WF-001](tasks/WF-001-workflow-bootstrap.md). No product feature is accepted by this checklist. PLAN-001 approval is complete; only explicitly READY implementation tasks may proceed.

## Dashboard presentation — WF-002

- [x] WF-002 / AC1: Illustrated engineer/miner/user role strip and responsive layout. Status: DONE.
- [x] WF-002 / AC2: Status-accurate selected role and finite work animation. Status: DONE.
- [x] WF-002 / AC3: Reduced motion, accessibility, escaping and read-only behavior. Status: DONE.
- [x] WF-002 / AC4: Regression and browser evidence within the local-tool scope. Status: DONE.

Task: [WF-002](tasks/WF-002-animated-role-strip.md). User approved revision 1 on 2026-09-21; blueprint readiness PASS, assigned separately to Builder. User directly accepted the implementation and marked the task DONE on 2026-09-21, overriding finite animation with infinite animation. CORE-001 remains the current implementation focus until its existing handoff; do not overwrite its concurrent status/evidence.

## Dashboard handoff consistency — WF-003

- [x] WF-003 / AC1: Canonical/decorated roles agree; genuine conflicts remain visible. Status: DONE.
- [x] WF-003 / AC2: Latest bare/text handoff is parsed without stale fallback or substring-based false validation. Status: DONE.
- [x] WF-003 / AC3: Read-only escaped dashboard and focus/checklist diagnostics pass regression and HTTP checks. Status: DONE.

Task: [WF-003](tasks/WF-003-progress-owner-consistency.md). User approved on 2026-09-23. Separate bounded acceptance is recorded in CORE-003 Architect Review Round 4 and its evidence/review.md. Status synchronized on 2026-09-23 from that existing decision; no new verification or acceptance is claimed. Do not reopen dashboard scope.

## Retired automation — WF-004 removal

Original automation AC1–AC5 were abandoned by user instruction and are not accepted. They remain recorded in the task history, excluded from active acceptance counts.

- [x] WF-004 / RM1: Exclusive automation removal accepted by user. Status: DONE.
- [x] WF-004 / RM2: Regression evidence accepted by user without Architect rerun. Status: DONE.
- [x] WF-004 / RM3: Dashboard removal behavior accepted by user without Architect rerun. Status: DONE.
- [x] WF-004 / RM4: Manual workflow restoration accepted by user. Status: DONE.

Task: [WF-004](tasks/WF-004-json-handoff-controller.md). DONE by direct user acceptance on 2026-09-24. Further review/checks explicitly waived; no independent verification claimed.

## Architect token reduction — WF-005

- [x] WF-005 / AC1: Compact startup instructions and workflow meet measured size targets. Status: DONE.
- [x] WF-005 / AC2: Current tasks are concise; historical evidence and authority are preserved. Status: DONE.
- [x] WF-005 / AC3: Bounded reading scenarios demonstrate measured reduction. Status: DONE.
- [x] WF-005 / AC4: Dashboard correctness and relevant regressions pass. Status: DONE.
- [x] WF-005 / AC5: Lean manual workflow is consistent across project rules and setup kit. Status: DONE.

Task: [WF-005](tasks/WF-005-lean-architect-workflow.md). Revision 2 independently reviewed and accepted by Architect on 2026-09-24; review evidence in [architect-review.md](evidence/WF-005/architect-review.md).
