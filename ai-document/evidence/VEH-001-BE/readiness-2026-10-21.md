# VEH-001 backend readiness review — 2026-10-21

Owner: Backend Architect (Codex).
Review type: read-only source inspection and implementation-scope proposal.
Readiness: INCOMPLETE; implementation remains BLOCKED.

## Dependency and authorization

The Tester explicitly confirmed all remaining CUST-001-UAT cases passed on 2026-10-21 and requested the next core workstream. CUST-001, CUST-001-FE and CUST-001-UAT are now DONE. CUST-001-BE remains the existing SELF_REVIEWED_BACKEND technical checkpoint. The next dependency-ordered feature is VEH-001; CORE-001 through CORE-004 are already accepted.

G-04, G-05 and G-10 remain approved product rules. The next-work instruction authorizes readiness investigation and planning. The concrete implementation proposal below must be presented to the Product Owner before source changes, following AGENTS.md's explicit scope-approval requirement. No new product rule is accepted by this report.

## Inspected source and concrete gaps

| Area | Current evidence | Required work |
| --- | --- | --- |
| Identity | `src/Common/Vehicle/VehicleIdentifier.php` validates and projects jurisdiction, plate, VIN and year; only two unit tests cover this class. | Expand complete-record validation, malformed UTF-8, optional identifier combinations, boundary years and normalization cases. Preserve approved Unicode/nonstandard VIN behavior. |
| Duplicate protection | `RecordSchema.php` registers the minimal vehicle fields and projections but no vehicle `unique_keys`. `RecordRepository::check_unique_keys_under_lock()` requires every declared unique projection to exist. | Define optional plate/jurisdiction and VIN uniqueness without treating missing identifiers as duplicates or rejecting valid plate-only/VIN-only records. Verify comparisons use approved normalization rather than accidental database collation rules. |
| Owner relation | `CustomerRelations::validate_customer_id()` accepts an optional positive integer; it does not prove that a referenced private customer exists and is active. Customer archive already checks active vehicles on the pinned write handle. | Validate assignment/reassignment/restore against private customer records inside the same DATA-001 write unit, closing the race with customer archive. Preserve the reserved current-customer projection atomically. |
| Vehicle domain | No `VehicleService`, `VehicleQuery` or odometer policy class exists in the inspected source. | Add bounded domain operations, queries and baseline validation using existing storage, capability and regional primitives. |
| Baseline | The vehicle schema currently has no baseline distance/date, preferred unit, make, model or color fields. `Distance` and `CalendarDate` already supply exact regional primitives. | Specify the full field schema and compatibility behavior for existing minimal vehicle records, including the customer UAT fixture. Do not rewrite existing records during plugin load. |
| HTTP/admin | `AdminMenu` wires Settings and Customers only; no `VehiclePage` or `VehicleListTable` exists. | Define manager mutations and authorized staff read-only views, native WordPress markup, action-specific POST/nonces, safe feedback, bounded search and pagination. |
| Verification | Only `VehicleIdentifierTest.php` exists for vehicle-specific coverage; no vehicle integration/HTTP fixture exists. | Add isolated domain, permission, transaction and HTTP verification plus regression checks for customer archive and DATA-001. |
| Task scope | Parent proposes classes directly under `src/Common/`; BE permits `src/Common/Vehicle/`. Neither defines a complete current view contract or linked FE/UAT task. | Reconcile both tasks to `src/Common/Vehicle/`, explicitly allow any required storage extension, and create bounded FE and Vietnamese UAT blueprints before implementation. |

These are implementation/readiness gaps, not failed vehicle UAT results. No new runtime checks, lint, build, database probes or browser tests were executed by this review.

## Proposed implementation sequence

1. Finalize the BE blueprint against the existing G-04/G-05/G-10 decisions. Define field types, limits, optional values, normalized uniqueness comparisons, migration-free handling of minimal records, stable errors and the manager/staff projection contract. Record proposed engineering choices as proposals until scope approval.
2. Implement the vehicle core first under `src/Common/Vehicle/`: `VehicleService`, `VehicleQuery`, the existing `VehicleIdentifier`, and `OdometerPolicy`. Extend `RecordSchema` and, only where necessary, `RecordRepository` to enforce authoritative invariants on both repository and service write paths. Keep customer assignment and active-customer checks within the shared pinned write transaction; do not add a second transaction or a racy pre-check.
3. Enforce identifier uniqueness across active and archived vehicles, excluding the edited record itself. Distinct omitted identifiers remain allowed. Preserve display values separately from lookup normalization. Test simultaneous duplicate creation and reassignment versus customer archive.
4. Store known baseline distances with original decimal value/unit, canonical millimetres and a non-future site-local calendar date. Unknown remains distinct from zero. Reuse `Distance`/`CalendarDate`; no binary-float persistence. Preserve existing baseline identity when display defaults change. Define a fail-closed boundary for finalized-service chronology; SERV-001 owns service finalization/correction implementation, and physical resets remain excluded.
5. Add PHP admin handlers and native list/detail/create/edit/archive/restore markup after the domain contract is stable. Managers mutate; technicians only read authorized operational fields and customer summaries `{id, name, state}`. Never expose customer phone/email through views, search, errors, URLs or hidden fields. Declare exact capability, nonce, stale-version and request-ID rules per action. Use deterministic bounded pagination and batched hydration.
6. Self-review BE source and relevant shared storage changes. Run targeted/unit/static and isolated WordPress gates. Only then hand the stable presentation contract to Frontend Developer for separately assigned CSS/JS/generated assets, if needed. Backend Architect does not implement presentation assets.
7. Prepare explicit UAT prerequisites and a Vietnamese VEH-001-UAT with actual environment/account/data requirements, numbered actions, expected results, negative/concurrency cases, responsive/RTL/keyboard/screen-reader checks and a per-case PASS/FAIL/BLOCKED table. Active-site fixture writes require their own bounded authorization. Only Tester accepts the completed feature.

## Proposed file boundaries

- Backend runtime: `src/Common/Vehicle/`, `src/Common/Storage/RecordSchema.php`, `src/Common/Storage/RecordRepository.php` if needed for shared authoritative guards, `src/Admin/VehiclePage.php`, `src/Admin/VehicleListTable.php`, `src/Admin/AdminMenu.php`, and `src/Core/Plugin.php` where composition requires it.
- Existing `CustomerRelations` is a consumed contract; any change requires an explicit addition to the reconciled BE file list and customer regression coverage. No customer identity redesign is proposed.
- Backend verification: specifically named vehicle tests under `tests/Unit/`, isolated fixtures under `tests/fixtures/`, and the bounded `--task=VEH-001` runner/HTTP/concurrency helpers under `tests/workflow/`.
- Documentation: parent/BE/FE/UAT tasks, architecture/domain documentation, checklist, README and task evidence.
- Excluded: CSS/JS/generated assets in BE, customer WordPress accounts, public/REST endpoints, service workflow implementation, physical odometer resets, automatic data migration, active-site writes, commits, deployment and release.

## Required executable verification matrix

| Case | Required assertion |
| --- | --- |
| V1 identity | Plate with jurisdiction, VIN-only, both identifiers, Unicode, malformed input, year boundaries, same plate in different jurisdictions and optional identifiers behave as specified. |
| V2 uniqueness | Normalized duplicate create/update, archived identifiers, edit-self, case/Unicode comparison and concurrent creates preserve one authoritative winner without silent merge. |
| V3 relations | Missing/wrong-type/archived customer is rejected for new assignment; reassignment and customer archive racing under the shared coordinator leave a coherent result and immutable historical snapshots. |
| V4 baseline | Unknown versus zero, km/mi precision, local dates, future dates, invalid tuples, preference changes and lower readings follow the approved chronology and audit contract. |
| V5 permissions | Manager mutation succeeds; technician reads only operational projections; denied actors and invalid/missing nonces cannot change state or discover contacts. |
| V6 integrity | Stale versions, request replay, archive/restore, SQL failure/rollback and existing minimal vehicle records preserve state, projections and audit consistency. |
| V7 admin | Real isolated HTTP form/list/detail/search/pagination requests exercise the same production validators and handlers, including safe failure notices and recovery. Browser observations remain Tester-owned. |
| V8 regressions | Existing customer relation guards, repository recovery and regional parsing remain covered after shared changes. |

Planned commands: `composer run lint`, `composer run test -- --filter Vehicle`, `composer run test`, isolated product smoke for VEH-001 on WordPress 6.4.3 and 6.7.2, the target MySQL 8.4 compatibility lane, relevant CUST-001/DATA-001 regressions, and `git diff --check`. The VEH-001 runner entry does not exist yet; these are required future gates, not passed commands. Frontend production builds apply only when the assigned frontend scope changes assets.

## Remaining readiness gate and cleanup

The Product Owner must approve the concrete backend-first implementation scope after presentation in chat. Backend Architect must then complete and synchronize the exact field/view/failure/fixture contracts and BE/FE/UAT file maps before marking readiness PASS. No runtime work starts from the current BLOCKED task.

No scratch scripts, databases, processes, browser sessions or temporary directories were created. Only retained task/workflow/acceptance documentation was changed; existing uncommitted documentation changes were preserved.
