# VeLog product master plan

Status: APPROVED master-plan revision 3, 2026-09-18. Owner: Architect. Acceptance task: [PLAN-001](tasks/PLAN-001-master-plan.md). The user approved the MVP and authorized starting, with the correction that the product targets international markets and must support configurable units rather than fixed km/VND. This approves product direction, not implemented behavior. Original drafts remain historical inputs; this plan and recorded decisions govern their differences.

## Goal and success condition

Help one repair shop maintain a usable vehicle service record and identify the next maintenance action. The approved MVP succeeds when authorized staff can create a customer and vehicle, record a service, retrieve its history, and manage a due reminder through WordPress admin. Complete that journey on an isolated installation with permission-negative tests and user acceptance before claiming product readiness.

## Repository facts

Inspected at HEAD f04d4fc on 2026-09-18. Runtime PHP contains Core scaffolding only: Plugin, Loader, Activator and Deactivator. No product CPT or REST route registrations were found. Admin/frontend module hooks and scheduling remain TODOs. The existing tests cover constants and singleton construction, not product journeys. Tooling WF-001 is recorded DONE; its historical checks were not rerun for this planning pass.

Static inspection also found that velog.php instantiates Plugin without calling Plugin::run(); the only Loader::run() call is inside that uncalled method. Foundation work must verify hook execution and text-domain timing in real WordPress before adding feature hooks. This is a planning finding, not a runtime reproduction.

## Users and permissions — approved direction

One WordPress installation serves one shop. Multiple shops, tenant isolation and multisite support are excluded from MVP.

| Actor | Access |
|---|---|
| Administrator / shop manager | Configure VeLog, manage customers/vehicles, correct service records, manage reminder lifecycle and archive records |
| Technician | Read shop vehicle history, add service records, edit their own draft service records; no customer deletion, configuration or finalized-history correction |
| Customer / visitor / unrelated WP account | No VeLog access in MVP; a customer record does not create a WordPress login |

Use dedicated capabilities and object-level checks for every supported read/write path. Existing draft capability names manage_vehicles and log_vehicle_service remain design inputs; the foundation task must define the full mapping and assignment/removal lifecycle before implementation. Being logged in alone is insufficient. All staff in this proposed single-shop model share the shop's operational vehicle records.

## End-to-end journeys — approved direction

1. Manager creates a minimal customer record and registers a vehicle; links the current customer and checks identifiers for duplicates.
2. Technician finds the vehicle, opens a draft service, supplies date, odometer, service type and notes, and finalizes it. The vehicle timeline shows latest service date first with deterministic ordering for ties.
3. Manager corrects a finalized record with a recorded reason and author/time; historical corrections must be visible. MVP history is an operational record, not a tamper-proof certification.
4. Manager creates a date and/or odometer threshold reminder, sees due/upcoming items, and snoozes or completes it. Odometer due status depends on the latest recorded reading, not live telemetry. Completion does not silently create a service record or recurring rule.
5. Manager archives a vehicle/customer without erasing linked history. Reassigning a current owner preserves the customer association recorded for earlier services.

## Data and validation — approved direction

Use private WordPress CPTs and registered metadata for Customer, Vehicle, Service and Reminder, with a service-type taxonomy. No custom tables or external libraries are proposed for MVP; confirm query performance against an agreed dataset before accepting that design.

| Entity | Minimum content and constraints |
|---|---|
| Customer | Name and optional phone/email; internal ID; no login, marketing consent or mandatory personal identifiers |
| Vehicle | Internal ID; plate or VIN required; make/model, optional year/color, recorded odometer and current customer relation; explicit duplicate policy required |
| Service | Vehicle relation, service date, odometer, configurable type, notes, technician account, customer snapshot/reference, draft/finalized state and correction metadata |
| Cost | Optional; one configured shop currency; integer minor units or validated fixed decimal with per-record currency/scale, never binary floating-point arithmetic; no invoices/tax/payment workflow |
| Reminder | Vehicle relation, title, date and/or odometer threshold, active/snoozed/completed state and actor/time metadata |

International configuration is mandatory: km/mi distance, selectable currency and locale-aware number/date presentation with WordPress timezone support. Follow [the international product contract](internationalization.md); no fixed km/VND defaults. Unknown odometer is distinct from zero. Lower readings require an explicit correction reason; older service entry must not overwrite the current reading indiscriminately. Allow vehicles without a standard VIN; detailed normalization and uniqueness rules must be approved in the vehicle task.

Archive rather than hard-delete linked entities. Retain operational records on deactivation/uninstall by default; a future explicit purge requires its own scope. Retention/privacy requirements and any required customer erasure/export process must be resolved before production rollout. This is a product design gate, not a claim of regulatory compliance.

## MVP boundaries and draft reconciliation

| Input | Proposed MVP | Deferred / reason |
|---|---|---|
| vehicle-passport.md | Private admin customer/vehicle records, search and detail | Public URL, QR and PDF deferred; draft shareability conflicts with admin-only design |
| service-timeline.md | Draft/finalized service workflow, reverse chronology, date/type filtering, visible corrections | Export deferred; configurable types proposed; cost storage revised from float |
| photo-checkin.md | Excluded from first operational MVP | Separate phase for protected image delivery, check-in/service/check-out tags and upload limits; omit GPS/EXIF collection by default |
| maintenance-reminder.md | Manual date/odometer thresholds and staff dashboard, snooze/complete | Automatic interval rule generation, email, SMS and customer portal deferred until scheduling, retry and recipient policies are approved |

Admin-only record access must not be treated as proof of private media delivery. The photo phase requires an explicit original/thumbnail URL access design and anonymous-access tests before storing sensitive images. A public passport phase requires field-level disclosure, revocation and ownership rules. No REST API is required solely because the draft lists routes; add only routes needed by an approved UI/integration and apply the same authorization policy.

## Delivery phases and acceptance gates

These approved phases are split into bounded assignments with allowed files, detailed rules, fixtures and handoffs. CORE-001 is the first READY task; later phase work is not authorized for Builder until its task is READY.

| Phase | Dependency | Deliverable | Acceptance and verification |
|---|---|---|---|
| P0 Foundation | Approved PLAN-001; bounded tasks in order | CORE-001 bootstrap/i18n → regional settings/data primitives → capability lifecycle/private data skeleton | Real WordPress hook execution; international contract matrix; activation/reactivation; authorized vs anonymous/unrelated-account read/write checks; no public archives/search/REST leakage |
| P1 Customers and vehicles | P0; identifier/customer rules approved | Admin create/edit/search/archive and ownership relation | Valid/invalid input, duplicate policy, nonexistent relations, reassignment history, archive and unauthorized request tests; user accepts customer-to-vehicle journey |
| P2 Service history | P1; correction rules and regional foundation accepted | Draft/finalized entries, configurable types, costs and filtered timeline | Ownership/capability tests, invalid links, chronology/ties, odometer corrections, visible history corrections; user records and retrieves a complete service |
| P3 Maintenance queue | P2; reminder rules approved | Date/odometer due list and explicit transitions | Boundary dates/timezone, unknown odometer, OR trigger behavior, snooze precedence, completion and repeat submission tests; user accepts due-item workflow |
| P4 MVP acceptance | P0–P3 | Installable local ZIP and end-to-end evidence | PHP quality gates/tests, affected builds, isolated ZIP installation, full journey and negative permissions; user manual acceptance; no publication without separate authorization |
| P5 Photos | Accepted MVP plus media/privacy decision | Protected service-linked images and tags | Original/thumbnail unauthorized access, forged file types, size limits, link ownership, removal and release checks |
| P6 Sharing and notifications | Separate approved scope | Public passport/QR/PDF and/or email reminders as separate tasks | Disclosure/revocation tests; export field policy; notification deduplication, scheduling, retry and delivery evidence |

Priority is P0 → P1 → P2 → P3 → P4. P5/P6 are roadmap options, not commitments or MVP acceptance requirements. Photos and email remain deferred under the user approval. No delivery dates are promised until scope and capacity are known.

## Approved decisions and remaining task gates

P-001–P-006 are approved, with P-004 amended for international markets; see [decisions](decisions.md). Detailed permission mapping, identifier duplicates, regional parsing/catalog/conversion limits, retention procedures and performance dataset remain gates in their owning tasks. This is intentional staged design, not permission for Builder to invent product rules.

## Verification state

VERIFIED by read-only inspection: Core scaffolding, lack of product registrations, existing test scope and contradictions among the four draft plans. NOT VERIFIED: product runtime behavior, proposed permissions/data model, performance, end-to-end tests, manual product acceptance. Master-plan approval is recorded in PLAN-001. No application code, production data, builds or dependencies were changed for this plan.
