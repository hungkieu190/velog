# CORE-004-FE: Regional settings presentation verification

## Current handoff
- Workstream: Frontend
- Status: DONE
- Plan revision: 1
- Implementation round: 3
- Blueprint readiness: PASS
- Owner: Frontend Developer
- Contributors and review mode: Frontend Developer implemented; Backend Architect review required.
- Related checklist items: CORE-004 / AC4.
- Latest report: `ai-document/evidence/CORE-004-FE/manual-acceptance-2026-10-06/review.md`.
- Evidence: `ai-document/evidence/CORE-004-FE/frontend-round-3/`.
- Temporary resource cleanup: Completed.
- Next actor: None
- Next actor and exact next action: None; CORE-004-FE is closed by direct Product Owner manual acceptance.

## Problem and intended behavior

The backend regional settings contract exists, but the presentation needs a separately owned frontend pass for responsive, RTL, keyboard, focus, long-content, and exact-page asset behavior.

## Approved scope and boundaries

Allowed source and output files:

- `src/css/admin.scss`
- `src/js/admin.js` only when a demonstrated presentation interaction requires it
- `assets/css/admin.css`
- `assets/js/admin.js` only when its source changes
- this task and its evidence/walkthrough files

Do not edit PHP, WordPress hooks, capabilities, nonces, validation, storage, SQL, service classes, lifecycle logic, backend fixtures, or server-side tests. Report a missing backend contract to Backend Architect.

## Verification matrix

| Case | Expected result |
|---|---|
| Exact VeLog settings page | Generated admin asset loads; no source asset path is used |
| Unrelated WordPress admin page | No VeLog admin CSS or JS loads |
| Keyboard and focus | All controls and submit flow are operable in logical order with visible focus |
| 320px and long translated content | No horizontal page overflow or clipped control/label text |
| RTL locale | Direction, spacing, labels, notices, and controls remain usable |
| Production build | `npm run production` exits 0 and generated output matches source |

## Acceptance criteria

- Presentation behavior is verified in real WordPress with retained observations or screenshots.
- Source and generated assets are synchronized.
- No backend boundary is crossed.
- Frontend Developer records `READY_FOR_REVIEW`; Backend Architect reruns relevant gates and decides approval.

## Architect review — 2026-10-06

Decision: `CHANGES_REQUESTED`. See `ai-document/evidence/CORE-004-FE/architect-review-1/review.md`. The submitted CSS is within the presentation boundary, but `git diff --check` fails and the claimed browser checks have no retained evidence for this revision. All acceptance criteria remain unchecked.

## Architect review — Round 2, 2026-10-06

Decision: `CHANGES_REQUESTED`. Whitespace, build, source/generated synchronization, focus, 320px stacking, scope, and cleanup pass. The RTL test only injected a CSS class instead of activating an RTL WordPress locale, and the narrow-layout evidence did not contain long translated content. See `ai-document/evidence/CORE-004-FE/architect-review-2/review.md`.

## Product Owner manual acceptance — 2026-10-06

Decision: `DONE` by direct Product Owner acceptance after manual verification. The Backend Architect observed that the retained Round 3 screenshots showed the corrupted-settings state and therefore did not independently demonstrate the rendered form's 320px wrapping or RTL control usability. The Product Owner explicitly confirmed manual testing passed and directed the task to be marked done. See `ai-document/evidence/CORE-004-FE/manual-acceptance-2026-10-06/review.md`.

### Chat handoff prompt

```text
Status: DONE
Recipient: Backend Architect
Intent: plan

CORE-004-FE and parent CORE-004 are DONE by direct Product Owner manual acceptance on 2026-10-06. Preserve the recorded limitation that the retained Round 3 screenshots show the corrupted-settings state and do not independently demonstrate the rendered form's 320px wrapping or RTL controls; the Product Owner manually verified the behavior and explicitly accepted it. Continue with CUST-001 readiness planning and resolve its remaining product gates before implementation.
```
