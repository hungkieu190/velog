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
- Latest report: This task.
- Evidence: Per feature task.
- Temporary resource cleanup: Per feature task.
- Next actor: Backend Architect
- Next actor and exact next action: Apply the split beginning with CORE-004 backend completion and CORE-004-FE frontend delivery, then split later mixed feature tasks before implementation.

## Delivery plan

Backend Architect creates and owns backend tasks and creates bounded frontend tasks after the backend/view contract is stable. Every major feature also receives a Vietnamese `<ID>-UAT` manual test task owned by Tester. Existing task IDs may remain the parent authority when renaming would break evidence links; new mixed features use `<ID>-BE`, `<ID>-FE`, and `<ID>-UAT`.

| Feature | Backend ownership | Frontend ownership | Order |
|---|---|---|---|
| CORE-004 | Settings persistence, authorization, handlers, menu/enqueue contract, server tests | Regional settings styling and browser presentation evidence | Backend contract first; FE task is READY |
| CUST-001 | Customer model, authorization, validation, storage, server tests | Customer admin screens and interaction | BE then FE |
| VEH-001 | Vehicle identity, ownership rules, storage, server tests | Vehicle admin screens and interaction | BE then FE |
| SERV-001 | Draft/finalization/correction domain and persistence | Service workflow screens | BE then FE |
| HIST-001 | Timeline queries, privacy, pagination, API/view contract | Timeline presentation and filters | BE then FE |
| REM-001 | Threshold rules, state transitions, queue query | Reminder queue presentation | BE then FE |
| MVP-001 | Integration, packaging, performance, release evidence | End-to-end presentation fixes and browser evidence | Stable feature contracts first |

## Rules

- Backend Architect owns all PHP and backend logic and self-reviews it as `SELF_REVIEWED_BACKEND`.
- Frontend Developer owns only explicit presentation files and never self-accepts.
- Backend Architect reviews all frontend work and either approves it or records stable findings.
- Backend Architect review covers code and technical evidence only; it cannot provide functional acceptance.
- Tester performs the Vietnamese UAT task through the real product UI. Every major parent task requires explicit UAT PASS before DONE.
- Manual FAIL is triaged by Backend Architect and returned to the affected BE or FE task as CHANGES_REQUESTED.
- `Next actor` in each task is authoritative; status never assigns a role by itself.

### Chat handoff prompt

```text
Status: IN_PROGRESS
Recipient: Backend Architect
Intent: work

Apply PLAN-003 with three workstreams for every major feature: Backend Architect implements and self-reviews `<ID>-BE`; Frontend Developer implements `<ID>-FE` and Backend Architect reviews its code; Tester executes the Vietnamese `<ID>-UAT` task and alone provides functional acceptance. Never mark a major parent task DONE without explicit UAT PASS.
```
