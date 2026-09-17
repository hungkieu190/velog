# Implementation checklist

## Current focus

- Task: PLAN-001
- Status: DRAFT
- Next actor: Architect
- Exact next action: Architect proposes the product master plan and MVP, resolves product decisions with the user and records approval before feature implementation.

## Project status

Tooling bootstrap is complete. Product planning is incomplete: there is no approved master plan, MVP or feature implementation roadmap. Checklist counts represent recorded work only, not overall product completion.

## Next phase — Product master plan and MVP

- [ ] PLAN-001 / AC1: Consolidate product goals, users, journeys, data/permissions and MVP boundaries. Status: DRAFT.
- [ ] PLAN-001 / AC2: Define dependency-ordered delivery phases and acceptance criteria. Status: DRAFT.
- [ ] PLAN-001 / AC3: Record explicit user approval of master plan/MVP before feature implementation. Status: DRAFT.

Task: [PLAN-001](tasks/PLAN-001-master-plan.md).

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

Task: [WF-001](tasks/WF-001-workflow-bootstrap.md). No product feature is accepted by this checklist. Complete PLAN-001 master-plan/MVP approval before preparing READY feature implementation tasks.
