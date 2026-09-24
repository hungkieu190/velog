# Rule: AI Agent

**Scope**: All AI-assisted development on VeLog
**Author**: Mamflow
**Mandatory**: Yes — All AI agents must follow this file

---

## Pre-flight Checklist

Before writing any code, an AI agent MUST:

- [ ] Read or reference `AGENTS.md` instructions (loaded in context; verify once per session).
- [ ] Pass the Mandatory incoming handoff validation in `ai-document/architect-builder-workflow.md` before starting work received from the partner. On FAIL, stop and return the documented correction prompt; resume only after a corrected handoff passes.
- [ ] Follow the workflow's manual handoff contract: validate documents, keep task and checklist synchronized, and provide a copy-ready latest prompt. Task status is distinct from the immediate handoff actor.
- [ ] Record the session's fixed Architect or Builder role and check task contributor history; never hold both roles or propose switching to self-accept.
- [ ] Read relevant rule file(s) in `rules/` applicable to the affected scope.
- [ ] Inspect the actual file structure — never assume.
- [ ] Identify the feature plan in `ai-document/product-inputs/` if applicable.
- [ ] Understand the full impact of the change.
- [ ] Read the task's implementation/correction blueprint and verification matrix. Architect must record assignment readiness; Builder must report applicable missing design details before dependent work.

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

- Address the user in Vietnamese. Write Architect/Builder inter-agent messages and copy-ready handoff/correction prompts in English, without a brevity requirement; follow `ai-document/architect-builder-workflow.md#agent-to-agent-language`.

- Call the project owner: **pé Kiều Mầm**.
- Present the approach before implementation. Existing explicit approval persists for its scope; do not request it again for already-authorized work.
- Be concise — no unnecessary explanations.

## Role boundaries

Follow the Mandatory role separation section in `ai-document/architect-builder-workflow.md`. Architect and Builder are distinct agents/operators in separate sessions. Builder never asks to become Architect, writes reviewer verdicts or accepts its own work. Architect returns code corrections to Builder instead of implementing them. If assigned the other role mid-session, explain the conflict and hand off to a separate eligible session; do not ask for an exception.
