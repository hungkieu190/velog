# CUST-001-FE round 3 — native admin design findings

Date: 2026-10-15
Reviewer: Codex / Backend Architect
Review decision: CHANGES_REQUESTED.
Status: CHANGES_REQUESTED; Next actor: Frontend Developer.
Review basis: source/architecture review, not browser verification.

The Product Owner adopted wordpress-plugin-admin-ui and the VeLog admin design system. Historical Round 2 approval remains preserved; it does not accept these existing styles against the new native-first baseline. No frontend files were edited by Backend Architect.

## Findings and required corrections

- FE-R3-01: Customer h1 and form panel rules add decorative borders/backgrounds, typography and spacing over core admin. Remove the customer-specific visual skin; keep native hierarchy and form density. Shared overview/settings must retain their existing presentation.
- FE-R3-02: Shared input/select and customer/table action selectors force fixed-color outlines and box shadows using $mf-primary. Let WordPress retain focus visibility and selected admin color scheme. Exclude Customers from inherited shared skins as needed; do not simply delete all keyboard focus feedback.
- FE-R3-03: Page-number rules paint custom button borders/backgrounds/colors. PHP now supplies native .button links, .tablenav-pages-navspan and aria-current semantics. Retain only wrapping/spacing; core owns control appearance.
- FE-R3-04: Keep justified customer table scroll, long-content, narrow-width, RTL and inline row-form layout. Avoid global rules, copied controls or unrelated page changes. No new dependencies or JS are justified.

## Stable backend interface

Customer IDs remain private record IDs; name is required and contacts optional. States are active/archived. All existing customer selectors and the exact page hook remain. Search is nonce-protected POST, pagination carries an actor-scoped reference, and missing/expired/foreign references show a server-rendered error. Clear search, checkbox/filter labels, table column scopes and native input sizes are already emitted by PHP. No PHP or backend changes are assigned to Frontend Developer.

Backend verification is recorded in ../../CUST-001-BE/backend-round-3/report.md. The complete implementation blueprint, allowed files and required build/diff evidence are in CUST-001-FE. Frontend Developer must not perform manual acceptance, create LocalWP fixtures or supply mandatory screenshots. Tester owns browser/color-scheme/responsive/RTL/keyboard/screen-reader observations after code review.

## Inspected baseline hashes

- `src/css/admin.scss`: `4fbd350dfad028c7065a5c0df096eca30aad27934b4c87309281f4db909fd427`.
- `assets/css/admin.css`: `a48ba01f7e1a3539e0b3eb7f3d1e816e873ceb39fd08e6e8ddabd9ece3dd453a`.
