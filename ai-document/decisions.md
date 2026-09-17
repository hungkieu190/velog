# Decision log

## Approved bootstrap decisions — user approval on 2026-09-17

- D1: Adopt ai-document/implementation-checklist.md and tasks/ as workflow sources of truth. Preserve legacy product plans and rules; reconcile conflicting workflow instructions during implementation.
- D2: Keep existing Sass and esbuild. Move JS to src/js/ and SCSS to src/css/ with its partials; SCSS is the documented CSS-preprocessor variation. Preserve compiled filenames and behavior.
- D3: Keep PHP classes in src/Core/ and the existing namespace. Do not exclude all src/ from releases: allowlist runtime PHP directories explicitly.
- D4: Track production assets and both dependency lockfiles. Clean checkouts require composer install to supply the current runtime autoloader; packaged installations require no Node or Composer.
- D5: velog.php plugin header is the version authority; validate VELOG_VERSION, package.json, and README.md Stable tag against it. composer.json currently has no version: do not invent one or automatically bump versions.
- D6: Generate a production-only Composer autoloader in isolated staging using the existing lockfile and no network installation during release. Do not copy development vendor wholesale. Fail when prerequisites cannot be satisfied.
- D7: Preserve npm run build as a production compatibility alias; migrate legacy bin entry points to the same implementation. Correct check.sh exit-code handling so tool failures cannot pass.
- D8: Keep existing dependencies; adding a ZIP library requires a specifically recorded scope approval. Prefer built-in Node packaging if practical. Selected Node 24 LTS; verified Node 24.21.0/npm 11.19.0. Use PHP ZipArchive for packaging with no new npm dependency.
- D9: No feature implementation, deployment, tag, commit, push, publication, or changes to the active site's database are included.

## Implementation clarifications

- Tooling-only dashboard CSS is read from src/css/progress.css and never packaged with the plugin.
- Frontend scaffold callbacks were named and formatted to satisfy the existing WordPress PHPCS rules; no application PHP or behavior changed.
- rules/coding-style.md source path/indentation directions were reconciled to avoid contradictory enforcement after migration.
- Initially acceptance was reserved for a separate reviewer. The user subsequently explicitly reassigned this session to Architect and requested self-review to completion. A distinct review pass, defect reproductions, corrections and rerun evidence are recorded in WF-001.
