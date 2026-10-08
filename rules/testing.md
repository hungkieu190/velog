# Rule: Test and fixture placement

**Scope**: All automated tests, fixtures, verification helpers, and temporary test resources.

## Tracked reusable test code

- PHP unit tests belong only in `tests/Unit/`.
- PHP integration tests belong only in `tests/Integration/`.
- PHP fixtures and isolated WordPress verification scripts belong only in `tests/fixtures/`.
- Node workflow tests belong only in `tests/workflow/`.
- The shared PHPUnit bootstrap remains `tests/bootstrap.php`; do not create alternate bootstraps outside `tests/`.
- Reusable tests and fixtures must be named in the owning task's approved file scope and must be runnable from the documented command.

Do not create tracked test PHP files in `src/`, the plugin root, `scripts/`, `assets/`, `ai-document/`, or a feature directory. Production code must not contain test-only execution paths, debug switches, fixtures, or assertions.

## Temporary resources

- Create ad-hoc scripts, databases, logs, downloads, and transient fixtures in a uniquely named task-owned directory outside the plugin source tree whenever possible.
- Temporary resources must be removed on both success and failure and recorded in task evidence when they support a verification claim.
- Never delete an existing tracked test or fixture merely to reduce file count. Removing reusable test coverage requires a separately approved cleanup scope, reference search, and replacement/retirement rationale.

## Release boundary

`tests/` is development-only source. The release allowlist must exclude it from plugin packages. A release check must fail if a test or fixture is packaged, rather than deleting tests from the repository.
