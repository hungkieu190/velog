# Rule: AI Agent

## Owner decision — 2026-10-21: Code first; all testing is deferred debt

The Product Owner instructed: "bỏ qua hết tester, kể từ bây giờ các task test sẽ là các task nợ nhé; làm hết code đi đã, test và fix sau". This supersedes earlier test/Tester gates for ongoing and subsequent implementation.

- Codex continues all remaining MVP application code directly, without waiting for Tester/UAT, test environments or test/fix rounds between features. The current instruction authorizes the remaining documented code queue; do not repeatedly request implementation permission for it.
- Preserve already recorded test results and accepted features. Move unperformed manual, unit/integration/runtime/performance/regression work to explicit test-debt tasks; do not mark skipped work PASS or accepted.
- Distinguish implementation completion from functional acceptance. Parent features may remain AWAITING_MANUAL_ACCEPTANCE with Next actor Developer for the next code item; this status and outstanding UAT are not implementation dependencies. A code checkpoint may be DONE only when explicitly labelled implementation-only. Do not infer product acceptance.
- Test-debt tasks use DRAFT with Debt: TEST_DEBT and Scheduling: DEFERRED; they are not current Tester handoffs. Keep their cases and prerequisites for a later consolidated test/fix phase. No new test suite or fixture preparation is required during the code phase.
- Perform necessary compilation/build and PHP syntax checks to produce loadable artifacts, inspect source/security boundaries, and record actual limitations. These checks are not functional tests. Do not defer a known syntax/build failure or bypass capabilities/nonces/validation just to keep coding.
- Keep source/generated assets synchronized. Do not run functional suites, browsers, benchmarks, test seeds or live fixture writes merely to close a feature during this phase. Actual known functional issues may be recorded as fix debt; disclose material limitations honestly.
- User remains Product Owner and may resume testing later. Codex is sole Developer; no active Tester gate or separate reviewer is required. External commit/push/deploy/release still requires explicit authorization.


**Scope**: All AI-assisted development on VeLog
**Author**: Mamflow
**Mandatory**: Yes — All AI agents must follow this file

---

## Pre-flight Checklist

Before writing any code, an AI agent MUST:

- [ ] Read or reference `AGENTS.md` instructions (loaded in context; verify once per session).
- [ ] Pass the Mandatory incoming handoff validation in `ai-document/backend-frontend-workflow.md` before starting work received from the partner. On FAIL, stop and return the documented correction prompt; resume only after a corrected handoff passes.
- [ ] Follow the workflow's manual handoff contract: validate documents, keep task and checklist synchronized, and provide a copy-ready latest prompt. Task status is distinct from the immediate handoff actor.
- [ ] Resolve the current role from agent-roles.json: Codex is the sole Developer for backend/frontend; the user is Tester / Product Owner. Developer self-reviews technically and never self-accepts a feature.
- [ ] Read relevant rule file(s) in `rules/` applicable to the affected scope.
- [ ] Inspect the actual file structure — never assume.
- [ ] Identify the feature plan in `ai-document/product-inputs/` if applicable.
- [ ] Understand the full impact of the change.
- [ ] Read the task's implementation/correction blueprint and verification matrix. Developer records blueprint readiness and resolves missing design details before dependent work.

---

## Constraints

### What AI Agents MUST Do

- ✅ Prefix all functions with `mf_`.
- ✅ Use namespace `MF\VeLog\`.
- ✅ Add `ABSPATH` guard to every PHP file.
- ✅ Use `sanitize_*()` for all input.
- ✅ Use `esc_*()` for all output.
- ✅ Use `$wpdb->prepare()` for all queries.
- ✅ Add PHPDoc to all public methods.
- ✅ Write in English only (comments, docstrings).
- ✅ Follow the one-function-one-responsibility principle.
- ✅ Request clarification before writing code if requirements are unclear.

### What AI Agents MUST NOT Do

- ❌ Modify WordPress core files.
- ❌ Modify third-party plugin files.
- ❌ Add new Composer or npm packages outside explicitly approved task scope.
- ❌ Rename existing hooks, filters, or functions.
- ❌ Change hook priority without explicit instruction.
- ❌ Leave `var_dump`, `console.log`, or `print_r` in code.
- ❌ Write business logic in templates.
- ❌ Write inline CSS or JS (unless explicitly requested).
- ❌ Implement features without an approved task in `ai-document/tasks/`.
- ❌ Guess about file structure — always inspect first.
- ❌ Invent undiscoverable requirements. Inspect repository facts first; ask only for unresolved product decisions or information that cannot be obtained locally.
- ❌ Refactor outside the requested scope.

---

## Forbidden Phrases

AI agents must NOT use these phrases:

- "I think..."
- "It might be located in..."
- "Assuming the structure is..."
- "I'll refactor this to be cleaner..."

Instead: inspect the actual files and report what is found.

---

## Output Requirements

Every code output must be:

- ✅ Production-ready.
- ✅ No debug code.
- ✅ No placeholder logic without `// TODO:` comment.
- ✅ Minimal explanation — code speaks for itself.
- ✅ Scoped to only what was requested.

---

## Conflict Resolution Priority

```
Security → Stability → Backward Compatibility → Performance → Clean Code
```

---

## When Information Is Missing

If ANY of the following is unknown, DO NOT write code. Request:

1. Exact file path.
2. Usage context.
3. Scope of impact.
4. Related hooks/filters.

---

## Communication Style

- Address the user in Vietnamese; write technical documentation and copy-ready handoff prompts in English. Follow ai-document/backend-frontend-workflow.md. UAT instructions are written in Vietnamese for Tester.

- Call the project owner: **pé Kiều Mầm**.
- Present the approach before implementation. Existing explicit approval persists for its scope; do not request it again for already-authorized work.
- Be concise — no unnecessary explanations.

## Role boundaries

The 2026-10-21 owner decision in AGENTS.md supersedes retired Architect/Builder and Backend Architect/Frontend Developer separation. Codex is the sole Developer for architecture, backend, frontend, tests, generated assets and technical self-review. The human is Tester / Product Owner and alone grants manual feature acceptance. No separate frontend agent or independent code reviewer is required or claimed. Record SELF_REVIEWED_DEVELOPMENT and actual limitations; only explicit Tester PASS permits feature DONE.
