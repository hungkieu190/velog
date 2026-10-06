# CORE-004 manual admin walkthrough

- Date: 2026-10-05.
- Reviewer: Codex Architect under mandatory takeover.
- Environment: disposable WordPress 6.7.2 product-smoke installation on loopback HTTP; administrator session; generated production CSS.
- Independence: non-independent verification for CORE4-F-002/F-004/F-005.

## Observations

- Labels and keyboard: `Distance Unit`, `Currency`, and `Region Hint (Optional)` were exposed as accessible labels. Tab traversal moved from distance to currency to region to the submit button in that order.
- 320px viewport: browser `innerWidth` was 320px; document width was 306px and the form/select/text controls were 295px. No document-level horizontal overflow occurred.
- Long content: a 4,000+ character Arabic region value remained inside the text input. The input scrolled internally while document width remained 306px at the 320px viewport.
- RTL: after installing and activating the disposable Arabic WordPress language pack, the settings page reported `lang="ar"`, `dir="rtl"`, the WordPress `rtl` body class, right-aligned settings headers, and no document overflow at 320px.
- Asset scope: the settings page loaded `wp-content/plugins/velog/assets/css/admin.css?ver=0.1.0`. The unrelated WordPress Dashboard loaded no stylesheet URL containing `velog`.
- Content: the page displayed the selected km/KWD settings and stated that WordPress locale/timezone are authoritative. JPY/USD/KWD scale identity was verified separately by the product-smoke oracle.

## Cleanup

The temporary browser tab, viewport override, WordPress/PHP/MariaDB processes, product-smoke directory, UI hold script, Arabic language files inside the disposable installation, and task-owned barrier files were removed. No matching task-owned process or temporary directory remained.
