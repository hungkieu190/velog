# Architecture baseline and target

Unchanged runtime: velog.php loads vendor/autoload.php when present and registers Core activation/deactivation/bootstrap callbacks. Composer maps MF\VeLog\ to src/. Core/Plugin.php is scaffold code. No WordPress asset enqueues were found in the inspected PHP source.

Baseline frontend: src/assets/js/{admin,frontend}.js and src/assets/scss/{admin,frontend}.scss import _variables.scss. bin/build-assets.js uses Sass/esbuild through npx and silently skips missing entries. bin/release.sh uses a ZIP exclusion list and removes vendor/, leaving class loading unavailable in a clean installation.

Implemented workflow: scripts/build.config.mjs defines explicit entry/output ownership and browser/runtime format; scripts/build.mjs uses local tools and safe staging. scripts/release.config.mjs defines runtime allowlist and version policy; scripts/release.mjs validates, builds, stages, checks, archives. scripts/progress-dashboard.mjs reads Markdown through a localhost-only server.

Dashboard is a visibility tool, not acceptance evidence. No application hooks or business behavior changes are needed for bootstrap. Missing asset enqueue integration remains a documented product follow-up, not permission to add global enqueues.

Build output is staged and manifest-owned. Release captures production bytes before another build can run, uses PHP ZipArchive, and generates Composer autoloading offline in isolated staging. The dashboard uses server-rendered escaped HTML and a local source stylesheet, with no browser-side JavaScript.

## Approved product direction — PLAN-001 revision 3

Single-shop private admin MVP: customers/vehicles, service history and internal reminders. Internationalization is a foundation requirement, not a later display patch; follow internationalization.md for original/canonical distances, record-specific currencies, Unicode, timezone and locale separation. Common owns reusable regional primitives; Admin owns presentation/settings; Core owns lifecycle wiring. No implementation is claimed here.

P0 sequence: CORE-001 fixes bootstrap/translation timing first; a subsequent bounded task specifies regional settings, catalog provenance, parsing/conversion precision and tests; then capability/private-CPT foundation is scoped before P1 CRUD. Existing Core source remains unchanged during planning.
