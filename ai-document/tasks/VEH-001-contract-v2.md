# VEH-001 implementation contract — revision 2

Approval: Product Owner explicitly approved the backend-first proposal on 2026-10-21: "ok cho phép làm". G-04/G-05/G-10 remain authoritative. Blueprint readiness: PASS for the bounded backend implementation below. Tests listed here are planned gates, not evidence of a pass.

## Files, ownership and impact

Developer owns `src/Common/Vehicle/{VehicleIdentifier,VehicleService,VehicleQuery,OdometerPolicy}.php`, `src/Common/Storage/{RecordSchema,RecordRepository}.php`, `src/Admin/{VehiclePage,VehicleListTable,AdminMenu}.php`, vehicle unit tests, `tests/fixtures/{veh-001-verify,veh-001-concurrent-worker}.php`, `tests/workflow/{product-smoke.sh,veh-001-endpoint.sh}`, and related tasks/docs/evidence. `src/Common/Customer/CustomerRelations.php` may accept its canonical zero sentinel on repeated validation so explicit unassignment survives both public and pinned repository validation; positive IDs and customer identity policy are unchanged. Customer regression coverage is required. `Assets.php` may add only the exact vehicle page hook for existing admin CSS; no asset content changes. Storage extensions are schema-declared optional/binary uniqueness and a trusted write guard, executed on the existing pinned transaction. Customer and DATA-001 callers without that guard retain behavior; regression fixtures are mandatory. No hook renaming or automatic schema/data migration. Existing minimal vehicle records remain readable; new optional fields are absent until an authorized save. Preserve existing customer UAT fixture data.

The same Developer also owns vehicle-only `src/css/admin.scss` and matching generated assets, with technical review covering backend and presentation together. No delegation is required; Developer owns both BE and FE. Tester owns manual acceptance. No active-site fixtures, commits, publication, service workflow, customer login or physical odometer reset.

## Field and persistence contract

- `jurisdiction`: optional plain UTF-8 text <=100; `plate`: optional <=64; `vin`: optional <=64. Plate requires jurisdiction, jurisdiction requires plate, and at least plate/jurisdiction or VIN is required. Trim display values; collapse ASCII space/tab for plate/jurisdiction, ASCII uppercase only for lookup. Reject controls, markup, malformed UTF-8 and wrong types. Preserve Unicode code points. Independent VIN and jurisdiction/plate uniqueness uses binary comparison of normalized projections across active/archived records; skip a unique rule only when its entire optional tuple is absent. Exclude edited ID.
- `year`: optional, stored 0 when explicitly cleared; otherwise integer 1886 through current site-calendar year +1. `make`, `model`: optional plain text <=100; `color`: optional <=64. Optional fields absent on legacy records remain valid.
- `current_customer_id`: optional positive private customer record ID; absent/empty means unassigned. No WP user IDs. Nonzero assignments to active vehicles, changed assignments and restores require an active existing private customer, read on the pinned handle. Archiving a vehicle may retain an archived customer relation. The write guard runs even for direct repository callers so no service bypass can violate the relation.
- `preferred_odometer_unit`: explicit `km` or `mi` when supplied; absent on legacy records means unconfigured, not a guessed unit. The create form suggests the configured shop default and requires explicit selection.
- `baseline`: empty array means unknown; known value is an exact closed tuple `{original_value,unit,canonical_mm,date}`. Distance is nonnegative ASCII decimal <=3 places with exact original-unit and canonical-mm verification; site-local date cannot be future. Empty and zero remain distinct. UI converts localized decimal input with regional primitives; no persisted float. Clearing a known baseline or decreasing it requires a manager's nonempty bounded plain-text reason, retained in audit. Changing display defaults never rewrites stored tuples.
- Baseline edits after finalized services fail closed with `service_history_locked` until SERV-001 supplies the neighbour-aware correction contract. As the service relation schema is not implemented, any finalized service prevents a baseline change rather than guessing relevance. This conservative temporary boundary is explicit; no finalized-service chronology implementation is claimed here. Reassignment does not mutate service snapshots.
- DATA-001 owns versions, audit, serialized shared write lock, rollback and create request replay. Create/updates/state transitions all reach authoritative schema/guard checks. No nested coordinator. A schema-defined guard accepts full candidate fields, prior envelope/ID, resulting state and audit reason.

## Operations and admin view

`VehicleService` delegates create/update to the repository and archive/restore to one WriteCoordinator unit; errors are stable WP_Error codes. `VehicleQuery` searches only plate, jurisdiction and VIN, with active/archived filter, stable ID ordering, 50 rows/page, strict page bounds and batched repository hydration. Customer summaries are batch-read as `{id,name,state}`; no per-row SQL. No contact search or WP account creation.

Classic `velog-vehicles` submenu requires `mf_velog_read_records`; managers with `mf_velog_manage_vehicles` can create/update/archive/restore, technicians see read-only list/detail. Page container `.wrap.velog-vehicles`, native `.form-table`, `.regular-text`, `.widefat.striped`, `.button`/`.button-primary`, `.notice`, labelled controls, stable pagination and text status. Create/edit form and list are siblings; action forms never nest. The detail request is explicitly validated; missing/wrong IDs show a safe error and do not silently switch to create mode. No async loading state (synchronous POST/redirect/GET).

HTTP mutation: `admin_post_mf_velog_vehicle_save`; POST only, allowlisted operation, exact capability, action/object-specific nonce `mf_velog_vehicle_<operation>_<id>`, scalar unslashed fields, strict positive ID/version, valid UUID request ID generated in the form, and bounded correction reason. Reject malformed input with fixed safe notices or 403, never raw database diagnostics. Non-sensitive search uses a GET form with `page=velog-vehicles`, `term`, `state`, `paged`; contacts are never queried. Pagination is built from validated arguments. No bulk mutation is included in this bounded vehicle scope. Safe attempted values may be retained only in a short-lived actor-scoped transient, never query strings; failed requests must not change data.

Fields have English translated labels, length/format help and native controls; baseline unit is shown alongside value/date. Unknown reading is labelled explicitly. Customer selection uses an optional positive record-ID input with current customer's safe name/ID summary; ID resolves only in the service under the lock. No unbounded customer dropdown or contact disclosure. States: initial, populated, empty, invalid input, denied, saved, stale conflict, duplicate, invalid relation, storage error and chronology lock. FE handles scoped layout, 320px overflow, RTL and focus; Tester verifies actual behavior and admin schemes.

## Ordered execution and gates

1. Finalize/synchronize parent/BE/FE/UAT and current focus; record PASS/readiness plus explicit approval before source changes.
2. Implement validators/baseline policy and schema/projection/guard contract; add meaningful validation tests.
3. Implement services and bounded query; verify direct-repository paths, binary uniqueness, optional tuples, owner checks, rollback, state transitions, stale versions, replay, baseline correction reasons and minimal-record compatibility.
4. Implement manager/staff PHP handlers and view contract without CSS/JS; add real HTTP positive and negative controls using the same handlers.
5. Run `composer run lint`, `composer run test -- --filter Vehicle`, `composer run test`, `bash tests/workflow/product-smoke.sh --task=VEH-001 --wp-version=6.4.3`, repeat 6.7.2 and target MySQL 8.4 lane; run CUST-001 and DATA-001 regressions for shared changes. `git diff --check` and source/security review required. Do not infer browser PASS. Reuse existing runner isolation/cleanup; scratch resources remain outside source and are removed on success/failure.
6. Record SELF_REVIEWED_DEVELOPMENT only after applicable gates pass; send FE its exact bounded contract and evidence. Missing gates keep BE open with honest next action. No automatic feature DONE.

## Verification map

- V1: missing/partial identifiers, Unicode, malformed UTF-8, VIN "0", year limits, plain-text and length/type bounds.
- V2: same/different jurisdiction, normalized binary VIN/plate, active/archived collision, self-edit, plate-only/VIN-only repeated omissions, simultaneous duplicate creation.
- V3: private customer existence/state/type, changed/unassigned owner, archive versus reassignment race, restore after customer archive; all direct repository paths enforce invariants.
- V4: unknown/zero, exact km/mi, invalid/future/local dates, forged mm, lower/cleared readings with/without reason, finalized-service lock and historical tuple preservation.
- V5: manager vs technician/subscriber/anonymous; invalid/missing nonce and replay; denied writes preserve bytes. Read-only views never contain customer contacts or mutation controls.
- V6: stale versions, rollback, audit/state/projection consistency, minimal legacy records; shared customer/storage regressions.
- V7: actual isolated HTTP create/update/archive/restore, valid nonce with denied role, detail/search/pagination, error messages and route registration. Responsive/RTL/keyboard/screen-reader/manual acceptance are deferred to the Vietnamese UAT.

## Owner amendment — 2026-10-21

The explicit sole-Developer decision supersedes separate BE/FE-agent routing. Codex owns both scope subdivisions and combined SELF_REVIEWED_DEVELOPMENT. Developer also owns the necessary read-only dashboard actor support in scripts/progress-data.mjs, scripts/progress-view.mjs and scripts/progress-client.mjs with tests/workflow/progress-roles.test.mjs and tests/workflow/progress-alert.test.mjs. Historical role labels remain parseable; current Developer/Tester focus shows two actors. This does not dispatch agents or change product data.

The existing tests/fixtures/cust-001-uat-seed.php may recognize the exact VEH-001 generated CSS hash only on its already-restricted disposable path, so CUST-001 provisioning regressions remain executable after the scoped vehicle CSS addition. Its live LocalWP pinned asset hash and authorization boundary remain unchanged; no live seed is run.

Preparation implementation adds tests/fixtures/veh-001-uat-seed.php and tests/workflow/veh-001-uat-seed.sh. plan/apply/verify is tested only on disposable sites until the Product Owner separately authorizes live fixture writes. Scope: reuse the two existing CUST-001 staff accounts, create three separate private customers, 51 VIN-only vehicles and one denied-role subscriber, with private manifest/credentials, exact site/actor checks, stable request IDs, a shared transaction, mail blocking, drift/collision refusal and mandatory live backup. Never change existing roles/passwords, reset fixtures or run live apply within this implementation authorization.

## Live preparation authorization and completion — 2026-10-21

After the separate fixture package was presented, the Product Owner replied "OK làm luôn". The exact scoped LocalWP backup/fixture apply/verify completed; see ../evidence/VEH-001-UAT/preparation-2026-10-21/report.md. Existing identities/records were preserved. This approval does not broaden runtime product scope or authorize migrations, resets, commit/deploy/release. Parent/FE/UAT now await Tester acceptance.

## Revision 3 amendment — approved 2026-10-21

Product Owner approved with "ok sửa" after reporting usability feedback. This amendment supersedes the earlier Year text field and per-vehicle preferred/baseline unit selectors. Other revision 2 boundaries remain unchanged.

- Native Year select: optional blank, descending site-current-year + 1 through 1886. Existing server validation remains authoritative for forged submissions.
- ShopSettings.distance_unit is authoritative for vehicle form input, list and detail display. Remove preferred/baseline unit selectors and ignore submitted legacy overrides. Missing/corrupt settings fail closed with explicit recovery guidance.
- Bind form distance context to the current system unit using an actor/session nonce; reject forms opened before a unit switch and discard attempted reading values rather than relabelling them.
- Preserve original baseline tuple and canonical millimetres when submitted normalized value matches the displayed converted reading; changing only date retains the original distance. Changed readings use the server settings snapshot. Canonical history is never rewritten merely by changing Settings.
- Historical preferred-unit metadata is retained only for compatibility, with no operational effect or live migration. New UI writes omit that field. All vehicle screens display the system unit.
- Allowed correction files: VehiclePage.php, VehicleListTable.php, OdometerPolicy.php, VehicleCoreTest.php, veh-001-endpoint.sh; relevant vehicle contract/task/UAT/architecture/checklist/README/decision/evidence documentation. No CSS/JS dependency changes or live fixture reseeding.
- Verify full static/unit gates and isolated authenticated HTTP domain/concurrency regressions. Add conversion/no-drift, missing/changed settings, overridden-unit, dropdown and invalid-year coverage. Tester reruns T01/T04/T07/T09/T10/T12–T15 and remaining feature acceptance cases.
