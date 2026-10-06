# CUST-001-FE: Customer admin presentation

## Current handoff
- Workstream: Frontend
- Status: BLOCKED
- Plan revision: 1
- Implementation round: 1
- Blueprint readiness: FAIL — Backend HTML contract is missing the `type="text"` attribute on the search input, breaking CSS targeting.
- Owner: Frontend Developer
- Contributors and review mode: Frontend Developer implementation; Backend Architect review required.
- Related checklist items: CUST-001 / AC4.
- Latest report: Not started.
- Evidence: `ai-document/evidence/CUST-001-FE/frontend-round-1/`.
- Temporary resource cleanup: Completed.
- Next actor: Backend Architect
- Next actor and exact next action: Add `type="text"` to the search input in `CustomerPage.php` to restore the CSS contract, then return to Frontend Developer for styling.

## Problem and intended behavior

The customer admin journey needs usable presentation for narrow screens, RTL locales, long Unicode content, keyboard navigation, focus, validation states, list actions, and pagination after the backend contract exists.

## Provisional scope and boundaries

Allowed files:

- `src/css/admin.scss`
- `assets/css/admin.css` generated through the production build
- `src/js/admin.js` and `assets/js/admin.js` only if a demonstrated presentation interaction is approved
- frontend task and browser evidence files

Frontend Developer must not edit PHP, WordPress hooks, capabilities, nonces, validation, storage, SQL, service/query classes, tests, fixtures, or backend documentation. Stable selectors include `.velog-customers`, `.velog-customer-form`, `.velog-customer-search`, `.velog-customer-table`, `.velog-customer-state-form`, and `#velog-customer-bulk`. The exact page hook is `velog_page_velog-customers`.

## Provisional verification matrix

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
- Real WordPress browser evidence covers keyboard, focus, 320px, Unicode, RTL, error/empty states, pagination/actions, and asset scoping.
- Backend Architect approves the diff, generated assets, evidence, and boundary compliance as a code review only.
- After approval, Backend Architect activates `CUST-001-UAT`; the parent feature remains open until Tester records manual PASS.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

CUST-001-FE is blocked due to a backend HTML contract defect. The `velog-customer-search` input field in `CustomerPage.php` is missing the `type="text"` attribute (rendered as `<input id="velog-customer-search" name="term">`), making it impossible to target cleanly with standard `input[type="text"]` CSS selectors without introducing brittle hacks. Please add the missing `type="text"` attribute, verify the PHP changes, and hand the task back to Frontend Developer to resume presentation work.
```
