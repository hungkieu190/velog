# Implementation checklist

## Current focus

- Task: CORE-001
- Status: CHANGES_REQUESTED
- Next actor: Builder
- Exact next action: Builder completes CORE-001-F-003 shared warning detector and CORE-001-F-004 atomic directory/bounded startup; return Round 5 evidence.

## Project status

Tooling bootstrap and master-plan revision 3 are accepted. The user approved an international MVP; product implementation begins with CORE-001 and is not accepted yet. Checklist counts represent recorded work only, not overall product completion.

## Accepted product master plan and MVP

- [x] PLAN-001 / AC1: Consolidate product goals, users, journeys, data/permissions and MVP boundaries. Status: DONE.
- [x] PLAN-001 / AC2: Define dependency-ordered delivery phases and acceptance criteria. Status: DONE.
- [x] PLAN-001 / AC3: Record explicit user approval of master plan/MVP before feature implementation. Status: DONE.

Task: [PLAN-001](tasks/PLAN-001-master-plan.md).

## Product P0 — Foundation

- [ ] CORE-001 / AC1: Real bootstrap runs Loader once. Status: READY.
- [ ] CORE-001 / AC2: Correct translation lifecycle and fixture translation. Status: READY.
- [ ] CORE-001 / AC3: Compatibility and PHP quality/test gates. Status: READY.
- [ ] CORE-001 / AC4: Isolated WordPress evidence and independent review. Status: READY.

Task: [CORE-001](tasks/CORE-001-bootstrap-i18n.md). Next planned work: regional settings/primitives per internationalization.md, then capability/private-data foundations. These later assignments are not READY yet.

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
