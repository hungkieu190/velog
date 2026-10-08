# CUST-001-FE: Customer admin presentation

## Current handoff
- Workstream: Frontend
- Status: AWAITING_MANUAL_ACCEPTANCE
- Plan revision: 2
- Implementation round: 2
- Blueprint readiness: PASS — Backend HTML contract now declares the search input as `type="text"`.
- Owner: Frontend Developer
- Contributors and review mode: Frontend Developer implementation; Backend Architect code review APPROVED; Tester manual acceptance pending.
- Related checklist items: CUST-001 / AC4.
- Latest report: Round 2 presentation code approval remains valid; T02 backend correction is ready for Tester retest. No frontend files changed by this correction.
- Evidence: `ai-document/evidence/CUST-001-FE/frontend-round-2/architect-review.md`.
- Temporary resource cleanup: Completed.
- Next actor: Tester
- Next actor and exact next action: Retest T02 after backend correction, then complete remaining CUST-001-UAT cases.

## Problem and intended behavior

The customer admin journey needs usable presentation for narrow screens, RTL locales, long Unicode content, keyboard navigation, focus, validation states, list actions, and pagination after the backend contract exists.

## Provisional scope and boundaries

Allowed files:

- `src/css/admin.scss`
- `assets/css/admin.css` generated through the production build
- `src/js/admin.js` and `assets/js/admin.js` only if a demonstrated presentation interaction is approved
- frontend task and build-result files

Frontend Developer must not edit PHP, WordPress hooks, capabilities, nonces, validation, storage, SQL, service/query classes, tests, fixtures, or backend documentation. Stable selectors include `.velog-customers`, `.velog-customer-form`, `.velog-customer-search`, `.velog-customer-table`, `.velog-customer-state-form`, and `#velog-customer-bulk`. The exact page hook is `velog_page_velog-customers`.

## Verification matrix — browser cases owned by Tester

| Case | Expected result |
|---|---|
| Keyboard and focus | Create, edit, search, pagination, row actions, and bulk controls have logical order and visible focus |
| 320px and long Unicode | No page overflow, clipped controls, or unreadable actions |
| Real RTL locale | Form, notices, table/actions, pagination, and validation remain usable |
| Error and empty states | Field errors and empty results remain associated and readable |
| Asset scope | Generated assets load on exact customer pages and remain absent elsewhere |
| Production build | Source and generated assets match; build and diff checks pass |

## Acceptance criteria

- Backend view contract is stable and recorded before implementation.
- Frontend changes stay within assigned presentation files.
- Backend Architect prepares UAT for keyboard, focus, 320px, Unicode, RTL, error/empty states, pagination/actions, and asset scoping. Tester executes it; no frontend screenshots or browser tests are required.
- Backend Architect approves the diff, generated assets, build results, and boundary compliance as a code review only.
- After approval, Backend Architect activates `CUST-001-UAT`; the parent feature remains open until Tester records manual PASS.

## Round 1 finding disposition

- FE-R1-01 and FE-R1-02: Superseded as frontend evidence requirements by the owner decision of 2026-10-08. Transferred to UAT T10–T18; browser behavior is NOT TESTED, not inferred PASS from CSS edits.
- Review decision: APPROVED for code only; final DONE belongs to Tester.

### Chat handoff prompt

```text
Status: AWAITING_MANUAL_ACCEPTANCE
Recipient: Tester
Intent: accept

T02 backend correction is SELF_REVIEWED_BACKEND. Reload Customers and repeat T02: create a Unicode customer, confirm the success notice and saved row, edit the phone, save, and reload to confirm persistence. Then run T04, T06, T07, T09, T10, and T13 for related regressions. T01 remains PASS; the original T02 FAIL remains recorded until your retest. Evidence: ai-document/evidence/CUST-001-BE/backend-round-2/report.md. Report PASS/FAIL with actual results; only Tester may authorize DONE after the remaining UAT cases are accepted.
```
