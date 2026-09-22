# TOOL-001: Optional regression harness hardening

## Current handoff
- Status: DRAFT
- Plan revision: 1
- Architect session reference: existing Codex Architect conversation, 2026-09-22 scope reset.
- Builder session reference: unassigned; implementation contributors: none for this task.
- Related checklist items: TOOL-001 / AC1–AC2.
- User approval: user approved separating additional tooling improvements from CORE-001 closeout. Implementation is NOT authorized or dispatched.
- Latest round: Initial deferred backlog record.
- Next actor: Architect
- Next actor and exact next action: Reconsider only when a concrete maintenance need justifies the cost; specify a bounded blueprint and obtain implementation approval before READY. This task does not block CORE-001 or the product queue.

## Purpose and scope boundary

CORE-001 revision 2 owns current resource safety and truthful success gates B1/B2. This task is only for additional confidence/maintainability beyond those gates: broader supervisor fault permutations, optional signal/retry/concurrency coverage, more structured evidence collection and portability improvements if justified. Do not transfer unresolved B1/B2 defects here or describe them as accepted.

## Proposed scope, not an assignment

Potential files: tests/workflow/core001-smoke-controls-regression.sh and a separately justified verification helper. No runtime PHP, product feature, dependencies or active database work. Reinspect accepted sources before design. Prefer a small fixture over a generic test framework; estimate benefit and maintenance burden before approving any item.

## Implementation blueprint

Blueprint readiness: INCOMPLETE, intentionally undispatched. Exact change map, ordered implementation, critical-path pseudocode, cleanup/failure controls and verification commands will be supplied only when specific work is selected. No Builder execution is permitted from this backlog note.

## Acceptance criteria

- AC1: Architect selects a concrete tooling need and documents bounded scope, expected benefit and verification budget; user approves implementation.
- AC2: Selected improvement has independent evidence and preserves accepted CORE-001 behavior without introducing unsafe cleanup or false results.

## Verification and documentation

No tests run for this draft and no implementation result claimed. Future verification matrix must cover only selected behavior with exact expected exits and owned-resource cleanup. Evidence and reports belong in this task; update the authoritative workflow only if a separately approved workflow change is needed. Do not repeat unchanged product/runtime gates merely for documentation-only changes.

### Chat handoff prompt

```text
Architect backlog only: read ai-document/tasks/TOOL-001-regression-hardening.md. Status DRAFT, no Builder assignment. Revisit only for a concrete justified tooling need; design a bounded blueprint and obtain approval before READY. CORE-001 owns its two blocking gates and this draft must not delay product work.
```
