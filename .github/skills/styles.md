# Skill: Styles Architecture — Library Vue

## Scope

This skill covers the **frontend SCSS architecture**: tokens, themes, modular layers, family-coherence master mixins, PrimeVue overrides, and refactor workflow. Use it whenever you write or modify `<style>` blocks in Vue components or files under `frontend/src/assets/styles/`.

## Tech Stack

- **Sass**: 1.99 (modern module system: only `@use` / `@forward`, **no** `@import`)
- **Loader**: `sass-loader` 16 (Vue CLI 5)
- **Theming**: CSS Variables in `:root` (light) and `.app-dark` (dark) — runtime switch, no recompile
- **PrimeVue**: 4.5 with Lara preset (`darkModeSelector: '.app-dark'`, `cssLayer: false`) — palette in JS via `definePreset(Lara, ...)` from `config/design-tokens.js`
- **Methodology**: BEM relaxed + atomic utilities (`.u-*`) + states (`is-`/`has-`)
- **Approach**: Mobile-first; opt-in shared mixins (no zero-cost CSS leak)

## Philosophy

| Principle | Implementation |
|---|---|
| **Single Source of Truth** | Palette in [`design-tokens.js`](../../frontend/src/config/design-tokens.js) (PrimeVue) ↔ visual tokens in [`tokens/*.scss`](../../frontend/src/assets/styles/tokens) (CSS vars) — manual sync required |
| **Theming** | CSS Variables in `:root` (light) and `.app-dark` (dark). Runtime switch. |
| **Reuse without leak** | Shared components expose **mixins** (not classes). They emit CSS only when a component does `@include`. |
| **Mobile-first** | `responsive($key)` mixin uses `min-width`. `responsive-below($key)` for max-width exceptions. |
| **No `@import`** | Only `@use` / `@forward`. Namespace isolation per file. |
| **BEM relaxed** | `.library-item__cover`, `.library-item--reading`. Utilities `.u-*`. States `is-`/`has-`. |

## Directory Structure

```
frontend/src/assets/styles/
├── index.scss               # Single entry point (loaded once from main.js)
├── abstracts/               # Compile-time helpers (NO CSS output)
│   ├── _index.scss          # @forward of everything
│   ├── _tokens.scss         # SCSS maps mirroring CSS vars
│   ├── _breakpoints.scss    # bp(), responsive(), responsive-below()
│   ├── _functions.scss      # spacing(), radius(), shadow(), z(), transition()
│   └── _mixins.scss         # card(), truncate(), focus-ring, flex-center, ...
├── tokens/                  # CSS variables (runtime source of truth)
│   ├── _colors.scss         # --color-primary, --color-card-{entity}-*, etc.
│   ├── _spacing.scss        # --spacing-3xs..3xl
│   ├── _typography.scss     # --font-family-base, --font-size-*, --font-weight-*
│   ├── _radius.scss         # --radius-none..full
│   ├── _shadows.scss        # --shadow-sm/light/medium/heavy/xl
│   ├── _transitions.scss    # --transition-fast/medium/slow
│   └── _z-index.scss        # --z-modal, --z-toast, etc.
├── themes/
│   ├── _light.scss          # Defaults (in :root)
│   └── _dark.scss           # Overrides under `.app-dark`
├── base/
│   ├── _reset.scss          # Minimal reset
│   ├── _globals.scss        # body, #app, page transitions
│   └── _typography.scss     # Element base styles
├── components/              # Shared patterns (opt-in mixins)
│   ├── _buttons.scss        # .btn (global class)
│   ├── _cards.scss          # @mixin card-base, card-interactive
│   ├── _library-item.scss   # @mixin library-item($variant, $cover-aspect, $cover-size, $entity)
│   ├── _list-item.scss      # @mixin list-item($variant, $cover-aspect, $cover-size)
│   ├── _carousel-item.scss  # @mixin carousel-item-base, carousel-cover
│   ├── _notes.scss          # @mixin notes-panel($entity) + notes-dialog-form
│   ├── _dashboard.scss      # @mixin dashboard-grid, dashboard-card
│   ├── _detail-view.scss    # @mixin detail-view-page($entity, $selector?) + detail-section-card
│   ├── _modal.scss          # @mixin modal-overlay-base, -content + @keyframes
│   ├── _forms.scss          # @mixin form-group, form-control
│   ├── _search.scss         # @mixin search-page
│   └── _primevue-overrides.scss  # Global PrimeVue overrides (no :deep)
└── utilities/               # Atomic classes (.u-*)
    ├── _layout.scss         # .u-flex*, .u-grid*, .u-stack
    ├── _text.scss           # .u-text-*, .u-truncate*
    ├── _spacing.scss        # .u-mt-*, .u-mb-*, .u-gap-*, .u-p-*
    └── _visibility.scss     # .u-sr-only, .u-hidden-*
```

Layer order in `index.scss`: `tokens` → `themes/light` → `themes/dark` → `base` → `components` → `utilities`.

## Tokens

### Spacing
`3xs` (2px) · `2xs` (4px) · `xs` (8px) · `sm` (12px) · `md` (16px) · `lg` (24px) · `xl` (32px) · `2xl` (48px) · `3xl` (64px)

### Radius
`none` · `sm` (4px) · `md` (8px) · `lg` (12px) · `xl` (16px) · `2xl` (24px) · `full` (9999px)

### Shadows
`sm` · `light` · `medium` · `heavy` · `xl`

> ⚠️ **No `md` / `lg`** keys for shadow. `shadow(md)` or `shadow(lg)` triggers `@error` at compile time. (Spacing and radius DO have them.)

### Z-index
`hide` (-1) · `base` (1) · `dropdown` (1000) · `sticky` (1100) · `overlay` (1200) · `modal` (1300) · `popover` (1400) · `toast` (1500) · `tooltip` (1600)

### Transitions
`fast` (0.2s ease) · `medium` (0.3s ease) · `slow` (0.5s ease)

### Breakpoints
`xs` (0) · `sm` (480) · `md` (768) · `lg` (1024) · `xl` (1280) · `2xl` (1536)

### Available helpers after `@use 'abstracts' as *`

| Helper | Returns | Example |
|---|---|---|
| `spacing($key)` | `var(--spacing-*)` | `padding: spacing(md);` |
| `radius($key)` | `var(--radius-*)` | `border-radius: radius(lg);` |
| `shadow($key)` | `var(--shadow-*)` | `box-shadow: shadow(medium);` |
| `z($key)` | `var(--z-*)` | `z-index: z(modal);` |
| `transition($key)` | `var(--transition-*)` | `transition: transform transition(fast);` |
| `@include responsive($k)` | min-width media | `@include responsive(md) { ... }` |
| `@include responsive-below($k)` | max-width media | `@include responsive-below(sm) { ... }` |
| `@include card()` | bg + radius + shadow | base card surface |
| `@include truncate($lines)` | line-clamp ellipsis | `@include truncate(2);` |
| `@include focus-ring` | a11y focus outline | for interactive elements |
| `@include flex-center` | flex+center+center | utility |

All functions return `var(--*)` and `@error` on missing key.

## Component Boilerplate

Always `<style scoped lang="scss">`. Only `@use` / `@forward`. **No** hex/px hardcoded — use tokens.

```vue
<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/library-item' as *;

.library-book-item {
  @include library-item-container;

  &__cover { @include library-item-cover(80px, 70px); }
  &__title { @include library-item-title; }

  padding: spacing(md);
  border-radius: radius(lg);
  box-shadow: shadow(medium);

  @include responsive(md) { padding: spacing(lg); }
}
</style>
```

## Naming Conventions

- **Components**: BEM relaxed → `.book-item`, `.book-item__cover`, `.book-item--reading`
- **Utilities**: `.u-*` → `.u-flex-center`, `.u-truncate-2`, `.u-mt-md`
- **States**: `.is-*`, `.has-*` → `.is-loading`, `.has-error`
- **Legacy global classes** (`.btn`, `.modal-overlay`): still valid; future renames to `.u-*` or BEM.

## PrimeVue Overrides

PrimeVue teleports overlays (`.p-multiselect-panel`, `.p-dropdown-panel`, `.p-dialog`, ...) to `<body>`. **`:deep()` does NOT work** for them from a `<style scoped>` component.

**Rule**: all PrimeVue overlay overrides go in [`components/_primevue-overrides.scss`](../../frontend/src/assets/styles/components/_primevue-overrides.scss) **without** `:deep()`.

**Exception**: in-place tweaks that don't affect a teleported overlay (e.g. `.dashboard-tabs :deep(.p-tab)`) can live in the component.

PrimeVue components keep Lara vars (`--p-primary-color`, etc.). Only your custom selectors should use project tokens.

## Dark Theme

Activated by adding `.app-dark` to `<html>` or `<body>`. All tokens in [`themes/_dark.scss`](../../frontend/src/assets/styles/themes/_dark.scss) override light values automatically.

```js
document.documentElement.classList.toggle('app-dark', isDark)
```

PrimeVue is synced via `darkModeSelector: '.app-dark'` in [`main.js`](../../frontend/src/main.js).

> ⚠️ **Do NOT** use `@media (prefers-color-scheme: dark)`. Project relies exclusively on the `.app-dark` class selector.

## JS ↔ SCSS Sync

The main palette lives **twice** (intentionally):

- [`config/design-tokens.js`](../../frontend/src/config/design-tokens.js) → consumed by PrimeVue (`definePreset(Lara, ...)`)
- [`tokens/_colors.scss`](../../frontend/src/assets/styles/tokens/_colors.scss) → consumed by Vue/CSS

⚠️ **When you change a value, update BOTH**. PrimeVue needs JS at compile time; Vue needs CSS vars at runtime for dynamic theming.

## Entity Color Identity

Each library entity has a card color set in [`tokens/_colors.scss`](../../frontend/src/assets/styles/tokens/_colors.scss):

```css
--color-card-{book|movie|game|album}-{bg,bg-hover,border,accent}
```

| Entity | Accent | Notes |
|---|---|---|
| `book`  | `#c9943a` (oro)     | |
| `movie` | `#8b5cf6` (violeta) | **shared with Series** |
| `game`  | `#4ade80` (verde)   | |
| `album` | `#f59e0b` (ámbar)   | |

Use the accent in borders, hover states, icon colors, and tag gradients to give each entity a coherent visual identity across families (`*ListItem`, `Library*Item`, `*Notes`, `*DetailView`).

## Family-Coherence Pattern (parametric master mixins)

For component families that share structure but differ by entity (Books / Movies / Games / Albums / Series), use a **single parametric master mixin** in `components/_{family}.scss`. Phase 6b reduced ~3000 → ~700 lines (-75%) across 5 families.

### Available master mixins

| File | Mixin | Components |
|---|---|---|
| `_list-item.scss` | `list-item($variant, $cover-aspect, $cover-size)` | `*ListItem.vue` (compact rows in MyLibrary) |
| `_library-item.scss` | `library-item($variant, $cover-aspect, $cover-size, $entity)` | `Library*Item.vue` (action-rich cards) |
| `_search.scss` | `search-page` | `*Search.vue` wrappers |
| `_notes.scss` | `notes-panel($entity)` + `notes-dialog-form` | `*Notes.vue` |
| `_detail-view.scss` | `detail-view-page($entity, $selector?)` + `detail-section-card` | `*DetailView.vue` |
| `_dashboard.scss` | `dashboard-content-page` + `dashboard-card` + `dashboard-grid($min)` | `*DashboardContent.vue`, `StatCard`, `ChartCard` |

### Mixin pattern (master example)

```scss
@use '../abstracts' as *;

// $entity   → controls accent colors (book/movie/game/album)
// $selector → controls selector prefix (defaults to $entity)
//             Decouple them when an entity wants another's color
//             (e.g. Series uses ('movie', 'series') for violet color
//             with .series-* selectors).
@mixin detail-view-page($entity: 'book', $selector: $entity) {
  .#{$selector}-header {
    border-top: 3px solid var(--color-card-#{$entity}-accent);
    background: var(--color-background-mute);
    padding: spacing(lg);
    border-radius: radius(lg);
    box-shadow: shadow(medium);
  }

  .category-tag {
    background: linear-gradient(
      135deg,
      var(--color-card-#{$entity}-accent),
      color-mix(in srgb, var(--color-card-#{$entity}-accent) 70%, black)
    );
  }
}
```

### Component usage (minimal style block)

```vue
<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.movie-detail-view {
  @include detail-view-page('movie');

  // Wrap entity-specific sections with the section-card helper
  .movie-plot-section,
  .movie-crew-section,
  .library-form-section {
    @include detail-section-card;
  }

  // Only blocks unique to this entity stay here
  .movie-ratings { /* IMDb stars #f5c518, etc. */ }
}
</style>
```

### `$entity` vs `$selector` decoupling

When an entity inherits another's **color** but keeps its own **selector prefix** (Series), pass both parameters:

```scss
// SeriesDetailView.vue — violet from Movies, .series-* selectors
.series-detail-view {
  @include detail-view-page('movie', 'series');
}
```

Coupling them silently kills ALL prefixed selector styles in the inheriting entity (header card, border-top accent, fade-in animation...). Visible in the rendered page as missing card containers and borders.

### Refactor workflow (proven on 5 families)

1. **Read all sibling files** in the family; catalog drift in a markdown table
2. Identify **intentional drift**:
   - Album cover **1:1** (musical convention) instead of 2:3
   - Spotify green `#1DB954` (brand)
   - Movie/Series IMDb gold `#f5c518` (brand)
3. Identify **accidental drift**:
   - Random hex (`#1976d2`, `#1e2028`)
   - PrimeVue `--surface-*` vars instead of project tokens
   - Dead CSS classes defined in `<style>` but absent from `<template>`
4. Write or extend the master mixin parametrized by `$entity`
5. Replace each component's `<style>` block:
   - `sed -i 'N,$d' file.vue` to truncate from the `<style>` line onward (N = line number of `<style scoped lang="scss">`)
   - Append the new minimal block via the edit tool
6. **Verify the build**: `docker compose logs frontend --tail 30` → expect `Build finished at HH:MM:SS by 0.000s`
7. Preserve entity-unique blocks **outside** the mixin call (Album `.tracks-list`, Game `.screenshots-grid`, Movie `.movie-ratings`, Series `.season-tracker-section`, Book `.book-classifications-section`, etc.)

### Common drift to fix

| Found | Replace with |
|---|---|
| `--surface-card`, `--text-color`, `--primary-color`, `--text-color-secondary` (PrimeVue Lara) | `--color-background-mute`, `--color-text`, `--color-primary`, `--color-text-secondary` (project tokens) |
| Random hex (`#1976d2`, `#1e2028`) | Token or entity accent (`var(--color-card-{entity}-accent)`) |
| `@media (prefers-color-scheme: dark)` | Remove — project uses `.app-dark` selector |
| `gap: 40px`, `padding: 20px` | `spacing(xl)`, `spacing(lg)` |
| Dead CSS classes not in template | Delete (always grep `<template>` first) |
| `shadow(md)`, `shadow(lg)` | `shadow(medium)`, `shadow(heavy)` |

## Adding a New Component

1. Is there a shared pattern in [`components/_*.scss`](../../frontend/src/assets/styles/components)? → `@use` and `@include`.
2. None? → write local styles using tokens (`spacing()`, `radius()`, ...). **No hex/px hardcoded.**
3. Pattern repeats ≥ 2 times → extract as mixin in `components/_*.scss`.
4. Atomic utility class? → add to `utilities/_*.scss` with `.u-` prefix.

## Adding a New Family Master Mixin

1. Read all sibling components in the family (4-5 files typical).
2. Identify shared structure vs entity-specific blocks.
3. Create `components/_{family}.scss` with `@mixin {family}($entity, $selector?: $entity)`.
4. Use `var(--color-card-#{$entity}-accent)` for color identity.
5. Use `.#{$selector}-{block}` for prefixed selectors that need to vary.
6. Apply refactor workflow (above) to all components in the family.
7. Update this skill's "Available master mixins" table.

## Common Pitfalls

1. **Shadow tokens lack `md` / `lg`** — only `sm/light/medium/heavy/xl`. Spacing and radius DO have md/lg.
2. **`$entity` vs `$selector` decoupling** — needed when an entity inherits another's color identity (Series ← Movies).
3. **`:deep()` does not work for PrimeVue overlays** — they teleport to `<body>`. Use `_primevue-overrides.scss`.
4. **No `prefers-color-scheme: dark`** — use `.app-dark` class selector.
5. **Dead CSS** — common in older components. Grep template before preserving any rule.
6. **PrimeVue Lara vars in custom selectors** — replace with project tokens.
7. **Palette lives in two places** (JS + SCSS) — both must be updated together.
8. **`@import` is forbidden** — only `@use` / `@forward`.

## References

- Frontend skill: [`.github/skills/frontend.md`](frontend.md) (Vue components, stores, composables)
- Refactor plan history: [`.github/STYLES_REFACTORING_PLAN.md`](../STYLES_REFACTORING_PLAN.md)
