# CUST-001-FE: Customer admin presentation

> G-10 restores this private customer-record contract. CUST-002 is retired. Tester accepted all CUST-001-UAT cases on 2026-10-21; prior results remain historical evidence.

## Current handoff
- Workstream: Frontend
- Status: DONE
- Plan revision: 3
- Implementation round: 3
- Blueprint readiness: PASS — bounded native admin CSS correction; stable PHP view/state contract and backend gates are ready.
- Owner: Developer
- Contributors and review mode: Frontend Developer implementation; Backend Architect review 2 APPROVED for code only. No further frontend correction is assigned; Tester accepted all UAT cases on 2026-10-21.
- Related checklist items: CUST-001 / AC4.
- Latest report: FE round 3 review 2 APPROVED; FE-R3-01 through FE-R3-05 resolved. LocalWP prerequisites verified in ai-document/evidence/CUST-001-UAT/preparation-2026-10-15/report.md. Tester confirmed all UAT cases PASS on 2026-10-21; frontend functional acceptance is complete.
- Evidence: `ai-document/evidence/CUST-001-FE/frontend-round-3/architect-review-r2.md`; implementation/build/hashes in the same round 3 directory.
- Temporary resource cleanup: Completed.
- Next actor: Tester
- Next actor and exact next action: Execute VEH-001-UAT T01–T15 on prepared LocalWP, starting with T01, and report actual PASS/FAIL/BLOCKED; only Tester may accept the feature.

## Problem and intended behavior

The customer admin journey needs usable presentation for narrow screens, RTL locales, long Unicode content, keyboard navigation, focus, validation states, list actions, and pagination after the backend contract exists.

## Presentation scope and boundaries

Allowed files:

- `src/css/admin.scss`
- `assets/css/admin.css` generated through the production build
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
- After approval and verified preparation, Backend Architect activates `CUST-001-UAT`; missing prerequisites return preparation to Backend Architect with BLOCKED. The parent remains open until Tester records manual PASS.

## Round 3 correction blueprint — native admin design system

G-10 restores the private customer record workflow. The Product Owner requested adoption of the project-local wordpress-plugin-admin-ui skill; the historical Round 2 approval does not approve the existing visual overrides against this new baseline. Backend owns PHP markup and has supplied stable native controls, accessible checkbox/filter labels, table header scopes and pagination classes. Frontend owns the following CSS correction only.

Read `.agents/skills/wordpress-plugin-admin-ui/SKILL.md` and `ai-document/admin-design-system.md` before implementation. Blueprint readiness is PASS for this bounded presentation contract.

### Findings to resolve

1. FE-R3-01: `src/css/admin.scss` applies a custom customer heading panel, typography and form panel. Remove customer-specific decorative heading/card treatment and let WordPress heading/form density and typography lead. Shared overview/settings presentation must retain its existing behavior; isolate shared rules so they do not reintroduce custom customer control skins.
2. FE-R3-02: Shared and customer rules override input/select/action focus using fixed `$mf-primary` outlines and shadows. Preserve WordPress's native focus indicators and admin color scheme rather than repainting them. Do not remove focus visibility.
3. FE-R3-03: `.tablenav-pages .page-numbers` paints custom buttons, colors, borders and current-page styles. Backend pagination now emits `.button` on links and `.tablenav-pages-navspan` on the current page. Keep only necessary wrapping/spacing; use core control appearance and retain the current-page text/aria indication.
4. FE-R3-04: Keep only justified customer layout rules for long content, table scrolling, 320px controls, RTL and inline row forms. Do not recreate native typography, input dimensions, backgrounds, or focus styles under a different selector. Empty rows/notices must remain usable.

### Architect review 1 — FE-R3-05

Historical review 1 finding, now RESOLVED in review 2: preserve the pre-existing Settings mobile 100% heading width while keeping its 250px desktop width under the new isolation selector. See `ai-document/evidence/CUST-001-FE/frontend-round-3/architect-review-r1.md` for the cascade proof and exact correction scope. Correct report wording/link along with the code/build resubmission; no browser evidence is required.

### Exact change map and prohibited boundaries

- Allowed implementation: `src/css/admin.scss`; generated `assets/css/admin.css` through `npm run production`; this task and evidence under `ai-document/evidence/CUST-001-FE/frontend-round-3/`.
- Build-owned manifest changes are allowed only when generated by the existing pipeline and actually required. Unrelated JS/frontend outputs must remain byte-identical; explain any unexpected build diff rather than editing it manually.
- No JavaScript, PHP, shared token changes, new dependencies, account setup, fixtures, customer identity changes, SQL, validation, authorization or lifecycle hooks. Return a precise backend dependency instead of editing those boundaries.

### Stable backend view and state contract

- Classic page `/wp-admin/admin.php?page=velog-customers`; page hook `velog_page_velog-customers`. Existing `.velog-customers`, `.velog-customer-form`, `.velog-customer-search`, `.velog-customer-table`, `.velog-customer-state-form` and `#velog-customer-bulk` stay stable.
- Fields: required name, optional phone/email; no username/password/account activation. States: active/archived. Native `.regular-text` inputs and `.form-table[role=presentation]` are server-rendered.
- Search is POST with a nonce; results/pagination carry only `customer_search`, an actor-scoped 15-minute reference. Search/filter labels and Clear search already exist in PHP. Missing/foreign/expired references show an error, not unfiltered results.
- Server-rendered initial/list/empty, invalid input, storage/permission/stale/active-vehicle errors, success and mixed bulk results are already supplied. Loading is not applicable: this is synchronous form navigation, with no asynchronous action added by this task.
- Bounded backend/customer gates are recorded in `ai-document/evidence/CUST-001-BE/backend-round-3/report.md`. Browser, responsive, RTL, color-scheme, keyboard and screen-reader results belong to Tester, not this implementation handoff.

### Ordered implementation and verification

1. Read the references and map each finding to the exact customer/shared selectors before editing. Preserve the existing Settings/overview UI.
2. Remove customer control/heading skins; retain only necessary scoped layout and long-content behavior. Use the new native pagination classes; add no behavior or dependencies.
3. Use Node 24; run `npm run production`. Inspect source/generated diffs and run `git diff --check`. Record commands, exit codes, generated hashes, and a disposition for FE-R3-01 through FE-R3-04. Browser checks/screenshots are not required.
4. Remove owned scratch files, inspect `git status --short`, then synchronize this task/checklist/README to READY_FOR_REVIEW, Next actor Backend Architect. Provide one matching review prompt. Never mark the feature DONE or imply a manual case passed.

LocalWP has insufficient manual-test prerequisites (dedicated staff accounts, pagination dataset, active linked vehicle). Backend Architect owns later explicit preparation; do not create fixtures or modify the active database as part of FE correction.

## Round 1 finding disposition

- FE-R1-01 and FE-R1-02: Superseded as frontend evidence requirements by the owner decision of 2026-10-08. Transferred to UAT T10–T18; browser behavior is NOT TESTED, not inferred PASS from CSS edits.
- Historical Round 2 review decision: APPROVED for code only. Current Round 3 review decision: APPROVED for code in Architect review 2; final DONE belongs to Tester.

### Chat handoff prompt

```text
Status: DONE
Recipient: Tester
Intent: accept

Run VEH-001-UAT T01–T15 in ai-document/tasks/VEH-001-UAT-manual-test.md on https://velog.local/wp-admin/, starting with T01. Developer completed technical self-review and verified the authorized LocalWP fixtures; see ai-document/evidence/VEH-001-UAT/preparation-2026-10-21/report.md. Customer A/B/Archived IDs are 66/67/68; pagination vehicles are 69–119; subscriber test actor is ID 4. Keep pagination fixtures unchanged until T11 and use Customer B for T06. Record actual PASS/FAIL/BLOCKED and environment/tool details; missing RTL/screen-reader tools must be reported. Only explicit Tester acceptance permits DONE. No commit, deploy or release.
```
