# PLAN-003: Backend, frontend, and Tester delivery split

## Current handoff
- Workstream: Backend
- Status: IN_PROGRESS
- Plan revision: 1
- Implementation round: 0
- Blueprint readiness: PASS
- Owner: Backend Architect
- Contributors and review mode: Backend Architect planning; `SELF_REVIEWED_BACKEND` for backend tasks; Backend Architect code review for frontend tasks; Tester manual acceptance for every major feature.
- Related checklist items: Product implementation queue.
- Latest report: Product Owner approved backend-first sequencing on 2026-10-08: backend workstreams proceed in dependency order; frontend workstreams are intentionally deferred until the backend sequence is stable.
- Evidence: Per feature task.
- Temporary resource cleanup: Per feature task.
- Next actor: Backend Architect
- Next actor and exact next action: Define and execute the next eligible backend workstream; keep frontend workstreams deferred without treating them as accepted or complete.

## Delivery plan

Backend Architect creates and owns backend tasks and creates bounded frontend tasks after the backend/view contract is stable. Every major feature also receives a Vietnamese `<ID>-UAT` manual test task owned by Tester. Existing task IDs may remain the parent authority when renaming would break evidence links; new mixed features use `<ID>-BE`, `<ID>-FE`, and `<ID>-UAT`.

| Feature | Backend ownership | Frontend ownership | Order |
|---|---|---|---|
| CORE-004 | Settings persistence, authorization, handlers, menu/enqueue contract, server tests | Regional settings styling and build results | Backend contract first; FE task is READY |
| CUST-001 | Customer model, authorization, validation, storage, server tests | Customer admin screens and interaction | CUST-001-BE complete; FE reactivated by Product Owner on 2026-10-08 |
| VEH-001 | Vehicle identity, ownership rules, storage, server tests | Vehicle admin screens and interaction | Begin BE when CUST-001-BE and vehicle gates are complete; FE deferred |
| SERV-001 | Draft/finalization/correction domain and persistence | Service workflow screens | Begin BE when VEH-001-BE and service gates are complete; FE deferred |
| HIST-001 | Timeline queries, privacy, pagination, API/view contract | Timeline presentation and filters | Begin BE when SERV-001-BE and history gates are complete; FE deferred |
| REM-001 | Threshold rules, state transitions, queue query | Reminder queue presentation | Begin BE when HIST-001-BE and reminder gates are complete; FE deferred |
| MVP-001 | Integration, packaging, performance, release evidence | End-to-end presentation fixes and build results | Stable feature contracts first |

## Rules

## Owner decision — 2026-10-08: Tester owns browser verification

This decision supersedes conflicting frontend verification requirements in earlier task descriptions, review requests, and setup guidance. Historical evidence remains unchanged.

- Frontend Developer implements assigned presentation source, builds generated assets, records build/diff results, and hands off code. It is not required to run browser/manual verification, capture screenshots, or provide visual evidence.
- Backend Architect reviews source/generated diffs and architecture/security boundaries, verifies relevant build/static checks, and creates a concrete Vietnamese `<ID>-UAT` task. It must specify environment, accounts, data, numbered actions, expected results, negative cases, and a per-case result table. Missing browser screenshots are not grounds to reject a frontend code handoff.
- Tester / Product Owner owns all manual UI, responsive, RTL, keyboard, screen-reader, error-state, pagination, and asset-loading verification. Screenshots/videos are optional supporting material, not a mandatory builder deliverable.
- Code approval moves the FE task, UAT task, and parent feature to `AWAITING_MANUAL_ACCEPTANCE`, with `Next actor: Tester`. `Review decision: APPROVED` means code review only; it does not mean a browser case passed.
- Tester records actual results as PASS, FAIL, or BLOCKED and alone decides the transition to DONE. Agents may synchronize DONE only after the user's explicit acceptance; they never infer it from build success or elapsed time. Partial/blocked results are not full acceptance. Backend workstream DONE remains a technical `SELF_REVIEWED_BACKEND` checkpoint, not acceptance of the feature.
- On FAIL, Backend Architect triages the reported case and assigns correction to the appropriate BE/FE owner. After code review, Tester reruns affected cases and relevant regressions before deciding DONE.
- Test data/environment preparation must be explicit. If a prerequisite is unavailable, record BLOCKED and return preparation to Backend Architect; do not silently skip it or require Frontend Developer to create backend fixtures.


- Backend Architect owns all PHP and backend logic and self-reviews it as `SELF_REVIEWED_BACKEND`.
- Frontend Developer owns only explicit presentation files and never self-accepts.
- Backend Architect reviews all frontend work and either approves it or records stable findings.
- Backend Architect review covers code and technical evidence only; it cannot provide functional acceptance.
- Tester performs the Vietnamese UAT task through the real product UI. Every major parent task requires explicit UAT PASS before DONE.
- Manual FAIL is triaged by Backend Architect and returned to the affected BE or FE task as CHANGES_REQUESTED.
- `Next actor` in each task is authoritative; status never assigns a role by itself.
- **Backend-first sequencing (approved 2026-10-08):** A downstream backend workstream may start when its required upstream backend contract is `DONE` with `SELF_REVIEWED_BACKEND`, even when the upstream feature's FE/UAT work remains deferred. This never makes the parent feature DONE, weakens capability/nonce/validation/test gates, or replaces frontend review and Tester UAT before final acceptance.

### Chat handoff prompt

```text
Status: IN_PROGRESS
Recipient: Backend Architect
Intent: work

Apply the approved backend-first PLAN-003 sequence: Backend Architect implements and self-reviews each eligible `<ID>-BE` in dependency order, while `<ID>-FE` workstreams remain intentionally deferred until the backend sequence is stable. Frontend Developer still implements each `<ID>-FE` and Backend Architect reviews its code before Tester executes the Vietnamese `<ID>-UAT` task. Never mark a major parent task DONE without explicit UAT PASS.
```
