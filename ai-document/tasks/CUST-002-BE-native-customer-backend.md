# CUST-002-BE: Native customer identity and migration blueprint

> RETIRED BY G-10 (2026-10-15). The specifications and old approvals below are historical; they do not authorize native-user implementation. BLOCKED is used because the workflow has no cancelled status. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md.

## Current handoff

- Workstream: Backend
- Status: BLOCKED
- Plan revision: 3
- Implementation round: 1
- Blueprint readiness: INCOMPLETE — G-10 retires this native-user direction; no further implementation or UAT.
- Owner: Backend Architect
- Contributors and review mode: Backend Architect planning only; later implementation requires SELF_REVIEWED_BACKEND.
- Related checklist items: CUST-002 / AC1–AC4.
- Latest report: Native-user direction withdrawn by the Product Owner on 2026-10-15. Unaccepted runtime/test additions were removed after backup; earlier evidence is historical, not feature acceptance.
- Evidence: `ai-document/evidence/CUST-002-BE/feasibility-round-1/report.md`; candidate `ai-document/native-customer-write-contract.md`.
- Temporary resource cleanup: Owned disposable databases and scratch directories removed; retained reusable probes live under tests/fixtures/cust-002-feasibility/.
- Next actor: Backend Architect
- Next actor and exact next action: Do not resume CUST-002. Follow CUST-001 restoration under G-10; this document is historical only.

## Authority and inspected baseline

G-09 approves native WP_User identity and blueprint preparation only. All choices below are proposals until approved; they are not descriptions of implemented behavior.

Inspected source: `src/Common/Customer/{CustomerService,CustomerQuery,CustomerRelations}.php`, `src/Common/Storage/{RecordSchema,WriteCoordinator}.php`, `src/Core/{Capabilities,PostTypes,Plugin}.php`, `src/Admin/{CustomerPage,AdminMenu}.php`, and local WordPress `wp-includes/user.php`, `wp-includes/default-filters.php`, `wp-admin/includes/schema.php`. This is source inspection, not a database or installed-version verification.

Current customer services persist private CPT envelopes. The role registry has staff roles but no customer role. DATA-001 coordinates post/meta/option writes through a pinned mysqli handle and explicitly disallows side effects within its transaction. It cannot safely be assumed to support WordPress user APIs, users/usermeta tables, and user-hook effects without further design and verification.

WordPress checks login/email existence before insertion, but the inspected core users schema has ordinary indexes for both fields. API checks alone do not guarantee concurrent uniqueness. Core user hooks and cache effects also run before a surrounding caller could commit. These are explicit B1 design constraints.

Reference APIs: [wp_insert_user](https://developer.wordpress.org/reference/functions/wp_insert_user/), [determine_current_user](https://developer.wordpress.org/reference/hooks/determine_current_user/), and [application-password errors](https://developer.wordpress.org/reference/hooks/wp_authenticate_application_password_errors/). Verify compatibility against both minimum and recorded current test versions; latest reference pages alone are not compatibility evidence.

## Proposed identity and schema contract

Single-site only. `WP_User::ID` is the customer ID, with no offset, CPT fallback, shadow customer envelope, or dual writes. Existing unrelated users are never automatically enrolled or given a customer role.

| Field | Authoritative storage | Proposed validation / behavior |
|---|---|---|
| ID | Native users ID | Positive native user ID; immutable |
| Login | `user_login` | Required, 1–60 characters, valid WordPress strict login; reject if core sanitization changes input; immutable after create |
| Email | `user_email` | Required, valid `is_email`, at most 100 characters to fit core schema; reject changed sanitization, missing values and duplicates including archived users |
| Name | `display_name` | Required trimmed Unicode plain text, 1–200 characters; no name copied into VeLog metadata |
| First/last names | Native WordPress user meta | Not required or inferred by splitting the display name; preserve existing values |
| Phone | `_mf_velog_customer_phone` user meta | Optional trimmed Unicode plain text, at most 64 characters; no country-specific normalization |
| Customer marker | `_mf_velog_customer_schema` user meta | Integer schema version 1; role alone is insufficient for a valid customer |
| State | `_mf_velog_customer_state` user meta | Closed enum: pending, invited, active, archived |
| Version | `_mf_velog_customer_version` user meta | Positive decimal integer; starts at 1, increments once per committed domain mutation |
| Invitation | `_mf_velog_customer_invitation` user meta | Versioned manager verification and delivery-attempt metadata; no password, raw reset key, or reset URL |
| Migration marker | `_mf_velog_customer_legacy_id` user meta | Positive source CPT ID only for migrated customers; provenance, not an alternate identity |

Namespaced meta is private (`show_in_rest: false`) and protected by explicit authorization callbacks. Reject missing, duplicate, corrupt, or unsupported schema/state/version rows; do not silently repair them on read. Meta types must be specified by the B1 storage design, including serialized/raw database comparison rules.

Audit storage is proposed as a private append-only operational ledger, not a second customer identity store. Entries contain event ID, native user ID, actor ID, UTC timestamp, expected/result version, action, request ID, reason, and changed field names. Avoid duplicating current name/email/phone in new audit entries. Legacy audit remains intact in the frozen source. Exact ledger persistence and indexes are a B1 approval gate; do not add an unapproved table during implementation.

Every mutation receives a server-resolved actor, allowlisted payload, request UUID, and expected version except initial create. A request replay with the same actor/action/target/payload returns its original outcome; reuse with a different payload is a conflict. Durable request records must not store plaintext credentials or contact payloads. Retention cannot silently prune idempotency/audit records before an approved policy exists.

## Role, privacy, and native user administration

Propose `mf_velog_customer` with only `read`, no VeLog staff capabilities, no authoring, upload, user-list/edit, or application-password permission through VeLog. Preserve role collision/ledger checks from CORE-003. VeLog managers retain their domain capability without receiving broad `create_users`, `edit_users`, or `promote_users` capabilities. Customer-domain handlers may create only the exact customer role and cannot modify arbitrary native users or staff roles.

Propose no staff/customer mixed-role enrollment. A native administrator or another existing user cannot be selected implicitly by matching email. Explicit existing-account conversion is outside this task; report it as a conflict. Unknown/mixed role combinations fail closed for customer authentication and mutations without removing other roles automatically.

Managers with `mf_velog_manage_customers` and `mf_velog_read_customer_contacts` may view/edit the customer list and contacts. Technician-safe summaries expose exactly ID, name, state; do not return WP_User objects, login, email, phone, reset state, or audit contacts. Unrelated users receive no customer API access. Search of email/phone requires contact capability; no contact searches in public URLs or logs. Use manager-only POST search with pagination preserving an opaque scoped search token, or obtain explicit approval for a different privacy contract.

Native user fields are not automatically private merely because VeLog uses protected meta. Required coverage includes REST user lists/detail, author archives and `?author=ID`, sitemaps, feeds, XML-RPC user methods, native user lists and profile pages. Native administrators remain trusted WordPress administrators. Do not grant customers post authorship or create a public portal. Protect namespaced fields from generic profile/REST editing. Hooks on native profile changes/deletion/role changes must prevent bypass or flag an integrity conflict; do not silently overwrite WordPress core fields from a mirrored store.

## Proposed lifecycle and authentication

| Action | Prior state | Result and conditions |
|---|---|---|
| Create | Absent | Pending, exact customer role, unknown random initial password generated in memory by WordPress APIs; no automatic email |
| Verify and invite | Pending / invited | Manager verifies identity; audited native reset-key invitation; invited only after accepted local mail handoff; retry has an explicit new attempt ID |
| Complete invitation | Invited | Native valid unexpired reset key, current verified invitation generation and unchanged email; native reset succeeds, then active |
| Change email | Pending / invited / active | Manager only, required unique valid email; return to pending, invalidate old invitation/reset keys and sessions |
| Archive | Pending / invited / active | No active vehicle relation; archived, invitation revoked and sessions revoked |
| Restore | Archived | Pending; preserve native ID, contacts and history; a new verified invitation is required |
| Ordinary password reset | Active | Native reset flow; remains active; no domain privilege changes |

Archive/restore follows G-03's active-vehicle guard and version/audit controls; restore-to-pending is a proposed G-09 lifecycle amendment requiring B2 approval. Password reset does not activate pending or archived accounts. Invited reset permission must be scoped to a verified current invitation, not a blanket allow for any pending account. Reissued keys, changed email, archive, and failed version checks invalidate the activation attempt. Successful password reset with failed domain activation remains denied and reports recoverable failure; never activate merely because a callback fired.

Server enforcement must cover password username/email login (`wp_authenticate_user` plus final `authenticate` guard), cookie/current-user resolution (`determine_current_user` after native callbacks), application-password issuance/authentication, XML-RPC and REST authentication. Every customer-specific entry point must re-read valid state and role policy; generic login errors must not enumerate contacts or expose state. Remove existing sessions when leaving active, and reject stale cookies even if session invalidation fails. Do not alter authentication for unrelated non-customer users, especially the local administrator.

Use native reset APIs and key expiry checks; never return keys or passwords to manager screens or evidence. A successful `wp_mail` return means accepted for sending, not delivered. LocalWP mail capture must be verified and selected before any invitation UAT; external delivery/configuration is out of scope. Define retry and ambiguous-send behavior under B3 before coding; database rollback cannot retract mail.

## B1: Persistence, concurrency, and failure boundary

Required invariant: a successful operation represents verified native fields, customer metadata, version, audit, and idempotency outcome. No successful result after partial failure. No blind compensating write may overwrite an intervening native profile update.

Recommended investigation for approval: extend the existing shop-wide write coordination discipline to native users/usermeta and a minimal approved audit/request ledger, while retaining native APIs as the only identity writer. Require InnoDB for all touched tables, one pinned connection, deterministic lock ordering, exact native-row/meta snapshots and expected version checks, post-write verification, and cache invalidation after success and rollback. No direct SQL writes to native user identity fields. Do not simply call user APIs inside the current WriteCoordinator: user hooks can send mail or invalidate caches before commit.

Before this can become PASS, specify and prove in disposable fixtures:

1. How native API callbacks use the pinned connection; reconnect, nested transaction, hook exception, rollback and ambiguous COMMIT handling.
2. Which hook side effects are permitted, deferred, or incompatible; no global suppression of unrelated plugins or unverified atomicity claim.
3. How identity uniqueness is serialized against every supported writer, including native Users screens, REST, CLI and imports. A VeLog-only lock does not cover third-party API writers. Do not add UNIQUE indexes to core tables without separate schema-change approval.
4. How native edits, role removal, hard deletion, duplicate metadata, persistent cache, and request retry preserve the contract. Authentication must fail closed during incomplete writes and on corrupt or ambiguous identity.
5. The exact ledger schema and retention, raw metadata representation, transaction ownership and lock shared with vehicle assignment/archive.

If the native APIs cannot meet these invariants on the supported compatibility boundary, return an explicit revised persistence proposal for approval. Do not implement a shadow user store, claim universal race safety, or weaken G-09 silently. Environment/compatibility restrictions require explicit Product Owner approval and an executable preflight, not a documentation disclaimer alone.

## Migration contract

No migration on activation, ordinary page load, login, or plugin update. No active LocalWP database migration is authorized by this document.

1. **Inventory/dry-run:** manager-authorized explicit action; fixed ascending source-ID batches of at most 50, resumable cursor and run ID. Read raw private customer envelopes/audit and every inbound relation before writing. Report missing/invalid login, email >100 characters, duplicate login/email within legacy and native data, corrupt versions, ambiguous links, existing roles and unsupported environments. Dry-run changes no records and sends no email.
2. **Remediation:** old customer records do not contain a login; require manager-supplied explicit per-source mapping. Never synthesize a final login, merge by email, truncate email/name, or convert an existing native account automatically. Store reviewed mappings and source fingerprints in the migration run, with restricted access and audit. Source edits invalidate approval for that item.
3. **Preparation:** approve a redacted report, backup/restore procedure and maintenance window. Freeze old customer writes and all relation mutations before apply; verify freeze server-side. Re-read native uniqueness, source version/fingerprint, and relation references under the approved B1 boundary before each item.
4. **Apply:** create one pending native customer per resolved active source, or archived for an archived source; no invitations are sent. Retain old audit with the original actor/time IDs. Persist a unique durable mapping `(run, legacy ID) -> native user ID` and item checkpoint atomically with the identity operation. No mapping without a verified native target. No target without a recoverable request/provenance marker. B1 defines the precise storage; postmeta is not a uniqueness guarantee.
5. **Relations:** enumerate actual vehicle/service/reminder links from schema, including archived records and historical snapshots; distinguish current-owner references from historical identity. Write live-owner references only after native targets are verified. Persist a relation schema version so equal numeric CPT/user IDs cannot be mistaken for migrated references. Preserve original historical snapshot values, and use an explicit typed legacy-to-native resolution for historical IDs. Never reinterpret every numeric ID automatically.
6. **Resume/failure:** each item records pending/applied/conflict/failed with a bounded reason. Resume verifies native target, mapping, source fingerprint and checkpoint; applied items are verified and skipped, never recreated. A conflict pauses that item and blocks final cutover. Crash before/after each checkpoint and lost response must have deterministic recovery tests.
7. **Cutover:** require zero unresolved conflicts, complete relation reconciliation and counts, valid account states, and explicit manager approval. Switch the application reader to native identity once; no dual-read fallback. Keep the old CPT registered privately as a frozen migration archive while retained records exist, but remove it from operational customer CRUD. Old contact projections must not feed native customer search. Do not delete legacy records/audit or unregister privacy controls prematurely.
8. **Rollback:** pre-cutover failed items roll back only through the approved B1 boundary. Do not delete native users to undo a batch. After native activity or cutover, rollback requires a reviewed forward repair or full backup restore with downtime; it is not a reversible toggle to the stale CPT store.

Completion does not assert that legacy customers are verified account owners. Active legacy customers start pending; archival state remains archived. Backup retention and eventual legacy-data disposal are outside this task and require separate approval.

## Vehicle dependency contract

After blueprint approval only, rebase VEH-001-BE on native user IDs and relation schema version. Proposed assignable customer: valid schema, dedicated customer role, and non-archived state (pending/invited customers may own vehicles before logging in). Customer login eligibility and operational ownership are separate rules. This B2 product choice must be approved.

Archive checking and vehicle assignment/reassignment must share the same write lock and re-read state. Deletion of a linked native user must be denied through supported native administration paths; explicit external SQL tampering is detected as invalid ownership, never resolved to a same-numbered CPT. Existing service snapshots are preserved. Never treat a successful customer migration as approval of vehicle implementation.

## Proposed implementation change map

Planning authorization does not authorize these source edits. Finalize exact added filenames and the ledger schema after B1 before marking READY.

- Backend: `src/Common/Customer/` service/query/relation replacement and new native store, account policy, invitation, migration and audit classes; `src/Core/{Capabilities,Activator,Plugin,PostTypes}.php`; `src/Common/Storage/{WriteCoordinator,WriteUnit,RecordSchema}.php` only for reviewed reusable coordination and legacy retirement; `src/Admin/{CustomerPage,CustomerListTable,AdminMenu}.php` and a proposed migration admin controller. No role reassignment of existing staff.
- Tests: named customer unit/integration tests under `tests/Unit/` and `tests/Integration/`, an isolated `tests/fixtures/cust-002-verify.php`, and task registration in `tests/workflow/product-smoke.sh` if needed. Exact test file list is a readiness requirement.
- Documentation: parent/BE/FE/UAT tasks, checklist/index, architecture after implementation, local setup walkthrough and redacted execution evidence.
- Frontend: separately assigned CUST-002-FE after backend view contracts stabilize; only scoped source styling/interaction and generated assets. Backend owns PHP rendering/handlers. No FE dispatch from this draft.
- Prohibited: customer portal, public REST CRUD, marketing email, new dependencies, source implementation before approval, live migration, commit/push/deploy/release.

## Ordered implementation plan after approval

1. Close B1–B4; freeze schema, API signatures, typed error codes, exact change map and fixture commands; validate parent/checklist/Next actor synchronization.
2. Implement role/account policy and negative authentication coverage before enabling account creation.
3. Implement native persistence, protected metadata, audit/version/request replay, bounded manager search and contact-safe summaries. Re-run existing DATA-001 and CORE-003 regressions.
4. Implement explicit invitation/reset lifecycle and failure recovery, with fixture mail capture only.
5. Implement migration dry-run, remediation and resumable apply; exercise injected failures before cutover support.
6. Replace customer admin handlers with capability checks, action-specific nonces, scalar validation, fixed notices and POST/redirect/GET; define stable FE contract.
7. Rebase vehicle relations in a separately synchronized task after approval; create all UAT prerequisites explicitly.
8. Inspect backend diff, run gates, record SELF_REVIEWED_BACKEND. Review FE source/generated assets when ready, then hand Vietnamese UAT to Tester. Parent cannot become DONE from build success.

## Verification matrix and required evidence

| Case | Required assertion |
|---|---|
| N01 Identity | Valid Unicode display name; missing/changed/long login or email; 100/101 email boundary; no silent core truncation |
| N02 Duplicates | Existing staff/archived users, email/login case/collation equivalents, same-value update, concurrent VeLog and supported native writers |
| N03 Authorization | Manager allowed, technician/subscriber/customer/anonymous denied; direct service callers, forged roles/IDs and invalid nonce fail |
| N04 Privacy | Contact-safe summary, native user endpoints/archives/sitemaps, profile meta writes and native list permissions; no raw WP_User output |
| N05 Auth | All four states and corrupt metadata over password, cookies, REST, XML-RPC and application passwords; unaffected admin login |
| N06 Invitation | Verify/send/reset, expired/reissued key, email change, archive race, failed/ambiguous mail, failed activation and retry |
| N07 Integrity | Stale version, replay conflict, API/filter mutation, duplicate meta, hook exception, DB/cache/COMMIT failure and reconnect |
| N08 Migration | Dry-run zero writes; missing login/email, colliding IDs, conflicts, archived source, crash/resume, fingerprints, all inbound relations, cutover refusal |
| N09 Ownership | Pending/invited ownership proposal, archive versus assignment race, hard delete, no historical snapshot rewriting |
| N10 Admin | Bounded prepared list/search, stable name/ID sort, 50-row pagination, mixed per-item bulk results, notices without secrets |
| N11 Regression | DATA-001 storage, CORE-003 roles/privacy, CORE-004 settings, uninstall/reinstall retention and release exclusion of tests/secrets |

Future gates: `composer run lint`, targeted native customer tests, full `composer run test`, relevant workflow tests, isolated WordPress fixtures on 6.4.3 and 6.7.2 plus the recorded LocalWP version where different, and `git diff --check`. CUST-002 fixture registration/flags must be implemented and documented before claiming its smoke command exists. No gates have run for application behavior during this planning turn.

LocalWP reference: [local environment](../local-environment.md). Do not read credentials into evidence. Use disposable isolated sites for destructive/concurrency/failure fixtures. The active LocalWP site is reserved for explicitly prepared manual UAT. Record actual PHP/WP/database versions, storage engines, cache configuration, commands/exits and limitations before reporting any gate. Remove owned temporary resources and inspect git status before handoff.

## Open approval gates

- **B1 — compatibility boundary APPROVED; technical readiness pending:** Disposable native API primitives passed; the hook-side-effect negative control prevents a claim of arbitrary-plugin atomicity. Candidate connection/lock/ledger contract is in `../native-customer-write-contract.md`. Product Owner approved the bounded first adapter on 2026-10-15 with "ok tôi duyệt". Do not request this approval again. Production atomicity, cache and failure behavior remain unverified.
- **B2 — APPROVED:** Product Owner's 2026-10-15 reply approves immutable strict login, core-compatible 100-character required email, restore/email-change returning to pending, no mixed-role conversion, and pending/invited vehicle ownership. See decisions.md. Do not request this approval again.
- **B3 — concrete candidate:** Native invitation, post-commit handoff, ambiguous send, generation revocation and reset activation sequencing are specified in the write contract. Implementation verification remains required; real external delivery is excluded.
- **B4 — partially resolved:** Exact proposed runtime/test files, source relation inventory, remediation retention and reproducible feasibility runner are specified. Supported callback/version coverage, native reset integration and exact FE view contract still need finalization against the accepted adapter. VEH-001 remains blocked.

Retained probe scope authorized by the continued feasibility work: `tests/fixtures/cust-002-feasibility/{adapter.php,verify.php,worker.php,side-effect.php,run.sh,run-isolated.sh}`. These are disposable-environment verification tools only; no prototype adapter is loaded by the plugin.

### Chat handoff prompt

```text
Status: BLOCKED
Recipient: Backend Architect
Intent: work

G-10 retires CUST-002 without acceptance. Preserve historical evidence and follow CUST-001 restoration; do not implement native customer accounts, invitation/reset hooks, or migration. See ai-document/evidence/CUST-001-BE/architecture-restoration-2026-10-15/report.md. No active-site database changes, commit, deploy, or release.
```
