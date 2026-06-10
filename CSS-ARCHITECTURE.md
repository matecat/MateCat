# CSS Architecture Guide

> **One-page reference for contributors.** For full analysis and migration roadmap, see `MateCat-docs/frontend/css-architecture-analysis.md`.

---

## Tier System

All CSS in this project follows a three-tier model:

| Tier | Scope | Location | Rules |
|------|-------|----------|-------|
| **Tier 1 — New components** | CSS Modules (`.module.scss`) | Co-located with component in `public/js/components/` | BEM naming, max 3 nesting levels, no `!important`, use tokens |
| **Tier 2 — Existing components** | Global SCSS with BEM discipline | `public/css/sass/components/` | Max 3 nesting levels (warning), no new `!important` |
| **Tier 3 — Legacy (frozen)** | Global SCSS, quarantined | `public/css/sass/style.scss`, `commons/_manage.scss`, `commons/_outsource.scss` | No new additions. Extract incrementally when touched. |

### Which tier do I use?

- **New component?** → Tier 1. Create `ComponentName.module.scss` next to your `.jsx`.
- **Modifying existing component (<100 lines)?** → Convert to Tier 1.
- **Modifying existing component (≥100 lines)?** → Stay in Tier 2. Flatten nesting + replace hardcoded colors.
- **Need a global style (resets, base typography)?** → `public/css/sass/tokens/` or `commons/`.
- **Tempted to edit `style.scss`?** → Don't. Find or create the right component file.

---

## Design Tokens

Tokens live in `public/css/sass/tokens/`. Three token files:

| File | Purpose | Example |
|------|---------|---------|
| `_colors.scss` | Color palette + semantic aliases | `var(--color-approved)` or `colors.$approvedGreen` |
| `_z-index.scss` | Named stacking context scale | `var(--z-modal)` or `$z-modal` |
| `_spacing.scss` | Consistent spacing scale (4px base) | `var(--space-4)` or `$space-4` |

### Usage

**New code (preferred):**
```scss
.my-component {
  color: var(--color-approved);
  padding: var(--space-4);
  z-index: var(--z-dropdown);
}
```

**Legacy code (still works):**
```scss
@use '../tokens/colors';
.my-component {
  color: colors.$approvedGreen;
}
```

### Rules

- **Never hardcode hex/rgb values** in new code. Use a token.
- **Never use raw z-index numbers** in new code. Use the named scale.
- **Shadows**: use `rgba()` with a token color base (e.g., `rgba(colors.$black, 0.1)`).

---

## Z-Index Scale

| Token | Value | Use for |
|-------|-------|---------|
| `--z-content` | 1 | Default positioned elements |
| `--z-dropdown` | 10 | Dropdown menus, popovers |
| `--z-sticky` | 20 | Sticky headers, fixed nav |
| `--z-overlay` | 30 | Overlays, backdrop layers |
| `--z-modal` | 40 | Modal dialogs |
| `--z-toast` | 50 | Toast notifications, popups |
| `--z-tooltip` | 60 | Tooltips (highest UI layer) |

If your element doesn't fit one of these, you're likely creating a new stacking context that should be rethought.

---

## CSS Modules

Vite handles `*.module.scss` files automatically. No configuration needed.

```jsx
// public/js/components/common/MyWidget/MyWidget.jsx
import styles from './MyWidget.module.scss'

export default function MyWidget({ active }) {
  return (
    <div className={styles.widget}>
      <span className={active ? styles['widget--active'] : ''}>
        Content
      </span>
    </div>
  )
}
```

```scss
// public/js/components/common/MyWidget/MyWidget.module.scss
.widget {
  padding: var(--space-4);
  border: 1px solid var(--color-grey-200);

  &--active {
    border-color: var(--color-approved);
  }
}
```

### BEM in Modules

Even though CSS Modules scope classes locally, we use BEM for readability:

- **Block**: `.widget` (component name, lowercase)
- **Element**: `.widget__header` (double underscore)
- **Modifier**: `.widget--active` (double dash)

Stylelint enforces this pattern in `.module.scss` files.

---

## Naming Convention

| Context | Convention | Example |
|---------|-----------|---------|
| CSS Modules | BEM (enforced) | `.button__icon--large` |
| Existing components | BEM (encouraged) | `.segment-footer__tab` |
| Legacy files | Leave as-is | Don't rename existing classes |

---

## Stylelint

Run manually:
```bash
yarn lint:css
```

### What it enforces:

| Rule | Severity | Scope |
|------|----------|-------|
| `max-nesting-depth: 3` | Warning | All files (except legacy quarantine) |
| `declaration-no-important` | Warning | All files (except legacy quarantine) |
| BEM selector pattern | Error | `.module.scss` files only |

### Warning budget

The current baseline is **1023 warnings**. Adding new `!important` or deep nesting will push over budget and fail CI. Fix existing violations to earn headroom — or remove them when you touch a file.

---

## Quick Reference

### Do ✓

- Use CSS Modules for new components
- Use design tokens for colors, spacing, z-index
- Keep nesting ≤3 levels
- Use BEM naming
- Co-locate `.module.scss` with the component
- Remove `!important` when you touch a file with it

### Don't ✗

- Add styles to `style.scss`
- Use raw hex colors in new code
- Use raw z-index numbers in new code
- Use `!important` in new code
- Nest selectors deeper than 3 levels
- Create new files in `commons/` (use `tokens/` or component co-location)
