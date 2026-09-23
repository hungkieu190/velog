# VeLog project workflow

Project current focus: [CORE-003](tasks/CORE-003-access-private-types.md), CHANGES_REQUESTED after independent Architect Round 1 review on 2026-09-23. Builder (Antigravity) implements the approved [WF-003 dashboard correction](tasks/WF-003-progress-owner-consistency.md), READY revision 1, first; then fixes CORE-003 F-001–F-006 using Correction blueprint Round 2 revision 3. Required PHP gates currently fail; reported smoke evidence is insufficient. CORE-001 and CORE-002 retain their historical DONE status. PHP 8.1 remains NOT VERIFIED. Other product implementation tasks remain DRAFT.

Read [product plan](product-plan.md), [requirements](requirements.md), [decisions](decisions.md), [architecture](architecture.md), [checklist](implementation-checklist.md), [testing strategy](testing-strategy.md), [release readiness](release-readiness.md), and [build and release](build-and-release.md).

The copied [Architect / Builder specification](architect-builder-workflow.md) and [source / build / release specification](source-build-release-workflow.md) are the requested workflow references. Their examples are requirements to adapt, not proof of implementation or permission to publish.

The checklist owns project progress. Task files own approval, implementation reports, findings, evidence, and handoffs. Existing ai-document/product-inputs/ documents remain product inputs; they are not approved implementation tasks. Technical documentation is maintained here; rules/ remains the coding-constraint reference. See the documentation ownership policy below.

## Documentation ownership

This directory owns maintained technical documentation and workflow records. Use architecture.md for architecture, decisions.md for decisions, product-plan.md for the approved roadmap, and task files for assignments/review evidence. Specialist feature/API/database/UI documentation is added here only when needed. Link to the authoritative document instead of maintaining duplicate accounts. Each task declares documentation updates or a justified N/A. The empty legacy docs/ scaffolding was removed by user approval; product-inputs/ retains draft inputs.

## Application role assignment

Read [agent-roles.json](agent-roles.json) through the startup instructions in root AGENTS.md. Codex is Architect; Antigravity is Builder. Both read the same static mapping and announce their assigned role at session start without writing a shared current-role value. Antigravity has a workspace adapter at .agents/rules/project-role.md; automatic loading in a fresh Antigravity session is NOT VERIFIED. No global IDE/user configuration was changed.
