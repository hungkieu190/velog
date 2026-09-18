# Rule: UI/UX

**Scope**: All admin and frontend interfaces
**Author**: Mamflow
**Mandatory**: Yes

---

## Design Principles

1. **Clarity** — Every screen has one clear purpose.
2. **Consistency** — Follow WordPress admin UI patterns.
3. **Accessibility** — WCAG 2.1 AA minimum.
4. **Mobile-first** — Admin UI must be responsive.
5. **Progressive Disclosure** — Show only what the user needs at each step.

---

## WordPress Admin UI

- Use native WordPress admin components wherever possible:
  - `postbox`, `wrap`, `form-table`, `button-primary`, etc.
- Do NOT create custom admin frameworks.
- Settings pages must use `add_options_page()` or `add_menu_page()`.
- All admin forms use WordPress nonces.

---

## Color Usage

- Respect user's WordPress admin color scheme.
- Brand accent color: to be defined in `ai-document/ui/` when UI design documentation is introduced.
- ❌ No hardcoded colors that conflict with dark mode.

---

## Typography

- Use WordPress admin fonts (`-apple-system, BlinkMacSystemFont, "Segoe UI"...`).
- Heading hierarchy: H1 per page, H2 per section, H3 per subsection.

---

## Forms

- Every field must have a visible `<label>` with `for` attribute.
- Required fields must be marked visually and with `required` attribute.
- Display inline validation errors next to fields, not only at the top.
- Success/error admin notices use `notice notice-success` / `notice notice-error`.

---

## Loading States

- Long-running operations must show a loading indicator.
- Use WordPress Spinner CSS class: `spinner is-active`.

---

## Data Tables

- Use `WP_List_Table` for admin list views.
- All tables must support: sorting, bulk actions, search, pagination.

---

## Forbidden

- ❌ No full-page overrides of WordPress admin layout.
- ❌ No inline CSS (use enqueued stylesheets).
- ❌ No inline JS (use enqueued scripts).
- ❌ No UI elements without keyboard support.
