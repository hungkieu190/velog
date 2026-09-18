# Rule: Release Checklist

**Scope**: Every plugin release
**Author**: Mamflow
**Mandatory**: Yes

---

## Pre-Release

- [ ] All planned features for this version are implemented.
- [ ] All `// TODO:` items are resolved or deferred to next version.
- [ ] `CHANGELOG.md` is updated with all changes.
- [ ] Version declarations match: plugin header, VELOG_VERSION, package.json and README.md Stable tag. Bump only when explicitly requested.
- [ ] `Tested up to` in `README.md` is updated to latest WordPress version tested.

## Code Quality

- [ ] `composer run phpcs` passes with zero errors.
- [ ] `composer run phpstan` passes at level 8.
- [ ] `composer run test` passes 100%.
- [ ] No `var_dump`, `console.log`, `print_r` in codebase.
- [ ] No debug plugins or constants active.

## WordPress Compatibility

- [ ] Tested on minimum supported WordPress version (6.4).
- [ ] Tested on latest WordPress version.
- [ ] Tested on minimum supported PHP version (8.1).
- [ ] Tested on latest PHP version.
- [ ] Multisite compatibility verified (if applicable).

## Security

- [ ] All user inputs sanitized.
- [ ] All outputs escaped.
- [ ] All forms protected with nonces.
- [ ] Capability checks in place.
- [ ] No SQL injection vulnerabilities.

## i18n

- [ ] All user-facing strings wrapped in translation functions.
- [ ] `.pot` file updated: `wp i18n make-pot . languages/velog.pot`.
- [ ] Text domain matches plugin slug: `velog`.

## Assets

- [ ] All assets built for production (minified).
- [ ] Asset versions updated (cache busting).
- [ ] No unused CSS/JS enqueued.

## Documentation

- [ ] `README.md` / `readme.txt` up to date.
- [ ] Relevant feature documentation in `ai-document/` updated; record N/A with a reason if no feature documentation is affected.
- [ ] Release evidence and readiness updated in `ai-document/release-readiness.md`, with `CHANGELOG.md` as the change history; link supporting evidence rather than duplicating it.

## Release

- [ ] With separate user authorization, create git tag: `git tag v{version}`.
- [ ] Build distribution zip (exclude dev files).
- [ ] Upload to mamflow.com only with explicit authorization.
- [ ] Submit to WordPress.org SVN only with explicit authorization (if applicable).
- [ ] Announce release only with explicit authorization.

---

## Package authority

Use `npm run release`, `scripts/release.config.mjs` and `ai-document/build-and-release.md`. The package must include runtime PHP under src/ and production Composer autoloading. Never copy development vendor wholesale. Local package creation does not authorize publication.
