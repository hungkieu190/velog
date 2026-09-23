# Source, Build, and Release Workflow Bootstrap

## 1. Purpose and Authority

Kit revision: 2026-09-18 / 2. Use with `01-architect-builder-workflow.md` and the target project's product brief. On installation, copy this specification to `ai-document/source-build-release-workflow.md`, install file 01 as `ai-document/architect-builder-workflow.md`, and use those paths for cross-references. The pair is self-contained; no original project files or results are required.

File 01 governs application role mapping, startup acknowledgement, approvals, blueprint gates, statuses, evidence and documentation ownership. Before build work, resolve the role through AGENTS.md and ai-document/agent-roles.json; do not infer Builder from an implementation request. The startup rule adapter is development metadata, not runtime code. This file governs technical build/package requirements. Neither file authorizes role switching or publication. Inspect and adapt actual project identity/runtime requirements; never copy another project's namespaces, versions, local filesystem paths or accepted evidence.

Establish a project-specific workflow for:

- Authoring frontend source files.
- Managing `package.json` and dependencies.
- Building development and production assets.
- Packaging a complete release.
- Maintaining `.gitignore`.
- Enforcing these rules through `AGENTS.md`.

Follow the existing Architect / Builder approval workflow.

Architect inspects the repository and prepares the implementation task.
Builder creates or modifies build scripts only after approval and an Architect-authored blueprint readiness PASS. The Architect session never implements then accepts its own tooling.

Do not replace an existing working build system without examining it first.

All paths below are relative to the project root. `/release/` means the project's release directory, not the operating system root.

## 2. Mandatory Source-of-Truth Rule

All project-authored frontend JavaScript and CSS must originate in `/src/`.

- Edit JavaScript in `/src/js/`.
- Edit CSS/Sass in `/src/css/`; retain an existing Sass pipeline. Sass is the default for a new WordPress plugin unless a different preprocessor is explicitly approved.
- Keep imported frontend modules and partials under `/src/`.
- Generate distributable files into `/assets/`.
- Never manually patch generated JavaScript or CSS in `/assets/`.
- Never edit release output to fix application behavior.
- Fix the source, rebuild, and regenerate the release.

Build tooling belongs in `/scripts/`, not in frontend `/src/js/`.

Third-party assets must be identified separately. Do not relocate or modify vendor code as if it were project-authored source.

For an existing project, migrate source files only after inspecting imports, runtime references, enqueue paths, and build configuration. Do not delete the original files until the replacement build and runtime references are verified.

## 3. Standard Directory Structure

Use this structure unless the framework requires a documented variation:

```text
project-root/
  AGENTS.md
  package.json
  package-lock.json
  .gitignore
  .nvmrc

  src/
    js/
      admin.js
      frontend.js
      modules/
    css/
      admin.scss
      frontend.scss
      _variables.scss
      components/
    static/
      images/
      fonts/

  assets/
    js/
      admin.js
      frontend.js
    css/
      admin.css
      frontend.css
    images/
    fonts/

  scripts/
    build.mjs
    release.mjs
    build.config.mjs
    release.config.mjs

  ai-document/
    build-and-release.md

  release/
    <project-slug>/
    <project-slug>-<version>.zip
```

Create only entries that the actual project needs.

`src/static/` contains source assets copied by the build without unnecessary transformation.

Backend source files remain in their framework-appropriate directories. The `/src/` rule in this document concerns frontend asset sources.

## 4. Required npm Commands

### npm run dev

Perform one complete development build and then exit.

Required behavior:

- Compile and bundle the configured entry points.
- Do not minify JavaScript or CSS.
- Generate source maps for debugging.
- Copy required static assets.
- Write output into `/assets/`.
- Exit nonzero when the build fails.

This command must not start a permanent watcher or development server.

### npm run production

Perform one complete production build and then exit.

Required behavior:

- Build from `/src/`, not from previous `/assets/` output.
- Minify JavaScript and CSS.
- Preserve required license notices.
- Exclude development source maps by default.
- Remove stale development maps and obsolete generated outputs.
- Copy all required static assets.
- Validate required output files and referenced local resources.
- Exit nonzero when any required build step fails.

Keep output filenames consistent between development and production by default:

```text
src/js/frontend.js  -> assets/js/frontend.js
src/css/frontend.scss -> assets/css/frontend.css
```

Production files are minified even without `.min` in the filename.

If the project already requires `.min` names or hashed filenames, document the mapping and update runtime resolution accordingly.

### npm run release

Create a complete local distribution package.

Required sequence:

1. Validate configuration, versions and runtime/tool prerequisites.
2. Run actual configured quality gates and tests; fail on any nonzero result.
3. Run and await a fresh production build.
4. Stage runtime files using an explicit allowlist.
5. Validate staged contents, autoloaders and required resources.
6. Create the versioned ZIP and expose it only after validation.
7. Report output paths, actual checks and limitations.

Required outputs:

```text
release/<project-slug>/
release/<project-slug>-<version>.zip
```

The ZIP must contain one top-level `<project-slug>/` directory.

Do not package development assets left over from a previous build.

This command does not publish, upload, deploy, create a Git tag, or push commits.

### npm run build

Required compatibility alias for `npm run production`; it must have the same output/exit behavior.

### Optional npm run watch

If useful, provide a separate watcher:

- Perform an initial development build.
- Rebuild affected assets when source files change.
- Keep the process running until stopped.
- Report build errors clearly.

Do not change the required meaning of `npm run dev`.

## 5. package.json Requirements

Inspect an existing `package.json` before editing it. Preserve unrelated scripts and dependencies.

For a new project, define:

- A valid package name.
- A version consistent with the documented release policy.
- `"private": true` unless npm publication is explicitly intended.
- A supported Node.js range in `engines`.
- The required scripts.
- Only dependencies actually needed by the project.

Use `.mjs` for build scripts so an existing CommonJS project does not require a global `"type": "module"` change.

Recommended script interface:

```json
{
  "scripts": {
    "dev": "node scripts/build.mjs --mode=development",
    "production": "node scripts/build.mjs --mode=production",
    "build": "npm run production",
    "release": "node scripts/release.mjs"
  }
}
```

The release script must invoke and await the production build itself. Do not rely on someone having run it beforehand.

Dependency rules:

- Put build tools and packaging libraries in `devDependencies`.
- Put packages required by the installed application at runtime in `dependencies`.
- Do not invent dependency versions.
- Select compatible versions and generate `package-lock.json` through npm.
- Keep `package.json` and `package-lock.json` version-controlled; also track composer.lock when Composer is used. Creating a commit still requires explicit user authorization.
- Use `npm install` when intentionally initializing or changing dependencies.
- Use `npm ci` for clean verification and CI.
- Use project-local JavaScript build tools. Any OS/runtime packaging prerequisite (for example PHP ZipArchive) must be explicitly documented and validated; never silently install a missing tool.
- Do not silently fetch new dependencies during release packaging.

For a simple JavaScript/CSS project, esbuild is an acceptable default. Preserve an existing framework-native build tool when it satisfies the required commands.

The reference workflow uses Node 24 with `.nvmrc` major 24 and engines >=24 <25; re-verify support and actual dependency compatibility when initializing a new project, and record any approved variation. Do not claim a patch/npm version tested without running it. Document the selected Node/npm/runtime versions and keep `.nvmrc`, engines, CI and setup instructions consistent.

## 6. Build Configuration and Runtime Integration

Maintain one explicit configuration for:

- JavaScript entry points.
- CSS entry points.
- Output paths.
- Static asset copying.
- Browser targets.
- External dependencies.
- Production optimization.
- Generated-file ownership.

Development and production must use the same entry-point mapping.

Do not compile every file independently when some files are imported modules or CSS partials.

Inspect runtime integration:

- Runtime code must load built files from `/assets/`.
- Runtime pages must not load frontend authoring files from `/src/js/` or `/src/css/`; backend runtime PHP under `/src/` remains valid and must be packaged.
- Avoid loading the same library twice.
- Preserve required script execution order.
- Select module format based on actual runtime loading.
- Preserve CSS cascade and import order.
- Ensure fonts and images referenced by CSS exist at their emitted paths.
- Keep application-level cache invalidation consistent with rebuilt assets.

For WordPress projects:

- Use supported WordPress enqueue APIs.
- Declare script dependencies correctly.
- Do not bundle another copy of WordPress-provided libraries unnecessarily.
- Match script format to the enqueue mechanism.
- Generate dependency metadata when required by the chosen tooling.
- Include that metadata in the release.
- Keep Node.js a development requirement, not a requirement for installing the plugin.

Do not modify application behavior merely to simplify the bundler configuration.

## 7. Safe Output Cleaning

Build output cleanup must be limited to documented generated files.

- Resolve paths against the project root.
- Reject unsafe output paths, including the project root itself.
- Never recursively delete a configurable path without validating it.
- Do not delete manually maintained files or third-party assets.
- Use an output manifest or explicitly owned output directories.
- Build into temporary staging locations where practical.
- Do not report success or package partially written output after failure.

Running production after development must not leave development maps in the production artifact.

Running the same build repeatedly must not accumulate obsolete bundles.

## 8. Release Packaging Rules

Define package contents in `scripts/release.config.mjs` or an equivalent explicit configuration.

Use a runtime-file allowlist. Do not copy the entire repository and hope an exclusion list catches everything.

A WordPress plugin release normally includes applicable files such as:

- Main plugin PHP file.
- Runtime PHP directories.
- Templates.
- Built assets.
- Translation files.
- Runtime dependency autoloaders and production dependencies.
- `uninstall.php`, when present.
- Distribution readme and license files.

Normally exclude:

- Frontend authoring sources (`/src/js/`, `/src/css/`, unneeded static originals), NOT backend runtime files under `/src/`.
- `/scripts/`.
- `/node_modules/`.
- `/ai-document/` (including agent-roles.json).
- IDE agent instruction adapters such as `/.agents/`, `/.agent/`, and any development-only session state.
- `/release/`.
- `.git/` and repository metadata.
- Local environment files and credentials.
- Tests, fixtures, coverage, and temporary logs.
- Development-only dependency files.
- Source maps unless explicitly approved for distribution.
- Editor and operating-system files.

Do not blindly exclude runtime dependencies such as Composer `vendor/` or backend `src/`. Determine what the application needs after installation. Explicitly allowlist runtime PHP namespaces/directories, production Composer autoload files and required runtime packages.

For WordPress with Composer, stage an optimized no-dev autoloader and required locked production dependencies; never copy the entire development vendor tree. Packaging must not silently fetch packages. Specify offline staging/cached inputs and fail when unavailable. A project with no third-party runtime packages may generate only its optimized autoloader in staging. Verify class resolution in the unpacked package without repository vendor or source-tree access.

If a dependency's license requires source or notice distribution, satisfy that requirement explicitly.

For non-WordPress projects, determine whether the deliverable is a static site, server application, library, or another package type. Document its installation and runtime prerequisites instead of assuming the WordPress package layout applies.

Version handling:

- Identify the authoritative version source.
- Validate other required version declarations against it.
- Fail on mismatches.
- Do not automatically bump versions.
- Validate the project slug and version before using them in filesystem paths.

Package safety:

- Never include the release directory inside itself.
- Reject paths escaping the project or staging root.
- Reject external symlinks unless explicitly supported and reviewed.
- Validate required files before exposing the package as successful.
- Preserve previous versioned archives unless cleanup was requested.
- Replace the current staging directory only through a controlled process.

## 9. .gitignore Initialization Logic

At project initialization:

1. Locate the actual Git repository root and project root.
2. Read existing ignore files and relevant tracked-file state.
3. Identify generated outputs, dependencies, local configuration, caches, and release artifacts.
4. Create `.gitignore` if missing.
5. Otherwise add only missing relevant rules.
6. Preserve existing rules, comments, and exceptions.
7. Verify representative ignored and tracked paths.

For a nested project, use a project-local `.gitignore` where appropriate. Do not overwrite a parent repository's policy.

Baseline project-local rules:

```gitignore
# Installed frontend dependencies
/node_modules/

# Generated release packages and staging
/release/

# Local environment files
.env
.env.*
!.env.example
!.env.*.example

# Build caches and test output
/.cache/
/coverage/

# npm diagnostic logs
npm-debug.log*

# Operating-system files
.DS_Store
Thumbs.db
```

Add framework-specific rules only when the corresponding files exist or will be generated.

Do not ignore:

- `/src/`.
- `/scripts/`.
- `package.json`.
- `package-lock.json`.
- Shared build configuration.
- Project instructions and technical documentation.
- Sanitized environment examples needed for setup.

Do not use broad rules such as `*.json`, `*.zip`, or `vendor/` without understanding their impact.

Never place real credentials in environment example files.

### Generated Assets and Git

Record one explicit project policy.

Default for a WordPress plugin repository (source checkout may require composer install; packaged installation must not):

- Track production JavaScript/CSS output in `/assets/`.
- Ignore generated development maps using targeted rules.
- Run production before preparing distributable changes.
- Include source changes and corresponding generated changes in the same implementation handoff.

Suggested map rules:

```gitignore
/assets/js/**/*.map
/assets/css/**/*.map
```

For projects whose CI always builds before deployment:

- Generated asset directories may be ignored.
- Document the mandatory build step after checkout.
- Ensure clean-checkout and release verification prove the workflow works.

Do not automatically ignore `/assets/` without deciding how runtime files will be supplied.

Adding `.gitignore` does not untrack existing files. If generated files or secrets are already tracked, report them and propose a separate targeted correction. Do not silently remove unrelated files from Git tracking.

## 10. Required AGENTS.md Instructions

Add or reconcile the following section in the project's `AGENTS.md`:

```markdown
## Frontend Source, Build, and Release Rules

- `/src/` is the authoritative source for project-authored frontend assets.
- Edit JavaScript in `/src/js/` and CSS in `/src/css/`.
- Never manually edit generated JavaScript or CSS in `/assets/`.
- Never fix application behavior by editing `/release/`.
- After changing source assets, run the appropriate build and verify
  the generated output and affected runtime behavior.
- `npm run dev` performs one unminified development build.
- `npm run production` builds production assets with JavaScript and
  CSS minification.
- `npm run build` aliases production.
- `npm run release` passes quality gates, performs a fresh production build and packages
  the runtime application under `/release/`.
- `/release/` and `/node_modules/` must be ignored by Git.
- Keep `package.json` and `package-lock.json` version-controlled; also track composer.lock when Composer is used. Creating a commit still requires explicit user authorization.
- Track production assets and Composer lockfiles where applicable; ignore development maps.
- Include source and matching production output in the same handoff.
- Never blanket-exclude backend src/ or required runtime autoload files from packages.
- PHP-only/documentation-only changes do not inherently require a frontend rebuild.
- Progress-dashboard CSS is development-only, read directly and excluded from runtime packaging.
- Follow the documented policy for tracking generated assets.
- Do not claim a build or release passed unless the command actually
  completed successfully.
- If build prerequisites are unavailable, record the exact blocker;
  do not manually patch generated files as a workaround.
- Build configuration changes follow the Architect / Builder task
  and approval workflow.
- Read `ai-document/build-and-release.md` before changing entry
  points, dependencies, output paths, or release packaging.
```

Adapt paths only when the approved project structure requires it. Preserve the rule that generated CSS/JS is never edited directly.

## 11. Required Documentation

Create or update `ai-document/build-and-release.md` with:

- Supported Node.js and npm environment.
- Installation commands.
- Source-to-output mapping.
- Development, production, watch, and release commands.
- Static asset and third-party dependency handling.
- Runtime asset loading.
- Generated-assets Git policy.
- Release allowlist and exclusions.
- Version source and validation.
- Installation procedure for the packaged application.
- Verification commands and results.
- Known limitations.

Link this document from `AGENTS.md` and the documentation index.

## 12. Acceptance and Verification

Builder must verify the following and record evidence in the task file:

### A. Installation

- `npm ci` succeeds from the committed lockfile.
- Required commands use project-local dependencies.
- Dependency installation does not unexpectedly rewrite the lockfile.

### B. Development Build

- `npm run dev` completes and exits.
- Expected JavaScript and CSS files are generated.
- JavaScript and CSS are not minified.
- Source maps resolve to the appropriate source files.

### C. Production Build

- `npm run production` completes and exits.
- Both JavaScript and CSS minification are enabled and reflected in output.
- Required license notices remain available.
- Development source maps are absent.
- CSS resource paths and runtime asset references resolve.
- Relevant application behavior still works.

Do not use smaller file size as the only proof of correct minification.

### D. Source Ownership

- Modify a source fixture or approved source file and verify rebuilding changes its output.
- Remove a generated test output and verify the build recreates it.
- Confirm runtime references point to built assets.
- Restore temporary verification changes.

### E. Release

- `npm run release` performs its own production build.
- The staged directory and ZIP exist at the documented paths.
- Required runtime files are present.
- Frontend authoring sources, development dependencies, secrets and nested releases are absent; required backend source and production autoload/runtime dependencies are present.
- Unpack the ZIP into a clean location and perform an appropriate installation or smoke test.
- For WordPress, verify plugin detection, activation, and affected asset loading in an isolated local site.
- Required external runtime dependencies are documented.

### F. Failure and Repeatability

- A deliberate source build error causes a nonzero exit.
- A failed build does not produce a newly reported successful release.
- Missing required package files cause release failure.
- Repeated builds do not accumulate obsolete output.
- Production following development removes development-only output.

### G. Git Ignore Policy

Use `git check-ignore` or equivalent inspection to verify:

- `/release/` and `/node_modules/` are ignored.
- Source, build scripts, and lockfiles are not accidentally ignored.
- Generated assets follow the approved tracking policy.
- Previously tracked files are identified separately.

Syntax checks alone are not acceptance of the build and release workflow.

Architect independently reviews the scripts, packaging boundaries, and relevant verification evidence before setting the task to `DONE`.


## 13. Required build/release blueprint

Before a Builder task is READY, Architect provides the file-01 blueprint plus these concrete project mappings:

| Area | Required design detail |
|---|---|
| Inputs/outputs | Every real entry, imports/partials, static resource mapping, browser/module target, emitted files and generated ownership manifest |
| Runtime | Backend directories, scoped enqueue/load paths, dependency order, version/cache rules and production autoload strategy |
| Build safety | Staging/validation before writes, owned output cleanup, traversal/symlink rejection, stale map handling and concurrency policy |
| Packaging | Version authority and consistency checks, runtime allowlist, offline prerequisites, one ZIP root and atomic success exposure |
| Tests | Exact commands/fixtures, expected files/output/exit codes, intentional failures, reset/cleanup and evidence paths |

Recommended critical sequence (design pseudocode, not a ready-made implementation):

```text
validate project root, output destinations and configuration
acquire the appropriate owned build/release lock; fail clearly on contention
try:
    validate prerequisites and version declarations
    run required gates, propagating their real exit codes
    compile configured sources using local tools into private staging
    validate every required output and local resource reference
    for production: remove only obsolete manifest-owned outputs/maps
    publish generated outputs through controlled replacement
    for release:
        retain a stable snapshot of production bytes while protected from concurrent builds
        stage allowlisted backend/runtime assets and offline production autoload/dependencies
        validate completeness, exclusions and paths; create ZIP with one root
        expose package only after validation; preserve unrelated/versioned archives
finally:
    clean only resources owned by this operation
    release owned locks; preserve primary failures and report cleanup failures
```

A lock file alone is not proof a process is dead. Do not delete another process's live lock. Document recovery of confirmed stale locks. Use the same behavior for legacy wrappers when retained; do not keep divergent build paths.

## 14. Isolated verification contract

All build/package/WordPress smoke checks must have reproducible positive, negative and cleanup behavior:

- Allocate unique owned workspaces atomically (mktemp or atomic creation with collision retry); never adopt an existing fixed/RANDOM directory then recursively delete it.
- Use separate database/datadir/socket and test installation. Do not connect to the active site's database. Copy the required plugin/runtime files; do not write catalogs or test fixtures through a symlink into the working plugin.
- Record launched child identities; bounded readiness checks must stop on child death or timeout. On exit, request shutdown and wait for the owned child before removing its data. Preserve the main failure, report cleanup errors, and never report success with a leftover child/datadir race.
- Pin and report each required runtime version. Run isolated baseline installations or explicitly label upgrade/reused-database scenarios. Missing versions remain NOT VERIFIED.
- For translation-sensitive tasks, install a real non-English catalog and assert a different expected translation after the natural lifecycle. Install diagnostics before bootstrap. A late replay of init/plugins_loaded is not early-lifecycle testing.
- Positive and negative controls use the SAME validator/detector. Prove a known broken condition fails that validator. Record inner expected failure separately from overall test success. Do not grep for an unrelated warning, mask errors with a success echo, or infer success from text/file existence alone.
- Capture exact commands, exit codes, test totals, versions, relevant raw output and cleanup proof under the task's evidence directory. Sanitize secrets; preserve failed-run history and append corrections to unsupported claims.
- A failed connection to the active site's database does not establish inability to create a disposable instance. Report actual isolated-setup attempts and precise blockers.

### Evidence lessons for integration and failure controls

Apply these when the approved task includes the relevant behavior; do not add unrelated product features or runtime requirements.

- Negative controls must trigger the real validator, with the fault actually passed to the child/fixture. A direct forced exit is not validation evidence. Match both failure category and exit behavior; reject an unrelated failure even if its numeric exit code matches.
- Own resources by unique run ID, path, PID and process start identity. Bound connect/request and overall readiness time, detect early child death, stop and reap owned children, verify their absence, then remove owned data. Parallel and signal paths need the same cleanup. Do not count or delete global temporary-directory matches or share log names between concurrent runs. Preserve the original failure and make cleanup failure visible/nonzero.
- For authenticated denial tests, prove the request is authenticated with the correct session/cookie context and a positive authorized control first. A login redirect is not authorization denial evidence. Verify rejected mutations leave stored state unchanged, using valid request verification such as a nonce when required by the framework.
- For rollback, compare exact before/after existence, raw value, storage metadata (such as WordPress option autoload), caches and reloaded runtime state where applicable. Inject actual persistence failures; a successful repair is not a rollback test. Mocks must derive reads from stateful writes, not return canned expected success rows. Continue remaining cleanup if one restoration/check fails.
- Record per-case commands, versions, exits, raw results and resource cleanup, not just a summarized success report. Required skipped/incomplete cases remain unmet; Node fixtures, mocks, real integration and browser checks must be labeled separately. Missing supported-runtime coverage remains NOT VERIFIED.

## 15. Dashboard and source-control boundaries

The dashboard is local development tooling, owned by the approved workflow task. Its stylesheet may live in src/css/progress.css and be read directly by the localhost server; exclude it from plugin runtime compilation/release. Do not add runtime enqueues solely to satisfy dashboard/build tests.

Track authoritative sources, scripts/configuration, npm/Composer lockfiles, production assets and generated ownership manifest for the WordPress profile. Ignore installed dependencies, development maps, release output, private staging/caches and temporary fixtures. Keep sanitized environment examples. Test representative paths with git check-ignore; do not untrack unrelated files or commit automatically.

Do not treat a source checkout as an installable distribution until its autoload/dependencies are installed. Test the actual ZIP independently. Do not count an echo/TODO lint command as a passing quality gate. Only the independent Architect can accept the bounded bootstrap task after inspecting scripts, package contents, failure behavior and real installation evidence; completion of this tooling does not approve or complete the product MVP.

## Final handoff validation integration

For a requested handoff controller, follow the installed Architect/Builder workflow's Handoff state and publication contract. Do not build an unsolicited controller during ordinary project setup.

Use one shared read-only validator for the CLI preflight, publisher and dispatcher. Aggregate field-level errors, identify the exact receipt/snapshot, reject an invalid newest prompt instead of falling back, and keep intake correction ownership separate from product-task status. A validator PASS proves the checked structural conditions only, not product correctness.

Verification must include: a valid fixture; invalid newest heading/block with a valid older prompt; contradictory current metadata; stale hashes after a successful check; rejected publication preserving the previous signal bytes; duplicate delivery; same rejected receipt replay; correction routed to sender without changing task status; crash and restart; and an unchanged-invalid receipt stopping after the retry limit. Inner validation exits are 0 valid, 1 content invalid, 2 command/environment failure; outer regression gates pass only for the expected cause and state. Preserve raw results and owned-resource cleanup. Use stub processes before actual CLI integration.

No local rule or JSON file alone proves an agent was awakened or its rules loaded. Verify actual receipt, workspace, role and pinned session during isolated integration. Initialize dispatch disabled until independent acceptance and explicit activation. Do not describe a draft command as implemented or a stub as a live agent loop.
