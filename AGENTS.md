# VeLog agent instructions

Read [the documentation index](ai-document/README.md), [the task checklist](ai-document/implementation-checklist.md), the assigned task, and relevant rules before changes. AGENT.md retains architecture guidance; this file and approved task decisions govern the current workflow when legacy instructions differ.

## Communication and authority

- Reply to the user in Vietnamese. Code, comments, variable names and technical documentation are in English.
- Inspect the actual repository and preserve unrelated user changes.
- Explain scope and obtain explicit approval before implementation; existing approval persists for the approved task.
- Architect plans, creates tasks, independently reviews evidence and accepts. Builder implements approved scope and reports; Builder cannot mark DONE or complete checklist items.
- User approves product decisions and performs required manual acceptance.
- Use DRAFT, READY, IN_PROGRESS, BLOCKED, READY_FOR_REVIEW, CHANGES_REQUESTED, AWAITING_MANUAL_ACCEPTANCE and DONE exactly as defined in ai-document/architect-builder-workflow.md.
- Keep current handoff, latest round, next actor/action and checklist status synchronized. Only Architect changes accepted checkboxes.
- Every handoff needs a copy-ready prompt in both chat and task under `### Chat handoff prompt`, naming exact files, task/status/scope, criteria/findings, verified and NOT VERIFIED checks, and next action.
- Append implementation/review/fix history; preserve stable finding IDs. Missing tests remain NOT VERIFIED.
- Commit, push, tag, deploy, publish, production data changes and external messages require explicit user authorization.
- npm run progress is a read-only visibility tool, never acceptance evidence.

## WordPress implementation

Preserve MF\VeLog namespaces, existing hooks and application behavior unless explicitly scoped. Follow WordPress Coding Standards, capability checks, nonce verification, input validation/sanitization, output escaping, prepared SQL and scoped WordPress enqueue APIs. Keep backend PHP in its current src/ namespaces. Read AGENT.md and relevant rules for architecture and security.

## Frontend Source, Build, and Release Rules

- /src/ is authoritative for project-authored frontend assets: JavaScript in /src/js/, CSS and Sass in /src/css/.
- Sass is retained as the documented CSS preprocessor. Build tools belong in /scripts/.
- Never manually edit generated JavaScript/CSS in /assets/ or fix behavior in /release/.
- After changing sources, build and verify generated output and affected runtime behavior.
- npm run dev performs one unminified development build with maps and exits.
- npm run production builds minified production JS/CSS; npm run build is its compatibility alias.
- npm run release performs fresh production build and stages/packages runtime files under /release/; it never publishes.
- Track production assets, package.json, package-lock.json and composer.lock. Ignore development maps, /release/ and /node_modules/.
- Include source and corresponding production output in the same handoff.
- Do not claim build/release success without a successful actual command. Record exact blockers; do not patch generated outputs.
- Build configuration/dependency changes follow approved Architect / Builder tasks.
- Read ai-document/build-and-release.md before changing entry points, dependencies, outputs or packaging.
- The local dashboard is development tooling: its stylesheet is src/css/progress.css, read directly by its local server, and is excluded from plugin runtime builds/releases.
