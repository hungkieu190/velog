# VEH-001: Vehicle identity and current customer

## Approved correction — 2026-10-21

Tester requested a native Year dropdown and one system distance unit. Product Owner approved the proposed correction with "ok sửa". Revision 3 amendment in VEH-001-contract-v2.md overrides per-vehicle unit controls; no full manual acceptance was reported. Correction implementation/self-review and Tester acceptance are complete; prior round evidence remains historical.

## Current handoff
- Workstream: Backend
- Status: DONE
- Plan revision: 3
- Implementation round: 2
- Blueprint readiness: PASS — revision 3 Year/global-unit correction approved with "ok sửa"; technical gates passed.
- Owner: Developer
- Contributors and review mode: Developer; SELF_REVIEWED_DEVELOPMENT technical review PASS (non-independent); manual acceptance remains Tester-owned.
- Related checklist items: VEH-001 / AC1–AC4.
- Latest report: Tester explicitly accepted all vehicle tests/environments on 2026-10-21 and requested continuing development. VEH-001 is functionally DONE; SERV-001 G-06 and implementation were subsequently approved.
- Evidence: ai-document/evidence/VEH-001-BE/correction-round-2/report.md; original LocalWP preparation: ai-document/evidence/VEH-001-UAT/preparation-2026-10-21/report.md.
- Temporary resource cleanup: Verified for all owned correction runs; see correction-round-2/cleanup.txt. Prior run cleanup remains historical.
- Next actor: Developer
- Next actor and exact next action: Developer implements approved SERV-001 revision 2 after G-06 approval; VEH-001 is accepted DONE.

## Approved scope and executable blueprint

[Revision 2 contract with the approved revision 3 amendment](VEH-001-contract-v2.md) is authoritative for exact file boundaries, fields, operations, view states, pseudocode-equivalent execution steps, failure behavior, verification matrix and cleanup. It supersedes the previous conflicting root/Common class paths. Read the local admin UI skill and admin design system before PHP views. Presentation scope remains tracked in the FE subtask and is implemented by the same Developer.

## Acceptance criteria

- AC1: Unique validated plate/jurisdiction or VIN, including concurrent writes and archived identifiers.
- AC2: Private customer relation, manager mutation/staff read boundaries, nonces, versions and audited archive/restore.
- AC3: Exact unknown/zero baseline and km/mi identity, site-local dates, attributed lower/cleared readings and fail-closed finalized-history boundary.
- AC4: Bounded native admin forms/list/detail/search/pagination, isolated backend/HTTP evidence, FE code review and Tester acceptance.

## Dependencies and history

CUST-001 is DONE by explicit Tester acceptance on 2026-10-21. G-04/G-05/G-10 and the revision 3 G-11 amendment remain approved. [Readiness review](../evidence/VEH-001-BE/readiness-2026-10-21.md) records the prior gaps; runtime checks there were not performed. Earlier parent blueprint details remain in ai-document/history/VEH-001/pre-lean.md. Linked presentation task: [VEH-001-FE](VEH-001-FE-vehicle-presentation.md); manual task: [VEH-001-UAT](VEH-001-UAT-manual-test.md).

## Tester acceptance — 2026-10-21

Tester explicitly accepted all testing environments and instructed continuing implementation on 2026-10-21: "đã pass hết các môi trường kiểm thử; làm tiếp code". VEH-001 T01–T15 accepted; no unreported per-browser observations are invented.

### Chat handoff prompt

```text
Status: DONE
Recipient: Developer
Intent: work

Continue approved SERV-001 revision 2 under ai-document/service-workflow-contract.md; VEH-001 is accepted DONE by Tester. Preserve prior evidence and data. No commit, deploy or release.
```
