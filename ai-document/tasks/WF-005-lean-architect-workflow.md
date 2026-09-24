# WF-005: Reduce Architect context and review overhead

## Current handoff
- Status: DONE
- Plan revision: 2
- Implementation round: 1
- Blueprint readiness: PASS
- Architect session reference: Codex desktop Architect session, 2026-09-24.
- Builder session reference: Antigravity Builder session cea4e738-ea00-4622-938c-b68f677c5217 (2026-09-24).
- Implementation contributors and reviewer independence check: Antigravity Builder implemented revision 2; Architect Codex must independently review diffs and evidence. Builder never self-accepts or checks criteria boxes.
- Related checklist items: WF-005 / AC1–AC5 in [implementation-checklist.md](../implementation-checklist.md).
- Baseline branch and commit: `main` at `2aa3b8d` plus working-tree tooling; preserves concurrent WF-004 removal changes.
- User approval reference and approved scope: On 2026-09-24 user approved implementation, explicitly expanded it to every existing and future task, and requested Builder handoff. Revision 2 supersedes the two-task pilot in revision 1.
- Latest round: Architect independent review — Round 1 (DONE).
- Latest report: [ai-document/evidence/WF-005/architect-review.md](../evidence/WF-005/architect-review.md)
- Evidence: [ai-document/evidence/WF-005/](../evidence/WF-005/)
- Next actor: Architect
- Next actor and exact next action: No further WF-005 action; project focus has moved to CORE-003 revision 6 for Builder.

## Outcome and baseline

Permanent project-wide lean workflow migration to dramatically reduce routine Architect reading and repeated administrative reviews without removing security, role boundaries, approval records, open findings, or independent verification.

- Baseline measurements (2026-09-24): `AGENTS.md` 18,708 characters; `architect-builder-workflow.md` 35,732; `implementation-checklist.md` 13,929; `WF-004` task 169,525; `CORE-003` task 75,758.
- Post-migration measurements: `AGENTS.md` 3,439 characters (target <=5,000); `architect-builder-workflow.md` 5,620 characters (target <=10,000); `README.md` 1,439 characters (target <=2,000); all 19 task files <=12,000 characters each.
- Full historical rounds and verbose blueprints for 14 tasks archived losslessly under `ai-document/history/<TASK-ID>/pre-lean.md` with SHA-256 hashes recorded in `archive-index.json`.

## Approved change map and implementation summary

1. **`AGENTS.md` (3,439 chars)**: Kept client/role resolution, Vietnamese user language, English technical content, approval boundaries, security essentials, and conditional reading map. Removed task history and duplicated policies.
2. **`ai-document/architect-builder-workflow.md` (5,620 chars)**: Consolidated lean manual pair-programming protocol, scalable planning and review rules, and standard statuses. Kept implementation/reviewer separation.
3. **Current task files (all <=12,000 chars)**: Compacted all 19 task files. Each task maintains approved scope, approval/contributor provenance, criteria checkboxes, history links, and at most ONE current prompt.
4. **`ai-document/README.md` (1,439 chars)**: Compact documentation index and navigation map.
5. **Rules and Setup-new templates**: Aligned `rules/ai-agent.md`, `set-up-new/01-architect-builder-workflow.md`, and `set-up-new/02-source-build-release-workflow.md` with the lean manual workflow.
6. **Dashboard compatibility**: Preserved full compatibility with `scripts/progress-data.mjs` and `scripts/progress-view.mjs`. 20/20 Node 24 workflow tests pass.

## Acceptance criteria

- [x] WF-005 / AC1: Compact startup instructions and workflow meet measured size targets. Status: DONE.
- [x] WF-005 / AC2: Current tasks are concise; historical evidence and authority are preserved. Status: DONE.
- [x] WF-005 / AC3: Bounded reading scenarios demonstrate measured reduction. Status: DONE.
- [x] WF-005 / AC4: Dashboard correctness and relevant regressions pass. Status: DONE.
- [x] WF-005 / AC5: Lean manual workflow is consistent across project rules and setup kit. Status: DONE.

## Verification summary

- **AC1 (Size targets)**: PASS. `AGENTS.md` 3,439 chars (target <=5,000); workflow 5,620 chars (target <=10,000); `README.md` 1,439 chars (target <=2,000).
- **AC2 (Task files & archives)**: PASS. All 19 tasks <=12,000 chars each; each task has at most 1 prompt. 14 tasks archived verbatim with matching SHA-256 in `ai-document/history/archive-index.json`.
- **AC3 (Reading scenarios)**: PASS. Startup reading reduced from 108,395 chars to 4,939 chars (95.44% reduction, exceeding >=70% target).
- **AC4 (Dashboard & regressions)**: PASS. Node 24 `npm run test:workflow` passed 20/20 tests. `git diff --check` passed exit 0. Dashboard parser `readProgress('.')` reports focus WF-005 with zero task-specific issues.
- **AC5 (Consistency)**: PASS. No automated dispatch, JSON signals, or mandatory full-history reading in active rules or setup templates.

## History and archives

- Full pre-lean task specification and blueprints are archived verbatim at: [ai-document/history/WF-005/pre-lean.md](../history/WF-005/pre-lean.md) (SHA-256: `26ce65456165451ecd3bef87dfac59f0bf9c4c11b4347add9cea4cc1966a9f97`).

### Chat handoff prompt

```text
Status: DONE
Recipient: Architect
Intent: review

WF-005 is DONE after independent Architect review. Resume CORE-003 revision 6 from ai-document/tasks/CORE-003-access-private-types.md. Address F-002–F-006 and return a READY_FOR_REVIEW report with raw evidence.
```
