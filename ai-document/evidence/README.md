# WF-001 verification evidence

Verification date: 2026-09-17. Builder evidence below was subsequently reviewed by this session acting as Architect at the user’s explicit request. WF-001 is accepted after review round 2.

| Check | Result | Evidence |
|---|---|---|
| Environment | Node 24.21.0, npm 11.19.0, PHP 8.3.6, Composer 2.7.1 | build-and-release.md; command outputs recorded in session |
| Dependency install | npm install followed by npm ci succeeded; no new direct dependency | npm-install.log, npm-ci.log |
| Lockfile stability | SHA-256 unchanged across npm ci | lockfile-before.txt, lockfile-after.txt |
| Development | Eight outputs including maps; command exits | development.log and workflow-tests.log |
| Production | Four files; JS identifiers/whitespace optimized, CSS minified, legal notice retained, maps removed | production.log, assets/, workflow-tests.log |
| Ownership/failure | Modified source changes output; deleted output rebuilt; malformed/missing source fails; missing resource fails; unsafe paths/symlinks rejected; obsolete manifest output removed | workflow-tests.log |
| PHP quality | Syntax, PHPCS, PHPStan pass in final release; PHPUnit 5 tests / 10 assertions pass | release.log |
| ZIP helper style | PHPCS passes; two narrowly justified native CLI/API exceptions | archive-phpcs.log (empty output, exit 0) |
| Workflow suite | Four test groups pass, zero failed | workflow-tests.log |
| Release | Fresh production build, 22 runtime files, versioned ZIP and staging | release.log, package-manifest.txt |
| Release failure | Version mismatch, invalid Sass, missing LICENSE, symlink and invalid slug fail; existing successful archive bytes unchanged; temporary staging removed | workflow-tests.log |
| Quality-gate failure | Mock Composer startup exit 17 propagates; no success message | workflow-tests.log |
| Isolated installation | WordPress 6.4 detects/installs/activates ZIP; Core Plugin class and version resolve without developer dependencies | wordpress-smoke.log |
| Installed assets | Four URLs return HTTP 200 with bytes matching package | wordpress-smoke.log; direct file check, not enqueue evidence |
| Dashboard | npm run progress -- --port=4187 prints local URL; API/HTML/CSS work; writes/unknown host rejected; docs unchanged | dashboard-http.txt, dashboard.json, dashboard.html |
| Dashboard visual | Browser opened http://127.0.0.1:4187/; screenshot inspected at desktop viewport: focus, roles, counts, phases and history readable, no clipping | Actual browser inspection in session; no screenshot file is claimed |
| Dashboard parsing | Contradictory checklist/task/owner, missing prompt and hostile HTML text surfaced/escaped | workflow-tests.log |
| Git policy | Dependencies/releases/maps ignored; source/scripts/lockfiles/production outputs not ignored | git-ignore.txt |
| Whitespace | git diff --check passes | final-checks.txt |

## Earlier failures and corrections

php-checks.log and js-format.log retain the initial JS formatting failures. They are superseded by the successful final release.log. The migrated frontend callbacks were named and formatted for existing WordPress sniffs; application PHP remains unchanged. No quality rule was disabled globally.

## Isolation and cleanup

Workflow tests create temporary copies under the system temporary directory and remove them in finally blocks. No source fixture mutation remains in the project. Installation used /tmp/velog-wf001-smoke with a dedicated MariaDB datadir/socket and WordPress database; it did not connect to the working site's database. The plugin was deactivated, the isolated database and PHP server were stopped, and the temporary site/datadir were removed. The progress dashboard remains available for user inspection until its process is stopped.

## NOT VERIFIED / acceptance limits

- AC7 is now complete: see the task’s final acceptance. This is a user-authorized self-review, not an assertion of a separate reviewer.
- Hosted GitHub Actions execution; configurations changed locally only.
- Runtime on minimum PHP 8.1 or WordPress versions other than 6.4.
- Real application enqueue integration and product features: absent from the existing scaffold and outside this tooling task.
- Clean installation from a committed revision: the lockfiles and outputs are present and verified locally but no commit/push was authorized or performed.

The existing lint:js/lint:css echo placeholders are not successful lint evidence. WordPress PHPCS was run through the existing configuration instead.

## Architect review evidence

- review-reproduction.log: two new regression tests fail before corrections (F-010/F-011).
- architect-workflow-tests.log: all six workflow test groups pass after corrections.
- architect-release.log: full quality gates, PHPUnit, production and release pass again.
- architect-clean-snapshot.log: fresh npm ci and Composer install with no shared dependencies, dev and release pass; npm lockfile unchanged; temporary snapshot removed.
- architect-accepted-files.sha256: reviewed file bytes, excluding evidence to avoid recursive hashing.
- architect-final-checks.txt: final whitespace, package, unchanged PHP and accepted dashboard consistency results.

No required local check remains unavailable. Hosted CI, broader runtime compatibility and verification of a future committed revision remain explicitly unclaimed.
