# Implementation checklist

## Current focus

- Task: WF-004
- Status: READY_FOR_REVIEW
- Next actor: Architect
- Exact next action: Eligible Architect (contributor self-review authorized) reviews Round 4 implementation against revision 4 blueprint. 60/60 workflow tests pass, source diff exits 0. R3–R7 and live checks remain NOT VERIFIED.
- Planning queue: WF-004 takes priority by user instruction on 2026-09-23; other implementation work follows afterward. CORE-001 and CORE-002 are DONE. CORE-003 is CHANGES_REQUESTED after Architect Round 4; WF-003 is DONE. G-02 is approved; G-03–G-08 remain pending.
- Documentation recovery: CORE-001 status was restored from retained Round 10 independent acceptance evidence after the user confirmed the apparent reset was not intentional. No historical evidence was rewritten and no new verification claim was added.

## Project status

Tooling bootstrap and master-plan revision 3 are accepted. CORE-001 and CORE-002 are DONE; CORE-003 is CHANGES_REQUESTED after Architect Round 4; WF-003 is DONE by the recorded separate bounded acceptance. Round 4 lint/PHPCS/PHPStan and PHPUnit passed (42 tests, 172 assertions, no skips); required fault/callback coverage and real integration/cleanup evidence remain insufficient. Checklist counts represent recorded work only, not overall product completion.

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

- [ ] CORE-003 / AC1: Explicit manager/admin/technician policy is enforced at object and primitive levels. Status: CHANGES_REQUESTED.
- [ ] CORE-003 / AC2: Anonymous, subscriber and unrelated editor cannot discover private records through public/native endpoints. Status: CHANGES_REQUESTED.
- [ ] CORE-003 / AC3: Activation/reactivation and role collisions preserve unrelated roles/capabilities. Status: CHANGES_REQUESTED.
- [ ] CORE-003 / AC4: Real WordPress permission/lifecycle evidence and runner failure/cleanup controls pass. Status: CHANGES_REQUESTED.

Task: [CORE-003](tasks/CORE-003-access-private-types.md). Prerequisites: CORE-001 DONE.

### DATA-001 — Versioned private storage and retained data

- [ ] DATA-001 / AC1: Typed versioned repository preserves canonical regional values and rejects forged/invalid payloads. Status: DRAFT.
- [ ] DATA-001 / AC2: Concurrent/stale/multi-record writes either commit coherently or fail without lost history/duplicates. Status: DRAFT.
- [ ] DATA-001 / AC3: Uninstall/reinstall retains records, configuration and semantic identity; no automatic purge. Status: DRAFT.
- [ ] DATA-001 / AC4: Metadata privacy, real storage failure/recovery and cache consistency verified. Status: DRAFT.

Task: [DATA-001](tasks/DATA-001-record-storage.md). Prerequisites: CORE-002 and CORE-003 DONE.

### CORE-004 — Explicit regional setup and admin shell

- [ ] CORE-004 / AC1: Manager explicitly configures units/currency; invalid/unconfigured values cannot silently default. Status: DRAFT.
- [ ] CORE-004 / AC2: Capability, nonce and stale-version checks protect writes. Status: DRAFT.
- [ ] CORE-004 / AC3: Preference/locale changes preserve historical value identity. Status: DRAFT.
- [ ] CORE-004 / AC4: Accessible translated/RTL admin UI and scoped generated assets pass verification. Status: DRAFT.

Task: [CORE-004](tasks/CORE-004-regional-settings.md). Prerequisites: CORE-002, CORE-003 and DATA-001 DONE.

### CUST-001 — Private customer management

- [ ] CUST-001 / AC1: Manager creates/edits/searches Unicode customer records with explicit validation and stable pagination. Status: DRAFT.
- [ ] CUST-001 / AC2: Customer contact data and every write are protected across list/detail/direct request paths. Status: DRAFT.
- [ ] CUST-001 / AC3: Archive/restore preserves records and rejects active-vehicle linkage; stale writes/bulk checks behave correctly. Status: DRAFT.
- [ ] CUST-001 / AC4: Real WordPress CRUD, negative controls and accessible admin journey have evidence. Status: DRAFT.

Task: [CUST-001](tasks/CUST-001-customer-records.md). Prerequisites: CORE-004 and DATA-001 DONE.

### VEH-001 — Vehicle identity and current customer

- [ ] VEH-001 / AC1: Plate-or-VIN registration validates international data and exact duplicate rules, including concurrent create. Status: DRAFT.
- [ ] VEH-001 / AC2: Owner relation/reassignment and archive/restore preserve links/history semantics. Status: DRAFT.
- [ ] VEH-001 / AC3: Unit-aware known/unknown baseline and dates are valid; no unreasoned lower-reading shortcut. Status: DRAFT.
- [ ] VEH-001 / AC4: Authorized search/detail/admin flows and negative security/accessibility checks pass. Status: DRAFT.

Task: [VEH-001](tasks/VEH-001-vehicle-records.md). Prerequisites: CUST-001 DONE (and its foundation dependencies).

### SERV-001 — Service drafts, finalization and corrections

- [ ] SERV-001 / AC1: Creator/manager draft and finalization rules enforce required relations, dates, reading and type. Status: DRAFT.
- [ ] SERV-001 / AC2: Optional costs and original units/customer/type snapshots preserve historical meaning. Status: DRAFT.
- [ ] SERV-001 / AC3: Corrections/decreases are manager-only, attributed/reasoned and cannot erase prior audit. Status: DRAFT.
- [ ] SERV-001 / AC4: Backdating/ties/concurrent transitions and failures preserve coherent current reading and service state. Status: DRAFT.

Task: [SERV-001](tasks/SERV-001-service-workflow.md). Prerequisites: VEH-001 DONE.

### HIST-001 — Private service history and correction visibility

- [ ] HIST-001 / AC1: Authorized vehicle timeline/filter/search uses deterministic newest-first pagination. Status: DRAFT.
- [ ] HIST-001 / AC2: Historical units/currencies/customer/type and visible corrections are accurate. Status: DRAFT.
- [ ] HIST-001 / AC3: Private/draft/contact boundaries hold for direct/query/detail paths without per-row lookup growth. Status: DRAFT.
- [ ] HIST-001 / AC4: Keyboard, long content, RTL and mobile retrieval journey is manually demonstrated. Status: DRAFT.

Task: [HIST-001](tasks/HIST-001-service-timeline.md). Prerequisites: SERV-001 DONE.

### REM-001 — Manual maintenance thresholds and queue

- [ ] REM-001 / AC1: Manual reminder validation requires valid vehicle/title and at least one trigger. Status: DRAFT.
- [ ] REM-001 / AC2: OR due, unknown/zero, cross-unit, local-date and snooze rules are exact and stable. Status: DRAFT.
- [ ] REM-001 / AC3: Manager transitions/archive effects are attributed, concurrent-safe and generate no notifications/services. Status: DRAFT.
- [ ] REM-001 / AC4: Due queue filtering/count/paging and accessible manager workflow pass real WordPress checks. Status: DRAFT.

Task: [REM-001](tasks/REM-001-maintenance-queue.md). Prerequisites: HIST-001 DONE.

### MVP-001 — MVP integration, performance and local package acceptance

- [ ] MVP-001 / AC1: Actual local ZIP contains complete runtime and installs/runs without development environment fallback. Status: DRAFT.
- [ ] MVP-001 / AC2: Complete international staff journey and negative authorization/retention cases pass across required runtime matrix. Status: DRAFT.
- [ ] MVP-001 / AC3: Agreed performance dataset/targets and query correctness are evidenced without hidden shortcuts. Status: DRAFT.
- [ ] MVP-001 / AC4: User manual acceptance and production limitations are explicitly recorded before final DONE decision. Status: DRAFT.

Task: [MVP-001](tasks/MVP-001-acceptance-package.md). Prerequisites: CORE-001/002/003/004, DATA-001, CUST-001, VEH-001, SERV-001, HIST-001 and REM-001 DONE.

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

## Priority tooling — WF-004

- [ ] WF-004 / AC1: Atomic final JSON is the sole dispatch trigger and invalid handoffs cannot start work. Status: CHANGES_REQUESTED.
- [ ] WF-004 / AC2: Serial dispatch, pinned sessions, replay/crash handling and owned cleanup pass. Status: CHANGES_REQUESTED.
- [ ] WF-004 / AC3: Real Codex adapter reads handoffs and resumes the intended session. Status: CHANGES_REQUESTED.
- [ ] WF-004 / AC4: Antigravity adapter and isolated bidirectional loop have real evidence. Status: CHANGES_REQUESTED.
- [ ] WF-004 / AC5: Progress integration, regressions, rules and setup-kit protocol are verified. Status: CHANGES_REQUESTED.

Task: [WF-004](tasks/WF-004-json-handoff-controller.md). CHANGES_REQUESTED after user-authorized contributor Review Round 3. Workflow 60/60 and nine validation controls pass; reproduced transport, cleanup, intake and dashboard defects require correction revision 4. WF4-I-005 is resolved; implementation findings remain open and no criteria accepted. Dispatch stays disabled.
