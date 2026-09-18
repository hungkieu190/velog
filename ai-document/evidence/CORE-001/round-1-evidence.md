# CORE-001 Evidence — Round 1

## Environment

- Builder session: 866ba911-4817-495a-90db-c2e198e686cc
- Date: 2026-09-18
- Branch: main
- Baseline HEAD: f04d4fc5dbb103423d81f324701611bcefd871d8
- PHP: 8.3.6 (local site runtime)
- PHPUnit: 10.5.64
- PHPCS: 3.9.x + WPCS 3.1.x
- PHPStan: 1.12.x

## Baseline check (before changes)

```
composer run test
```
Output: `OK (5 tests, 10 assertions)` — exit 0

```
composer run lint
```
Output: PHPCS — no violations; PHPStan — `[OK] No errors` — exit 0

## Changes made

### velog.php
- Replaced anonymous closure in `plugins_loaded` callback with named function `mf_velog_bootstrap()`
- `mf_velog_bootstrap()` now calls `mf_velog()->run()` so the Loader registers all queued hooks

### src/Core/Plugin.php
- Added `private bool $ran = false` idempotency guard
- `run()` checks `$this->ran`; second and subsequent calls are no-ops
- `set_locale()` changed hook from `plugins_loaded` to `init` (AC2 fix)
- `load_plugin_textdomain()` path changed from `basename(plugin_dir_path(...)) . '/languages/'` to `plugin_dir_path(VELOG_PLUGIN_FILE) . 'languages'` (reliable path; PHPStan-clean)

### tests/Unit/LoaderRunTest.php (new)
- `test_loader_run_registers_queued_action_once`: verifies Loader::run() forwards a queued action to add_action() once
- `test_loader_run_called_twice_registers_hooks_twice`: documents Loader-level behaviour (not idempotent)
- `test_plugin_run_is_idempotent`: verifies Plugin::run() idempotency via Brain\Monkey expect()->once()
- `test_plugin_run_sets_ran_flag`: verifies the $ran flag is set after run()

### tests/Unit/I18nLifecycleTest.php (new)
- `test_textdomain_is_registered_on_init_not_plugins_loaded`: reflection on Loader.actions confirms `load_plugin_textdomain` is queued on `init`, not `plugins_loaded`
- `test_textdomain_action_registered_exactly_once_after_run`: alias intercepts add_action(); two run() calls yield one registration
- `test_load_plugin_textdomain_uses_correct_domain_and_path`: stubs plugin_dir_path() and load_plugin_textdomain(); asserts domain=velog, path contains "languages", no double-slash

### tests/Integration/BootstrapTest.php (extended)
- Original test preserved
- `test_get_loader_returns_loader_instance`: public API intact
- `test_loader_has_queued_actions_after_construction`: at least one action queued (init/i18n)
- `test_run_called_twice_registers_hooks_only_once`: counts add_action() calls across two run() invocations
- `test_get_instance_returns_same_singleton`: singleton identity

## Post-change check

```
composer run test
```
Output: `OK (16 tests, 26 assertions)` — exit 0

```
composer run lint
```
Output: PHPCS — no violations; PHPStan — `[OK] No errors` — exit 0

```
git diff --check
```
Output: exit 0 (no whitespace errors)

## NOT VERIFIED

- Runtime reproduction in a real WordPress installation (isolated WP site with actual plugins_loaded → init lifecycle)
- Real WordPress translation fixture (non-English .po/.mo file applied and string verified after init)
- WordPress 6.4 minimum version check (tested only on local site runtime)
- WordPress >= 6.7 i18n diagnostic absence (early-translation warning suppression)
- Activation / deactivation / reactivation sequence on isolated site
- WordPress 6.4 + PHP 8.1 matrix (tested: PHP 8.3.6)

These checks remain NOT VERIFIED as declared in the Architect assignment round 1 and must be verified before DONE.
