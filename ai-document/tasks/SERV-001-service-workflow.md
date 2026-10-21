# SERV-001: Service drafts, finalization and corrections

## Code-first scheduling — 2026-10-21

- Debt: TEST_DEBT
- Scheduling: DEFERRED
- Implementation checkpoint: SERV-001 code complete with prior technical evidence; functional acceptance is not granted.
- Current instruction: CODE-001 completed the remaining application code. Keep tests/fixes deferred until Product Owner resumes that phase.

## Current handoff
- Workstream: Development
- Status: AWAITING_MANUAL_ACCEPTANCE
- Plan revision: 2
- Blueprint readiness: PASS — revision 2 technically self-reviewed; LocalWP login/CSS and read-only account/data prerequisites reverified after restart.
- Owner: Developer
- Contributors and review mode: Codex; SELF_REVIEWED_DEVELOPMENT PASS, intentionally non-independent; manual acceptance remains Tester-owned.
- Related checklist items: SERV-001 / AC1–AC4.
- Latest report: Technical gates passed; user restarted LocalWP and Developer confirmed login HTTP 200, served CSS hash and unchanged UAT prerequisites. No manual service case has passed yet.
- Evidence: ai-document/evidence/SERV-001/round-1/.
- Next actor: Developer
- Next actor and exact next action: Developer retains deferred consolidated test/fix debt under CODE-001; no current Tester action.

## Approved scope and executable blueprint

Read [revision 2 executable contract](../service-workflow-contract.md), G-02/G-05/G-06/G-10/G-11, current storage/vehicle/regional interfaces, admin UI skill and design system. No timeline, portal, upload, invoicing, reminder automation or hard deletion. Historical revision 1 is retained in history/SERV-001/pre-lean.md and superseded for paths, current role model and coherent computed read-model design.

## Acceptance criteria

- AC1: Creator/manager draft and finalization rules enforce active relations, dates, reading and type.
- AC2: Optional exact costs, original readings and finalization snapshots preserve historical meaning.
- AC3: Corrections and decreases are manager-only, attributed/reasoned with complete audit history.
- AC4: Backdating/ties/concurrent transitions and rollback preserve coherent current reading and service state.

## Verification

V1–V4, file boundaries, HTTP contract, ordered steps and cleanup are specified in service-workflow-contract.md. Technical gates pass; see evidence/SERV-001/round-1/report.md. Browser/manual acceptance is not claimed. Fixture preparation on the active site requires a separate bounded authorization.

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Developer
Intent: deferred-test-fix

CODE-001 completed the approved application code queue. Keep SERV-001 acceptance as TEST_DEBT / DEFERRED until Product Owner resumes the consolidated test/fix phase. Preserve prior evidence and do not mark deferred tests PASS. No commit, deploy or release.
```
