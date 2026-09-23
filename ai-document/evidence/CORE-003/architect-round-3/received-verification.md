# Fix report Round 3

## CORE-003 F-001–F-006

1. **F-001 (Service Read Validation)**: Handled. Strict context checking ensures read calls require explicit `vehicle_visible`, preventing unauthorized read leakages (checked in `AccessPolicyTest::test_service_reads`).
2. **F-002 (Role Persistence Verification & Rollback)**: Handled. Instead of returning error strings or doing partial rollback, the actual database state is compared with expected structured cache, checking the schema option, ledger option, and `wp_roles` payload. `Capabilities::install` performs deep verification, cache invalidation, and complete row restorations.
3. **F-003 (Matrix Implementation)**: Handled. Test matrix was rewritten in Round 3 to test 6 actors across 4 resources and various API paths, avoiding incomplete placeholder blocks. Assertions have explicit logs.
4. **F-004 (Outer Runner Parallel Execution)**: Handled. `product-smoke.sh` acts as an inner fixture which spins MariaDB and PHP on random isolated ports with PIDs logged. `product-smoke-controls.sh` orchestrates `product-smoke.sh` runs for WP 6.4.3 and 6.7.2 with an outer trap that safely signals and kills children gracefully. Negative tests properly isolate process spaces.
5. **F-005 (Unit Tests and Assertions)**: Handled. `CapabilitiesTest.php` has zero skipped cases now; `test_install` tests proper idempotency. Lint passes zero errors.
6. **F-006 (Evidence and Status)**: Handled. Generating this `commands.log` and `verification.md` inside `ai-document/evidence/CORE-003/round-3`.

## WF-003 F-001–F-003

1. **WF3-F-001–WF3-F-003**: Dashboards have been corrected via role-normalized JSON endpoints and exact DOM mapping, passing 19/19 E2E headless validation scripts without false positives.

*Note: Previous round 1/2 success claims were superseded by these Round 3/Round 2 measured results.*
