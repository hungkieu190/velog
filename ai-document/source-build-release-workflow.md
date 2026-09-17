# Source, Build, and Release Workflow Bootstrap

## 1. Purpose and Authority

Use this document with the Architect / Builder bootstrap and the product plan.

Establish a project-specific workflow for:

- Authoring frontend source files.
- Managing `package.json` and dependencies.
- Building development and production assets.
- Packaging a complete release.
- Maintaining `.gitignore`.
- Enforcing these rules through `AGENTS.md`.

Follow the existing Architect / Builder approval workflow.

Architect inspects the repository and prepares the implementation task.
Builder creates or modifies build scripts only after approval.

Do not replace an existing working build system without examining it first.

All paths below are relative to the project root. `/release/` means the project's release directory, not the operating system root.

## 2. Mandatory Source-of-Truth Rule

All project-authored frontend JavaScript and CSS must originate in `/src/`.

- Edit JavaScript in `/src/js/`.
- Edit CSS in `/src/css/`.
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
      admin.css
      frontend.css
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
src/css/frontend.css -> assets/css/frontend.css
```

Production files are minified even without `.min` in the filename.

If the project already requires `.min` names or hashed filenames, document the mapping and update runtime resolution accordingly.

### npm run release

Create a complete local distribution package.

Required sequence:

1. Validate release configuration and version.
2. Run a fresh production build.
3. Stage runtime files using an explicit allowlist.
4. Validate the staged package.
5. Create the versioned ZIP archive.
6. Report the output paths and verification result.

Required outputs:

```text
release/<project-slug>/
release/<project-slug>-<version>.zip
```

The ZIP must contain one top-level `<project-slug>/` directory.

Do not package development assets left over from a previous build.

This command does not publish, upload, deploy, create a Git tag, or push commits.

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
    "release": "node scripts/release.mjs",
    "watch": "node scripts/build.mjs --mode=development --watch"
  }
}
```

The release script must invoke and await the production build itself. Do not rely on someone having run it beforehand.

Dependency rules:

- Put build tools and packaging libraries in `devDependencies`.
- Put packages required by the installed application at runtime in `dependencies`.
- Do not invent dependency versions.
- Select compatible versions and generate `package-lock.json` through npm.
- Commit `package.json` and `package-lock.json`.
- Use `npm install` when intentionally initializing or changing dependencies.
- Use `npm ci` for clean verification and CI.
- Do not rely on globally installed build or ZIP tools.
- Do not silently fetch new dependencies during release packaging.

For a simple JavaScript/CSS project, esbuild is an acceptable default. Preserve an existing framework-native build tool when it satisfies the required commands.

Document the selected Node.js version and keep `.nvmrc` consistent with `engines`.

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
- Runtime code must not load `/src/` files.
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

- `/src/`.
- `/scripts/`.
- `/node_modules/`.
- `/ai-document/`.
- `/release/`.
- `.git/` and repository metadata.
- Local environment files and credentials.
- Tests, fixtures, coverage, and temporary logs.
- Development-only dependency files.
- Source maps unless explicitly approved for distribution.
- Editor and operating-system files.

Do not blindly exclude runtime dependencies such as Composer `vendor/`. Determine what the application needs after installation.

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

Default for a WordPress plugin repository that must work immediately after checkout:

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
- `npm run release` performs a fresh production build and packages
  the runtime application under `/release/`.
- `/release/` and `/node_modules/` must be ignored by Git.
- Commit `package.json` and `package-lock.json`.
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
- Source, development dependencies, secrets, and nested releases are absent.
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
