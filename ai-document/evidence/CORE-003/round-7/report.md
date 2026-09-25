# Builder report — CORE-003 Round 7

Builder: Antigravity. Session: 2026-09-25. Blueprint revision: 8.
Reviewer independence: Codex Architect (separate session, no implementation contribution).

## Quality gate results

| Gate | Result | Evidence file |
|------|--------|---------------|
| `composer run lint` (PHPCS + PHPStan) | exit 0 — no errors | [lint.txt](lint.txt) |
| `composer run test -- --display-skipped` | exit 0 — 45 tests, 224 assertions, **0 skipped** | [phpunit.txt](phpunit.txt) |
| `npm run test:workflow` | exit 0 — 20/20 pass | [workflow-tests.txt](workflow-tests.txt) |
| `bash -n product-smoke.sh` & `product-smoke-controls.sh` | exit 0 — syntax OK | [shell-syntax.txt](shell-syntax.txt) |
| `git diff --check` & `git diff --cached --check` | exit 0 — clean staged and unstaged diffs | [diff-check.txt](diff-check.txt) |
| `product-smoke.sh --task=CORE-003 --wp-version=6.7.2` | exit 0 — all positive, negative, capability, lifecycle tests passed | [smoke-6.7.2.txt](smoke-6.7.2.txt) |
| `product-smoke.sh --task=CORE-003 --wp-version=6.4.3` | exit 0 — all positive, negative, capability, lifecycle tests passed | [smoke-6.4.3.txt](smoke-6.4.3.txt) |
| `product-smoke-controls.sh` | exit 0 — 4 negative controls + parallel normal mode passed | [smoke-controls.txt](smoke-controls.txt) |

> **FULLY VERIFIED**: `mariadbd` (10.11.14), `mysql_install_db`, `php` (8.3.6), `composer` (2.7.1), `node` (24.21.0), and `wp-cli` (2.11.0) are operational on this host. Real WP 6.4.3 and 6.7.2 isolated smoke tests and full negative/concurrency controls have been executed and verified (exit 0). All per-case raw evidence is retained in tracked, portable `.txt` files in this directory.

## Per-finding evidence

### F-001 (CLOSED — preserved)
CPT registration timing on `init` priority 10 preserved.

### F-002 (CLOSED / addressed — preserved)
Fixture lifecycle section (`core-003-verify.php` Section 6) verifies repair, collision rejection, state-verified rollback, and reactivation with exact option existence/value/autoload + role capability checks.

### F-003 (addressed per Revision 8)

**Fixture Section 2 — Authenticated positive controls**:
- Established successful authenticated HTTP control for `subscriber`: requests `$base_url/wp-admin/profile.php` and asserts HTTP 200 (verifying subscriber session cookies work).
- Preserved existing positive controls for `editor` and `administrator` editing public post (HTTP 200).
- Strengthened positive controls for `mf_velog_manager` and `mf_velog_technician`: requests `$base_url/wp-admin/index.php`. If 302, validates specific landing destination (`wp-admin/index.php` or `wp-admin/profile.php`) and asserts `false === strpos( $location, 'wp-login.php' )`. Both returned HTTP 200 in verification runs.

**Fixture Section 4 — Native admin denial matrix** (subscriber and editor):
- GET `post.php` (edit private CPT): asserts 5xx rejected; asserts 403 or specific redirect destination (`wp-login.php`, `wp-admin/index.php`, or `wp-admin/profile.php`); explicitly asserts redirect does NOT lead back to requested resource (`post=$pid`, `post.php`).
- GET `post-new.php` (create private CPT): asserts 5xx rejected; asserts 403 or specific redirect destination; explicitly asserts redirect does NOT lead back to requested resource (`post_type=$cpt`, `post-new.php`).
- POST mutation with valid nonce: asserts 5xx rejected; asserts 403 or 302 to `wp-login.php` without redirecting to `post.php`; post state is verified unchanged in database.

### F-005 (addressed per Revision 8)

**Unit tests — `CapabilitiesTest.php`**:
- Updated `setup_wpdb_mock( $fail_snapshot, $fail_restore, &$restore_failure_called )` to record when the failing `$wpdb->update` branch is invoked.
- Rewrote `test_restore_failure_does_not_propagate()`:
  - Setup preexisting options in `db_store`: `wp_user_roles` (`autoload=yes`), schema version (value `0`, `autoload=no`), and ledger (`mf_velog_manager` owned, `autoload=no`).
  - In install mutation, manager and technician roles are updated, and ledger is updated with both roles.
  - Updating schema version throws to trigger the rollback catch block.
  - During rollback, `restore_option( self::OPTION_ROLE_LEDGER, $snap_ledger )` finds the ledger in database and executes `$wpdb->update()`, where `fail_restore` is active.
  - The failing `$wpdb->update` branch is genuinely reached and throws `new \Exception( 'DB Update Failed' )`.
  - Assertions:
    - `$this->assertTrue( $restore_failure_invoked )` proves the failure branch was reached during rollback.
    - `$this->assertFalse( $result )` proves `Capabilities::install()` catches the restore failure and does not propagate an uncaught exception.
    - `$this->assertEquals( serialize( $initial_roles ), $this->db_store['wp_user_roles']['value'] )` proves roles were restored.
    - `$this->assertEquals( serialize( 0 ), $this->db_store[ Capabilities::OPTION_SCHEMA_VERSION ]['value'] )` proves schema was restored.
    - `$this->assertNotEquals( serialize( $initial_ledger ), $this->db_store[ Capabilities::OPTION_ROLE_LEDGER ]['value'] )` and `$this->assertEquals( serialize( $expected_mutated_ledger ), ... )` prove ledger restore failed and retained the mutated value safely.
- Preserved separate clean test `test_install_exact_restoration()` where all options are restored successfully without failure.

### F-006 (addressed per Revision 8)

- Created portable, tracked evidence files in `ai-document/evidence/CORE-003/round-7/`:
  - `lint.txt`: PHPCS + PHPStan (exit 0, 0 errors).
  - `phpunit.txt`: 45 tests, 224 assertions, 0 skipped (exit 0).
  - `workflow-tests.txt`: 20/20 Node workflow tests passing (exit 0).
  - `shell-syntax.txt`: `bash -n` for both runners (exit 0).
  - `diff-check.txt`: `git diff --check` (exit 0) and `git diff --cached --check` (exit 0).
  - `runtime.txt`: Detailed environment dump (PHP 8.3.6, MariaDB 10.11.14, WP-CLI 2.11.0, Composer 2.7.1, Node 24.21.0, npm 11.19.0).
  - `smoke-6.7.2.txt`: Complete raw output from isolated WordPress 6.7.2 smoke test run (exit 0).
  - `smoke-6.4.3.txt`: Complete raw output from isolated WordPress 6.4.3 smoke test run (exit 0).
  - `smoke-controls.txt`: Complete raw output from negative controls and parallel execution (exit 0).
  - `report.md`: This comprehensive Builder report.
- Checked git status: all `round-7/*.txt` and `round-7/*.md` files have been normalized (LF line endings, trimmed trailing whitespace), staged and tracked in git (not ignored by `.gitignore`), making all raw verification evidence directly portable and visible to any independent reviewer.

## Verification scope boundaries

- **VERIFIED**: Local execution on Ubuntu Linux with PHP 8.3.6, MariaDB 10.11.14, Node 24.21.0, WordPress 6.4.3 and 6.7.2.
- **NOT VERIFIED**: Hosted CI execution (GitHub Actions); PHP versions other than 8.3; production packaging/release.
