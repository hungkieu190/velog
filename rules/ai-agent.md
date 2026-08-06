# Rule: AI Agent

**Scope**: All AI-assisted development on VeLog
**Author**: Mamflow
**Mandatory**: Yes — All AI agents must follow this file

---

## Pre-flight Checklist

Before writing any code, an AI agent MUST:

- [ ] Read `AGENT.md` completely.
- [ ] Read the relevant rule file(s) in `rules/`.
- [ ] Inspect the actual file structure — never assume.
- [ ] Identify the feature plan in `plans/` if applicable.
- [ ] Understand the full impact of the change.

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
- ❌ Add new Composer or npm packages.
- ❌ Rename existing hooks, filters, or functions.
- ❌ Change hook priority without explicit instruction.
- ❌ Leave `var_dump`, `console.log`, or `print_r` in code.
- ❌ Write business logic in templates.
- ❌ Write inline CSS or JS (unless explicitly requested).
- ❌ Implement features not yet documented in `plans/`.
- ❌ Guess about file structure — always inspect first.
- ❌ Make assumptions — always ask.
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

- Call the project owner: **pé Kiều Mầm**.
- Present the approach first and ask for approval before implementing.
- Be concise — no unnecessary explanations.
