# React + @wordpress/components Reference

## Setup essentials

```bash
npm install @wordpress/scripts @wordpress/components @wordpress/api-fetch @wordpress/i18n @wordpress/element --save
```

Enqueue in PHP (example):

```php
wp_enqueue_script(
    'my-plugin-admin',
    plugins_url( 'build/index.js', __FILE__ ),
    [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
    filemtime( plugin_dir_path( __FILE__ ) . 'build/index.js' ),
    true
);

wp_enqueue_style( 'wp-components' ); // important
```

## Most-used components

### Button
```jsx
import { Button } from '@wordpress/components';

<Button variant="primary" onClick={ onSave }>
  Save Changes
</Button>

<Button variant="secondary">Cancel</Button>
<Button variant="tertiary">Learn more</Button>
<Button isDestructive>Delete</Button>
<Button isBusy={ isSaving } disabled={ isSaving }>
  { isSaving ? 'Saving…' : 'Save' }
</Button>
```

### TextControl / TextareaControl
```jsx
import { TextControl, TextareaControl } from '@wordpress/components';

<TextControl
  label="API Key"
  value={ apiKey }
  onChange={ setApiKey }
  help="Enter your API key from the dashboard."
/>

<TextareaControl
  label="Description"
  value={ description }
  onChange={ setDescription }
  rows={ 4 }
/>
```

### SelectControl / ToggleControl
```jsx
import { SelectControl, ToggleControl } from '@wordpress/components';

<SelectControl
  label="Status"
  value={ status }
  options={ [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
  ] }
  onChange={ setStatus }
/>

<ToggleControl
  label="Enable feature"
  checked={ enabled }
  onChange={ setEnabled }
  help="Turn this on to activate the feature."
/>
```

### Notice
```jsx
import { Notice } from '@wordpress/components';

{ notice && (
  <Notice
    status={ notice.type } // 'success' | 'error' | 'warning' | 'info'
    isDismissible
    onRemove={ () => setNotice( null ) }
  >
    { notice.message }
  </Notice>
) }
```

### Card (group settings)
```jsx
import { Card, CardHeader, CardBody, CardFooter } from '@wordpress/components';

<Card>
  <CardHeader>
    <h2>General Settings</h2>
  </CardHeader>
  <CardBody>
    {/* controls here */}
  </CardBody>
  <CardFooter>
    <Button variant="primary">Save</Button>
  </CardFooter>
</Card>
```

### Spinner / Placeholder (loading & empty)
```jsx
import { Spinner, Placeholder } from '@wordpress/components';

{ isLoading && <Spinner /> }

{ ! items.length && ! isLoading && (
  <Placeholder
    icon="admin-settings"
    label="No items yet"
    instructions="Create your first item to get started."
  >
    <Button variant="primary" onClick={ onCreate }>
      Add New
    </Button>
  </Placeholder>
) }
```

### TabPanel
```jsx
import { TabPanel } from '@wordpress/components';

<TabPanel
  tabs={ [
    { name: 'general', title: 'General' },
    { name: 'advanced', title: 'Advanced' },
  ] }
>
  { ( tab ) => (
    <div>
      { tab.name === 'general' && <GeneralSettings /> }
      { tab.name === 'advanced' && <AdvancedSettings /> }
    </div>
  ) }
</TabPanel>
```

## Recommended page structure

```jsx
import { __ } from '@wordpress/i18n';
import { Button, Notice, Card, CardBody } from '@wordpress/components';

function SettingsPage() {
  return (
    <div className="wrap my-plugin-settings">
      <h1>{ __( 'My Plugin Settings', 'my-plugin' ) }</h1>

      { notice && (
        <Notice status={ notice.type } onRemove={ clearNotice }>
          { notice.message }
        </Notice>
      ) }

      <Card>
        <CardBody>
          {/* form controls */}
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

## Styling rules with React

- Always enqueue `wp-components` style.
- Prefer component props over custom CSS.
- Use `className` only for layout spacing if needed.
- Avoid Tailwind/Bootstrap on the same page unless the whole admin is an isolated React app.
- For theme color support (WP 7.1+), wrap with `ThemeProvider` from `@wordpress/theme` when available.

## Data fetching pattern

```jsx
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

useEffect( () => {
  apiFetch( { path: '/my-plugin/v1/settings' } )
    .then( setSettings )
    .catch( ( err ) => setNotice( { type: 'error', message: err.message } ) );
}, [] );
```
