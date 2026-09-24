# VeLog project workflow

Project current focus: [WF-004](tasks/WF-004-json-handoff-controller.md), READY_FOR_REVIEW. Round 3 implementation was completed by Antigravity Builder. The tests for the controller and agent adapters have successfully passed. Awaiting Architect review. CORE-003 remains deferred.

## Current focus

- Task: WF-004
- Status: READY_FOR_REVIEW
- Next actor: Architect
- Exact next action: Review Round 3 implementation for acceptance.
- Handoff: Manual prompt published; no JSON receipt; dispatch remains disabled pending review.

## Deferred work

Deferred product work: [CORE-003](tasks/CORE-003-access-private-types.md), CHANGES_REQUESTED after Architect Round 4 review on 2026-09-23. F-001 is closed; Builder (Antigravity) must fix F-002–F-006 under correction revision 6. [WF-003](tasks/WF-003-progress-owner-consistency.md) is DONE under the separate bounded acceptance recorded in that review and is excluded from these corrections. Round 4 independent lint/PHPCS/PHPStan and PHPUnit passed (42 tests, 172 assertions, no skips); required fault/callback coverage and real integration/cleanup evidence remain insufficient. Pinned WordPress authorization/lifecycle/cleanup and PHP 8.1 remain NOT VERIFIED independently. CORE-001/002 retain historical DONE status; other product tasks remain DRAFT. Current metadata was synchronized from the retained decision; no new runtime verification is claimed.

Read [product plan](product-plan.md), [requirements](requirements.md), [decisions](decisions.md), [architecture](architecture.md), [checklist](implementation-checklist.md), [testing strategy](testing-strategy.md), [release readiness](release-readiness.md), and [build and release](build-and-release.md).

The copied [Architect / Builder specification](architect-builder-workflow.md) and [source / build / release specification](source-build-release-workflow.md) are the requested workflow references. Their examples are requirements to adapt, not proof of implementation or permission to publish.

The checklist owns project progress. Task files own approval, implementation reports, findings, evidence, and handoffs. Existing ai-document/product-inputs/ documents remain product inputs; they are not approved implementation tasks. Technical documentation is maintained here; rules/ remains the coding-constraint reference. See the documentation ownership policy below.

## Documentation ownership

This directory owns maintained technical documentation and workflow records. Use architecture.md for architecture, decisions.md for decisions, product-plan.md for the approved roadmap, and task files for assignments/review evidence. Specialist feature/API/database/UI documentation is added here only when needed. Link to the authoritative document instead of maintaining duplicate accounts. Each task declares documentation updates or a justified N/A. The empty legacy docs/ scaffolding was removed by user approval; product-inputs/ retains draft inputs.

## Application role assignment

Read [agent-roles.json](agent-roles.json) through the startup instructions in root AGENTS.md. Codex is Architect; Antigravity is Builder. Both read the same static mapping and announce their assigned role at session start without writing a shared current-role value. Antigravity has a workspace adapter at .agents/rules/project-role.md; automatic loading in a fresh Antigravity session is NOT VERIFIED. No global IDE/user configuration was changed.
