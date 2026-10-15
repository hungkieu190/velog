---
name: wordpress-plugin-admin-ui
description: Design clean native WordPress plugin admin interfaces. Use when building or fixing plugin settings pages, forms, tables, buttons, inputs, notices, or any wp-admin UI so it looks consistent with core WordPress instead of custom ugly styles. Also covers React + @wordpress/components approach.
---

# WordPress Plugin Admin UI

Make plugin admin screens look native, clean, and consistent with WordPress core. Prefer WordPress design system over custom CSS.

## Core Rules

1. **Never invent your own button/input/table styles** unless the plugin is a full SPA that completely replaces wp-admin.
2. Use native WordPress classes and components first.
3. Match density of core admin (tight spacing, clear hierarchy).
4. Support the user's admin color scheme via design tokens when possible.
5. Always handle loading, empty, error, and success states.

## Buttons (PHP / classic)

```html
<button type="submit" class="button button-primary">Save Changes</button>
<button type="button" class="button button-secondary">Cancel</button>
<a href="..." class="button">Secondary Link</a>
```

- Primary action = `button-primary`
- Secondary / cancel = `button` or `button-secondary`
- Use `submit_button()` helper in PHP when possible

## Forms & Inputs (PHP / classic)

Prefer the Settings API. Manual markup:

```html
<table class="form-table" role="presentation">
  <tr>
    <th scope="row">
      <label for="my_option">Field Label</label>
    </th>
    <td>
      <input name="my_option" type="text" id="my_option" value="" class="regular-text">
      <p class="description">Helpful description goes here.</p>
    </td>
  </tr>
</table>
```

Input size classes: `regular-text`, `small-text`, `large-text`, `code`.

## Tables (data lists)

```html
<table class="widefat striped">
  <thead>...</thead>
  <tbody>...</tbody>
</table>
```

Best practice: extend `WP_List_Table`.

## Notices (PHP)

```html
<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
<div class="notice notice-error"><p>Something went wrong.</p></div>
```

## Layout & Structure

- Wrap main content in `.wrap`
- Page title: `<h1 class="wp-heading-inline">Plugin Name</h1>`
- Use postboxes (`.postbox`, `.postbox-header`, `.inside`) for grouped settings
- Keep primary action near the top or bottom of the form

## Modern Stack — React + @wordpress/components (preferred for new plugins)

Use this approach when the plugin already has a JS build step or needs interactive UI.

### Required packages
- `@wordpress/scripts` (build tool)
- `@wordpress/components`
- `@wordpress/element`
- `@wordpress/api-fetch`
- `@wordpress/i18n`

### Enqueue correctly
```php
wp_enqueue_script( 'my-plugin-admin', ..., [
  'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'
], ..., true );

wp_enqueue_style( 'wp-components' ); // required
```

### Core components to use

| Need              | Component                          |
|-------------------|------------------------------------|
| Primary button    | `<Button variant="primary">`      |
| Secondary button  | `<Button variant="secondary">`    |
| Text input        | `<TextControl>`                   |
| Textarea          | `<TextareaControl>`               |
| Select            | `<SelectControl>`                 |
| Toggle / switch   | `<ToggleControl>`                 |
| Notice            | `<Notice status="success\|error">`|
| Card / group      | `<Card> <CardHeader> <CardBody>`  |
| Loading           | `<Spinner>`                       |
| Empty state       | `<Placeholder>`                   |
| Tabs              | `<TabPanel>`                      |

### Recommended page skeleton
```jsx
import { __ } from '@wordpress/i18n';
import { Button, Notice, Card, CardBody, Spinner } from '@wordpress/components';

function SettingsPage( { isLoading, notice, onSave, isSaving } ) {
  if ( isLoading ) return <Spinner />;

  return (
    <div className="wrap">
      <h1>{ __( 'Plugin Settings', 'my-plugin' ) }</h1>

      { notice && (
        <Notice status={ notice.type } isDismissible onRemove={ clearNotice }>
          { notice.message }
        </Notice>
      ) }

      <Card>
        <CardBody>
          {/* TextControl, ToggleControl, SelectControl… */}
        </CardBody>
      </Card>

      <p className="submit">
        <Button variant="primary" isBusy={ isSaving } onClick={ onSave }>
          { __( 'Save Changes', 'my-plugin' ) }
        </Button>
      </p>
    </div>
  );
}
```

### Styling rules with React
- Always load the `wp-components` stylesheet.
- Prefer component props over custom CSS.
- Use `className` only for minor layout spacing.
- Do **not** mix Tailwind or Bootstrap on the same admin page unless the entire screen is an isolated React app.
- For WordPress 7.1+ design tokens, wrap the app with `ThemeProvider` from `@wordpress/theme` when available.

See `references/react-components.md` for full component examples and data-fetching patterns.

## CSS Rules (both classic & React)

- Enqueue styles with dependency on `wp-admin` / `common` / `wp-components`
- Prefer CSS custom properties (`--wp-admin-theme-color`) over hard-coded hex
- Never set global `input`, `button`, `select { ... }` rules that override core
- Only add CSS for layout spacing or plugin-specific elements

## Anti-patterns (never do these)

- Large border-radius, heavy shadows, gradients on form controls
- Custom fonts that ignore admin typography
- Purple/blue gradient buttons that look like marketing sites
- Huge padding that makes the admin feel sparse
- Ignoring empty/loading/error states
- Hard-coding colors that ignore the user’s admin color scheme
- Mixing heavy third-party CSS frameworks with native admin styles

## Checklist before shipping any admin screen

- [ ] Uses native classes or `@wordpress/components`
- [ ] Has proper labels + descriptions / help text
- [ ] Success/error notices present
- [ ] Empty state exists when no data
- [ ] Loading state for async actions
- [ ] Works with different admin color schemes
- [ ] No custom CSS fighting core styles
- [ ] Accessible (labels, focus states, contrast)

## Quick PHP helpers

```php
echo '<div class="wrap">';
echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

settings_fields( 'my_plugin_options' );
do_settings_sections( 'my_plugin' );
submit_button();

add_settings_error( 'my_plugin', 'settings_updated', 'Settings saved.', 'success' );
settings_errors( 'my_plugin' );
```

When in doubt, open a core WordPress screen (Settings → General, Plugins list, Posts list) and match its visual language exactly.
