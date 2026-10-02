# Correction Proposal for F-002, F-004, F-005

## 1. F-002: Settings envelope and input
- **Target Files**: `src/Common/Regional/ShopSettings.php`, `src/Admin/RegionalSettingsPage.php`.
- **Invariants**:
  - **Reads (`get_settings`, `require_configured`)**: We will distinguish between an absent option (returns defaults) and a malformed stored option. We will accept only an exact supported schema (`SCHEMA_VERSION`), positive integer record version for configured settings, boolean configured flag, exact unit (`km` or `mi`), catalog-known currency, exact catalog-derived scale, and provenance version. If malformed, we will reject it with a typed error (or unconfigured state) without silently substituting a valid value. We will keep the exact raw serialized value available for F-001 CAS.
  - **Writes (`save_settings`, `handle_save`)**: Allow only scalar string fields for unit/code/region/nonce and a canonical nonnegative integer expected version. Reject arrays, objects, malformed strings, and out-of-range versions *before* casts, nonce functions, or sanitization. Rejections will result in no PHP warning, no DB write, and no URL payload on redirection.
- **Test Oracles**:
  - Unit tests will include malformed stored schema/version/scale/code and submitted arrays/objects.
  - Unit tests will include JPY/USD/KWD scale provenance verification.

## 2. F-004: Executable oracles
- **Target Files**: `tests/fixtures/core-004-verify.php`.
- **Invariants**:
  - **Handler Exercise**: Exercise the actual POST handler for authorized/denied actors and nonce failures.
  - **CAS & Denial Verification**: Compare the exact option row before and after denied requests. Use two independent WordPress processes (or DB connections synchronized at the read/write boundary) to demonstrate exactly one CAS winner and one stale failure without lost update.
  - **Historical equivalence**: Create at least one valid DATA-001 record through its public repository/coordinator interface. Capture the full stored envelope (canonical distance, currency identity). Switch global settings (`km/mi` and `JPY/USD/KWD` plus locale), then assert byte-equivalent historical payload and unchanged interpretation.
  - **Lifecycle**: Verify retention through the isolated uninstall/reinstall lifecycle, or explicitly report NOT VERIFIED if the harness cannot perform it.
- **Test Oracles**:
  - All assertion failures must make the outer runner exit nonzero; unexpected acceptance is considered a failure.

## 3. F-005: Gates and handoff
- **Target Files**: `phpcs.xml.dist`, `ai-document/architecture.md`, `ai-document/internationalization.md`, `ai-document/walkthroughs/CORE-004.md`.
- **Invariants**:
  - Fix PHPCS warnings or configure custom capabilities narrowly in `phpcs.xml`.
  - Document direct SQL exceptions only where the atomic invariant strictly requires them.
  - Run `composer run lint` through PHPStan, full PHPUnit, both isolated WP versions (`6.4.3`, `6.7.2`), `npm run production`, and `git diff --check`. Preserve command, version, exit, and cleanup logs.
  - Update `architecture.md` and `internationalization.md` for the accepted implementation.
  - Record keyboard, 320px, long-string, RTL, and unrelated-screen asset observations in `walkthroughs/CORE-004.md`. Mark them NOT VERIFIED if a manual check is not performed (and thus do not claim AC4 complete).
  - Synchronize task, checklist, README, and one current handoff prompt.

Please review this blueprint. Do not proceed with implementation of these three findings until APPROVED FOR IMPLEMENTATION is recorded.
