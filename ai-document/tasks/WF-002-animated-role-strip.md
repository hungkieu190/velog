# WF-002: Animated role scenes in the progress dashboard

## Current handoff
- Status: DONE
- Plan revision: 1
- Architect session reference: Codex planning conversation of 2026-09-21
- Builder session reference: Antigravity Builder
- Implementation contributors and reviewer independence check: User accepted the Builder's work directly.
- Related checklist items: WF-002 / AC1–AC4 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d`.
- User approval reference and approved scope: User explicitly requested infinite animation and accepted the work ("ok done task này đi, k cần architect review nữa") on 2026-09-21.
- Latest round: Direct user acceptance — 2026-09-21.
- Evidence: [ai-document/evidence/WF-002/architect-round-1/](../evidence/WF-002/architect-round-1/).
- Next actor: Architect
- Next actor and exact next action: None. Task is DONE.

## Problem and intended behavior

Replace numbered owner labels in the dashboard with recognizable, responsive illustrated role scenes (Architect drawing, Builder mining, User checklist). Motion reflects recorded task state truthfully without claiming live telemetry.

## Scope and references

- Allowed files: `scripts/progress-view.mjs`, `src/css/progress.css`, `tests/workflow/progress-roles.test.mjs`.
- Preserved: CSP, read-only behavior, 20-second refresh, server endpoint, and existing data contracts. No browser libraries or runtime asset builds.
- Reference documentation: [architect-builder-workflow.md](../architect-builder-workflow.md), [build-and-release.md](../build-and-release.md).

## Acceptance criteria

- [x] WF-002 / AC1: Illustrated engineer/miner/user role strip and responsive layout. Status: DONE.
- [x] WF-002 / AC2: Status-accurate selected role and work animation. Status: DONE.
- [x] WF-002 / AC3: Reduced motion, accessibility, escaping and read-only behavior. Status: DONE.
- [x] WF-002 / AC4: Regression and browser evidence within the local-tool scope. Status: DONE.

## Findings and acceptance summary

- **State contract**: DRAFT animates drawing; IN_PROGRESS animates mining; queued, blocked, completed, and contradictory states remain static.
- **User acceptance (2026-09-21)**: User approved continuous loop animation and directly marked the task DONE without requiring Architect review.

## History and archives

- Full pre-lean task history, design specification, SVG implementation details, and verification matrix are archived verbatim at: [ai-document/history/WF-002/pre-lean.md](../history/WF-002/pre-lean.md) (SHA-256: `0bf819d5c377015733e8bfebc780c22f0c9a39eebfd9dd894def049d8a718fe7`).

### Chat handoff prompt

```text
Status: DONE
Recipient: None
Intent: closure

WF-002 is DONE per direct user acceptance on 2026-09-21. Animated role scenes (engineer drawing, miner swinging pickaxe, user reviewing) are implemented in scripts/progress-view.mjs and src/css/progress.css. All role tests pass. No further work required on WF-002.
```
