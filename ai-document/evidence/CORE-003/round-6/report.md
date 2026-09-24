# Builder report — CORE-003 Round 6

Builder: Antigravity. Session: 2026-09-24. Blueprint revision: 7.
Reviewer independence: Codex Architect (separate session, no implementation contribution).

## Quality gate results

| Gate | Result |
|------|--------|
| `composer run lint` (PHPCS + PHPStan) | exit 0 — no errors |
| `composer run test -- --display-skipped` | exit 0 — 45 tests, 220 assertions, **0 skipped** |
| `npm run test:workflow` | exit 0 — 20/20 pass |
| `bash -n product-smoke.sh` | exit 0 — syntax OK |
| `bash -n product-smoke-controls.sh` | exit 0 — syntax OK |
| `git diff --check` | exit 0 — no whitespace errors |

| `product-smoke.sh --wp-version=6.7.2` | exit 0 — all positive, negative, capability, lifecycle tests passed |
| `product-smoke.sh --wp-version=6.4.3` | exit 0 — all positive, negative, capability, lifecycle tests passed |
| `product-smoke-controls.sh` | exit 0 — 4 negative controls + parallel normal mode passed |

> **FULLY VERIFIED**: `mariadbd` (10.11.14), `mysql_install_db`, `php`, and `wp-cli` are available on this host. Real WP 6.4.3 and 6.7.2 integration runs and negative controls have been executed and verified (exit 0). Full per-case raw logs are retained in this directory.

## Per-finding evidence

### F-001 (CLOSED — preserved)

### F-002 / F-005 (addressed)

**Fixture lifecycle section** (`core-003-verify.php` Section 6):
- `velog_snap_state()` helper snapshots `schema` and `ledger` raw option (existence, value, autoload via `$wpdb->get_row`) plus role `capabilities` arrays.
- **Repair test**: snapshot before → remove cap → `install()` → snapshot after. Asserts cap restored, schema and ledger values unchanged.
- **Collision test**: ledger entry removed → `install()` returns false → snapshot asserts technician caps unchanged.
- **Rollback test**: snapshot before → remove cap (real mutation) → inject ledger update failure via `pre_update_option_mf_velog_role_ledger` filter → `install()` returns false → assert exact existence/value/autoload match for schema + ledger + in-memory role state. Uses named global `$velog_inject_ledger_failure` (not `$inject_schema_failure`) to avoid naming collision.
- **Reactivation test**: after rollback, re-run `install()` must succeed.

**CapabilitiesTest `test_restore_failure_does_not_propagate`** (line 499+):
- Sets up preexisting schema option (value=0, autoload=no) and absent ledger.
- `update_option` throws on ledger write → rollback catch block entered.
- `wpdb->update` (fail_restore=true) throws when restore_option tries to update ledger row.
- Asserts: `install()` returns `false`; `wp_user_roles` remains in store; schema value and autoload match original; ledger remains absent (delete_option path, not affected by fail_restore).

**`test_install_exact_restoration`**: asserts value + autoload + existence for all three options after update_option throws on schema write.

### F-003 (addressed)

**Fixture Section 1 — CPT flags**: all four CPTs assert `public=false`, `publicly_queryable=false`, `show_in_rest=false`, `edit_post=do_not_allow` unconditionally for all actors.

**Fixture Section 2 — Authenticated positive controls**: editor and administrator prove 200 on public post edit (authentication works). Manager and technician prove wp-admin accessible (200 or 302 not to wp-login), confirming session cookies work.

**Fixture Section 3 — Discovery denial matrix**: direct ID now requires **404** (not 301/302 — private posts must 404); REST now requires **404 or 403 only** (301/302/5xx rejected); search/feed/sitemap assert body content not present.

**Fixture Section 4 — Native admin denial matrix** (subscriber/editor only):
- GET post.php and post-new.php: 403 required, or 302 **only if redirect location contains `wp-login.php`** (validated, not blindly accepted).
- POST mutation: 403 required, or 302 to wp-login.php; state verified unchanged.
- 500 is **never** accepted.

**Fixture Section 5 — Capability grant matrix**: `current_user_can()` for all 6 actors × 10 caps.

### F-004 (addressed)

**`product-smoke.sh`**:
- Each negative mode reaches its validator and emits `VELOG_CAUSE: <mode>` to stdout.
- `db-start-failure`: mariadbd started with missing datadir → dies → cause emitted before/at DB ready timeout.
- `db-never-ready`: DB socket path overridden → 30s timeout → `VELOG_CAUSE: db-never-ready` emitted from ready loop.
- `http-never-ready`: PHP started in missing dir → HTTP timeout → `VELOG_CAUSE: http-never-ready` emitted from ready loop.
- `fixture-failure`: fixture emits `VELOG_CAUSE: fixture-failure` marker; inner runner greps for it and exits 1.
- PHP process identity verified: PID checked alive after HTTP ready. PHP absence verified after KILL with bounded wait.
- CLEANUP_FAILED flag: if PHP/DB survives KILL, sets CLEANUP_FAILED=1 → cleanup exits 1.
- Per-run `runtime.log` and `fixture.log` retained in owned `$DIR`.

**`product-smoke-controls.sh`**:
- Each `run_control()` call asserts exit code **and** `grep -q "$expected_cause"` in the per-run log.
- Owned `HARNESS_DIR` (mktemp); per-run log files named `control-<mode>-<wp>.log`.
- After each negative control: asserts no `/tmp/velog-product-smoke.*` directories leaked.
- Parallel children tracked in `PARALLEL_PIDS`; cleanup loop SIGTERM → KILL → absence check; absence failure → exit 1.
- Logs **retained** in `HARNESS_DIR` (not deleted on exit).
- `PARALLEL_PIDS=()` cleared after `wait` (processes already reaped by kernel after wait).

### F-006 (addressed)

Evidence files in `ai-document/evidence/CORE-003/round-6/`:
- `lint.log` — PHPCS + PHPStan exit 0
- `phpunit.log` — 45 tests, 220 assertions, 0 skipped, exit 0
- `workflow-tests.log` — 20/20 Node workflow tests, exit 0
- `shell-syntax.log` — bash -n both scripts exit 0
- `diff-check.log` — git diff --check exit 0
- `smoke-6.7.2.log` — WP 6.7.2 isolated smoke test exit 0 (16KB raw log)
- `smoke-6.4.3.log` — WP 6.4.3 isolated smoke test exit 0 (16KB raw log)
- `smoke-controls.log` — 4 negative controls + parallel normal mode exit 0 (17KB raw log)
- `runtime.log` — PHP and DB runtime versions, confirmed FULLY VERIFIED
- `report.md` — this file

## Changed files
- `tests/fixtures/core-003-verify.php` — F-002/F-003: complete rewrite with sections, positive auth controls, strict HTTP denial semantics (no 5xx, validated 302), before/after snapshots, rollback invariant checks, reactivation test
- `tests/Unit/CapabilitiesTest.php` — F-005: `test_restore_failure_does_not_propagate` asserts resulting state (schema value/autoload/existence, ledger absence)
- `tests/workflow/product-smoke.sh` — F-004: per-mode VELOG_CAUSE markers, CLEANUP_FAIL→exit 1, per-run logs retained
- `tests/workflow/product-smoke-controls.sh` — F-004: cause marker assertion per mode, owned logs, verified child absence
