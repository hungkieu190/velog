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

1. **Document First** — No code is written without a feature plan in `plans/current/`.
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
- ✅ **Source-First Requirement**: Always edit SCSS and JS source files in `src/assets/scss/` and `src/assets/js/`.
- 🔄 **Rebuild Mandate**: After modifying any source file in `src/assets/`, ALWAYS run `npm run build` (or `npm run dev`) to compile changes into `assets/`.
- Enqueue correctly: scope (admin/frontend), dependencies, versioning, conditions.

### 4.5 Clean Code

- One function = one responsibility
- No business logic in templates
- No inline JS/CSS unless explicitly requested
- No `var_dump`, `console.log`, `print_r` in production

---

## 5. Development Workflow

```
1. Feature Request
       ↓
2. Create plan: plans/current/feature-name.md
       ↓
3. Design review (architecture, DB schema, API)
       ↓
4. Implementation
       ↓
5. Tests (Unit + Integration)
       ↓
6. Code review (PHPCS + PHPStan must pass)
       ↓
7. Documentation update
       ↓
8. Release
```

---

## 6. Feature Plan Template

Every feature plan in `plans/current/` must include:

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
6. **Never add new libraries or Composer packages** without explicit request.
7. **Always request missing information** rather than making assumptions:
   - File path
   - Usage context
   - Scope of impact
8. **Every output must be production-ready** — no debug code.
9. **Asset Build Rule**: ALWAYS edit SCSS/JS in `src/assets/` and rebuild using `npm run build` / `npm run dev`. NEVER edit `/assets/` (`assets/css/`, `assets/js/`) directly.

---

## 8. Release Process

1. Update `CHANGELOG.md`.
2. Bump version in `velog.php` and `composer.json`.
3. Run `composer run lint` (must pass 100%).
4. Run `composer run test` (must pass 100%).
5. Tag the release: `git tag v{version}`.
6. Build the production zip.
7. Submit to mamflow.com and WordPress.org.

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
