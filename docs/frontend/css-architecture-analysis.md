# CSS Architecture Analysis & Refactoring Roadmap

> **Status**: Pending — blocked on open PRs (design refactoring + context preview).
> Begin Phase 0 after both PRs are merged.
>
> _Analysis conducted: May 2026_

---

## Table of Contents

1. [Current State Audit](#1-current-state-audit)
   - [Structural Overview](#11-structural-overview)
   - [What Already Works](#12-what-already-works)
   - [Technical Debt Inventory](#13-technical-debt-inventory)
2. [Proposed New Architecture](#2-proposed-new-architecture)
   - [Isolation & Scoping Strategy](#21-isolation--scoping-strategy)
   - [File Organization](#22-file-organization)
   - [Design Token System](#23-design-token-system)
3. [Incremental Migration Roadmap](#3-incremental-migration-roadmap)
   - [Phase 0 — Foundations](#phase-0--foundations-23-weeks-no-visible-changes)
   - [Phase 1 — Component Library Migration](#phase-1--component-library-migration-ongoing-612-weeks)
   - [Phase 2 — Feature Component Cleanup](#phase-2--feature-component-cleanup-rolling)
   - [Phase 3 — Legacy File Extraction](#phase-3--legacy-file-extraction-618-months)
   - [Coexistence Rules](#coexistence-rules-during-transition)
4. [Summary Table](#4-summary-table)

---

## 1. Current State Audit

### 1.1 Structural Overview

All CSS lives in a single centralized directory (`public/css/sass/`) with **zero component-level colocation**. Styles reach the DOM via PHP-rendered `<link>` tags, not JS imports — meaning Vite cannot tree-shake unused styles.

```
public/css/sass/
├── style.scss                  ← Monolithic legacy entry (2,927 lines)
├── common.scss
├── common-main.scss
├── common-components.scss
├── cattool.scss                ← Editor page bundle
├── upload-page.scss
├── activity-log-main.scss
├── ... (other page entries)
│
├── commons/                    ← Shared utilities (22 files)
│   ├── _colors.scss            ← SCSS variable palette
│   ├── _variables.scss         ← Layout/spacing vars + vendor-prefix mixins
│   ├── _mixins.scss
│   ├── _typography.scss
│   ├── _buttons.scss
│   └── ... (icons, shadows, tooltips, nav-bar, etc.)
│
├── components/                 ← Feature-organized (83 files)
│   ├── common/                 ← 24 reusable UI components
│   │   └── Button.scss, Input.scss, Select.scss, Dropdown.scss ...
│   ├── segment/                ← 13 files (Editor, Glossary, Footer tabs…)
│   ├── settingsPanel/          ← 11 files
│   ├── pages/                  ← 9 page-level files
│   ├── header/                 ← 7 files
│   └── signin/                 ← 6 files
│
├── modals/                     ← 5 modal files
└── vendor_mc/semantic/         ← Semantic UI framework overrides
```

**Build pipeline**: Vite + Dart Sass. Multiple SCSS entry points compile to hashed CSS bundles in `/public/build/assets/`. No CSS Modules, no CSS-in-JS, no Tailwind.

**React coupling**: Complete decoupling. Only 2 React components import CSS (both import `react-datepicker/dist/react-datepicker.css` from npm). All other styles are loaded globally via PHP templates. 46 components use `style={{}}` inline props, but only for legitimate dynamic layout (toggling `display`, setting JS-driven `height`/`width`) — not a debt concern.

---

### 1.2 What Already Works

| Strength | Detail |
|---|---|
| Migrated to `@use` | SCSS module system adopted; only 1 legacy `@import` remains (vendor Google Fonts) |
| Component SCSS directory | `components/common/` has 24 dedicated files: Button, Input, Select, Dropdown, Checkbox, Switch, Tooltip, etc. |
| Color palette centralized | `_colors.scss` defines all brand colors as SCSS variables |
| BEM naming started | Segment and common components use `__`/`--` conventions consistently |
| Button has CSS custom properties | `Button.scss` uses `--btnTextColor`, `--btnBgColor`, etc. — a correct design token prototype |
| Vite already configured | SCSS compiles via Vite with `lightningcss`; source maps enabled |

---

### 1.3 Technical Debt Inventory

#### 🔴 Critical — `!important` Abuse

| Metric | Value |
|---|---|
| Files affected | 67 of 125 (54%) |
| Internal occurrences | **1,039** |
| Vendor CSS (Semantic UI) | +458 additional |
| Worst offender | `style.scss` — 151 occurrences |

Root cause: global scope collisions and deep selector nesting made the cascade unmanageable, so authors resorted to overrides. Self-reinforcing — once `!important` is common, new code also needs it to win.

#### 🔴 Critical — Monolithic Legacy File

`style.scss` at **2,927 lines** mixes global resets, page-specific layout, legacy component styles, and one-off overrides. No clear owner; high risk to modify.

Three `commons/` files have similarly grown beyond their mandate:

| File | Lines |
|---|---|
| `_outsource.scss` | 1,324 |
| `_analyze.scss` | 1,093 |
| `_manage.scss` | 1,078 |

These are effectively page stylesheets masquerading as shared utilities.

#### 🔴 High — Nesting Depth

SCSS nesting beyond 3–4 levels generates highly specific selectors that are brittle and impossible to reuse:

| File | Max Nesting Depth |
|---|---|
| `segmentsFilter.scss` | **20 levels** |
| `QualityReportPage.scss` | 18 levels |
| `_outsource.scss` | 14 levels |
| `_manage.scss` | 12 levels |

A 20-level nesting produces a selector like `.body .header .nav .menu .item .sub .link.active span::before` — unmaintainable and immune to reuse.

#### 🟠 High — Color Hardcoding

| Metric | Value |
|---|---|
| Hardcoded hex/rgb values (outside token files) | **1,014** |
| SCSS token usages (`colors.$xxx`) | 968 |
| CSS custom property usages (`var(--)`) | 29 |
| CSS custom properties defined | ~60 (mostly in `Button.scss`) |

Token adoption is ~50%. The other half of the codebase hardcodes values like `#002b5c` or `rgba(124, 197, 118, 0.15)` — often with comments like `/* $approvedGreen */` indicating the author knew the token existed but didn't use it.

#### 🟠 Moderate — Z-Index Chaos

No stacking context system. Z-index values are arbitrary, ranging from `0` to `99999999999999`:

```
z-index: 99999999999999  ← 2 occurrences
z-index: 100000000       ← 3 occurrences
z-index: 2147483647      ← INT32_MAX
```

Total hardcoded z-index declarations: **177**. No named scale or stacking context strategy exists.

#### 🟡 Moderate — Naming Convention Inconsistency

Three conventions coexist with no enforced rule:

| Convention | Example | Context |
|---|---|---|
| BEM (`block__element--modifier`) | `.mbc-comment-input__highlighter` | Newer components |
| Flat kebab-case | `.accordion-component`, `.badge-container` | Common components |
| camelCase | `.errorMessage`, `.dropdownmenu-indicator` | Legacy code |

#### 🟢 Low — Inline Styles in React

46 files / 105 instances use `style={{}}` in JSX. All are legitimate: toggling `display`, setting dynamic `height`/`width` from JS state. This is correct usage, not debt.

---

## 2. Proposed New Architecture

### 2.1 Isolation & Scoping Strategy

**Recommendation: CSS Modules for new components; BEM discipline for legacy.**

Full migration to Tailwind or CSS-in-JS (emotion/styled-components) would require rewriting thousands of `className` references and is incompatible with the PHP-rendered entry point model. The pragmatic path:

**Tier 1 — New components**: Use **CSS Modules** (`.module.scss`). Vite supports them natively with zero configuration. Class names are locally scoped at build time, eliminating global leakage.

```scss
/* Button.module.scss */
.button { ... }
.button--primary { ... }
```

```jsx
import styles from './Button.module.scss'
<button className={styles.button} />
```

**Tier 2 — Existing component SCSS** (`components/` directory): Enforce strict BEM and a **max 3-level nesting rule**. No `!important` in component files. Global selectors only in `commons/` and entry-point files.

**Tier 3 — Legacy** (`style.scss`, `_manage.scss`, `_outsource.scss`): Quarantine. Freeze new additions. Migrate sections out incrementally as features are touched.

> **Why not Tailwind?** The PHP-rendered backend and Vite-compiled SCSS pipeline work well together. Tailwind would require adding a PostCSS step, and immediate value is lower than the disruption given MateCat's established component vocabulary. Can be revisited if design system needs grow.

---

### 2.2 File Organization

Proposed target structure (co-location model for new code):

```
public/js/components/
├── common/
│   ├── Button/
│   │   ├── Button.jsx
│   │   ├── Button.module.scss    ← scoped, co-located
│   │   └── index.js
│   ├── Input/
│   │   ├── Input.jsx
│   │   └── Input.module.scss
│   └── ...
│
public/css/sass/
├── tokens/                       ← NEW: design token layer
│   ├── _colors.scss              ← migrated from commons/_colors.scss
│   ├── _spacing.scss             ← NEW
│   ├── _typography.scss          ← migrated/extended
│   ├── _z-index.scss             ← NEW: named z-index scale
│   └── _shadows.scss
│
├── globals/                      ← renamed from commons/ (non-token globals)
│   ├── _reset.scss
│   ├── _base.scss                ← body, html, typography defaults
│   └── _mixins.scss
│
├── pages/                        ← page-level layout only
│   ├── cattool.scss
│   ├── dashboard.scss
│   └── ...
│
└── legacy/                       ← quarantine zone (frozen)
    ├── style.scss
    ├── _manage.scss
    └── _outsource.scss
```

**Principle**: New feature work goes in `public/js/components/<Feature>/Feature.module.scss`. The centralized `public/css/sass/` handles global tokens, resets, and legacy quarantine only. The legacy directory shrinks over time.

---

### 2.3 Design Token System

**Recommendation: CSS Custom Properties as the canonical token layer; SCSS variables as build-time aliases for backward compatibility.**

The current `_colors.scss` uses SCSS variables (`$approvedGreen`). These work at build time but cannot be read or changed by JavaScript and cannot support runtime theming. `Button.scss` already demonstrates the right direction with CSS custom properties.

#### `tokens/_colors.scss`

```scss
:root {
  // 1. Primitive palette (raw values, no semantic meaning)
  --color-green-500: #2fb177;
  --color-green-600: #1c9f64;
  --color-blue-400: #0099cc;
  --color-red-500:   #e02020;
  --color-grey-100:  #f5f6f7;
  --color-grey-200:  #eaebee;
  --color-grey-300:  #d9e0e8;
  // ...

  // 2. Semantic aliases (meaning over value)
  --color-approved:       var(--color-green-500);
  --color-translated:     var(--color-blue-400);
  --color-rejected:       var(--color-red-500);
  --color-surface-subtle: var(--color-grey-100);

  // 3. Component-level tokens
  --btn-primary-bg:   var(--color-approved);
  --btn-primary-text: white;
}

// SCSS aliases — existing code continues to compile unchanged
$approvedGreen: var(--color-approved);
$translatedBlue: var(--color-translated);
```

#### `tokens/_z-index.scss`

```scss
:root {
  --z-content:  1;
  --z-dropdown: 10;
  --z-sticky:   20;
  --z-overlay:  30;
  --z-modal:    40;
  --z-toast:    50;
  --z-tooltip:  60;
}
```

#### `tokens/_spacing.scss`

```scss
:root {
  --space-1:  4px;
  --space-2:  8px;
  --space-3:  12px;
  --space-4:  16px;
  --space-6:  24px;
  --space-8:  32px;
  --space-12: 48px;
  --space-16: 64px;
}
```

The three-layer model (primitive → semantic → component) means changing `--color-green-500` updates everything that references `--color-approved`. Components are insulated from primitive color changes. Future theming (dark mode, whitelabel) only requires overriding semantic aliases in a `[data-theme="dark"]` selector.

---

## 3. Incremental Migration Roadmap

> **Prerequisite**: Merge open PRs (design refactoring + context preview) before starting Phase 0.
> This avoids conflicts and means all in-flight work already follows the current conventions.

Strategy: **strangler fig**. New work adopts the new architecture; legacy is quarantined and extracted opportunistically. No big-bang rewrite. No feature freeze.

---

### Phase 0 — Foundations (2–3 weeks, no visible changes)

These are infrastructure changes only. Zero visual impact; zero risk to existing features.

- [ ] **Quarantine `style.scss`**: Add a stylelint rule blocking new additions to the file. Add a comment header documenting the freeze.
- [ ] **Create `tokens/` directory**: Extract `_colors.scss` into CSS custom properties. Provide SCSS variable aliases so existing code keeps compiling unchanged.
- [ ] **Create `tokens/_z-index.scss`**: Replace the 6 extreme z-index values immediately (they're the most dangerous). Map all others to the named scale.
- [ ] **Configure CSS Modules in Vite**: Zero-config — Vite auto-enables CSS Modules for `*.module.scss` files. Optionally add `css.modules` option for custom naming patterns.
- [ ] **Add stylelint**: Enforce `max-nesting-depth: 3`, `declaration-no-important` (in component files), and BEM naming pattern for `.module.scss` files.
- [ ] **Write `CSS-ARCHITECTURE.md`**: One-page reference defining which tier to use for what, the token system, and BEM naming rules.

---

### Phase 1 — Component Library Migration (ongoing, 6–12 weeks)

Work happens component-by-component, attached to normal feature work.

**Trigger**: Any time a component in `components/common/` is touched for a feature, convert its SCSS to a `.module.scss`.

**Method**: Copy styles → scope them → update JSX `className` references → lint the old file → delete it.

**Priority order** (highest reuse first):

1. `Button.scss` → `Button.module.scss` _(has CSS custom properties already — good starting point)_
2. `Input.scss`
3. `Select.scss`
4. `Dropdown.scss`
5. `Tooltip.scss`
6. Modal shell
7. Remaining 18 components

Each conversion is self-contained, visually verifiable, and zero-risk to other components because CSS Modules generate unique class names that cannot collide with global ones.

---

### Phase 2 — Feature Component Cleanup (rolling)

As feature teams touch `components/segment/`, `components/header/`, `components/settingsPanel/`, etc.:

- Extract deeply nested SCSS into flatter modules (max 3 levels)
- Replace hardcoded colors with token references (`var(--color-approved)`)
- Delete `!important` by fixing the specificity root cause (usually: flatten the selector or remove an unnecessary ancestor)

**Rule of thumb**: Leave the file better than you found it. Do not refactor files you didn't touch.

---

### Phase 3 — Legacy File Extraction (6–18 months)

`style.scss`, `_manage.scss`, `_outsource.scss`, and `_analyze.scss` are extracted section by section:

1. Identify which component each section belongs to
2. Move styles to the component's `.module.scss`
3. Verify visually
4. Delete from the legacy file
5. Repeat until the file is empty and can be deleted

This is the slowest phase because legacy files have no clear component ownership. It can proceed in parallel with Phase 2 whenever there is capacity.

---

### Coexistence Rules During Transition

| Scenario | Rule |
|---|---|
| New component | Use `.module.scss` + CSS custom properties from `tokens/` |
| Modifying existing component | Convert to module if file is small (<100 lines); otherwise flatten nesting and replace hardcoded tokens only |
| Global styles | Only in `globals/` or `tokens/` — never in component files |
| z-index | Only named values from `tokens/_z-index.scss` — no raw numbers in new code |
| `!important` | Banned in all new code. Removal from legacy is opportunistic per-touch |
| Vendor CSS | Do not touch. Leave Semantic UI overrides in `vendor_mc/` |
| Plugin styles | Airbnb/Uber plugins follow the same rules within their own `static/src/` trees |

New CSS Modules and legacy global SCSS coexist without conflict. CSS Modules are scoped at build time by Vite; their generated class names cannot collide with global ones.

---

## 4. Summary Table

| Dimension | Current State | Target State |
|---|---|---|
| **Scoping** | Global SCSS, no isolation | CSS Modules for all new/migrated components |
| **Design tokens** | SCSS variables (build-time only) | CSS custom properties (runtime, themeable) |
| **File organization** | Centralized `public/css/sass/` | Collocated `.module.scss` + token layer |
| **Naming convention** | Mixed (BEM / flat / camelCase) | BEM in modules, enforced by stylelint |
| **Z-index** | 177 arbitrary values, max `99999999999999` | Named scale (7 tiers), enforced |
| **`!important`** | 1,039 occurrences in 67 files | 0 in new code; reduced in legacy on touch |
| **Nesting depth** | Up to 20 levels | Max 3 levels, enforced by stylelint |
| **Legacy quarantine** | None | `legacy/` directory, lint-blocked from new additions |
| **Enforcement** | None | stylelint with project-specific ruleset |

---

## Appendix — Key File Metrics (at time of analysis)

| File | Lines | `!important` | Max Nesting |
|---|---|---|---|
| `style.scss` | 2,927 | 151 | — |
| `commons/_outsource.scss` | 1,324 | 28 | 14 |
| `commons/_analyze.scss` | 1,093 | 22 | 11 |
| `commons/_manage.scss` | 1,078 | 24 | 12 |
| `components/header/segmentsFilter.scss` | 535 | 40 | 20 |
| `components/pages/QualityReportPage.scss` | 1,035 | 36 | 18 |
| `vendor_mc/semantic/semantic.css` | — | 458 | — (vendor) |

**Total SCSS source files**: 125  
**Total `!important` (internal)**: 1,039  
**Hardcoded hex/rgb values**: 1,014  
**CSS custom properties defined**: ~60 (concentrated in `Button.scss`)  
**CSS custom property usages**: 29  
