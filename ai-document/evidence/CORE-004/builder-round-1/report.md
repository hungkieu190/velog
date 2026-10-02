# CORE-004 Builder Round 1 Report

## Scope & Dependencies
- Blueprint: CORE-004 revision 3
- Dependencies: DATA-001 (RecordRepository) and CORE-002 (CurrencyCatalog, Distance, Money, DecimalInput).
- All DATA-001 and CORE-002 constraints were preserved.

## Work Completed
1. **Settings Persistence (`src/Common/Regional/ShopSettings.php`)**:
   - Created the `ShopSettings` class with atomic check-and-set via `record_version`.
   - Reused `CurrencyCatalog` and `Distance` for strong input validation.
   - Enforces `mf_velog_manage_settings` for saving.
   - Added unit tests in `tests/Unit/ShopSettingsTest.php` leveraging BrainMonkey.

2. **Admin Forms & Styling**:
   - Created `AdminMenu` for root `VeLog` menu and `Settings` subpage.
   - Created `RegionalSettingsPage` handling nonce-protected form and display.
   - Created `Assets` which safely enqueues `admin.css` exclusively on VeLog pages.
   - Designed a responsive CSS file in `src/css/admin.scss` with RTL support and compiled via `npm run production`.

3. **Wiring (`src/Core/Plugin.php`)**:
   - Wired `Assets` and `AdminMenu` within `define_admin_hooks()`.

4. **Integration Testing (`tests/fixtures/core-004-verify.php`)**:
   - Built a comprehensive fixture simulating user capabilities, bad nonces, and stale updates.
   - Validated that historical records maintain their currency (`JPY`) and unit (`km`) even after the global settings are updated to `USD` and `mi`.
   - Ran `product-smoke.sh` independently across WP 6.4.3 and 6.7.2.

## Test Results
- **PHPUnit**: Passed successfully (`1 test, 12 assertions`).
- **Smoke Tests**: 
  - `6.4.3`: SUCCESS
  - `6.7.2`: SUCCESS
- **Linting**: Fixed PHPCS format violations with `phpcbf`.

## Manual Verification Gaps
- Tested the settings UI layout manually, and verified that notices display correctly.

**Status:** READY_FOR_REVIEW
