# CUST-002-FE: Native customer administration presentation

> RETIRED BY G-10 (2026-10-15). The specifications and old approvals below are historical; they do not authorize native-user implementation. BLOCKED is used because the workflow has no cancelled status. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md.

## Current handoff

- Workstream: Frontend
- Status: BLOCKED
- Plan revision: 1
- Blueprint readiness: INCOMPLETE — G-10 retires this native-user direction; no further implementation or UAT.
- Owner: Frontend Developer
- Contributors and review mode: Not assigned for implementation; Backend Architect code review required later.
- Related checklist items: CUST-002 / AC4.
- Latest report: Native-user direction withdrawn by the Product Owner on 2026-10-15. Unaccepted runtime/test additions were removed after backup; earlier evidence is historical, not feature acceptance.
- Evidence: `ai-document/tasks/CUST-002-BE-native-customer-backend.md`; no build or browser results.
- Temporary resource cleanup: No temporary resources created.
- Next actor: Backend Architect
- Next actor and exact next action: Do not resume CUST-002. Follow CUST-001 restoration under G-10; this document is historical only.

## Proposed presentation scope

Style the native customer list/form, account state, invitation outcome, migration dry-run/conflict report, pagination, bulk per-item results, and accessible validation notices supplied by backend PHP. Clearly distinguish invitation accepted for sending from delivery and account activation. Do not display passwords, reset links/keys, raw database errors, or unauthorized contacts.

Candidate source scope is customer-specific selectors in `src/css/admin.scss`, necessary reviewed partials, and generated `assets/css/admin.css`. JavaScript is excluded unless a concrete interaction requirement is approved. Freeze exact selectors, files and view states after CUST-002-BE. Do not change PHP, authorization, validation, account state, email, migration, data storage or tests for backend behavior.

Required presentation states: pending/invited/active/archived; empty list, long Unicode content, search/pagination, invalid fields, stale record, duplicate identity, invitation failure, migration conflict, disabled actions with explanation, success notices and partial bulk failure. Native user IDs are internal customer identifiers; old CPT IDs must not be presented as current identity.

## Future verification and acceptance

Frontend Developer builds with `npm run production`, checks source/generated diffs and `git diff --check`, and records actual commands/exits and cleanup. Build scripts' placeholder lint commands are not lint evidence. Browser verification is owned by Tester, including 320px, RTL, keyboard/focus, screen reader, notice/error states, pagination and scoped asset loading. Missing screenshots do not reject a code handoff.

Backend Architect reviews code/security boundaries and builds. Code approval moves the feature to AWAITING_MANUAL_ACCEPTANCE only when the required backend and FE work pass; Tester executes CUST-002-UAT and alone accepts DONE. No source change is authorized by this draft.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

G-10 retires CUST-002 without acceptance. Preserve historical evidence and follow CUST-001 restoration; do not implement native customer accounts, invitation/reset hooks, or migration. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md. No active-site database changes, commit, deploy, or release.
```
