# Rule: Security

**Scope**: All plugin code
**Author**: Mamflow
**Mandatory**: Yes — HIGHEST PRIORITY

---

## Principle

**Security is the highest priority** — above performance, clean code, and backward compatibility.

---

## ABSPATH Guard

Every PHP file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## Input Sanitization

Always sanitize input before using it:

| Data Type         | Function                          |
|------------------|-----------------------------------|
| Text              | `sanitize_text_field()`           |
| Textarea          | `sanitize_textarea_field()`       |
| Email             | `sanitize_email()`                |
| URL               | `esc_url_raw()`                   |
| Integer           | `absint()` or `intval()`          |
| Float             | `(float)`                         |
| HTML              | `wp_kses_post()`                  |
| Filename          | `sanitize_file_name()`            |
| Key/Slug          | `sanitize_key()`                  |

---

## Output Escaping

Always escape before outputting:

| Context       | Function                          |
|--------------|-----------------------------------|
| HTML          | `esc_html()`                      |
| Attribute     | `esc_attr()`                      |
| URL           | `esc_url()`                       |
| JS            | `esc_js()`                        |
| Translation   | `esc_html__()`, `esc_attr__()`    |
| Rich text     | `wp_kses_post()`                  |

---

## Nonce Verification

All forms and AJAX requests must use nonces:

```php
// Create:
wp_nonce_field( 'mf_velog_action', 'mf_velog_nonce' );

// Verify:
check_admin_referer( 'mf_velog_action', 'mf_velog_nonce' );

// AJAX verify:
check_ajax_referer( 'mf_velog_action', 'nonce' );
```

---

## Capability Checks

Before every sensitive operation:

```php
if ( ! current_user_can( 'manage_vehicles' ) ) {
    wp_die( esc_html__( 'You do not have permission to do this.', 'velog' ) );
}
```

---

## Database

- Always use `$wpdb->prepare()`.
- Never `SELECT *`.
- Never queries inside loops.
- All WRITE queries: capability check + nonce + rollback if applicable.

---

## File Uploads

- Validate file type with `wp_check_filetype()`.
- Allowed types: jpg, jpeg, png, webp only.
- Store in WordPress uploads directory — never in plugin directory.

---

## Forbidden

- ❌ No `eval()`.
- ❌ No `$_GET` / `$_POST` without sanitization.
- ❌ No direct file inclusion from user input.
- ❌ No security bypass for "internal" or "trusted" use.
- ❌ No hardcoded credentials.
