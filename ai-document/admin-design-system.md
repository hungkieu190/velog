# VeLog admin design system

## Scope and authority

This is the design baseline for VeLog's WordPress administration screens, adopted at the Product Owner's request on 2026-10-15. It covers information hierarchy, interaction contracts, native controls, and presentation review. Backend architecture, storage, authorization, and transaction rules remain governed by `AGENTS.md`, `rules/`, and the approved feature contract.

The reference bundle is [wordpress-plugin-admin-ui](../.agents/skills/wordpress-plugin-admin-ui/SKILL.md), copied unchanged from the Product Owner's local folder. Its [native classes](../.agents/skills/wordpress-plugin-admin-ui/references/native-classes.md) and [React components](../.agents/skills/wordpress-plugin-admin-ui/references/react-components.md) are examples. Verify APIs, registered asset handles, package availability, and supported WordPress versions before using version-specific examples. Do not copy incomplete example handlers into production.

## Design decisions

- Prefer WordPress core controls, typography, spacing, focus indicators, and the selected admin color scheme. Do not create a parallel visual framework for ordinary admin screens.
- Keep existing PHP/classic pages. Use React only for a justified, explicitly approved interaction requirement; a JavaScript build step alone does not justify migration or new packages.
- Use the Settings API for plugin configuration where appropriate. Customer records, migrations, and other domain operations retain their approved service and authorization contracts; they are not settings merely because they use a form.
- Keep custom CSS limited to necessary layout or domain-specific visuals, scoped under the VeLog screen container and loaded only on relevant screens. Avoid global control selectors, custom control skins, marketing typography, gradients, and heavy shadows.
- Use core sizing and spacing first. Where custom spacing or colors are necessary, reuse existing VeLog tokens and supported WordPress variables with compatible fallbacks. Do not create a new token scale for each feature. Express state with text as well as color.

## Component and interaction catalogue

| Need | Classic WordPress baseline | Contract |
| --- | --- | --- |
| Page hierarchy | `.wrap`, one main `h1`, logical section headings | Clear page name; primary action placed consistently near the heading or form submission area. |
| Create action | `.page-title-action` | Visible only when the account can perform the operation. |
| Form | `.form-table`, associated labels, native inputs | Required markers, format guidance, descriptions linked with `aria-describedby`, and contextual validation. |
| Text sizes | `.regular-text`, `.small-text`, `.large-text`, `.code` | Choose by content; keep long values usable on narrow screens. |
| Submit/cancel | `submit_button()`, `.button`, `.button-primary` | One primary action per action group; explicit labels; cancel returns to a safe context. |
| Lists | Existing `WP_List_Table` integration; `.widefat`, `.striped` | Stable headings, row actions, empty state, bounded pagination, and clear selection/bulk behavior. |
| Feedback | `.notice` with success/error/warning/info modifiers | Describe the outcome and recovery action; dismiss only when losing the notice is safe. |
| Related settings | Logical headings; core postbox structure when needed | Group by user task; do not wrap every field in a decorative panel. |
| Tabs | Native navigation tabs | Use for peer sections; indicate the current section and preserve relevant navigation context. |
| Asynchronous state | Native spinner or approved WordPress component | Announce progress/outcome accessibly, prevent accidental duplicate submission, and provide recovery. |
| Destructive action | Explicit action label, core destructive styling where applicable | Explain consequences before confirmation; retain server-side authorization and concurrency checks. |

For approved React screens, use the supplied component reference and installed `@wordpress/components` equivalents. Load the required component stylesheet and declare actual dependencies. Theme providers or newer APIs require compatibility verification; they are not mandatory across supported cores.

## Required screen contract

Before implementation, the backend task or linked view contract must specify:

1. Audience and capabilities, visible fields, sensitive data boundaries, and permitted actions.
2. Page/list/form structure and the native components selected from the catalogue.
3. Field schema: labels, required status, types, length limits, read-only fields, validation, and help text.
4. Action contract: request method, nonce, capability checks, input validation, escaping, idempotency and stale-version handling where relevant, and redirect/response behavior. Hiding a button is not authorization.
5. Applicable states: initial, populated, empty, invalid input, permission denied, success, backend failure, stale/conflicting data, and loading for asynchronous operations. Record non-applicable states with a reason.
6. Search/filter/sort/pagination and bulk-action behavior where relevant. Never place credentials, reset tokens, or sensitive contact search terms in navigation URLs.
7. Responsive, RTL, keyboard/focus, accessible status messaging, long-content behavior, and scoped asset requirements.
8. Fixture/environment preparation and the corresponding Vietnamese UAT cases.

## Review and verification

- Under the 2026-10-21 owner decision, Developer implements backend and presentation, checks contracts/source/generated diffs/security boundaries and records technical self-review with applicable static/build evidence. Tester alone owns manual acceptance.
- Tester checks actual rendering and interaction in the documented [LocalWP environment](local-environment.md), including relevant admin color schemes, keyboard/focus, responsive/RTL behavior, feedback, pagination, and asset loading.
- Review records deviations with a concrete reason and approved scope. A skill checklist cannot override the project's role boundaries or require agents to supply browser screenshots.
- Adoption establishes the baseline for subsequent work. Existing screens still require task-specific review and Tester acceptance; this document does not certify their compliance.

## Maintaining the reference bundle

The project-local copy lives under `.agents/skills/wordpress-plugin-admin-ui/` so it travels with the repository. Treat upstream changes as explicit reviewed updates. Keep VeLog-specific decisions in this document rather than silently rewriting the supplied skill. Do not add dependencies, change runtime code, or publish a release as part of a documentation-only reference update.
