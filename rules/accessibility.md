# Rule: Accessibility

**Scope**: All frontend and admin UI output
**Author**: Mamflow
**Mandatory**: Yes

---

## Standard

Minimum compliance: **WCAG 2.1 Level AA**.

---

## HTML Semantics

- Use semantic HTML5 elements: `<nav>`, `<main>`, `<section>`, `<article>`, `<header>`, `<footer>`.
- One `<h1>` per page.
- Heading hierarchy must not skip levels.
- Never use a `<div>` or `<span>` where a semantic element exists.

---

## Images

- All `<img>` elements must have an `alt` attribute.
- Decorative images: `alt=""`.
- Informative images: descriptive `alt` text.
- Never use images of text.

---

## Forms

- Every input must have a corresponding `<label>` (linked via `for` + `id`).
- Required fields: `required` attribute + visual indicator.
- Error messages must be linked to the field via `aria-describedby`.
- Focus must be visible at all times (no `outline: none`).

---

## Keyboard Navigation

- All interactive elements must be reachable via Tab key.
- Custom interactive widgets must support keyboard events.
- Modal dialogs must trap focus while open.
- Escape key must close modals and dropdowns.

---

## ARIA

- Use ARIA attributes only when native HTML semantics are insufficient.
- Do NOT use ARIA to override native semantics.
- Required ARIA patterns:
  - `role="dialog"` + `aria-modal="true"` for modals.
  - `aria-expanded` for toggles.
  - `aria-live="polite"` for dynamic content updates.

---

## Color & Contrast

- Text contrast ratio: minimum 4.5:1 (normal text), 3:1 (large text).
- ❌ Never convey information by color alone.
- Test with: [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/).

---

## Dynamic Content

- Use `aria-live="polite"` for async-loaded content.
- Use `aria-busy="true"` during loading states.
- Focus management: after modal closes, return focus to trigger element.

---

## Testing

- Automated: `@wordpress/scripts` accessibility linting.
- Manual: keyboard-only navigation test.
- Screen reader: test with NVDA (Windows) or VoiceOver (macOS/iOS).
