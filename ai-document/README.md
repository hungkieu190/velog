# VeLog project workflow

Project status: WF-001 tooling and PLAN-001 master-plan revision 3 are DONE. The user approved the international MVP with configurable units/currency. Current focus: [CORE-001](tasks/CORE-001-bootstrap-i18n.md), CHANGES_REQUESTED after Architect review Round 4; F-001/F-002 are closed, Builder must resolve F-003/F-004. Product runtime is not yet accepted. See [the international product contract](internationalization.md); regional implementation follows bootstrap/i18n in a separate task.

Read [product plan](product-plan.md), [requirements](requirements.md), [decisions](decisions.md), [architecture](architecture.md), [checklist](implementation-checklist.md), [testing strategy](testing-strategy.md), [release readiness](release-readiness.md), and [build and release](build-and-release.md).

The copied [Architect / Builder specification](architect-builder-workflow.md) and [source / build / release specification](source-build-release-workflow.md) are the requested workflow references. Their examples are requirements to adapt, not proof of implementation or permission to publish.

The checklist owns project progress. Task files own approval, implementation reports, findings, evidence, and handoffs. Existing ai-document/product-inputs/ documents remain product inputs; they are not approved implementation tasks. Technical documentation is maintained here; rules/ remains the coding-constraint reference. See the documentation ownership policy below.

## Documentation ownership

This directory owns maintained technical documentation and workflow records. Use architecture.md for architecture, decisions.md for decisions, product-plan.md for the approved roadmap, and task files for assignments/review evidence. Specialist feature/API/database/UI documentation is added here only when needed. Link to the authoritative document instead of maintaining duplicate accounts. Each task declares documentation updates or a justified N/A. The empty legacy docs/ scaffolding was removed by user approval; product-inputs/ retains draft inputs.

## Application role assignment

Read [agent-roles.json](agent-roles.json) through the startup instructions in root AGENTS.md. Codex is Architect; Antigravity is Builder. Both read the same static mapping and announce their assigned role at session start without writing a shared current-role value. Antigravity has a workspace adapter at .agents/rules/project-role.md; automatic loading in a fresh Antigravity session is NOT VERIFIED. No global IDE/user configuration was changed.
