# PLAN-001: Define and approve the product master plan and MVP

## Current handoff
- Status: DONE
- Plan revision: 3
- Architect / Builder identity or session reference: Current session is Architect; no Builder assignment.
- Related checklist items: PLAN-001 / AC1–AC3.
- Baseline branch and commit; pre-existing relevant changes: Repository HEAD f04d4fc; preserve the user-approved uncommitted manual consolidation (AGENT.md deletion; AGENTS.md, CONTRIBUTING.md, rules/ai-agent.md and WF-001 documentation changes).
- User approval reference and approved scope: 2026-09-18 user approved the remaining MVP and authorized starting, amending units/region to international configuration instead of km/VND. See decisions.md revision 3 approval.
- Latest round: Final acceptance — Round 3
- Latest implementation/review round: Architect accepted planning AC1–AC3, not product implementation.
- Next actor: Builder
- Next actor and exact next action: Begin CORE-001 READY assignment; later P0 tasks require their own bounded Architect assignments.

## Problem and intended behavior
WF-001 completed development tooling, not product planning. Its previous next-action prompt skipped the missing master-plan prerequisite. The four feature documents remain drafts and do not define an approved, coherent delivery roadmap.

## Scope and references
Read AGENTS.md, ai-document/product-plan.md, ai-document/decisions.md, ai-document/architecture.md and ai-document/product-inputs/vehicle-passport.md, service-timeline.md, photo-checkin.md, maintenance-reminder.md. Planning documents only; no application code, build changes, commit or deployment.

## Implementation steps
1. Inventory implemented scaffolding, draft features and contradictory or unresolved business rules.
2. Propose users/roles, end-to-end journeys, data ownership/permissions, MVP inclusions/exclusions, feature dependencies and delivery milestones.
3. Define phase acceptance criteria and verification plans; record user decisions and explicit approval before drafting READY implementation assignments.

## Acceptance criteria
- AC1: One coherent proposed master plan covers product goals, users, journeys, data/permissions, MVP boundaries and explicit unresolved decisions.
- AC2: Delivery phases have dependencies, priorities, bounded deliverables and acceptance/verification criteria.
- AC3: User explicitly approves the master plan and MVP; approval reference and resolved/deferred decisions are recorded and checklist synchronized.

## Verification instructions
Compare the plan against repository facts and all four drafts. Review cross-feature consistency and trace each phase to product outcomes. User approval must be an actual recorded response; do not infer it from approval of tooling. Round 2 records the current proposal and verification state; no criterion is accepted until its required review/approval is recorded.

## History and archives

- Full task history, rounds 1–2 planning reports, and earlier prompts are archived verbatim at: [ai-document/history/PLAN-001/pre-lean.md](../history/PLAN-001/pre-lean.md) (SHA-256: `6557f201e630ba5811d19a87d4a2fc1b8aa72e9c9a014bad070cd6817a8b289a`).

## Planning summary — Rounds 1 & 2 (Architect, 2026-09-18)

- **Round 1**: Identified missing planning prerequisite and corrected focus.
- **Round 2**: Authored proposed master-plan revision 2 covering goals, actors, journeys, data/permissions, and P-001–P-006 pending decisions. PLAN-F-001–PLAN-F-005 identified.


## Final acceptance — Round 3 (Architect, 2026-09-18)

- Reviewed revision 2 against the user's international-market correction; authored revision 3 and internationalization.md. No approval of fixed km/VND remains current.
- Approval reference: user requested international markets/configurable units, then stated “còn lại okie, bạn có thể bắt đầu rồi”. This approves remaining product direction and starting the workflow.
- AC1 PASS: coherent goal, actors, journeys, data/permissions, international MVP boundary and future policy gates are documented.
- AC2 PASS: dependency-ordered P0–P6 roadmap and acceptance gates remain; P0 is explicitly split into bootstrap/i18n, regional primitives/settings, and permission/private-data foundations. CORE-001 is bounded and READY.
- AC3 PASS: explicit user approval and amendment recorded under P-001–P-006; checklist/index synchronized.
- PLAN-F-001/003 are resolved at planning level; PLAN-F-002/005 have approved direction and future task gates; PLAN-F-004 transfers to CORE-001. No implementation defect is claimed fixed by this acceptance.
- Accepted artifact: working-tree plan revision 3 at baseline f04d4fc, not a committed implementation. Product runtime, international behavior, performance and manual product acceptance remain NOT VERIFIED. Existing WF-001 evidence/history and manual-consolidation edits are preserved.
- Next: Builder executes CORE-001; Architect reviews its evidence independently and scopes regional foundation next.

### Chat handoff prompt

```text
Act as Builder for CORE-001, READY, revision 1. Read AGENTS.md, ai-document/tasks/CORE-001-bootstrap-i18n.md, ai-document/product-plan.md, ai-document/internationalization.md, ai-document/decisions.md, ai-document/architecture.md, ai-document/testing-strategy.md and rules/architecture.md, rules/security.md, rules/coding-style.md. The user approved the MVP with international configuration instead of fixed km/VND. Implement only CORE-001 AC1–AC4: execute the existing Loader from the real bootstrap exactly once and register translation loading at init, with regression tests and isolated WordPress evidence. Address PLAN-F-004; do not implement settings, roles, CPTs or product features. Static source inspection and Architect documentation checks passed. Runtime reproduction, PHP quality gates, PHPUnit and real WordPress translation checks are NOT VERIFIED for this task. Preserve all pre-existing documentation changes. Record baseline and change status to IN_PROGRESS, implement the bounded scope, then append evidence and a copy-ready Architect prompt and set READY_FOR_REVIEW. Do not mark DONE, check acceptance boxes, add dependencies, commit, deploy or change the active site database.
```
