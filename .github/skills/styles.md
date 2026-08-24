# Skill: Styles Architecture — Library Vue

## Scope

This skill covers the **frontend SCSS architecture**: tokens, the two themes, modular layers, family-coherence master mixins, entity and chart colour, PrimeVue overrides, the `stylelint` barrier, and refactor workflow. Use it whenever you write or modify `<style>` blocks in Vue components or files under `frontend/src/assets/styles/`.

## Tech Stack

- **Sass**: 1.99 (modern module system: only `@use` / `@forward`, **no** `@import`)
- **Loader**: `sass-loader` 16 (Vue CLI 5)
- **Theming**: CSS Variables in `:root` (light) and `.app-dark` (dark) — runtime switch, no recompile
- **Linting**: `stylelint` 16 (four rules, no preset) alongside ESLint — `npm run lint:styles`
- **PrimeVue**: 4.5 with Lara preset (`darkModeSelector: '.app-dark'`, `cssLayer: false`) — palette in JS via `definePreset(Lara, ...)` from `config/design-tokens.js`
- **Methodology**: BEM relaxed + atomic utilities (`.u-*`) + states (`is-`/`has-`)
- **Approach**: Mobile-first; opt-in shared mixins (no zero-cost CSS leak)

## Philosophy

| Principle | Implementation |
|---|---|
| **Single Source of Truth** | Palette in [`design-tokens.js`](../../frontend/src/config/design-tokens.js) (PrimeVue) ↔ visual tokens in [`tokens/*.scss`](../../frontend/src/assets/styles/tokens) (CSS vars) — manual sync required, with two documented divergences |
| **Theming** | CSS Variables in `:root` (**light**, warm bone) and `.app-dark` (dark). Runtime switch. |
| **Colour is measured, not chosen** | Every value carries its contrast ratio as a comment; entity and chart palettes are validated with the `dataviz` script, not by eye |
| **Enforced, not remembered** | `stylelint` rejects a stray hex, a pixel inside `@media`, `prefers-color-scheme`, and `@import` |
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
│   ├── _light.scss          # Emits NOTHING — light IS :root, by definition
│   └── _dark.scss           # Overrides under `.app-dark` (incl. its own entity + chart palettes)
├── base/
│   ├── _reset.scss          # Minimal reset
│   ├── _globals.scss        # body, #app, page transitions
│   └── _typography.scss     # Element base styles (the global `a` rule → --color-link)
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

> ⚠️ **Never write a pixel inside a `@media`.** Use `@include responsive($key)` /
> `@include responsive-below($key)`. `stylelint` rejects the raw form. Odd thresholds map to the
> nearest key — the ladder matters more than the exact pixel.
>
> From JS, the same threshold comes from
> [`composables/useBreakpoint.js`](../../frontend/src/composables/useBreakpoint.js), which owns
> **one** shared `resize` listener for the whole app. Do not add another `ref(window.innerWidth)`.
> Its `isMobile` is `< 768`, **not** `<= 768`, because `responsive-below(md)` compiles to
> `max-width: 767px`: at exactly 768 the CSS already says desktop.

### Colour token families

| Family | Tokens | Notes |
|---|---|---|
| Surfaces | `--color-background{,-soft,-mute,-card,-overlay}` | `card` is white in light, teal in dark |
| Text | `--color-text{,-dark,-light,-secondary,-muted}` | `--color-text-light` is for use **on** dark or coloured surfaces |
| Borders | `--color-border{,-light,-hover}` | `--color-border` outlines **controls** (inputs, buttons, search), so it must clear 3:1 — WCAG 1.4.11. The soft decorative hairline is `--color-border-light` |
| States | `--color-{success,warning,error,info}` + `-bg` tints + `--color-on-status` | See *The two themes* |
| Links | `--color-link`, `--color-link-hover` | Consumed by the global `a` rule |
| Buttons | `--btn-{primary,secondary,accent}-{bg,bg-hover,text}` | |
| Entity cards | `--color-card-{entity}-{bg,bg-hover,border,accent}` | See *Entity Color Identity* |
| Cover overlays | `--color-overlay-strong`, `--color-on-overlay`, `--color-rating-star`, `--color-media-letterbox` | Theme-independent by design |
| Charts | `--chart-1` … `--chart-7`, `--chart-other` | Fixed order — see *Charts* |

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
This is not a convention you have to remember: `stylelint` fails on a stray hex, a pixel inside a
`@media`, a `prefers-color-scheme` query, and `@import`.

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

## The two themes

`:root` in [`tokens/_colors.scss`](../../frontend/src/assets/styles/tokens/_colors.scss) is the
**light** theme; `.app-dark` in [`themes/_dark.scss`](../../frontend/src/assets/styles/themes/_dark.scss)
overrides it. `themes/_light.scss` deliberately emits no CSS — light *is* `:root`.

```js
document.documentElement.classList.toggle('app-dark', isDark)
```

PrimeVue is synced via `darkModeSelector: '.app-dark'` in [`main.js`](../../frontend/src/main.js).

> ⚠️ **Do NOT** use `@media (prefers-color-scheme: dark)`. The project relies exclusively on the
> `.app-dark` class selector, because `store/ui.js` lets the user override the system preference —
> a media query would silently ignore that switch. `stylelint` rejects the media feature outright.

**The light theme is warm bone (`#F7F2EC`), not white**, and the brand teal `#1D4E4A` is an
**accent** there, not a surface. In `.app-dark` the teal goes back to being a surface. Until
2026-08-20 `:root` *was* a second dark theme (background `#1D4E4A` over text `#E2CBBF`) while
`primevue-preset.js` already assumed a light surface — if you find documentation that says the
switch does nothing, that is what it is describing.

**Every colour value carries its measured contrast ratio as a comment**, against the harshest
surface of its theme. Keep that up when you touch one; a value without a number is a value nobody
can review. The state colours (`--color-success` and friends) are the *dark* variants in the light
theme on purpose: they are used 13 times as text and only 5 as fill.

### Tokens whose theme behaviour is not the obvious one

| Token(s) | Behaviour | Why |
|---|---|---|
| `--color-overlay-strong`, `--color-on-overlay`, `--color-rating-star`, `--color-media-letterbox` | **Never** overridden in `.app-dark` | They sit on top of an arbitrary cover image, not on an app surface, so legibility cannot depend on the theme. Measured against the worst case (white artwork): 10.85 with white ink, 7.73 with the rating gold |
| `--color-on-status` | **Does** flip (`#ffffff` → `#0f1412`) | It is the ink that goes on a semantic fill. In dark those fills are light colours, so a fixed `color: white` drops to 2.21 |
| `--color-link`, `--color-link-hover` | Separate from `--color-primary-light` | That one gives 2.76 over `--color-background-mute` in dark. Global `a` rule lives in `base/_typography.scss` |
| `--chart-1` … `--chart-7`, `--chart-other` | Own values per theme | Validated against the **real** chart surface, which is `--color-background-card` — white in light, teal `#1D4E4A` in dark |

## JS ↔ SCSS Sync

The main palette lives **twice** (intentionally):

- [`config/design-tokens.js`](../../frontend/src/config/design-tokens.js) → consumed by PrimeVue (`definePreset(Lara, ...)`)
- [`tokens/_colors.scss`](../../frontend/src/assets/styles/tokens/_colors.scss) → consumed by Vue/CSS

⚠️ **When you change a value, update BOTH**. PrimeVue needs JS at compile time; Vue needs CSS vars at runtime for dynamic theming.

> ⚠️ **Two documented exceptions — do not "fix" them into bugs.**
> - `palette.secondary` (`#A3CBC1`) and `--color-secondary` (`#4A8F84`) **diverge on purpose**: the
>   JS one is a *background* (PrimeVue's `colorScheme.light.highlight.background`, with
>   `primary[500]` on top at 5.30), the SCSS one is a *foreground* (the spinner in `App.vue:58`) and
>   has to be darker on a light surface.
> - The **entity accents live only in SCSS**. `design-tokens.js` has the `primary` scale and four
>   semantic colours, nothing else. Do not go looking for `--color-card-*` in the JS.

## Entity Color Identity

Each library entity has a card color set, defined **twice** — once per theme, in
[`tokens/_colors.scss`](../../frontend/src/assets/styles/tokens/_colors.scss) (light) and
[`themes/_dark.scss`](../../frontend/src/assets/styles/themes/_dark.scss) (dark):

```css
--color-card-{book|movie|game|album|video}-{bg,bg-hover,border,accent}
```

| Entity | Accent (light) | Accent (dark) | Notes |
|---|---|---|---|
| `book`  | `#BA6F0E` | `#C67D00` | amber |
| `movie` | `#9871F5` | `#8F74F9` | violet — **shared with Series** |
| `game`  | `#199975` | `#11A082` | green |
| `album` | `#9F1B8A` | `#A2309A` | fuchsia — **deliberately not amber**, see below |
| `video` | `#96280E` | `#C11C15` | red |

Use the accent in borders, hover states, icon colors, and tag gradients to give each entity a coherent visual identity across families (`*ListItem`, `Library*Item`, `*Notes`, `*DetailView`).

### These values are computed, not chosen

> ⚠️ **Do not hand-pick a replacement.** If you change one accent, re-validate all five, against
> **both** surfaces, in `--pairs all` mode:
>
> ```bash
> node <dataviz-skill>/scripts/validate_palette.js \
>   "#BA6F0E,#9871F5,#199975,#9F1B8A,#96280E" --mode light --surface "#F7F2EC" --pairs all
> node <dataviz-skill>/scripts/validate_palette.js \
>   "#C67D00,#8F74F9,#11A082,#A2309A,#C11C15" --mode dark  --surface "#0f1412" --pairs all
> ```
>
> Current margins: CVD ΔE 11.0 / normal ΔE 17.9 (light); 11.4 / 18.4 (dark). All five checks PASS.

**`--pairs all`, not the default `adjacent`.** Adjacent-pair mode only compares neighbours in the
list. In `/library` all five media are interleaved in one grid, so *any* pair can end up side by
side. The previous palette (`#c9943a` `#8b5cf6` `#4ade80` `#f59e0b` `#c0392b`) passed in adjacent
mode and failed in `--pairs all`: book↔game ΔE 5.0 under protanopia, and a normal-vision floor of
8.1 between book and album — both amber.

**Album is fuchsia on purpose.** With book already amber, the pair was indistinguishable under
deuteranopia. Changing its hue was the point, not an accident.

### Where the identity actually shows

At rest, the accent is **not** what you see: `_list-item.scss:51` only applies it on `:hover`. What
carries identity in the resting state is `bg` (accent at 11 % over white) and `border` (at 45 %).
A first attempt at 6 % / 30 % left the five media indistinguishable — if you re-derive these, check
the result on `/library` with all five filters on, not on a single card.

Text over an accent needs care: white on the movie and game accents lands at 3.49 and 3.59, below
AA for small text. `.category-tag` and `.ownership-format-badge` therefore darken the fill with
`color-mix(in srgb, var(--color-card-#{$entity}-accent) 80%, black)`, which clears 4.88 in both
themes.

## Charts

Chart colour does **not** live in the chart components. It lives in
[`config/chartTheme.js`](../../frontend/src/config/chartTheme.js), which reads the tokens:

| Function | Returns |
|---|---|
| `entityColor(media)` | the accent of that medium's card — so a series and its `/library` card match. Accepts singular or plural |
| `categoricalPalette(n)` | `--chart-1` … `--chart-7` in **fixed order**, plus `--chart-other` from the 8th |
| `foldToOther(labels, data)` | folds the tail into an "Otros" bucket instead of inventing hues |
| `chartInk()` | axis / grid / tick colours, recessive |
| `chartTooltip()` | tooltip background, border and ink |

**Fixed order is the point.** The old `StatsService.generateColors()` returned six demo colours and
**repeated them** from the seventh series on, assigning by list position — so filtering a series
recoloured the survivors. Series N now always gets slot N.

> ⚠️ **Chart.js paints on `<canvas>`, where `var()` means nothing.** The colour must be resolved to
> a concrete value at paint time. Rather than making every component subscribe, `chartTheme.js`
> keeps an internal `ref` that all its functions touch; since the dashboards' `chartConfigs` are
> already `computed`, they subscribe for free and repaint on theme change without a reload. If you
> add a chart, read its colours **inside a `computed`** or it will not follow the theme.

The palette is validated against the real chart surface — `--color-background-card`, i.e. white in
light and teal `#1D4E4A` in dark. Margins: CVD ΔE 7.9 / normal 15.4 (light), 9.3 / 15.5 (dark). The
CVD figure sits in the 6–8 floor band, which is only legal **with secondary encoding**, so
`useDashboardCharts` keeps the legend visible on every chart — do not turn it off.

> The dark palette fails the validator's **lightness band** on purpose. That band assumes a dark
> surface, and ours is a mid-luminance teal; against it, satisfying the band and satisfying the 3:1
> contrast requirement are mutually exclusive. Contrast wins, because contrast is what carries
> legibility. The alternative — giving `.chart-card` its own dark surface — is a design change, not
> a colour one.

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
| `@media (max-width: 768px)` | `@include responsive-below(md)` |
| `var(--color-danger, #ff6b6b)` | `var(--color-error)` — **`--color-danger` does not exist**, so that `var()` always fell through to the hex |
| `rgba(40, 167, 69, .2)` and friends | the matching `--color-{state}-bg` tint |
| `color: white` on a semantic fill | `var(--color-on-status)` |

> ⚠️ **Grep `*.scss` too, not just `*.vue`.** The deduplication work moved the per-medium components'
> `<style>` into the master mixins under `assets/styles/components/`, so that is where the drift now
> hides. A sweep that only looked at `.vue` files once left 38 live hex values behind — including the
> `#e0e0e0` that made the detail view unreadable in the light theme.

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
7. **Palette lives in two places** (JS + SCSS) — both must be updated together, **except** the two
   documented divergences in *JS ↔ SCSS Sync*.
8. **`@import` is forbidden** — only `@use` / `@forward`.
9. **Entity accents are computed** — re-validate all five in `--pairs all` mode if you touch one.
10. **The accent is invisible at rest** — identity comes from `bg` and `border`; the accent only
    appears on `:hover`.
11. **A raw pixel in a `@media`** — use `responsive()` / `responsive-below()`; `stylelint` rejects it.
12. **A colour that must not follow the theme** (over cover art) versus **an ink that must**
    (on a semantic fill) — see the table in *The two themes*.
13. **Chart colour read outside a `computed`** — it will not repaint on theme change.

## The barrier: `stylelint`

The rules above are enforced, not merely documented. `frontend/.stylelintrc.json` runs as
`npm run lint:styles` and from `./dev-setup.sh`:

| Rule | Catches |
|---|---|
| `color-no-hex` | any hand-written colour outside the two token-defining files |
| `media-feature-name-value-allowed-list` | a raw pixel inside a `@media` |
| `media-feature-name-disallowed-list` | `prefers-color-scheme` — theming goes through `.app-dark` |
| `at-rule-disallowed-list: ["import"]` | `@import` |

```bash
docker compose exec frontend npm run lint:styles
```

> ⚠️ **It extends no preset, and that is deliberate.** Adopting `stylelint-config-standard-scss`
> produced **180 errors**, of which ~175 were noise: it reads `spacing(2xs)` as an unknown unit,
> does not know Vue's `:deep()`, and has opinions about blank lines. A barrier with 180 false
> positives is a barrier everybody turns off on day one. If you want to add a rule, add the rule —
> do not extend the preset.

**Two exceptions, both explicit:**
- `tokens/_colors.scss` and `themes/_dark.scss` have `color-no-hex` disabled by override. They are
  the files that *define* the tokens.
- Brand colours (Last.fm, Spotify, IMDb, YouTube, Google, the `SimpleLink` networks) carry
  `/* stylelint-disable-next-line color-no-hex -- <brand>: … */` on the line above. That per-line
  justification **is** the review you want.

An inline `style="color: #…"` in a template cannot carry a stylelint comment — move it to a class in
the `<style>` block instead. That is the drift the barrier is meant to push out.

> The config file is mounted into the container (`docker-compose.yml`), like `vitest.config.js`, so
> rules can be edited without a rebuild. The **packages** are baked into the image: adding one still
> needs `docker compose build frontend`.

## References

- Frontend skill: [`.github/skills/frontend.md`](frontend.md) (Vue components, stores, composables)
- Refactor plan history: [`.github/STYLES_REFACTORING_PLAN.md`](../STYLES_REFACTORING_PLAN.md)
