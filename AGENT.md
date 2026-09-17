> Read [AGENTS.md](AGENTS.md) first for the current workflow and authority. This manual retains project architecture and coding guidance.

# AGENT.md — VeLog AI Development Manual

> **All AI agents must read this file completely before generating any code.**

---

## 1. Project Identity

| Field        | Value                                          |
|-------------|------------------------------------------------|
| Plugin Name  | VeLog — Digital Vehicle Passport               |
| Author       | Mamflow                                        |
| Website      | https://mamflow.com                            |
| Text Domain  | `velog`                                        |
| Namespace    | `MF\VeLog\`                                    |
| PHP Prefix   | `mf_`                                          |
| License      | GPL-2.0-or-later                               |
| PHP Min      | 8.1+                                           |
| WP Min       | 6.4+                                           |

---

## 2. Project Philosophy

1. **Document First** — Implement only an approved task in `ai-document/tasks/`; `plans/current/` contains product inputs.
2. **Design Second** — Architecture is reviewed before implementation.
3. **Implement Third** — Code is written only after documentation and design are approved.
4. **Never implement undocumented features.**
5. **Security is the highest priority** — always above performance and clean code.

---

## 3. Architecture

```
velog/
├── velog.php              ← Entry point ONLY: constants, loader, hooks
├── uninstall.php          ← Cleanup on plugin delete
├── src/
│   ├── Core/
│   │   ├── Plugin.php     ← Singleton bootstrap
│   │   ├── Loader.php     ← Hook/filter registry
│   │   ├── Activator.php  ← Activation logic
│   │   └── Deactivator.php
│   ├── Admin/             ← Admin-only classes
│   ├── Frontend/          ← Frontend-only classes
│   ├── Api/               ← REST API endpoints
│   └── Common/            ← Shared utilities
├── assets/
│   ├── css/               ← Compiled CSS (DO NOT edit directly)
│   └── js/                ← Compiled JS (DO NOT edit directly)
├── languages/             ← .pot / .po / .mo files
├── tests/
│   ├── Unit/
│   └── Integration/
├── docs/                  ← Technical documentation
├── plans/                 ← Feature planning
└── rules/                 ← Coding constraints
```

### Layers

| Layer      | Namespace            | Purpose                          |
|-----------|----------------------|----------------------------------|
| Core       | `MF\VeLog\Core\`     | Bootstrap, DI, hooks             |
| Admin      | `MF\VeLog\Admin\`    | WP admin UI, settings            |
| Frontend   | `MF\VeLog\Frontend\` | Public-facing output             |
| API        | `MF\VeLog\Api\`      | REST API endpoints               |
| Common     | `MF\VeLog\Common\`   | Shared utilities, interfaces     |

---

## 4. Mandatory Coding Rules

### 4.1 Security (Non-negotiable)

Every PHP file MUST start with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

Always:
- `sanitize_*()` → input
- `esc_*()` → output
- `wp_nonce_*()` + `check_admin_referer()` → forms
- `current_user_can()` → capability checks before any sensitive action

Never:
- Bypass security for "internal use"
- Use `$_GET` / `$_POST` without sanitization

### 4.2 Naming

- Functions: `mf_velog_*()` or `mf_*()`
- Classes: `MF\VeLog\Path\ClassName`
- Hooks: `mf_velog_hook_name`
- Constants: `VELOG_CONSTANT_NAME`
- Never use anonymous functions if logic > 3 lines

### 4.3 Database

- Always use `$wpdb->prepare()`
- Never `SELECT *`
- Never queries inside loops
- All WRITE queries require: capability check + nonce + rollback logic

### 4.4 Assets (Strict Build-First Rule)

- ❌ **STRICT PROHIBITION**: NEVER edit files inside `assets/css/` or `assets/js/` directly. They are compiled build artifacts.
- ✅ **Source-First Requirement**: Always edit SCSS and JS source files in `src/css/` and `src/js/`.
- 🔄 **Rebuild Mandate**: After modifying any source file in `src/`, ALWAYS run `npm run build` (or `npm run dev`) to compile changes into `assets/`.
- Enqueue correctly: scope (admin/frontend), dependencies, versioning, conditions.

### 4.5 Clean Code

- One function = one responsibility
- No business logic in templates
- No inline JS/CSS unless explicitly requested
- No `var_dump`, `console.log`, `print_r` in production

---

## 5. Development Workflow

```
1. Feature Request (see AGENTS.md and ai-document/ for task handoffs)
       ↓
2. Create approved task: ai-document/tasks/<id>-<slug>.md
       ↓
3. User scope approval and Architect design review
       ↓
4. Implementation
       ↓
5. Tests (Unit + Integration)
       ↓
6. Builder handoff; independent Architect review (PHPCS + PHPStan must pass)
       ↓
7. Documentation update
       ↓
8. Release
```

---

## 6. Feature Plan Template

Product feature inputs in `plans/current/` use the following outline; approved implementation tasks follow `ai-document/architect-builder-workflow.md`:

```markdown
# Feature: [Name]

## Summary
## User Story
## Acceptance Criteria
## Technical Design
## Database Schema (if applicable)
## REST API (if applicable)
## Security Considerations
## Test Plan
## Open Questions
```

---

## 7. AI Agent Rules

1. **Always read AGENT.md first** before generating any code.
2. **Always read the relevant rule file** in `rules/` before implementing.
3. **Never assume the file structure** — always inspect actual files.
4. **Never refactor outside requested scope.**
5. **Never rename hooks, filters, or functions** without explicit request.
6. Add dependencies only within explicitly approved task scope.
7. **Always request missing information** rather than making assumptions:
   - File path
   - Usage context
   - Scope of impact
8. **Every output must be production-ready** — no debug code.
9. **Asset Build Rule**: ALWAYS edit SCSS/JS in `src/` and rebuild using `npm run build` / `npm run dev`. NEVER edit `/assets/` (`assets/css/`, `assets/js/`) directly.

---

## 8. Release Process

1. Update `CHANGELOG.md`.
2. Validate version declarations per `ai-document/build-and-release.md`; do not automatically bump versions.
3. Run `composer run lint` (must pass 100%).
4. Run `composer run test` (must pass 100%).
5. Create a local package with `npm run release`; tagging requires separate explicit authorization.
6. Review package evidence and complete manual acceptance.
7. Publish only after explicit user authorization.

---

## 9. Documentation Standards

- All public classes and methods must have PHPDoc blocks.
- All hooks must be documented: `@hook`, parameters, return type.
- Architecture Decision Records (ADRs) go in `docs/decisions/`.
- Feature documentation goes in `docs/features/`.

---

## 10. Contact

**Author**: Mamflow
**Website**: https://mamflow.com
**Support**: https://mamflow.com/support
