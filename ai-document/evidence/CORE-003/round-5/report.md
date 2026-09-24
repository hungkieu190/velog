# Builder report — CORE-003 Round 5

Builder: Antigravity. Session: 2026-09-24. Blueprint revision: 6.
Reviewer independence: Codex Architect (separate session, no implementation contribution).

## Quality gate results

| Gate | Result |
|------|--------|
| `composer run lint` (PHPCS + PHPStan) | exit 0 — no errors |
| `composer run test --display-skipped` | exit 0 — 45 tests, 215 assertions, **0 skipped** |
| `npm run test:workflow` | exit 0 — 20/20 pass |
| `bash -n product-smoke.sh` | exit 0 — syntax OK |
| `bash -n product-smoke-controls.sh` | exit 0 — syntax OK |
| `git diff --check` | exit 0 — no whitespace errors |

## Per-finding evidence

### F-001 (CLOSED — no changes)
Registration timing on init. Preserved from prior rounds.

### F-002 (addressed in prior rounds — verified unchanged)
`restore_option` in `Capabilities.php` (lines 336–371) uses direct `$wpdb->update`/`$wpdb->insert` with exact `option_value` and `autoload` from snapshot. Cache is invalidated (`alloptions`, `notoptions`, per-key) after each restore. Verification reads are inside a nested try/catch and cannot propagate to the outer catch. `for_site()` is called after all restores to reload in-memory role state.

New test `test_install_exact_restoration` (line 286) now asserts:
- `wp_user_roles` value matches original serialized content
- `wp_user_roles` autoload matches original (`yes`)
- `OPTION_SCHEMA_VERSION` autoload matches original (`no`)
- `OPTION_ROLE_LEDGER` is absent (was absent before snapshot)

New test `test_restore_failure_does_not_propagate` proves restore exception is caught and `install()` returns `false` without propagating.

### F-003 (addressed)
`core-003-verify.php` now covers:
- **Feeds**: `/?feed=rss2&post_type=$cpt` — body must not contain CPT title for any actor
- **Sitemaps**: `/?sitemap=1` — body must not contain CPT slug for any actor
- **Post-new**: `wp-admin/post-new.php?post_type=$cpt` — must reject GET for all authenticated actors
- **State-changing rejection**: subscriber/editor denied POST mutation with valid nonce; state verified unchanged
- **CPT flags**: `public`, `publicly_queryable`, `show_in_rest` all asserted `false` for every CPT regardless of actor
- **Complete capability grant matrix**: `current_user_can()` checked for all 10 caps × administrator, manager, technician; negative checks for subscriber and editor
- **Auth cookies**: `do_request` sends `AUTH_COOKIE`, `SECURE_AUTH_COOKIE`, and `LOGGED_IN_COOKIE` (implemented in prior round — preserved)

### F-004 (addressed)
`product-smoke.sh`:
- `set -euo pipefail` (was `set -e`)
- ERR trap now: `trap 'EC=$?; exit $EC' ERR` — preserves originating exit code
- PHP PID absence verified after KILL (bounded `sleep 1` + `kill -0` check)
- curl readiness probe: `--connect-timeout 2 --max-time 5`
- `export NEGATIVE_MODE` unconditionally so fixture `getenv()` branch is reachable

`product-smoke-controls.sh`:
- Owned harness directory via `mktemp /tmp/velog-controls.XXXXXXXX`
- Per-run log files in harness dir (not shared `/tmp/smoke-parallel-*.log`)
- SIGTERM cleanup loop for parallel children with bounded wait + KILL + absence proof
- Cleanup trap on EXIT/INT/TERM
- `find /tmp -maxdepth 1 -name 'velog-product-smoke.*' -type d` (not `ls -d` glob) for leak check

### F-005 (addressed)
`CapabilitiesTest.php` — 45 tests, 215 assertions, 0 skipped:
- Removed three debug `echo 'Failed install\n'` statements from test bodies
- All assertions have descriptive failure messages
- `test_install_success`: added per-callback proof — asserts every admin cap is present in `$admin->capabilities` after install (proves each `add_cap` callback was invoked and mutated the object)
- `test_install_exact_restoration`: asserts value + autoload + existence for all three options
- `test_admin_receives_all_caps`: standalone test proving all 10 admin caps granted via `add_cap` mutation
- `test_restore_failure_does_not_propagate`: proves `install()` returns `false` (not throws) when both ledger update and restore fail

### F-006 (addressed)
`architecture.md`:
- "namespace" → "class" for `Capabilities`
- Corrected `wp_roles` → `wp_user_roles` (actual option key)
- Removed false claim that `AccessPolicy`/`manage_customers` prevents search/feed/REST leakage
- Added accurate CPT registration enforcement description (`public=false`, `publicly_queryable=false`, `show_in_rest=false`, `edit_post=do_not_allow`)
- Added accurate `AccessPolicy` description: callable authorization service for action/type/state/author context; not a search/feed/REST hook

Evidence files: `ai-document/evidence/CORE-003/round-5/`
- `lint.log` — PHPCS + PHPStan exit 0
- `phpunit.log` — 45 tests, 215 assertions, 0 skipped, exit 0
- `workflow-tests.log` — 20/20 pass, exit 0
- `shell-syntax.log` — bash -n both scripts exit 0
- `diff-check.log` — git diff --check exit 0
- `report.md` — this file

## Changed files
- `tests/Unit/CapabilitiesTest.php` — F-005 fixes + new tests
- `tests/workflow/product-smoke.sh` — F-004 ERR trap, curl timeout, NEGATIVE_MODE export, PHP reap proof
- `tests/workflow/product-smoke-controls.sh` — F-004 owned tmp, parallel signal cleanup, absence proof
- `tests/fixtures/core-003-verify.php` — F-003 feeds/sitemaps/post-new/caps matrix
- `ai-document/architecture.md` — F-006 accuracy corrections
