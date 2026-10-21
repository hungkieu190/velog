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

## Capability and Private Types (CORE-003)

The `MF\VeLog\Core\Capabilities` class owns strict schema definitions and persistence for VeLog roles and capabilities.
- **Table/Context**: The plugin leverages standard WordPress `$wpdb->options` (specifically the `wp_user_roles` payload) but uses deep transactional verification (`get_option_snapshot` + direct schema evaluation).
- **Lifecycle Contract**: Activation triggers capability install. A missing database read, serialization mismatch, or failed option rollback immediately aborts activation and restores exact rows with cache invalidation (`alloptions`, `notoptions`, and the specific option key).
- **Private CPT Enforcement**: Record types (`mf_velog_customer`, `mf_velog_vehicle`, `mf_velog_service`, `mf_velog_reminder`) are registered with `public => false`, `publicly_queryable => false`, and `show_in_rest => false`. This prevents exposure via search, feeds, sitemaps, REST, and direct permalink lookup for all actors. Capability checks (`edit_post => do_not_allow`) are applied at registration level to enforce native WordPress access control.
- **AccessPolicy**: `MF\VeLog\Common\AccessPolicy` is a callable authorization service that evaluates action/type/state/author context for application-level decisions. It is not a search, feed, or REST hook; it enforces business-level read/mutation rules on top of the WordPress capability layer.

## Regional Settings and Admin Shell (CORE-004)

`MF\VeLog\Common\Regional\ShopSettings` owns the non-autoloaded `velog_settings` option. An absent row means unconfigured. A present row must match the closed schema, exact PHP types, supported schema/catalog versions, km/mi allowlist, and catalog-derived currency scale. Corrupt rows return a typed error and are never normalized into valid configuration.

Settings writes use one raw database snapshot for validation, record-version checking, and a byte-exact conditional update. The first write relies on the unique option name and maps only native database error 1062 to `stale_version`. Later writes compare `BINARY option_value` against the same raw snapshot. Successful direct writes invalidate the option, `alloptions`, and `notoptions` caches. Failed or stale writes leave the prior bytes unchanged.

## Private Customer Management (CUST-001)

G-10 restores independent private customer records. A customer ID identifies a `mf_velog_customer` post, never a WordPress user. Name is required; phone/email are optional and need not be unique. Operational states are active/archived. WordPress users remain staff identities for authorization and audit actors. There is no customer account creation, invitation/reset integration, or separate authentication system. Any future portal account link requires its own approved contract.

`MF\VeLog\Common\Customer\CustomerService` validates Unicode customer names and optional phone/email fields, then delegates authoritative writes to DATA-001. Customer phone and email use the `contact` exposure policy and are removed from snapshots and audit payloads returned to actors without `mf_velog_read_customer_contacts`.

`CustomerQuery` performs manager-only, bounded prepared searches over schema-derived name/phone/email projections with state filters and deterministic name/ID ordering. Results remain private and are hydrated through bounded `RecordRepository::get_many()` batches (maximum 50 IDs), reusing the repository query decoder and contact policy. Customer list hydration uses five database reads for either one or 50 results, with no per-row SQL. `CustomerRelations` reserves `_mf_velog_current_customer_id` for VEH-001 and checks it under the DATA-001 write lock before customer archive. State transitions use schema-allowlisted states, exact versions, audit entries, and the same projection verification as field saves.

`CustomerPage` and `CustomerListTable` provide manager-only native WordPress forms, list/search/pagination, per-row archive/restore, and bounded per-customer bulk transitions. All mutations use action-specific nonces and fixed notice codes. Contact search uses an authorized nonce-protected POST followed by a 15-minute, per-actor opaque transient reference; pagination carries only that reference, never contact terms. Missing/foreign/expired search references report an error instead of broadening results. Classic controls use core sizing, checkbox/filter labels, table header scopes and native pagination buttons. The generated admin stylesheet is allowlisted only on the exact customer page hook in addition to the existing VeLog pages.

`MF\VeLog\Admin\RegionalSettingsPage` owns the manager-only POST/redirect/get boundary. It validates capability, nonce type/value, canonical nonnegative record version, and scalar field shapes before calling the service. `AdminMenu` and `Assets` provide the VeLog admin shell and restrict generated admin CSS to approved VeLog hook suffixes. WordPress locale and timezone remain authoritative; regional preferences affect future defaults and never rewrite DATA-001 envelopes.

## Dashboard and Workflow Parser (WF-003)

The `scripts/progress-data.mjs` script acts as the authoritative parser for the project's task and checklist tracking mechanism.
- **Ownership**: The workflow tooling is fully decoupled from the runtime plugin application. It parses Markdown via a local server (no browser JS logic).
- **Prompt Contract**: Handoff prompts are extracted solely from the last `### Chat handoff prompt` code block in a task document. 
- **Consistency Verification**: The parser validates structural correctness. It derives the expected role from an explicit task "Next actor" field or from the task's known status. Missing roles for BLOCKED tasks and contradictory actor assignments across the task and checklist are flagged explicitly without silently defaulting. The parser escapes all rendered task text to prevent Markdown/HTML injection.


## State-aware audit recovery (CUST-001 backend round 3)

Customer save audit snapshots include the reserved operational `__state`. Recovery compares both field values and state through the chain and against the final envelope. Historical field-only snapshots are read as the registered schema's initial state; persisted history is never rewritten. Explicit states are preserved and contradictions fail closed. Actor, request identity, version position and requested field changes remain exact. State-changing save recovery without an explicit state contract is refused rather than inferred.

## Vehicle management (VEH-001, 2026-10-21)

VehicleService, VehicleQuery, VehicleIdentifier and OdometerPolicy own private vehicle operations, bounded operational reads and exact baseline validation. RecordSchema declares binary optional plate/jurisdiction and VIN uniqueness plus an authoritative write guard invoked inside RecordRepository's existing pinned write unit for create/save/state changes. Active assignments/restores validate private customer records under the same serialized lock as customer archive. Missing optional tuples remain absent on legacy records; known baselines preserve original_value/unit/canonical_mm/date, with an empty tuple for unknown and a required reason for decreasing/clearing a known reading.

VehiclePage and VehicleListTable provide native classic manager mutation and staff read-only screens under velog-vehicles. POST-only action/object nonces, strict UUID/version/scalar parsing, fixed safe notices, no invalid-detail create fallback and batched customer summaries protect the HTTP boundary. Customer contact data is neither searched nor rendered. Existing admin CSS is scoped to the exact page; vehicle-only Sass preserves native controls and adds logical spacing/internal table overflow.

Developer owns backend/frontend implementation and combined SELF_REVIEWED_DEVELOPMENT under the 2026-10-21 owner decision. Tester accepted VEH-001 and its revision 3 correction on 2026-10-21. SERV-001 now replaces the conservative baseline lock for supported service records with vehicle-specific chronology; unsupported history remains fail-closed.

Revision 3 / G-11: native vehicle Year select is optional and ordered descending. VehiclePage trusts ShopSettings.distance_unit for input and uses an actor/session nonce bound to that unit to reject stale forms. VehiclePage/VehicleListTable convert canonical millimetres into the system unit for every view; historical preferred-unit metadata has no operational effect. OdometerPolicy preserves the exact authorized baseline when its rounded converted display is submitted unchanged, including a date-only correction. New or changed readings use the server settings snapshot. Missing/corrupt settings disable edits; no fixture reseeding or historical rewrite is performed.

## Service workflow (SERV-001, revision 2)

G-06 approval governs creator/manager drafts, finalization snapshots and reasoned manager corrections. New Common/Service classes reuse private service envelopes and the existing coordinator/audit/version boundary. ServiceFields and the schema record validator revalidate complete merged and stored fields; direct reads and repository writes obey draft privacy and active relation/finalization rules. No client-controlled technician or vehicle visibility flag authorizes a service.

Private service-type taxonomy rows have versioned, chained-audit termmeta. Writes use the same pinned serialized coordinator transaction as finalization, verify InnoDB for all taxonomy tables, and invalidate native caches after commit. Public/native UI/REST mutations remain disabled; service records keep authoritative type IDs/snapshots without rewriting history on rename/deactivation.

ServiceChronology orders date descending, then service ID descending, baseline after same-day finalized services. It checks both neighbours and requires a manager reason for exceptions. Current reading is computed consistently from authoritative records under the lock rather than stored as an eventually consistent duplicate. Baseline changes use the same vehicle-specific guard. Structural missing/duplicate projections and malformed canonical history fail closed. Catalog limit: 1000 types; per-vehicle history limit: 10000 services. Full G-08 performance is not established; service page hydration/current-history reads still require a future performance assessment as history grows.

Native Services/Service types screens use Settings distance units and preserved original cost currencies. Unchanged converted distance submissions retain exact original tuples. Actor/session Settings context prevents stale-unit/currency forms. Manager finalized detail exposes attributed before/after audit; technician views omit mutation controls and contact data. Service-only Sass constrains controls/audit and wraps keyboard-scrollable tables. Manual acceptance remains Tester-owned.

## CODE-001 application implementation checkpoint — 2026-10-21

HIST-001 and REM-001 source is implemented under remaining-core-contract.md. This is an implementation checkpoint with deferred functional verification, not acceptance or measured performance. Older planning and historical evidence above retain their original scope.

- ServiceHistoryQuery reads validated private finalized envelopes, batches creator/audit actor labels and filters historical fields before 50-row pagination. It fails closed beyond 10000 records per vehicle. Displayed corrections whitelist fields and escape historical content; no per-row customer/type fetch is used.
- ReminderService owns manager-only versioned lifecycle and server-generated completion attribution. ReminderPolicy derives due/upcoming/snoozed/completed/suspended without writes; date or known canonical distance is sufficient. No cron, delivery or generated service is introduced.
- CurrentOdometerIndex stores derived `_mf_velog_current_mm`, `_mf_velog_current_date` and `_mf_velog_current_service_id` projection rows. Authoritative service chronology plus vehicle baseline remains the source of truth. Unknown distance is an empty string, distinct from zero. Reminder writes initialize the index inside the pinned transaction. Existing service and vehicle writes refresh initialized indexes through schema after-write hooks before commit; any error rolls back the unit and touched vehicle caches are invalidated. No active-site backfill is run.
- ReminderQuery applies effective filters/counts before SQL pagination, then hydrates at most 50 canonical records and checks projection agreement. Concurrency, integrity fault injection, query plans and full-scale benchmarks remain deferred debt. Separate read statements are not a claimed transactionally stable snapshot; concurrent page/count drift requires later verification.

Runtime classes are included by the existing recursive release configuration. No package has been produced or verified for this checkpoint.

## QA-001 paused query optimization checkpoint

ServiceQuery now drives service reads from indexed state projections. ServiceHistoryQuery first selects bounded finalized relation IDs, then loads canonical envelopes in a batch with duplicate/cardinality validation; large audit blobs are not sorted during vehicle selection. Source does not claim one multi-statement transaction snapshot. Partial benchmark evidence and the failing reminder read are recorded in evidence/QA-001/round-1/report.md; full G-08 and matrix reruns after these changes remain outstanding.
