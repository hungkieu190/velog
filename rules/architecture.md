# Rule: Architecture

**Scope**: All plugin source code
**Author**: Mamflow
**Mandatory**: Yes

---

## Core Principles

1. **SOLID** — Single Responsibility, Open/Closed, Liskov, Interface Segregation, Dependency Inversion.
2. **Modular** — Each feature is isolated. Changing one module must not break another.
3. **WordPress-native** — Use WordPress APIs before custom solutions.
4. **No business logic in root file** — `velog.php` only defines constants and boots the plugin.

---

## Layer Architecture

```
┌─────────────────────────────────┐
│         velog.php               │ ← Entry point (constants + boot)
├─────────────────────────────────┤
│         Core Layer              │ ← Plugin, Loader, Activator, Deactivator
├─────────────────────────────────┤
│         Admin Layer             │ ← Admin pages, meta boxes, settings
├─────────────────────────────────┤
│         Frontend Layer          │ ← Shortcodes, public templates
├─────────────────────────────────┤
│         API Layer               │ ← REST endpoints
├─────────────────────────────────┤
│         Common Layer            │ ← Shared utilities, interfaces, traits
└─────────────────────────────────┘
```

---

## Rules

### Namespacing

- Root namespace: `MF\VeLog\`
- Each layer has its own sub-namespace.
- ❌ Never mix layers in one class.

### Dependency Injection

- Pass dependencies via constructor, not via static calls where possible.
- Use the `Loader` for all hook registrations.
- Never call `add_action()` / `add_filter()` outside of designated `define_*_hooks()` methods.

### File Structure

- One class per file.
- Filename matches class name: `Plugin.php` → `class Plugin`.
- All source files live in `src/`.

### Coupling

- Layers may only depend on the Common layer and the layer directly below them.
- ❌ Frontend must not call Admin classes.
- ❌ API must not call Admin classes.

### Hooks

- ❌ Do NOT rename existing hooks.
- ❌ Do NOT change hook priority without explicit justification.
- ❌ Do NOT change global state from inside hooks.

---

## Impact Assessment (Mandatory Before Change)

Before modifying any existing class, evaluate:

1. Is this class used elsewhere?
2. Which hooks depend on it?
3. Backward compatibility risk?
4. Cache or asset version impact?

❌ If impact cannot be fully assessed → DO NOT modify.
