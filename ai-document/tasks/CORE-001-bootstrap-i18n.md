# CORE-001: Wire bootstrap hooks and translation lifecycle

## Current handoff
- Status: DONE
- Plan revision: 2 (accepted bounded closeout)
- Architect session reference: Round 10 independent review
- Builder session reference: Round 7 Antigravity Builder
- Implementation contributors and reviewer independence check: Independent Architect acceptance (Round 10); Round 7 implementer Antigravity Builder; prior contributor 866ba911-4817-495a-90db-c2e198e686cc.
- Related checklist items: CORE-001 / AC1–AC4; PLAN-F-004.
- Baseline branch and commit: branch `main`, HEAD `f04d4fc5dbb103423d81f324701611bcefd871d8`.
- User approval reference and approved scope: 2026-09-18 user approved MVP; accepted in Round 10.
- Latest round: Architect independent acceptance — Round 10, revision 2.
- Latest report: ai-document/evidence/CORE-001/architect-round-10/review.md
- Evidence: ai-document/evidence/CORE-001/architect-round-10/
- Next actor: Architect
- Next actor and exact next action: Use the accepted CORE-001 interfaces and evidence as the prerequisite for CORE-003; do not reopen historical findings without new contradictory evidence.

## Problem and intended behavior

velog.php calls mf_velog() at plugins_loaded but previously never ran the Loader. The plugin must register its queued callbacks once on the normal entry path, before init, and configure translation loading at init. Repeated public run() calls must not duplicate callback registration.

## Scope and references

- Allowed files: `velog.php`, `src/Core/Plugin.php`, `src/Core/Loader.php`, unit/integration tests under `tests/`.
- Excluded: regional settings/conversion implementation, roles/capabilities, CPTs, REST routes, product UI/data.
- Reference documentation: [architecture.md](../architecture.md), [decisions.md](../decisions.md), [product-plan.md](../product-plan.md).

## Acceptance criteria

- [x] CORE-001 / AC1: Normal velog.php entry path instantiates the plugin singleton and registers callbacks before init; repeated run() calls do not duplicate registration. Status: DONE.
- [x] CORE-001 / AC2: Translation loading registered at init with velog domain and plugin-relative languages directory; non-English fixture translates cleanly. Status: DONE.
- [x] CORE-001 / AC3: Public API, activation/deactivation hooks intact; composer run lint and composer run test pass (21 tests, 35 assertions). Status: DONE.
- [x] CORE-001 / AC4: Isolated WordPress evidence covers plugin loading and translation; cleanup verified. Status: DONE.

## Findings and acceptance summary

- **F-001–F-005**: All CLOSED in Round 10 revision 2 bounded closeout.
- **Acceptance**: Accepted by independent Architect in Round 10 (2026-09-22). Retained review under [ai-document/evidence/CORE-001/architect-round-10/review.md](../evidence/CORE-001/architect-round-10/review.md).

## History and archives

- Full pre-lean task history and earlier implementation/review rounds are archived verbatim at: [ai-document/history/CORE-001/pre-lean.md](../history/CORE-001/pre-lean.md).

### Chat handoff prompt

```text
Status: DONE
Recipient: Architect
Intent: review

Continue as Architect for CORE-003 after accepted prerequisite CORE-001. Read AGENTS.md, ai-document/evidence/CORE-001/architect-round-10/review.md and ai-document/tasks/CORE-003-access-private-types.md. CORE-001 is DONE under its Round 10 revision-2 bounded closeout; this recovery note adds no new runtime claim. Prepare or review CORE-003 according to its current handoff without modifying accepted CORE-001 runtime or historical evidence.
```
