# Architecture Issues — Block-First Layout & DrupalCMS 2 Planning

**Last updated:** 2026-03-02
**Status:** Planning / pre-development. These specs are ready to open as GitHub issues.

> **Context:** The checkout progress bar is currently embedded directly in Twig templates
> (`commerce-checkout-form--with-sidebar.html.twig` and `--default.html.twig`). This is
> a dead-end: the bar cannot appear on `/cart`, on the `/review` step, or on any non-Commerce
> page without duplicating the markup in every template. The right architecture is a
> **custom Drupal block plugin** that auto-detects the current funnel position from the
> route and can be placed in any page region via the Block UI, Layout Builder, or
> (eventually) Drupal Experience Builder / Canvas.

---

## Issue ARCH-1 — Checkout Progress Bar: Custom Block Plugin

**Suggested GitHub title:** `feat: Checkout progress bar as a custom Drupal block plugin`
**Labels:** `feature`, `checkout`, `blocks`, `Epic 5: Checkout Layout`
**Milestone:** Epic 5 — Phase 2

### Problem

The progress bar is hardcoded inside two Twig templates. It cannot appear on `/cart`,
cannot be repositioned without editing templates, and cannot be disabled per-page
from the Block UI. It also cannot have per-placement configuration (e.g. show/hide
the Cart step cell on the cart page itself).

### Solution

Create `CheckoutProgressBarBlock` as a `@Block` plugin inside `store_fulfillment`:

```
web/modules/custom/store_fulfillment/src/Plugin/Block/CheckoutProgressBarBlock.php
```

**Plugin responsibilities:**
- Inject `RouteMatchInterface` to read `commerce_order` and `step` route params.
- Inject `CartProviderInterface` to find the active cart order for anonymous/auth users
  who have not yet entered checkout (used on the `/cart` page).
- Build the same 4-step structure as the current preprocess function, but as a
  `build()` return value — a `#theme`-able render array or plain markup element.
- Auto-detect context:
  - On `/cart` (`commerce_cart.page` route) → step = `cart` (active), rest future.
  - On `commerce_checkout.form` with `step=order_information` → Cart done, Order Details active.
  - On `commerce_checkout.form` with `step=review` → Cart + Order Details done, Review active.
  - On `commerce_checkout.form` with `step=complete` → Cart + Order Details + Review done, Complete active; back-links suppressed.
- Expose a block config option: `show_cart_step` (default: TRUE) — allows hiding the
  Cart cell when already on the cart page to avoid "Cart > …" appearing redundant.
- Return `['#cache' => ['contexts' => ['route', 'user']]]` so the block varies correctly
  per page and per user.

### Acceptance Criteria

- [ ] Block appears in Drupal's Block UI under "Store Fulfillment" category.
- [ ] Can be placed in any region on any page.
- [ ] Renders correct step states on `/cart`, `order_information`, `review`, `complete`.
- [ ] Done steps are `<a>` links; active step has `aria-current="step"`; future steps
      have `aria-disabled="true"`.
- [ ] Block cache invalidates when user adds/removes items from cart.
- [ ] PHPCS clean + at least one Kernel test covering step-state logic.
- [ ] `CheckoutProgressBarBlock` is tested with a mock `RouteMatchInterface`.

### Files to create / modify

| Action | File |
|--------|------|
| Create | `store_fulfillment/src/Plugin/Block/CheckoutProgressBarBlock.php` |
| Modify | `store_fulfillment/store_fulfillment.services.yml` (if new service needed) |
| Modify | `commerce.theme` — remove `duccinis_1984_olympics_preprocess_commerce_checkout_form` once block takes over |
| Modify | Both checkout Twig templates — remove `{% if checkout_progress_steps %}` block |

---

## Issue ARCH-2 — Progress Bar on `/cart` Page

**Suggested GitHub title:** `feat: Show checkout progress bar on /cart with Cart as active step`
**Labels:** `feature`, `checkout`, `blocks`, `UX`, `frontend`
**Depends on:** ARCH-1

### Problem

`/cart` is outside the Commerce checkout flow — it uses a Views display
(`commerce_cart_form` tag) rendered inside `page.html.twig`. There is no
`commerce_checkout_form` preprocess hook to attach to. The progress bar is invisible
until the user clicks "Proceed to Checkout", creating a dead zone in the funnel.

### Solution

Once ARCH-1 is done:
1. Place `CheckoutProgressBarBlock` in the correct region for `/cart` via Block UI
   (path-restricted to `/cart`).
2. The block's auto-detect logic identifies the route as `commerce_cart.page` and
   renders with `cart` as the active step.
3. "Order Details", "Review", "Complete" appear with `aria-disabled="true"` and
   `cursor: not-allowed` — the user is not yet in checkout.
4. If the user has an active draft order, the "Order Details" step can optionally
   link to `/checkout/{order_id}/order_information` to let them resume.

### Acceptance Criteria

- [ ] Progress bar renders on `/cart` with Cart visually active (pink/magenta bar).
- [ ] Future steps are visually locked — `cursor: not-allowed`, muted color.
- [ ] If no active cart: future steps have no links.
- [ ] If active draft order exists: "Order Details" step links to checkout resume URL.
- [ ] Block does not appear on `/cart` when embedded via Layout Builder on unrelated pages.

---

## Issue ARCH-3 — Progress Bar on Review and Complete Steps

**Suggested GitHub title:** `feat: Progress bar visible on checkout Review and Complete steps`
**Labels:** `feature`, `checkout`, `blocks`, `frontend`
**Depends on:** ARCH-1

### Problem

The `review` step uses `commerce-checkout-form--with-sidebar.html.twig` and
`complete` uses `commerce-checkout-form--default.html.twig`. Both already have the
progress bar via the current template approach, **but the block placement provides
correct cache context, placement flexibility, and will survive template refactors**.

### Solution

Once ARCH-1 is done, remove the `{% if checkout_progress_steps %}` Twig block from
both templates and place `CheckoutProgressBarBlock` in the layout region via Block UI,
restricted to `/checkout/*` paths. The block auto-detects `step=review` or
`step=complete` from the route.

### Acceptance Criteria

- [ ] Review step: Cart ✓ + Order Details ✓ + Review (active) + Complete (future).
- [ ] Complete step: Cart ✓ + Order Details ✓ + Review ✓ + Complete (active); no checkout back-links.
- [ ] Both steps render identically to the current template-embedded version.
- [ ] Twig templates no longer contain `checkout_progress_steps` logic.

---

## Issue ARCH-4 — Checkout Header: Extract to Custom Block

**Suggested GitHub title:** `refactor: Checkout header (title + Back to cart) as a block`
**Labels:** `refactor`, `checkout`, `blocks`, `frontend`

### Problem

The checkout header (`<h1>Checkout</h1>` + "← Back to cart" link) is hardcoded in
`commerce-checkout-form--default.html.twig`. This means:
- It cannot be repositioned or hidden per-step without editing the template.
- The title cannot be made dynamic (e.g. "Review your order") per checkout step.
- It will conflict with Experience Builder's section-based placement model.

### Solution

Create `CheckoutHeaderBlock` in `store_fulfillment/src/Plugin/Block/`:
- Renders the `<header class="checkout-header">` section.
- Injects `RouteMatchInterface` to vary `h1` text per step:
  - `order_information` → "Checkout"
  - `review` → "Review your order"
  - `complete` → "Order confirmed"
- "Back to cart" link: present on `order_information` and `review`; hidden on `complete`.
- Optional block config: `show_back_link` (default: TRUE).

### Acceptance Criteria

- [ ] Block renders on all three checkout steps with correct title + back-link logic.
- [ ] Hardcoded `<header>` removed from `commerce-checkout-form--default.html.twig`.
- [ ] Block is placeable in any layout region.
- [ ] per-step title text is translatable.

---

## Issue ARCH-5 — Block Audit: Identify All Template-Hardcoded Regions

**Suggested GitHub title:** `chore: Audit custom Twig templates for block extraction candidates`
**Labels:** `architecture`, `blocks`, `refactor`, `technical debt`

### Problem

Several UI elements are currently hardcoded inside Twig templates rather than placed
as discrete, repositionable Drupal blocks. This blocks adoption of Layout Builder and
Experience Builder / Canvas and makes the site harder to maintain.

### Audit scope

Review every file in `web/themes/custom/duccinis_1984_olympics/templates/`:

| Template | Hardcoded element | Extract to block? |
|----------|-------------------|-------------------|
| `commerce-checkout-form--default.html.twig` | `<header>` with h1 + back link | ✅ Yes — ARCH-4 |
| `commerce-checkout-form--with-sidebar.html.twig` | Progress bar | ✅ Yes — ARCH-1 |
| `commerce-checkout-form--default.html.twig` | Progress bar | ✅ Yes — ARCH-1 |
| Both checkout templates | "Continue to Review" CTA (`form.actions.next`) | Consider — currently inside sidebar card |
| `commerce-checkout-order-summary.html.twig` | Order summary pane | Already a Commerce pane — leave |

### Deliverable

A GitHub comment on this issue with a table of all candidate elements, a recommendation
(extract / leave / defer), and updated issue stubs for anything marked "extract".

---

## Issue ARCH-6 — DrupalCMS 2 / Experience Builder Migration Planning

**Suggested GitHub title:** `epic: Migration planning — Duccinis V3 → DrupalCMS 2 + Experience Builder`
**Labels:** `epic`, `architecture`, `DrupalCMS2`, `migration`, `planning`
**Priority:** Medium (no real content yet — ideal time to plan)

### Context

DrupalCMS 2 (formerly "Drupal Starshot") ships with Drupal Experience Builder (XB /
Canvas) as its layout and page-building system, replacing Layout Builder. Since
Duccinis V3 has no real content yet, migration is low-risk and high-reward — doing it
now avoids accumulating technical debt against a superseded architecture.

### Work to scope

#### 1. Compatibility audit

| Area | Risk | Notes |
|------|------|-------|
| `drupal/commerce` | Medium | Commerce 3.x + Drupal 11 — check DrupalCMS 2 Drupal core version |
| `store_fulfillment` module | Low | Pure custom — no framework coupling beyond Commerce |
| `store_resolver` module | Low | Pure custom |
| Stripe PE gateway | Medium | `commerce_stripe` — check DrupalCMS 2 composer constraint compatibility |
| `geofield` + Nominatim geocoding | Low | Standard field module |
| `radix` theme | High | Radix 6 is a Layout Builder–era base theme; XB uses its own frontend |
| `duccinis_1984_olympics` subtheme | High | Bootstrap 5 / webpack.mix.js — XB may require a different frontend pipeline |
| Feeds module | Low | Used for product CSV import only |

#### 2. Content migration (minimal — site is in development)

- Commerce Products + Variations (3 stores × menu items) — migrate via CSV/Drush or Migrate API
- Store entities (3 stores with geo data) — export/import as config or migrate
- User accounts (test users only — recreate)
- No articles, nodes, or editorial content to migrate

#### 3. Architecture decisions for XB

- **Block-first design** (issues ARCH-1 through ARCH-5 above) is a prerequisite —
  XB places blocks in sections, not template regions. Complete the block extraction
  sprint before migrating.
- Evaluate whether `duccinis_1984_olympics` subtheme can be ported or whether a fresh
  XB-native theme (using the XB design system primitives) is more pragmatic.
- Determine if Commerce's checkout flow (which controls its own templates) is
  compatible with XB section placement for the checkout pages specifically.

#### 4. Recipe approach

DrupalCMS 2 uses Recipes for feature packaging. Evaluate whether `store_fulfillment`
functionality can be packaged as a Recipe (`store_fulfillment_recipe/`) for clean
installation on a fresh DrupalCMS 2 site.

### Acceptance Criteria

- [ ] Compatibility matrix completed and reviewed.
- [ ] Go/no-go decision on Radix theme portability documented.
- [ ] Migration runbook written: steps to spin up a DrupalCMS 2 site with Commerce
      and re-import store + product data.
- [ ] ARCH-1 through ARCH-5 (block extraction sprint) completed first.
- [ ] Recipe stub created: `recipes/duccinis_store_fulfillment/` with module list + config.

---

## Issue ARCH-7 — Progress Bar Visual Affordance Phase 2

**Suggested GitHub title:** `feat: Progress bar — Phase 2 visual affordance (clickable vs locked states)`
**Labels:** `UX`, `accessibility`, `frontend`, `Epic 5: Checkout Layout`
**Depends on:** ARCH-1

> Supersedes ISSUE-PB-1 through ISSUE-PB-4 in `ISSUES_PROGRESS_BAR.md`.

### Problem

Currently, done steps (clickable) and future steps (locked) are only distinguished by
color. On mobile — where there is no hover state — there is no affordance that "Cart"
and "Order Details" are tappable links. This is both an accessibility gap and a
conversion risk (users who don't know they can go back may abandon instead).

### Required changes

1. **`a.step.done`** — Add `text-decoration: underline; text-underline-offset: 3px`
   so the link affordance is familiar and obvious without relying purely on color
   (WCAG 1.4.1 — Use of Color).
2. **`div.step` (future/active)** — Add `cursor: not-allowed` on future steps (desktop)
   so the pointer communicates "not interactive".
3. **Active step** — Increase `::after` bar height to `5px` (vs `3px` for others).
   Add `font-weight: 800` to make "you are here" unmistakable at 320px viewport.
4. **Step separators** — Add `›` chevron between steps via `::before` on `.step + .step`
   to visually group and separate the four cells.
5. **Mobile fallback** — Below `sm` (576px): show only the current step label +
   "Step N of 4" numeric indicator. Reduces label compression at 320px.
6. **Tooltip on future steps** — Add `title="{{ 'Complete this step first'|t }}"` to
   `<div class="step">` future step elements.

### Acceptance Criteria

- [ ] First-time phone user can identify clickable vs locked steps without hovering.
- [ ] WCAG 1.4.1 (Use of Color) passes — affordance does not rely solely on green/gray.
- [ ] WCAG 2.4.7 (Focus Visible) ring remains on `a.step:focus-visible`.
- [ ] At 320px viewport, the bar does not overflow or compress labels unreadably.
- [ ] Lighthouse accessibility score ≥ 95 on the checkout page.

---

## Issue ARCH-8 — Progress Bar Analytics Integration (GTM / GA4)

**Suggested GitHub title:** `feat: Progress bar — fire GA4 checkout_step_back event on step click`
**Labels:** `analytics`, `marketing`, `frontend`
**Depends on:** ARCH-1

> Supersedes ISSUE-PB-5 in `ISSUES_PROGRESS_BAR.md`.

### Problem

Back-navigation clicks via the progress bar are currently invisible in analytics.
Without event tracking, it is impossible to know:
- What % of users click back from Review to Order Details (edit address / switch fulfillment)?
- What % abandon from the Review step vs Order Details?
- Whether the progress bar links are being used at all.

### Solution

1. In `CheckoutProgressBarBlock::build()`, add data attributes to each `a.step`:
   ```html
   <a class="step done"
      href="/checkout/27/order_information"
      data-funnel-event="checkout_step_back"
      data-from-step="review"
      data-to-step="order_information"
      data-order-id="27">
   ```
2. Add `from_step` as a variable passed from the block (current step ID string).
3. Create `checkout-progress-analytics.js` as a Drupal behavior:
   ```js
   Drupal.behaviors.checkoutProgressAnalytics = {
     attach(context) {
       once('checkout-progress-analytics', '[data-funnel-event]', context)
         .forEach(el => {
           el.addEventListener('click', () => {
             window.dataLayer = window.dataLayer || [];
             window.dataLayer.push({
               event: el.dataset.funnelEvent,
               from_step: el.dataset.fromStep,
               to_step: el.dataset.toStep,
               order_id: el.dataset.orderId,
             });
           });
         });
     },
   };
   ```
4. Define library `duccinis_1984_olympics/checkout-progress-analytics`, attach in block.

### Acceptance Criteria

- [ ] Clicking a done-step link pushes a `checkout_step_back` event to `window.dataLayer`.
- [ ] Event includes `from_step`, `to_step`, and `order_id` properties.
- [ ] Library only loads on pages where the block is placed (no global JS bloat).
- [ ] GTM trigger and GA4 custom event documented in `ANALYTICS.md` (new).
- [ ] The Cart step click fires `checkout_step_back` with `to_step: 'cart'`.

---

## Summary: Recommended Sprint Order

```
Sprint A — Block extraction (prerequisite for everything):
  ARCH-1 (#70)  Custom block plugin
  ARCH-4 (#73)  Checkout header block
  ARCH-5 (#74)  Template audit

Sprint B — Full funnel coverage:
  ARCH-2 (#71)  Progress bar on /cart
  ARCH-3 (#72)  Progress bar on review + complete (remove from templates)
  ARCH-7 (#76)  Phase 2 visual affordance

Sprint C — Measurement + platform:
  ARCH-8 (#77)  Analytics integration
  ARCH-6 (#75)  DrupalCMS 2 migration planning
```

---

## Cross-references

- [ISSUES_PROGRESS_BAR.md](ISSUES_PROGRESS_BAR.md) — Phase 1 UX issues (PB-1 to PB-6); ARCH-7/ARCH-8 supersede PB-1 to PB-5.
- [Issue #31](https://github.com/micronugget/duccinisv3/issues/31) — original progress bar story (closed)
- [DrupalCMS 2 roadmap](https://www.drupal.org/about/drupal-cms)
- [Experience Builder project](https://www.drupal.org/project/experience_builder)
