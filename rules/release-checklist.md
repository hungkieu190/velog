# Rule: Release Checklist

**Scope**: Every plugin release
**Author**: Mamflow
**Mandatory**: Yes

---

## Pre-Release

- [ ] All planned features for this version are implemented.
- [ ] All `// TODO:` items are resolved or deferred to next version.
- [ ] `CHANGELOG.md` is updated with all changes.
- [ ] Version bumped in: `velog.php`, `composer.json`, `README.md`.
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
- [ ] `docs/` updated for any new features.
- [ ] `docs/releases/` entry created for this version.

## Release

- [ ] Create git tag: `git tag v{version}`.
- [ ] Build distribution zip (exclude dev files).
- [ ] Upload to mamflow.com.
- [ ] Submit to WordPress.org SVN (if applicable).
- [ ] Announce release.

---

## Files to Exclude from Distribution Zip

```
.git/
.github/
node_modules/
vendor/   (unless required)
src/      (only if compiled)
tests/
docs/
plans/
rules/
*.neon
*.xml (phpcs)
composer.json
composer.lock
package.json
package-lock.json
.editorconfig
.gitignore
AGENT.md
CONTRIBUTING.md
```
