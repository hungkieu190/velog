# CORE-004 Architect proposal review — round 5

## Intake and decision

- Intake: PASS. Task, checklist, and README identify `READY_FOR_REVIEW` and Architect as next actor; Builder round-5 proposal revision 3 and report exist. This is a proposal review, not an implementation review. F-002/F-004/F-005 retain two consecutive implementation review failures.
- Decision: **REVISE PROPOSAL**. Do not implement F-002, F-004, or F-005 yet. F-001/F-003/F-006 remain unverified on the normal implementation path.

## Decision-complete correction blueprint for proposal revision 4

### F-002: stored and submitted settings

1. `ShopSettings::read_raw_option()` must use a prepared `option_name` query, distinguish a missing row from `$wpdb->last_error`, and return the raw string for the CAS path. Decode with `maybe_unserialize()` only if object instantiation is prevented, or use `unserialize( ..., array( 'allowed_classes' => false ) )` with malformed serialized data handled without warnings. A failed decode, object, unsupported `schema_version`, or unknown key must fail closed. Define the exact allowed keys and state-specific invariants: unconfigured settings may have empty unit/code and scale 0; configured settings require a positive version, `km`/`mi`, catalog-known code, catalog-derived scale and supported catalog version. Validate `region` too. Keep raw bytes separate from the validated array. `get_settings(): array|WP_Error` and every caller, especially `require_configured()`, `RegionalSettingsPage::render()`, and `save_settings()`, must propagate errors without array access on `WP_Error`.
2. For an existing row, use a prepared conditional update with a binary comparison of the raw prior `option_value`; verify case-different bytes cannot match. Distinguish `false`/SQL error from zero affected rows; retain the first-insert unique-key path and classify insert conflict versus database failure. Invalidate the appropriate WordPress option caches after a successful insert or update, and verify a fresh `get_settings()` sees the winner. Do not rewrite malformed or unknown stored data.
3. `RegionalSettingsPage::handle_save()` must type-check `velog_settings_nonce` before `wp_unslash()`/`sanitize_key()`/`wp_verify_nonce()`. Validate `record_version` as a canonical decimal string in `0..PHP_INT_MAX` before conversion; `filter_var()` alone does not reject all noncanonical forms. Type-check unit, currency, and optional region before passing them to sanitizers. Keep user input out of the redirect URL and use only fixed error codes in the transient/URL. Invalid input or stored state causes no settings write or PHP warning. List exact `WP_Error` codes for corrupt state, invalid input, stale write and database failure.

### F-004: executable evidence

4. The two-worker test must synchronize **inside** the public `ShopSettings::save_settings()` flow after each process has read the same raw row and immediately before its conditional write. The proposed barrier between an external `get_settings()` call and `save_settings()` is too early because `save_settings()` reads again. Specify a test-only seam or controlled hook, its exact location, and how it is disabled in production. Use two independent WordPress processes, separate DB connections, a timeout, and per-worker outcome files. Assert one successful write, one stale/conflict result, exactly one version increment, winner payload and exact raw row, plus cache-visible result in a fresh process. Unexpected acceptance, timeout or worker failure must make the outer runner exit nonzero.
5. The isolated service schema must include `field_policies` for every field; `RecordSchema::normalize_definition()` rejects the proposed registration without them. Include `states` (`draft`, `finalized`), read/write capabilities, and validators that reject malformed or noncanonical distance/currency values. Register before sealing in a disposable WordPress site, then use `RecordRepository::create()` with a capable actor. Assert the returned ID and successful authoritative meta row. Capture full meta bytes and parsed canonical identity; after valid settings changes through km/mi and JPY/USD/KWD plus locale changes, assert identical bytes and unchanged interpreted identity. Exercise denied actor, bad nonce and malformed POST against the actual handler; compare raw option row before/after. A check that `velog_settings` survives direct `uninstall.php` inclusion alone is not reinstall verification: either run isolated uninstall/reinstall lifecycle and check retained state, or mark that control `NOT VERIFIED` with reason.

### F-005: lint and cleanup

6. Use the installed `WordPress.WP.Capabilities` sniff (class `CapabilitiesSniff`) and its `custom_capabilities` property; the proposed `WordPress.WP.Capabilities.RoleAndCapabilityCheck` name does not identify that sniff. Provide the actual PHPCS output and the exact ruleset property syntax accepted by this installed version. Limit direct-query suppressions to the exact reported sniffs and statements; do not blanket-disable security checks.
7. The runner must create a uniquely owned disposable WordPress/database environment and register a cleanup function that validates its nonempty temp path, terminates and waits only for child PIDs it started, and removes only resources created by this run. The proposed trap's `DELETE FROM wp_options WHERE option_name = 'velog_settings'` can destroy pre-existing retained configuration and is prohibited. Avoid double-running cleanup via `EXIT`, `INT` and `TERM` traps. Record cleanup results and leave pre-existing or tracked files untouched. Write commands, versions, exit codes, manual UI observations and `NOT VERIFIED` cases to a new implementation-round evidence directory, not the read-only Builder round-5 report.

## Evidence inspected

- Builder round-5 proposal revision 3 and report.
- `ShopSettings.php`, `RegionalSettingsPage.php`, `RecordSchema.php`, `RecordRepository.php`, `PostTypes.php`, `uninstall.php`, the installed WPCS `CapabilitiesSniff.php`, and existing DATA-001 fixtures.
- No application code or tests were changed by this review. No runtime tests were run for this read-only proposal decision.

## Next action

Builder submits read-only proposal revision 4 mapping these seven numbered requirements to files, symbols, failure paths and executable assertions. Do not edit application code for escalated F-002/F-004/F-005 until Architect records `APPROVED FOR IMPLEMENTATION`. Keep their two-failure count unchanged.
