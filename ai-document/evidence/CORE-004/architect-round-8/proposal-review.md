# CORE-004 Architect proposal review — round 8

## Intake and decision

- Intake: PASS. Task, checklist, and README consistently identify `READY_FOR_REVIEW` and Architect as next actor. Builder round-8 proposal revision 6 and report exist. No escalated application code was changed.
- Decision: **REVISE PROPOSAL**. The implementation gate remains closed for CORE4-F-002, F-004, and F-005.
- Revision 6 resolves the PHP union syntax, fixed historical seed validators, unsafe default-socket database deletion, native product-smoke ownership model, and 130/143 signal plan. Only the defects below remain.

## Blocking defects

1. **F-002 duplicate classification:** Matching the English text `Duplicate entry` in `$wpdb->last_error` is brittle. Use the database error number from the active `$wpdb->dbh` and classify only MySQL/MariaDB errno `1062` as `stale_version`; every other `false` result is `db_error`. Guard the handle type and provide a unit/integration oracle for duplicate versus unrelated failure.
2. **F-004 repository result shape:** `RecordRepository::get()` returns `array|WP_Error`, not a domain object. The proposed calls to `get_distance()`, `get_cost()`, `get_version()`, and `get_created_by()` will fail. Check `is_wp_error()`, then assert array keys `fields`, `record_version`, `created_by`, and `audit`, including the fixed seed fields.
3. **F-004 disabled assertions:** Do not use PHP `assert()` for evidence because runtime configuration can disable it. Use explicit conditions that record a failure and make the outer fixture exit nonzero.
4. **F-004 nonce contract:** The application verifies nonce action `velog_save_settings`, but the proposal creates nonces for `velog_settings_nonce`. More importantly, a nonce created in a separate WP-CLI session does not share the authenticated browser session token. Log in with curl, request the settings page using that cookie, and parse the `velog_settings_nonce` field generated for action `velog_save_settings` from the HTML. Use each actor's own cookie and nonce.
5. **F-004 HTTP outcomes:** Current `wp_die()` calls do not explicitly pass response 403, and the product-smoke normalizer does not reliably map `Unauthorized` or `Invalid nonce.` to 403. The proposal's exact 403 oracle is therefore unsupported. Include application changes that call `wp_die()` with explicit response 403, then assert exactly 403. Specify malformed array input such as `distance_unit[]=km`, exact fixed redirect/error behavior, and exact raw database bytes before/after denied requests.
6. **F-004 runner integration:** Every `wp` command used by the endpoint helper must include `--path="$WP_DIR"`; the helper must receive/export the existing random `$PORT`, `$WP_DIR`, socket context and owned output directory from `product-smoke.sh`. Do not create another server or fixed port.
7. **F-005 evidence directory:** `builder-round-8` now contains the read-only revision-6 proposal and report, so it cannot also be the new implementation evidence directory. Use `ai-document/evidence/CORE-004/builder-round-9/` for implementation commands, tests, cleanup and manual UI evidence.

## Repeated-defect status

- CORE4-F-002 insert-conflict classification remains unresolved, now narrowed to stable errno handling.
- CORE4-F-004 still maps the repository API and real HTTP authentication incorrectly despite prior symbol/oracle corrections.
- CORE4-F-005 lint and cleanup plans are now adequate at proposal level; only the occupied evidence path requires correction.
- Proposal reviews do not increment implementation-review failures. F-002/F-004/F-005 remain at two consecutive implementation review failures.

## Evidence inspected

- Builder round-8 proposal revision 6 and report.
- `RecordRepository::get()` and `build_snapshot()` return contract.
- `RegionalSettingsPage` nonce action and `wp_die()` calls.
- Existing CORE-004 fixture and `product-smoke.sh` random-port/private-datadir lifecycle.
- Valid PHP union syntax was independently confirmed. No application or test code was changed by Architect.

## Required revision 7

Submit a read-only delta addressing only defects 1–7. Preserve all resolved revision-6 decisions. Do not implement F-002/F-004/F-005 until Architect records `APPROVED FOR IMPLEMENTATION`.

F-001, F-003, and F-006 remain unverified on the normal implementation path.
