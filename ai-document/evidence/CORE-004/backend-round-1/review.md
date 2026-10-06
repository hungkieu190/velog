# CORE-004 backend self-review — 2026-10-06

Decision: `SELF_REVIEWED_BACKEND` for CORE4-F-001, the backend portion of F-003, and F-006. This review is non-independent under the approved workflow. CORE-004 remains IN_PROGRESS pending CORE-004-FE review and AC4 acceptance.

## Changes and findings

- F-001: Inspected `ShopSettings::save_settings()` and its single raw option snapshot, exact-byte `BINARY` conditional update, unique first insert, affected-row/error handling, and post-write cache invalidation. The two-process WordPress fixture proves one winner and one `stale_version` loser on both supported WordPress versions. The unit test covers duplicate errno 1062 versus a non-duplicate database error. No new application change was needed.
- F-003: Changed `Assets::enqueue_styles()` to build the CSS URL from `VELOG_PLUGIN_FILE`. The fixture proves the exact generated plugin-root URL and file, enqueues on the settings hook, and excludes an unrelated admin hook. Browser layout and presentation verification remain in CORE-004-FE.
- F-006: Inspected `AdminMenu::add_menu_pages()` and `Capabilities`. The fixture proves the root and settings menu capabilities, a callable root landing page, expected administrator/manager/technician/subscriber grants, and manager-only settings access. No new application change was needed.

## Verification

| Gate | Result |
|---|---|
| `composer run test -- --filter ShopSettingsTest` | Exit 0; 3 tests, 21 assertions |
| `composer run test` | Exit 0; 83 tests, 304 assertions |
| `composer run lint` | Exit 0; PHPCS and PHPStan passed |
| `npm run production` | Exit 0; 4 generated files |
| `bash tests/workflow/product-smoke.sh --task=CORE-004 --wp-version=6.4.3` | Exit 0; see `wp-6.4.3.log` |
| `bash tests/workflow/product-smoke.sh --task=CORE-004 --wp-version=6.7.2` | Exit 0; see `wp-6.7.2.log` |
| `git diff --check -- src/Admin/Assets.php tests/fixtures/core-004-verify.php ai-document/tasks/CORE-004-regional-settings.md` | Exit 0 |

The WordPress matrix ran on PHP 8.3.6. Other PHP runtimes were not run. Full `git diff --check` currently fails on two trailing-whitespace lines in the concurrent CORE-004-FE Sass submission; that is recorded in the frontend review. The product-smoke runner cleaned its disposable WordPress and process resources. Task-owned `/tmp` wrapper logs were copied into this directory and removed after review.
