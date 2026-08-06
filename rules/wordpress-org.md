# Rule: WordPress.org Compliance

**Scope**: All plugin releases
**Author**: Mamflow
**Mandatory**: Yes

---

## Overview

VeLog must comply with all [WordPress.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) to be listed in the plugin directory.

---

## Required Compliance

### License

- Plugin must be GPLv2 or later.
- All bundled code must be GPL-compatible.
- License must be declared in the plugin header and `LICENSE` file.

### Plugin Header

The main plugin file must include a valid plugin header:

```php
Plugin Name:       VeLog — Digital Vehicle Passport
Plugin URI:        https://mamflow.com/velog
Description:       ...
Version:           x.y.z
Requires at least: 6.4
Requires PHP:      8.1
Author:            Mamflow
Author URI:        https://mamflow.com
Text Domain:       velog
Domain Path:       /languages
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
```

### Readme

- Must include `readme.txt` or `README.md` in WordPress.org format.
- Must include: Description, Installation, FAQ, Changelog, Screenshots.
- Stable tag must match the latest release tag.

### Security

- No obfuscated code.
- No external calls without user consent.
- No tracking without explicit opt-in.
- Must use WordPress APIs — no direct DB access outside `$wpdb`.

### Assets

- Banner: 772×250px and 1544×500px.
- Icon: 128×128px and 256×256px.
- Screenshots: clear, descriptive filenames.

### Data Privacy

- If collecting data: declare in privacy policy.
- Must integrate with WordPress Privacy Policy tools.

### Forbidden

- ❌ No premium "unlock" features gated behind external service.
- ❌ No spam or unsolicited notifications.
- ❌ No hidden admin accounts.
- ❌ No calls to external update servers (unless WordPress.org update API).
