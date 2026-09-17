# PLAN-001: Define and approve the product master plan and MVP

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect / Builder identity or session reference: Current session is Architect; no Builder assignment.
- Related checklist items: PLAN-001 / AC1–AC3.
- Baseline branch and commit; pre-existing relevant changes: Repository HEAD b0cddd6; preserve completed WF-001 and existing product drafts.
- User approval reference and approved scope: User requested correction of project status and the missing master-plan next action. This records the planning prerequisite; no product decisions or implementation scope are approved.
- Latest round: Planning prerequisite recorded
- Latest implementation/review round: No master-plan review yet.
- Next actor: Architect
- Next actor and exact next action: Consolidate the existing drafts into a proposed master plan and MVP, identify unresolved decisions, then obtain user approval before any feature implementation task becomes READY.

## Problem and intended behavior
WF-001 completed development tooling, not product planning. Its previous next-action prompt skipped the missing master-plan prerequisite. The four feature documents remain drafts and do not define an approved, coherent delivery roadmap.

## Scope and references
Read AGENTS.md, ai-document/product-plan.md, ai-document/decisions.md, ai-document/architecture.md and plans/current/vehicle-passport.md, service-timeline.md, photo-checkin.md, maintenance-reminder.md. Planning documents only; no application code, build changes, commit or deployment.

## Implementation steps
1. Inventory implemented scaffolding, draft features and contradictory or unresolved business rules.
2. Propose users/roles, end-to-end journeys, data ownership/permissions, MVP inclusions/exclusions, feature dependencies and delivery milestones.
3. Define phase acceptance criteria and verification plans; record user decisions and explicit approval before drafting READY implementation assignments.

## Acceptance criteria
- AC1: One coherent proposed master plan covers product goals, users, journeys, data/permissions, MVP boundaries and explicit unresolved decisions.
- AC2: Delivery phases have dependencies, priorities, bounded deliverables and acceptance/verification criteria.
- AC3: User explicitly approves the master plan and MVP; approval reference and resolved/deferred decisions are recorded and checklist synchronized.

## Verification instructions
Compare the plan against repository facts and all four drafts. Review cross-feature consistency and trace each phase to product outcomes. User approval must be an actual recorded response; do not infer it from approval of tooling. All three criteria are currently NOT VERIFIED.

## Planning report — Round 1 (Architect)
Recorded the missing planning prerequisite and corrected project focus. A completed master plan has not been authored or approved. WF-001 acceptance and historical test evidence remain unchanged.

### Chat handoff prompt

```text
Act as Architect. Read AGENTS.md, ai-document/product-plan.md, ai-document/implementation-checklist.md, ai-document/tasks/PLAN-001-master-plan.md and the four feature drafts under plans/current/. WF-001 is DONE for tooling only; PLAN-001 is DRAFT and the product has no approved master plan or MVP. Next: consolidate the drafts into a proposed master plan covering users, end-to-end flows, data/permissions, MVP boundaries, dependencies, milestones and acceptance criteria. List unresolved product decisions for the user and obtain explicit approval before creating READY feature implementation tasks. Prior tooling checks passed; product behavior and master-plan acceptance are NOT VERIFIED. Do not treat bootstrap completion as product completion, invent approved business rules, implement features, commit or deploy.
```
