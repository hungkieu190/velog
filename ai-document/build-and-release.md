# Build and release

Implementation: WF-001, accepted after Architect review round 2. Verification is recorded in evidence/ and the task report.

## Environment and installation

- Node 24 LTS; `.nvmrc` selects major 24 and package.json requires >=24 <25. Verified locally with Node 24.21.0 and npm 11.19.0. npm range is >=10 <12; other npm versions are not separately verified.
- PHP >=8.1 and Composer 2 are development prerequisites. Local checks used PHP 8.3.6 and Composer 2.7.1. PHP's ZipArchive extension is required for local packaging, not plugin installation.
- Node 24 was selected using the [official release schedule](https://nodejs.org/en/about/previous-releases), checked 2026-09-17. Node 20 is no longer the supported project environment.

```bash
nvm install
nvm use
npm ci
composer install
npm run progress
```

Use `npm install` only for intentional dependency changes; use `npm ci` for verification/CI. Both lockfiles are included in the proposed version-controlled changes. No commit has been created. npm ci passed without changing the npm lockfile (matching SHA-256 files in evidence/). Existing dependencies were retained; no new npm library was added.

## Source ownership and output mapping

| Authoritative source | Generated output |
|---|---|
| src/js/admin.js | assets/js/admin.js |
| src/js/frontend.js | assets/js/frontend.js |
| src/css/admin.scss | assets/css/admin.css |
| src/css/frontend.scss | assets/css/frontend.css |

`src/css/_variables.scss` is imported by both Sass entries. Sass is the approved CSS-preprocessor variation. PHP remains in its existing src/ namespaces. The previous src/assets/ sources were removed only after replacement development and production builds passed.

The dashboard's development-only CSS is `src/css/progress.css`, read directly by its local tool server. It is not plugin runtime CSS and is not included in build output or release packages. Dashboard HTML is rendered server-side by scripts/progress-view.mjs; there is no client JavaScript bundle.

`build.config.mjs` owns entry mapping, ES2020 target, IIFE format, externals, static mapping and the generated-file manifest. No external frontend library or real image/font resource is currently required. Add explicit static mappings when real resources are introduced; CSS references to unconfigured local outputs fail validation. Do not bundle WordPress-provided libraries without a reviewed dependency plan.

## Commands

| Command | Behavior |
|---|---|
| npm run dev | One expanded development build, linked JS/CSS source maps, then exit. |
| npm run production | Fresh bundled/minified JS and minified CSS, legal notices retained, no maps, then exit. |
| npm run build | Compatibility alias for production. |
| npm run release | Validate version/configuration/prerequisites, run quality gates and PHPUnit, fresh production build, validate staging, create ZIP. |
| npm run progress | Read-only localhost dashboard, port 4177; `-- --port=4187` or PROGRESS_PORT overrides it; Ctrl+C stops it. |
| npm run check | PHP syntax, PHPCS and PHPStan using actual exit codes; saves a report under reports/. |
| npm run test:workflow | Disposable-fixture checks for build ownership/failure, dashboard parsing/escaping, release boundaries/failure and quality-gate propagation. |

Legacy `node bin/build-assets.js [--dev]` and `bash bin/release.sh` delegate to the same implementation. No watch command is provided. lint:js/lint:css remain existing TODO placeholders and must not be claimed as lint evidence.

## Cleanup and failure boundaries

Compilation finishes in memory and validates all destinations/resources before output writes. Temporary staging is under `.cache/`. `assets/.generated.json` records owned output files; production removes obsolete manifest-owned outputs and maps only. The manifest destination is fixed to assets/.generated.json; invalid destinations are rejected before compilation. Do not edit the manifest manually. Unowned files and vendor assets are preserved. Symlink/traversal output paths are rejected.

Build and release lock files prevent competing same-kind writers. Release captures exact production asset bytes while the build lock is held, so a subsequent development build cannot alter packaged assets. No successful release is reported on any failed step. An OS interruption during the output replacement phase may require rerunning the build; installation artifacts are exposed only after staging and ZIP validation.

If a process is forcibly killed, an orphaned `.cache/build.lock` or `.cache/release.lock` may remain. Confirm the corresponding process has stopped before removing that exact lock and retrying. Never remove a live process's lock.

## Runtime and packaging

The inspected plugin has no frontend/admin asset enqueues yet. This bootstrap preserves that behavior. Built asset URLs were fetched successfully from an isolated installed ZIP; automatic enqueue behavior remains NOT VERIFIED because no implementation exists. Future feature tasks must add scoped WordPress enqueue APIs, dependencies and cache invalidation without loading src/ directly.

`release.config.mjs` allows only velog.php, uninstall.php, README.md, LICENSE, PHP files from named runtime src/ directories, translation files, generated production assets, and generated production Composer autoload files. No frontend source, scripts, node_modules, tests, task docs, secrets, maps, development vendor or nested releases are packaged. Backend src/ is deliberately included.

Composer currently requires only PHP and has zero third-party runtime packages. Release generates its optimized no-dev autoloader in staging with network disabled; it does not install or copy development dependencies. New runtime dependencies intentionally fail packaging until the allowlist and offline dependency staging policy are reviewed. The full GPL-2 text and the existing GPL-2.0-or-later declaration are included; Composer-generated notices are retained in vendor/composer/LICENSE.

Version authority is the plugin header in velog.php. VELOG_VERSION, package.json version, and README.md Stable tag must match. No fallback or automatic bump exists. composer.json currently has no version field.

Outputs:

```text
release/velog/
release/velog-0.1.0.zip
```

ZIP entries have exactly one top-level `velog/` directory. Previous different-version archives remain. The current version's archive is replaced only after the new archive validates. npm run release never uploads, publishes, pushes or tags. The existing tag-triggered GitHub workflow now uses this same command; pushing a release tag still requires explicit user authorization.

## Git policy

Track frontend sources, scripts, configuration, both lockfiles, production outputs and assets/.generated.json. Ignore node_modules/, vendor/, release/, .cache/, maps and local environment files. Sanitized .env examples remain eligible for tracking. Representative `git check-ignore --no-index` results are in evidence/git-ignore.txt. Previously tracked CI files remain tracked; no git index removal was performed.

A source checkout needs `composer install` for the existing autoloader before WordPress activation. A packaged plugin needs neither Composer nor Node. Include source changes and matching production outputs in the same eventual commit.

## Installation and evidence

Upload the generated ZIP through WordPress Plugins > Add New, or unpack the single velog/ directory into wp-content/plugins/, then activate. Verified on an isolated WordPress 6.4 database/site with PHP 8.3.6: installation, detection, activation and MF\VeLog\Core\Plugin bootstrap succeeded. All four built files were available over HTTP. The working site's database was not used.

See evidence/README.md and testing-strategy.md for checks, results and cleanup. Hosted CI, PHP 8.1/minimum-version runtime, other WordPress versions, product features and real application enqueue behavior are NOT VERIFIED. These limits are not a production-readiness claim.
