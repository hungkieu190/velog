# CORE-004 Architect review — round 1

## Intake and result

- Date: 2026-10-02.
- Scope: Builder round 1 against CORE-004 revision 3; uncommitted DATA-001 work excluded.
- Intake: FAIL. Task and checklist say READY_FOR_REVIEW, but README says READY/Builder. Task still has the old READY prompt and says the implementation report was not started. Builder report exists at `ai-document/evidence/CORE-004/builder-round-1/report.md`, but no commands.log, verification matrix, or manual walkthrough evidence exists.
- Decision: CHANGES_REQUESTED. No acceptance criteria are verified or accepted.

## Findings

- **CORE4-F-001 — atomic update absent (AC2, critical):** `ShopSettings::save_settings()` calls `update_option( OPTION_NAME, $new_settings, $current )`. WordPress defines the third argument as `$autoload`, not the prior value. Two concurrent saves can both pass the read check and overwrite one another. Replace this with an atomic conditional update on the stored option value/version, check exactly one affected row, invalidate option cache on success, and test two competing writers. Preserve the prior envelope on failure.
- **CORE4-F-002 — invalid data accepted or returned (AC1/AC2):** `ShopSettings::get_settings()` merges arbitrary option arrays and `require_configured()` trusts the `configured` flag without validating unit, catalog code, scale or schema. `save_settings()` casts submitted arrays to strings and sends arbitrary `region` types to `sanitize_text_field()`. Validate allowlisted scalar fields and the stored envelope; malformed/corrupt settings must yield a controlled setup/error state without warnings or invalid defaults. Derive and store the catalog version as specified.
- **CORE4-F-003 — CSS URL points to missing file (AC4):** `Assets::enqueue_styles()` emits `build/admin.css` through `plugin_dir_url( dirname( __DIR__, 2 ) )`; the built asset is `assets/css/admin.css` and no `build/` directory exists. Use the plugin URL constant or a verified plugin-root path and enqueue only on exact VeLog hook suffixes. Assert the enqueued URL resolves to the generated file and is absent on unrelated pages.
- **CORE4-F-004 — verification fixture does not prove V2/V3 (AC2/AC3):** `tests/fixtures/core-004-verify.php` checks `current_user_can()` and `wp_verify_nonce()` in isolation, never invokes the POST handler for denied actors, and prints historical preservation without creating or comparing a DATA-001 record. Exercise actual handler and persistence paths, verify no forbidden write, run true concurrent stale writes, then compare historical payloads before and after settings changes. A printed PASS without assertions is insufficient.
- **CORE4-F-005 — required gates and documentation incomplete (AC4/handoff):** Independent `composer run lint` exited 1 (PHPCS: missing docs in `Plugin.php` and `ShopSettingsTest.php`, plus comment errors). Builder report has no exact command exits or versioned smoke evidence. The planned internationalization/architecture updates and manual keyboard, 320px, long-string, RTL, and unrelated-screen walkthrough are absent. Complete them or mark genuinely unavailable checks NOT VERIFIED with reasons; synchronize task, checklist, README and handoff prompt.

## Independent checks

- `composer run test -- --filter ShopSettingsTest`: exit 0, 1 test, 12 assertions. Mocks do not prove atomic persistence or HTTP authorization.
- `composer run lint`: exit 1 at PHPCS; PHPStan was not reached.
- `git diff --check`: exit 0.
- WordPress source `wp-includes/option.php`: `update_option( $option, $value, $autoload = null )` at line 846.
- `assets/css/admin.css` exists; `build/admin.css` does not.
- No independent WordPress smoke or manual UI run was performed in this review because the fixture does not assert the required paths and the intake metadata is inconsistent.

## Builder correction blueprint

Fix F-001 through F-005 in one round. Preserve unrelated DATA-001 changes. For F-001, do not use `update_option()` as CAS: use a prepared conditional SQL update tied to the exact prior serialized value or another demonstrably atomic primitive, and check affected rows before reporting success. Ensure the first insert is unique, the option remains non-autoloaded, and caches are invalidated only after committed writes. For F-002, strictly validate stored and submitted scalar values before casts/sanitization; return typed errors and never invent a currency scale. For F-003, use the generated asset's real URL and an exact hook allowlist. For F-004, make the disposable fixture fail nonzero on any missing rejection, unexpected write, stale race, or changed historical payload. Run the full required quality and WordPress matrix, retain logs and cleanup results. Report every remaining NOT VERIFIED case explicitly.
