# Front Page — Implementation Plan

> **Source of truth:** `output/prototype/index.html`
> **Target:** `/home` (Drupal front page, path alias → `/`)
> **Stack:** Drupal Canvas 1.2 · SDC (Single Directory Components) · `duccinis_1984_olympics` Radix 6 subtheme

---

## Architecture at a Glance

Canvas treats a page as an ordered stack of **components**. Each component maps 1-to-1 to a **Single Directory Component** in the theme. Canvas renders the component, exposes its `props` to content editors via a schema-driven UI panel, and lets site builders re-order, duplicate, or remove components without touching code.

```
Canvas Page Template "Homepage"
  └── Canvas Component: duccinis-hero                (FP-2.1)
  └── Canvas Component: duccinis-ticker              (FP-2.2)
  └── Canvas Component: duccinis-locations-section   (FP-2.3)
  └── Canvas Component: duccinis-order-band          (FP-2.4)
  └── Canvas Component: duccinis-menu-preview        (FP-2.5)
  └── Canvas Component: duccinis-stripe-separator    (FP-2.6)
  └── Canvas Component: duccinis-story-section       (FP-2.7)
  └── Canvas Component: duccinis-stripe-separator    (FP-2.6, reused)
  └── Canvas Component: duccinis-reviews-section     (FP-2.8)
```

Each SDC component lives at:
```
web/themes/custom/duccinis_1984_olympics/components/<name>/
  <name>.component.yml   # schema, props definition
  <name>.twig            # Twig template
  <name>.scss            # compiled to <name>.css by npm run dev
```

JS behaviors (`src/js/`) are registered in `libraries.yml` and attached via `#attached` in a custom block or `hook_page_attachments_alter`.

---

## Epic FP-1 — SDC Component Library

> Build the theme-level SDC components that power every homepage section.
> All SCSS compiles via `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"`.
> Run `ddev drush cr` after every component file change.

### FP-1.1 — `duccinis-hero` component

Build the full-viewport hero.

**Files:**
- `components/duccinis-hero/duccinis-hero.component.yml`
- `components/duccinis-hero/duccinis-hero.twig`
- `components/duccinis-hero/duccinis-hero.scss`

**Props schema (component.yml):**
```yaml
props:
  eyebrow:       string   # "Washington DC & Arlington · Since 1988"
  headline_l1:   string   # "JUMBO"
  headline_l2:   string   # "SLICE"
  headline_l3:   string   # "PIZZA"
  subline:       string   # "Home of DC's Original Oversized Slice"
  description:   string   # body text
  cta_primary_label: string
  cta_primary_url:   string
  cta_secondary_label: string
  cta_secondary_url:  string
```

**Implementation notes:**
- Diagonal stripe overlay via `::before` pseudo-elements — no extra markup
- Olympics rings decoration: 5 inline `<span>` elements with CSS-only ring styling
- Entrance animations: CSS-only `@keyframes fadeUp` with `animation-delay` stagger
- The `.hero-bg__grain` SVG data-URI noise texture from the prototype is inline in SCSS as a `background-image`
- All colour references use `var(--duccini-*)` tokens from `_variables.scss`

**Acceptance criteria:**
- [ ] Component renders in Canvas editor with editable headline/CTA props
- [ ] All three headline lines display in Bebas Neue at `clamp(6rem, 18vw, 16rem)`
- [ ] Entrance animation plays once on page load (not on every scroll)
- [ ] `ddev exec "… npm run dev"` exits 0, `ddev drush cr` exits 0

---

### FP-1.2 — `duccinis-ticker` component

Infinite-scroll marquee announcing fresh daily, open late, etc.

**Files:**
- `components/duccinis-ticker/duccinis-ticker.component.yml`
- `components/duccinis-ticker/duccinis-ticker.twig`
- `components/duccinis-ticker/duccinis-ticker.scss`

**Props schema:**
```yaml
props:
  items:
    type: array
    items:
      type: string
    description: List of ticker messages (e.g. "Fresh Made Daily")
```

**Implementation notes:**
- Twig loops `items` twice (duplicate for seamless CSS animation loop)
- CSS `@keyframes ticker` translateX only — no JS required
- Yellow background (`var(--duccini-gold)`) with dark text
- `will-change: transform` on the track for GPU compositing

**Acceptance criteria:**
- [ ] Canvas editor shows an editable list of ticker text items
- [ ] Animation loops without gap at seam

---

### FP-1.3 — `duccinis-location-card` component

One store card. Used three times inside `duccinis-locations-section`.

**Files:**
- `components/duccinis-location-card/duccinis-location-card.component.yml`
- `components/duccinis-location-card/duccinis-location-card.twig`
- `components/duccinis-location-card/duccinis-location-card.scss`

**Props schema:**
```yaml
props:
  index:         integer  # 1, 2, 3 — drives accent colour (red/blue/orange)
  name:          string   # "Adams Morgan"
  address_l1:    string
  address_l2:    string
  phone:         string   # tel: href value
  maps_url:      string
  hours:
    type: array
    items:
      type: object
      properties:
        days: string    # "Mon – Thu"
        time: string    # "11 AM – 3 AM"
```

**Implementation notes:**
- Accent colour injected as `data-store-index="{{ index }}"` on root element; SCSS `[data-store-index="1"]` selects red, 2 → blue, 3 → orange
- Card hover: `translateY(-4px)` with darkened background — pure CSS
- Ghost number (01 / 02 / 03) positioned absolutely with `opacity: 0.04`
- Open/closed badge DOM node is present in Twig; the **live badge JS behavior** (FP-3.3) updates its state at runtime

**Acceptance criteria:**
- [ ] Correct accent colour per card index
- [ ] Hours table renders from props array
- [ ] Directions and phone links are correct `<a>` elements with proper `rel="noopener noreferrer"` on external links

---

### FP-1.4 — `duccinis-locations-section` component

Container for the three location cards + section header.

**Files:**
- `components/duccinis-locations-section/duccinis-locations-section.component.yml`
- `components/duccinis-locations-section/duccinis-locations-section.twig`
- `components/duccinis-locations-section/duccinis-locations-section.scss`

**Props schema:**
```yaml
props:
  section_label: string  # "Three Locations"
  headline_l1:   string  # "FIND"
  headline_l2:   string  # "YOUR SLICE"
  cta_label:     string
  cta_url:       string
  stores:
    type: array
    maxItems: 3
    items: { $ref: 'duccinis-location-card props' }
```

**Implementation notes:**
- `stores` array is iterated; each item passed as props to `{{ include('duccinis_1984_olympics:duccinis-location-card', store) }}`
- 3-column CSS Grid with 1.5px gap on `var(--border)` background — same "gaps-as-lines" technique used in prototype
- Olympic 5-colour top accent bar via `::before` linear-gradient (matches the spec from `output/prototype/index.html`)
- Collapses to single column below 860px

**Acceptance criteria:**
- [ ] 3-column layout on ≥ 860px, 1-column on mobile
- [ ] Each store card receives its correct index (1/2/3) for accent colouring

---

### FP-1.5 — `duccinis-order-band` component

Full-bleed red CTA band.

**Files:**
- `components/duccinis-order-band/duccinis-order-band.component.yml`
- `components/duccinis-order-band/duccinis-order-band.twig`
- `components/duccinis-order-band/duccinis-order-band.scss`

**Props schema:**
```yaml
props:
  headline_l1: string  # "HUNGRY"
  headline_l2: string  # "NOW?"
  body:        string
  cta_label:   string  # "View the Menu"
  cta_url:     string  # "/menu"
```

**Implementation notes:**
- Background `var(--red)` with hatching via `repeating-linear-gradient` pseudo-element
- Ghost word watermark (e.g. "MENU") at `opacity: 0.04` via `::after` with Bebas Neue
- Parallelogram-clipped CTA button: `clip-path: polygon(14px 0%, 100% 0%, calc(100% - 14px) 100%, 0% 100%)`
- No JS required

---

### FP-1.6 — `duccinis-menu-preview` component

4-up grid of food category cards.

**Files:**
- `components/duccinis-menu-preview/duccinis-menu-preview.component.yml`
- `components/duccinis-menu-preview/duccinis-menu-preview.twig`
- `components/duccinis-menu-preview/duccinis-menu-preview.scss`

**Props schema:**
```yaml
props:
  headline_l1:  string
  headline_l2:  string
  cta_label:    string
  cta_url:      string
  items:
    type: array
    maxItems: 8
    items:
      type: object
      properties:
        icon:  string   # emoji or SVG markup
        name:  string
        desc:  string
```

**Implementation notes:**
- 4-column grid at ≥ 900px, 2-column at ≥ 500px, 1-column on mobile
- Dark card background with hover darkening — same CSS pattern as location cards
- Icon field accepts emoji (prototype uses 🍕🥙🍗🌀) but should also accept an inline SVG string for future polish

---

### FP-1.7 — `duccinis-story-section` component

Two-column: story copy on the left, 2×2 stats grid on the right.

**Files:**
- `components/duccinis-story-section/duccinis-story-section.component.yml`
- `components/duccinis-story-section/duccinis-story-section.twig`
- `components/duccinis-story-section/duccinis-story-section.scss`

**Props schema:**
```yaml
props:
  section_label: string
  headline_l1:   string  # "SINCE"
  headline_l2:   string  # "1988"
  paragraphs:
    type: array
    items:
      type: string    # each paragraph as plain text
  stats:
    type: array
    maxItems: 4
    items:
      type: object
      properties:
        number: string   # "37+"
        label:  string   # "Years in Business"
```

**Implementation notes:**
- Stats grid uses same "gaps-as-lines" pattern (1.5px gap on border background)
- `stat__num` uses Bebas Neue at `3.75rem` with `var(--duccini-gold)` colour
- Collapses to single column below 800px (story text above stats)

---

### FP-1.8 — `duccinis-reviews-section` component

3-column testimonial grid.

**Files:**
- `components/duccinis-reviews-section/duccinis-reviews-section.component.yml`
- `components/duccinis-reviews-section/duccinis-reviews-section.twig`
- `components/duccinis-reviews-section/duccinis-reviews-section.scss`

**Props schema:**
```yaml
props:
  headline_l1: string
  headline_l2: string
  reviews:
    type: array
    maxItems: 6
    items:
      type: object
      properties:
        text:     string
        author:   string
        source:   string  # "Google Review · Adams Morgan"
```

**Implementation notes:**
- Giant `"` watermark per card via `::before` with Bebas Neue at `6rem`, `opacity: 0.07`
- Star row: 5 `★` characters in `var(--duccini-gold)` — not an image
- Card grid: 3 columns ≥ 860px, 1 column below

---

### FP-1.9 — `duccinis-stripe-separator` component

5-colour Olympic stripe bar, reused between sections.

**Files:**
- `components/duccinis-stripe-separator/duccinis-stripe-separator.component.yml`
- `components/duccinis-stripe-separator/duccinis-stripe-separator.twig`
- `components/duccinis-stripe-separator/duccinis-stripe-separator.scss`

**Props schema:**
```yaml
props:
  height_px:
    type: integer
    default: 5
```

**Implementation notes:**
- Single `<div>` with `height` set via inline style from prop, `background: linear-gradient(to right, red 0% 20%, blue 20% 40%, yellow 40% 60%, orange 60% 80%, magenta 80% 100%)`
- No JS, no extra markup

---

## Epic FP-2 — Canvas Page Template

> Wire all SDC components into a Canvas page template and make it the site front page.
> All Canvas config exports via `ddev drush cex` and committed to `config/sync/`.

### FP-2.1 — Register SDC components with Canvas

Canvas discovers SDC components automatically when they declare
`canvas: true` (or equivalent Canvas annotation) in their `.component.yml`.

Add to each component's `.component.yml`:
```yaml
third_party_settings:
  canvas:
    label: 'Hero — Duccini's'   # display name in Canvas UI
    category: 'Duccini's'
```

Verify components appear in the Canvas component picker:
`https://duccinisv4.ddev.site/en/canvas/component/library`

**Acceptance criteria:**
- [ ] All 9 components from Epic FP-1 appear in Canvas component picker
- [ ] Each component's props are editable via the Canvas sidebar panel

---

### FP-2.2 — Create "Homepage" Canvas page template

1. Navigate to **Canvas → Page Templates → Add page template**
2. Name: `Homepage`
3. Machine name: `homepage`
4. Add all 9 components in order (see architecture diagram above)
5. Fill default content matching `output/prototype/index.html`
6. Save and export config: `ddev drush cex`

**Files to commit:**
- `config/sync/canvas.canvas_page_template.homepage.yml`
- Any Canvas block config UUIDs generated

**Acceptance criteria:**
- [ ] Page template renders all sections in correct order
- [ ] Canvas editor allows modifying any text prop without code changes
- [ ] `ddev drush cex` shows no uncommitted config changes

---

### FP-2.3 — Create homepage node and set as front page

1. Create a **Basic Page** node (or Canvas-managed page) using the `Homepage` template
2. Set path alias: `/home`
3. Navigate to `admin/config/system/site-information`
4. Set **Default front page** to `/home`
5. Verify `https://duccinisv4.ddev.site/` renders the homepage

**Acceptance criteria:**
- [ ] `https://duccinisv4.ddev.site/` shows the Canvas homepage
- [ ] `https://duccinisv4.ddev.site/home` also resolves (path alias canonical)
- [ ] `ddev drush cex` shows path alias config committed

---

## Epic FP-3 — Theme: SCSS, Animations & JS Behaviors

> All SCSS edits go in `src/scss/` or `components/<name>/<name>.scss`.
> All JS behaviors go in `src/js/` and must be registered in `duccinis_1984_olympics.libraries.yml`.
> Never edit `build/` directly.

### FP-3.1 — Global homepage SCSS

Create `src/scss/components/_homepage.scss` and `@import` it from `main.style.scss`.

Contains:
- `.site-nav` sticky navigation with `backdrop-filter: blur(12px)` and scroll-triggered `.scrolled` state
- `.btn-primary` and `.btn-ghost` parallelogram clip-path button variants
- `.clip` and `.clip-sm` utility classes
- `.reveal` / `.in-view` scroll-reveal utility (opacity + translateY transition)
- `.section-label` with `::before` line decoration
- Media query breakpoints following Bootstrap 5 `lg` / `md` / `sm` conventions

**Acceptance criteria:**
- [ ] `npm run dev` exits 0
- [ ] No SCSS lint errors (`ddev exec npx stylelint 'src/scss/**/*.scss'`)

---

### FP-3.2 — Scroll-reveal Drupal behavior

Create `src/js/homepage-reveal.js` — a namespaced Drupal behavior.

```js
Drupal.behaviors.homepageReveal = {
  attach(context) {
    const els = context.querySelectorAll('.reveal:not(.in-view)');
    const obs = new IntersectionObserver((entries) => { … }, { threshold: 0.08 });
    els.forEach(el => obs.observe(el));
  }
};
```

Register in `duccinis_1984_olympics.libraries.yml`:
```yaml
homepage:
  js:
    src/js/homepage-reveal.js: {}
  dependencies:
    - core/drupal
```

Attach in the `duccinis-hero` component's `.component.yml` or via `hook_page_attachments_alter` when the homepage node is rendered.

**Acceptance criteria:**
- [ ] `.reveal` elements animate in as user scrolls (threshold 8% visible)
- [ ] No JS errors in browser console
- [ ] Behavior is idempotent — safe on AJAX-rebuilt pages

---

### FP-3.3 — Live open/closed badge JS behavior

Create `src/js/store-hours-badge.js`.

Logic (ported from prototype):
- Store hours map keyed by store type (`dc` / `va`)
- On `attach`, computes current local time against open/close hours
- Updates badge element class (`open-badge--open` / `--late` / `--closed`) and text
- Handles overnight hours (close hour > 24 convention)
- Runs once on page load; no polling needed

Register in `libraries.yml` under the same `homepage` library or a separate `store-hours` library.

**Acceptance criteria:**
- [ ] Badge correctly shows **Open Now**, **Open Late**, or **Closed** based on current time
- [ ] Computed using the browser's local timezone (DC is ET)
- [ ] No hardcoded `Date` mocks — uses `new Date()` live

---

### FP-3.4 — Nav scroll behavior

Create `src/js/nav-scroll.js` or fold into `homepage-reveal.js`.

Adds `.scrolled` class to `.site-nav` when `window.scrollY > 60`.

Uses passive scroll listener:
```js
window.addEventListener('scroll', handler, { passive: true });
```

**Acceptance criteria:**
- [ ] Nav border bottom brightens at 60px scroll
- [ ] No cumulative layout shift (nav is `position: fixed`)

---

## Epic FP-4 — Fonts & Design Token Verification

> Ensure all prototype fonts load, CSS tokens resolve, and no visual regressions appear.

### FP-4.1 — Verify Google Fonts load strategy

The prototype uses `display=swap` for Bebas Neue, Archivo Black, and DM Sans. Confirm `duccinis_1984_olympics.info.yml` (or the base Radix theme) loads these via `libraries.yml` rather than inline `<link>` tags (Drupal best practice).

**Check:** `duccinis_1984_olympics.libraries.yml` should contain:
```yaml
fonts:
  css:
    theme:
      https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Archivo+Black&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap: { type: external }
```

---

### FP-4.2 — Confirm `--duccini-*` CSS custom properties reach components

In `src/scss/base/_variables.scss` the `:root` block defines all `--duccini-*` tokens.
Confirm that compiled `build/css/main.style.css` is loaded *before* any component CSS on the homepage so tokens are resolvable when component CSS runs.

Verify via browser DevTools: `getComputedStyle(document.documentElement).getPropertyValue('--duccini-blue')` should return `#0077C0`.

---

## Epic FP-5 — SEO & Metadata

### FP-5.1 — Set page title and meta description

On the homepage node:
- **Title:** `Duccini's Pizza — Home of the Jumbo Slice | DC & Arlington`
- **Meta description:** `Washington DC's original jumbo slice pizza. Three DMV locations open late. Fresh made daily since 1988. Order pickup or delivery.`

Use Metatag module (already present in DrupalCMS 2) or the node's SEO fields.

### FP-5.2 — Structured data: LocalBusiness JSON-LD

Add a `hook_page_attachments_alter` in `duccinis_1984_olympics.theme` (or a small custom module `duccinis_seo`) that injects `<script type="application/ld+json">` blocks for all three stores when the front page is rendered.

Schema: `LocalBusiness` → `name`, `address`, `telephone`, `openingHoursSpecification`.

**Acceptance criteria:**
- [ ] Google's Rich Results Test shows no errors for the homepage URL
- [ ] Three store entries present in JSON-LD

---

## Implementation Order

Work top-to-bottom within each epic. Epic ordering by dependency:

```
FP-4.1  →  FP-1.* (all SDC components)  →  FP-2.1  →  FP-2.2  →  FP-2.3
FP-3.1  →  FP-3.2  →  FP-3.3  →  FP-3.4   (can run in parallel with FP-1.*)
FP-5.*  (final, after page exists)
```

---

## Branching

All work targets `migration_branch`:

```bash
cd /home/lee/ams_projects/2026/week-10/v2/duccinisv4
git checkout migration_branch && git checkout -b issue/FP-1-sdc-homepage-components
```

One branch per epic is acceptable given the tight coupling between components.

---

## Pre-Merge Checklist

- [ ] `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` exits 0
- [ ] `ddev drush cr` exits 0
- [ ] `ddev drush cex` — config exported, no diff
- [ ] `ddev exec npx stylelint 'src/scss/**/*.scss'` exits 0
- [ ] All 9 SDC components appear in Canvas component picker
- [ ] Homepage renders at `https://duccinisv4.ddev.site/`
- [ ] Open/closed badge shows correct live state
- [ ] Responsive: 375px, 768px, 1280px viewports pass visual check
- [ ] No JS errors in browser console
- [ ] Lighthouse Performance ≥ 85 (mobile)
- [ ] `ddev phpunit` — all store_fulfillment tests still pass
