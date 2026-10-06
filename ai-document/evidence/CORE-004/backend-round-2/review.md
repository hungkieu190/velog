# CORE-004 backend self-review — legacy settings migration

Date: 2026-10-06.

Decision: `SELF_REVIEWED_BACKEND`. The legacy settings compatibility defect is fixed and the LocalWP option was migrated successfully.

## Root cause

The live `velog_settings` option contained the valid seven-key envelope written before `catalog_version` was introduced. The application later required an exact eight-key envelope while retaining `schema_version` 1.0.0 and providing no migration. It therefore classified the valid legacy row as `corrupt_settings` and disabled the settings form.

## Implementation

- Recognize only the exact historical seven-key envelope; unknown or extra keys remain rejected.
- Add the current catalog version only after validating every legacy type, distance unit, currency, and catalog-derived scale.
- Increment the configured record version and persist the upgraded envelope with an exact-byte SQL compare-and-swap.
- Return typed database or stale-version errors without overwriting a concurrent change.
- Invalidate option caches only after one successful database update.

## Verification

- Targeted PHPUnit: 5 tests, 29 assertions, exit 0.
- Full PHPUnit: 85 tests, 312 assertions, exit 0.
- PHPCS and PHPStan: exit 0.
- PHP syntax and scoped `git diff --check`: exit 0.
- LocalWP before migration: version 1, km, USD, scale 2, seven keys.
- LocalWP after migration: version 2, km, USD, scale 2, `catalog_version` `48.0.0-2026-09-22`.
- A second service read returned the same version 2 envelope, proving the migration is idempotent.
- Rendering `RegionalSettingsPage` as the LocalWP administrator produced the editable nonce-protected form and no corruption notice.

The live database was read and migrated through WordPress using the LocalWP MySQL socket. No unrelated option or record was changed.
