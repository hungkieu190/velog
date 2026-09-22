# Architecture baseline and target

Unchanged runtime: velog.php loads vendor/autoload.php when present and registers Core activation/deactivation/bootstrap callbacks. Composer maps MF\VeLog\ to src/. Core/Plugin.php is scaffold code. No WordPress asset enqueues were found in the inspected PHP source.

Baseline frontend: src/assets/js/{admin,frontend}.js and src/assets/scss/{admin,frontend}.scss import _variables.scss. bin/build-assets.js uses Sass/esbuild through npx and silently skips missing entries. bin/release.sh uses a ZIP exclusion list and removes vendor/, leaving class loading unavailable in a clean installation.

Implemented workflow: scripts/build.config.mjs defines explicit entry/output ownership and browser/runtime format; scripts/build.mjs uses local tools and safe staging. scripts/release.config.mjs defines runtime allowlist and version policy; scripts/release.mjs validates, builds, stages, checks, archives. scripts/progress-dashboard.mjs reads Markdown through a localhost-only server.

Dashboard is a visibility tool, not acceptance evidence. No application hooks or business behavior changes are needed for bootstrap. Missing asset enqueue integration remains a documented product follow-up, not permission to add global enqueues.

Build output is staged and manifest-owned. Release captures production bytes before another build can run, uses PHP ZipArchive, and generates Composer autoloading offline in isolated staging. The dashboard uses server-rendered escaped HTML and a local source stylesheet, with no browser-side JavaScript.

## Approved product direction — PLAN-001 revision 3

Single-shop private admin MVP: customers/vehicles, service history and internal reminders. Internationalization is a foundation requirement, not a later display patch; follow internationalization.md for original/canonical distances, record-specific currencies, Unicode, timezone and locale separation. Common owns reusable regional primitives; Admin owns presentation/settings; Core owns lifecycle wiring. No implementation is claimed here.

P0 sequence: CORE-001 fixes bootstrap/translation timing first; a subsequent bounded task specifies regional settings, catalog provenance, parsing/conversion precision and tests; then capability/private-CPT foundation is scoped before P1 CRUD. Existing Core source remains unchanged during planning.

## Inspected current source — PLAN-002, 2026-09-21

The opening baseline paragraphs above describe historical scaffold/tooling states. At main 2aa3b8d, Plugin::run() already has an idempotence guard and queues translation loading on init; CORE-001 is still CHANGES_REQUESTED because F-003/F-004 concern the verification harness. No product services, registrations or admin asset enqueues exist yet. uninstall.php deletes velog_settings; DATA-001 must reconcile that with approved retained-data direction before operational settings/records are introduced.

[PLAN-002](tasks/PLAN-002-mvp-task-batch.md) and its ten linked tasks are the proposed implementation design, not implemented architecture. They split exact regional primitives, private capability/types, versioned storage, explicit setup, customer/vehicle CRUD, service transitions, history retrieval, manual reminders and final package acceptance. Pending product rules and storage concurrency design must be resolved before READY. Adopt concrete schema/interfaces into this document only when approved in the owning task; do not create a second competing schema contract here while drafts are unresolved.

## Regional Primitives (CORE-002)

The `MF\VeLog\Common\Regional` namespace provides pure PHP helpers for distance, currency, and date operations:
- `Distance::parse( $input, $unit, $separator )` returns an array with `original_value`, `unit`, and `canonical_mm`.
- `Distance::to_decimal( $canonical_mm, $unit, $places )` takes canonical_mm and returns a fixed-place display decimal.
- `DecimalMath` provides exact half-up logic without binary floats. All stored/full quantities are exact decimal strings, and arithmetic is handled as bounded intermediates.
- `Money::parse( $input, $currency_code, $separator )` returns `original_value`, `minor_units`, `currency`, `scale`, and `catalog_version` using `CurrencyCatalog`.
- `CalendarDate::parse()` and `CalendarDate::today()` handle strict date-only values avoiding timezone offsets.
- `DecimalInput` enforces rigid numeric ASCII character constraints (no NUL bytes), limiting values to 256 bytes.
- `CurrencyCatalogData` is statically generated from Unicode CLDR via `derive-catalog.php` (no runtime network/database dependencies) and includes explicitly active currencies.
