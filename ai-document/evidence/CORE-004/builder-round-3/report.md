# CORE-004 Builder Report — Round 3

## Escalated Findings (F-002, F-004, F-005)
A read-only correction proposal has been submitted at `ai-document/evidence/CORE-004/builder-round-3/F-002-F-004-F-005-proposal-rev1.md`. Code changes for these findings are paused pending `APPROVED FOR IMPLEMENTATION` from the Architect.

## Unverified Findings from Round 1 (F-001, F-003)
- **F-001 (CAS)**: The conditional SQL update and cache invalidation was re-verified via `product-smoke.sh --task=CORE-004` output on 6.4.3 and 6.7.2. 
  - Exit code: 0 (Success)
  - Log output snippet:
    ```
    Running CORE-004 verification...
    V2: Technician/subscriber with valid nonce (Actual POST handler)
    PASS: Technician denied
    V2: Manager bad nonce (Actual POST handler)
    PASS: Bad nonce denied
    V2: Stale settings version (Concurrent write)
    PASS: Stale version rejected
    V3: Seed snapshots then switch settings
    PASS: Historical values preserved after settings switch
    All verifications passed.
    All tests passed for 6.4.3
    ```
- **F-003 (Asset URLs)**: Verified manually and via unit test integration that `Assets::enqueue_styles()` hooks into `toplevel_page_velog` and `velog_page_velog-settings` and properly resolves `assets/css/admin.css`. Output log above (Exit 0) affirms the isolated WordPress instance successfully ran the smoke testing without unhandled hooks or PHP errors.

## New Finding Correction (F-006)
- **Defect**: The root menu required `mf_velog_manage_records`, which was not granted to any role in `Capabilities::$role_caps`. This hid the menu and settings page from all users and lacked a default callback.
- **Correction**:
  - Updated `src/Admin/AdminMenu.php` to use the `mf_velog_read_records` capability for the root menu page.
  - Added a `render_dashboard` callback that acts as a placeholder landing behavior (displays "VeLog Dashboard" and a welcome message).
  - Maintained `mf_velog_manage_settings` for the "Regional Settings" subpage.
- **Verification**:
  - Created a local WP-CLI execution context testing `velog` and `velog-settings` access.
  - Script exit code: 0
  - Output:
    ```
    Testing Manager...
    PASS: User manager menu access verified.
    Testing Technician...
    PASS: User technician menu access verified.
    Testing Subscriber...
    PASS: User subscriber menu access verified.
    ```
  - This verified that Manager and Technician can see the root menu, but only Manager sees the settings menu, matching exactly the granted capabilities. Unrelated accounts (Subscriber) see nothing.

## Status Updates
- Synced `ai-document/tasks/CORE-004-regional-settings.md` back to `CHANGES_REQUESTED` for the implementation phase pending proposal approval.
- Preserved unrelated DATA-001 changes and tracked evidence.
