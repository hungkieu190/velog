# CUST-001-FE round 3 — Architect review 1

Date: 2026-10-15
Reviewer: Codex / Backend Architect
Review decision: CHANGES_REQUESTED
Status: CHANGES_REQUESTED
Next actor: Frontend Developer

## Blocking source finding

### FE-R3-05 [P2]: Preserve Settings mobile heading width when isolating styles

At src/css/admin.scss:56–58 the new non-customer selector compiles to `.velog-admin-wrap:not(.velog-customers) .form-table th { width: 250px; }`. It has specificity (0,3,1), exceeding the existing mobile `.velog-admin-wrap .form-table th { width: 100%; }` at lines 34–36, specificity (0,2,1). At <=782px, the new 250px width wins on non-customer pages. Previously the mobile rule overrode the desktop width under the same selector.

RegionalSettingsPage renders `.wrap.velog-admin-wrap` with `.form-table th`, so it matches the higher-priority non-customer rule. Core's mobile `.form-table th { width: auto; }` has still lower specificity and cannot restore the previous behavior. Both competing declarations are present in the submitted generated CSS. This is a source-proven regression in the explicitly protected Settings responsive layout, not an inferred browser screenshot result. No actual overflow or manual failure is claimed.

Keep the existing desktop width and restore 100% width at <=782px using a scoped override with matching specificity, or limit the 250px declaration to desktop widths. Do not use !important or change Customer/native controls. Rebuild and return updated source/output hashes.

## Original findings disposition

| Finding | Source review disposition |
| --- | --- |
| FE-R3-01 | Customer decorative h1/form skin removed; native sizing can apply. Existing root font stack matches the inspected WordPress core stack. No blocking typography finding. |
| FE-R3-02 | Fixed-color customer focus overrides removed; shared input focus rules exclude Customers. Runtime focus/color-scheme cases remain Tester-owned. |
| FE-R3-03 | Custom page-number control skin removed; PHP native classes retained. Current-page weight is a reasonable non-color indicator. |
| FE-R3-04 | Justified wrapping, table scrolling, row actions and RTL rules retained. Settings isolation is not complete because FE-R3-05 changes the mobile cascade. |

## Actual checks

- Submitted SCSS and CSS SHA-256 match the Frontend Developer report.
- Node 24.21.0/npm 11.19.0 production build: exit 0, Built 4 files (production). See architect-review-r1-build.txt.
- All four generated outputs are byte-identical before/after reviewer rebuild. The unrelated frontend CSS, JS outputs and generated manifest also match HEAD. Dependency lockfiles are unchanged. See architect-review-r1-hashes.json.
- All 14 backend/test baseline hashes from CUST-001-BE round 3 remain unchanged. No FE PHP/JS/backend boundary violation found.
- git diff --check: exit 0.
- No browser/manual checks, screenshots, live fixture preparation, commit, deploy or release performed. No full backend test rerun is required for this CSS-only review.

## Documentation corrections with the resubmission

The implementation report currently says the shared .velog-header moved inside :not(.velog-customers), but that header rule remains shared. Correct the wording to the actual source. Its task link should resolve from the report directory via ../../../tasks/CUST-001-FE-customer-presentation.md.

The feature remains open. UAT is still BLOCKED on code review and explicit Backend Architect-owned LocalWP preparation. Earlier Tester T01 PASS and T02 FAIL pending retest remain unchanged.
