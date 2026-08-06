# Rule: Coding Style

**Scope**: All PHP, JS, CSS source files
**Author**: Mamflow
**Mandatory**: Yes

---

## PHP

Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).

### Naming

| Type       | Convention                        | Example                          |
|-----------|-----------------------------------|----------------------------------|
| Functions  | `snake_case` with `mf_` prefix    | `mf_velog_get_vehicle()`         |
| Classes    | `PascalCase` in `MF\VeLog\`       | `MF\VeLog\Core\Plugin`           |
| Interfaces | `PascalCase` + `Interface` suffix | `VehicleRepositoryInterface`     |
| Constants  | `UPPER_SNAKE_CASE`                | `VELOG_VERSION`                  |
| Variables  | `snake_case`                      | `$vehicle_id`                    |
| Hooks      | `mf_velog_*`                      | `mf_velog_after_vehicle_created` |

### Formatting

- **Indentation**: Tabs (not spaces).
- **Line length**: Max 120 characters.
- **Brace style**: Allman (opening brace on new line for classes/functions).
- **Yoda conditions**: Required (e.g., `if ( 'value' === $var )`).

### Comments

- All public methods must have PHPDoc.
- Inline comments in English only.
- `// TODO:` for unimplemented placeholders.

### Anonymous Functions

- ❌ Forbidden if logic > 3 lines.
- Use named methods/callbacks instead.

---

## JavaScript

Follow [@wordpress/eslint-plugin](https://www.npmjs.com/package/@wordpress/eslint-plugin).

- **Indentation**: 2 spaces.
- **Quotes**: Single quotes.
- **Semicolons**: Required.
- No `var` — use `const` or `let`.
- No `console.log` in production.

---

## CSS / SCSS

Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/).

- **Indentation**: 2 spaces.
- **Naming**: BEM methodology — `.mf-velog__block--modifier`.
## Asset Editing Rule (Build-First)

- ❌ **NEVER edit files in `assets/css/` or `assets/js/` directly.** They are compiled build outputs.
- ✅ Always edit SCSS/JS source files in `src/assets/scss/` and `src/assets/js/`.
- 🔄 Always run `npm run build` (or `npm run dev`) after modifying source assets.

---

## Enforcement

```bash
# PHP
composer run phpcs
composer run phpstan

# JS (future)
npm run lint:js

# CSS (future)
npm run lint:css
```
