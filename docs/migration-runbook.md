# Duccinis V3 → DrupalCMS 2 Migration Runbook

**Issue:** #75 — epic: DrupalCMS 2 + Experience Builder migration planning  
**Status:** Planning complete — migration NOT yet begun  
**Sprint:** C (block extraction sprint #70–#74 completed as prerequisite)  
**Drupal versions:** V3 source = Drupal 11.2 + Commerce 3.3.3 | Target = DrupalCMS 2 (Drupal 11.x + Experience Builder)

---

## 1. Compatibility Matrix

| Area | Package / Component | V3 Version | Risk | DrupalCMS 2 Verdict |
|------|--------------------|-----------:|------|---------------------|
| Core | `drupal/core` | 11.2.4 | Low | ✅ DrupalCMS 2 targets Drupal 11.x — compatible |
| Commerce | `drupal/commerce` | 3.3.3 | Medium | ⚠️ Verify Commerce 3.x support for DrupalCMS 2 Drupal version; track commerce/commerce #3499 |
| Commerce Shipping | `drupal/commerce_shipping` | 3.0.2 | Low | ✅ No layout coupling |
| Commerce Stripe | `drupal/commerce_stripe` | 2.2.1 | Medium | ⚠️ Verify Stripe PE offsite checkout is compatible with XB page builder (checkout pages are not XB-editable sections) |
| `store_fulfillment` | Custom module | — | Low | ✅ Pure Commerce plugin + service + event-subscriber — zero layout coupling |
| `store_resolver` | Custom module | — | Low | ✅ Pure Commerce service — zero layout coupling |
| `back_to_cart_button` | Custom module | — | Low | ✅ Block plugin — drop into any XB section |
| `commerce_reorder` | Custom module | — | Low | ✅ Block plugin — drop into any XB section |
| `duccinis_feeds_fix` | Custom module | — | Low | ✅ Only needed for product CSV imports; remove after migration |
| `drupal/geocoder` | Contrib | 4.10 | Low | ✅ Field-level module, no layout coupling |
| `drupal/geofield` | Contrib | 10.3 | Low | ✅ Field module, no layout coupling |
| `drupal/feeds` | Contrib | dev-2.x | Low | ℹ️ Used for initial product import only — can be removed post-migration |
| `radix` base theme | Radix 6.x | — | Low | ✅ Radix 6 is a Bootstrap 5 theme, not a layout system — compatible with XB. See §2 |
| `duccinis_1984_olympics` | Sub-theme (Bootstrap 5 / webpack.mix.js) | — | Low | ✅ Keep and adapt — minor `info.yml` updates. See §2 |
| Experience Builder (XB) | `drupal/experience_builder` | — | Medium | ⚠️ Checkout pages (`/checkout/*`) are not managed by XB — Commerce controls those templates |
| PHP | — | 8.4 | Low | ✅ DrupalCMS 2 requires PHP 8.3+ |

---

## 2. Theme Strategy: Keep Radix 6 + `duccinis_1984_olympics`

**Decision: KEEP the Radix 6 sub-theme and adapt it for DrupalCMS 2.**

### Why Radix 6 Still Works

| Factor | Assessment |
|--------|-----------|
| Radix 6 + Bootstrap 5 | Radix 6 is a **theme**, not a page-builder plugin. It supplies Bootstrap 5, SDC component support, and Twig templates. None of this conflicts with XB. |
| Experience Builder scope | XB manages **layout composition** — placing components into sections. It does NOT replace your theme's CSS framework, build pipeline, or Twig template overrides. Your theme still controls the final HTML/CSS/JS output. |
| SDC components | Radix 6 already supports SDC. XB's component model is also SDC-based. The `saved-card` component and any future SDC components work with both systems. |
| webpack.mix.js (Laravel Mix) | Still functional, still compiles SCSS + JS. Not deprecated — just no longer actively developed. Can be replaced with Vite later as a separate quality-of-life improvement, not a migration blocker. |
| Commerce checkout pages | Commerce controls `/checkout/*` and `/cart` routes. XB does not manage these pages, so all checkout Twig overrides (`commerce-checkout-form--*.html.twig`, form templates) continue to work unchanged. |

### What Needs Adaptation for DrupalCMS 2

#### Phase A — Required (before migration)

| Task | Detail | Files Affected |
|------|--------|----------------|
| **A1. Verify `core_version_requirement`** | Confirm DrupalCMS 2's core version; update `info.yml` if needed (likely `^11`) | `duccinis_1984_olympics.info.yml` |
| **A2. Confirm Radix 6 Drupal core compat** | Run `ddev composer require drupal/radix:^6.0 --dry-run` in V4 project to confirm no version conflict | None (verification only) |
| **A3. Region mapping** | If DrupalCMS 2 expects different region names for navigation/admin chrome, add them alongside existing regions in `info.yml`. Current regions (`navbar_branding`, `navbar_left`, `navbar_right`, `header`, `content`, `page_bottom`, `footer`) are standard and likely compatible. | `duccinis_1984_olympics.info.yml` |
| **A4. XB compatibility flag** | If XB requires themes to declare `experience_builder: true` or similar in `info.yml`, add it. Check DrupalCMS 2 release notes at migration time. | `duccinis_1984_olympics.info.yml` |

#### Phase B — Recommended (post-migration polish)

| Task | Detail | Files Affected |
|------|--------|----------------|
| **B1. Test XB section rendering** | Place Drupal blocks (progress bar, checkout header, etc.) into XB sections and verify Bootstrap 5 classes still apply. Fix any CSS specificity conflicts. | SCSS files in `src/scss/` |
| **B2. Convert page templates to XB layouts** | `page.html.twig` and `page--front.html.twig` print hardcoded regions. For pages built with XB, add an `{% if page.xb_content %}` branch that outputs XB's rendered content alongside the theme's navbar/footer chrome. | `templates/page/page.html.twig`, `page--front.html.twig` |
| **B3. Register SDC components with XB** | XB uses SDC component metadata to make them available in the visual editor. Add `experience_builder` annotations to `*.component.yml` files so `saved-card` and `checkout-progress` appear in XB's component picker. | `components/*/` `.component.yml` files |
| **B4. Audit `libraries-extend`** | Verify that core library overrides (`drupal.ajax`, `drupal.checkbox`, `drupal.message`, `drupal.progress`) still match the core library signatures in DrupalCMS 2's Drupal version. | `duccinis_1984_olympics.libraries.yml` |

#### Phase C — Optional (quality-of-life, non-blocking)

| Task | Detail |
|------|--------|
| **C1. Migrate webpack.mix.js → Vite** | Laravel Mix works but is unmaintained. A Vite config (`vite.config.js`) can replace it with faster HMR and zero-config SCSS. Do this when convenient, not as a blocking migration step. |
| **C2. Drop `node_modules` from theme** | If DrupalCMS 2 ships a project-level asset pipeline, consider hoisting theme dependencies to the project root. |

### Theme Asset Inventory (What Carries Over Unchanged)

| Category | Count | Notes |
|----------|------:|-------|
| Twig template overrides | 66 files | All in `templates/` — keep as-is |
| SDC components | 3 (`saved-card`, `checkout-progress`, `archive-controls`) | SDC is XB-compatible |
| SCSS source files | ~15+ files in `src/scss/` + `components/` | No changes needed |
| JS behaviors | 12 files in `src/js/` | Drupal behaviors work regardless of layout system |
| Library definitions | 15 in `libraries.yml` | Verify `libraries-extend` (Phase B4) |
| Preprocess functions | `includes/form.theme` | No changes needed |

### Summary

The `duccinis_1984_olympics` Radix 6 sub-theme is **compatible** with DrupalCMS 2. XB is a layout composition tool — it does not replace your theme, your build pipeline, or your CSS framework. The adaptation work is limited to `info.yml` adjustments (Phase A) and optional XB integration polish (Phase B). No rebuild required.

---

## 3. Content Migration Plan

Duccinis V3 has minimal content — no editorial articles, no blog posts, no media library worth migrating. Migration is straightforward.

### 3.1 What to Migrate

| Entity Type | Count | Method |
|-------------|------:|-------|
| Commerce Stores (`commerce_store`) | 3 | Drush export / Migrate API |
| Commerce Products + Variations | ~50 products across 11 types | CSV re-import via Feeds (same CSV files used for V3 import) |
| Commerce Product Attributes (pizza_size, beverage_size, etc.) | 7 | Config export/import (`ddev drush cex` → `cim`) |
| Stripe payment gateway config | — | `settings.php` `$config` override (API keys are NOT in config YAML) |
| User accounts | 3 test users (admin, Geena, + 1) | Recreate manually; Stripe saved cards will be re-created on first checkout |
| Nodes (menu pages) | ~5 | Recreate manually (no real content yet) |

### 3.2 What NOT to Migrate

- Database (order history is test data — discard)
- Stripe saved payment methods (`PaymentMethod` entities linked to test cards)
- Draft/placeholder orders

---

## 4. Migration Runbook — Step by Step

### Phase 1: Set Up DrupalCMS 2

```bash
# 1. Scaffold a new DrupalCMS 2 project
composer create-project drupal/cms drupalcms2-project
cd drupalcms2-project

# 2. Add Commerce and Duccinis custom dependencies
composer require drupal/commerce:^3.3 \
  drupal/commerce_shipping:^3.0 \
  drupal/commerce_stripe:^2.1 \
  drupal/commerce_add_to_cart_link:^2.1 \
  drupal/geocoder:^4.10 \
  drupal/geofield:^10.3 \
  geocoder-php/nominatim-provider:^5.7 \
  drupal/feeds drupal/feeds_tamper

# 3. Copy custom modules (no modifications needed)
cp -r path/to/duccinisv3/web/modules/custom/* web/modules/custom/
# Modules: store_fulfillment, store_resolver, back_to_cart_button, commerce_reorder
# NOTE: duccinis_feeds_fix and duccinis_archive are not needed for V4.

# 4. Apply the Duccinis Store Fulfillment recipe
ddev drush recipe path/to/recipes/duccinis_store_fulfillment

# 5. Clear caches
ddev drush cr
```

### Phase 2: Import Configuration

```bash
# Export config from V3
cd duccinisv3
ddev drush cex -y

# Copy relevant config to V4
# Product types, variation types, product attributes, checkout flow,
# geocoder provider, number patterns, order type config.
cp config/sync/commerce_product.* path/to/v4/config/sync/
cp config/sync/commerce_checkout.commerce_checkout_flow.default.yml path/to/v4/config/sync/
cp config/sync/geocoder.* path/to/v4/config/sync/
cp config/sync/commerce_number_pattern.* path/to/v4/config/sync/
cp config/sync/commerce_order.commerce_order_type.default.yml path/to/v4/config/sync/
cp config/sync/commerce_order.commerce_order_item_type.default.yml path/to/v4/config/sync/

# Import into V4
cd path/to/v4
ddev drush cim -y
ddev drush cr
```

### Phase 3: Import Stores

Stores contain lat/lon field data (`store_location` geofield) and `delivery_radius` fields that are NOT in standard config export. Use Drush PHP eval or a Migrate plugin:

```bash
# Option A — Drush php:eval (paste store creation code)
ddev drush php:eval "
  \$store = \Drupal\commerce_store\Entity\Store::create([
    'type' => 'online',
    'name' => 'Adams Morgan DC',
    'mail' => 'orders@duccinis.com',
    'default_currency' => 'USD',
    'field_store_location' => ['lat' => 38.9217, 'lon' => -77.0421],
    'field_delivery_radius' => 4,
  ]);
  \$store->save();
  echo 'Store ID: ' . \$store->id();
"

# Repeat for: Arlington VA (lat 38.8816, lon -77.0910, radius 4.5 mi)
# and 7th Street NW (lat 38.9027, lon -77.0228, radius 4 mi)
```

### Phase 4: Import Products

Use the same CSV files from the original Feeds import (see `Commerce_Product_Importing.md`):

```bash
# 1. Upload CSVs via the Feeds admin UI at /admin/content/feeds
# 2. Run each product type feed (pizza, beverages, desserts, etc.)
# 3. Verify product counts match V3: ~50 products across 11 types
```

### Phase 5: Configure Stripe

Add to V4's `web/sites/default/settings.php`:

```php
// Stripe Payment Gateway — API keys (never commit to VCS).
$config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['secret_key'] = 'sk_test_YOUR_KEY_HERE';
$config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['publishable_key'] = 'pk_test_YOUR_KEY_HERE';
```

**Note:** Use NEW Stripe test keys. The V3 keys (`sk_test_51Sfs1Z…`) were exposed in git history and must be considered compromised — do not reuse them.

### Phase 6: Theme — Copy and Adapt Radix 6 Sub-theme

```bash
# 1. Copy the entire theme directory to V4
cp -r path/to/duccinisv3/web/themes/custom/duccinis_1984_olympics \
  web/themes/custom/duccinis_1984_olympics

# 2. Require Radix 6 base theme in V4
ddev composer require drupal/radix:^6.0

# 3. Install node dependencies and build
ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm install && npm run dev"

# 4. Update info.yml if DrupalCMS 2 Drupal core version requires it
#    (check: core_version_requirement, region names, XB flags)

# 5. Enable the theme
ddev drush theme:enable duccinis_1984_olympics
ddev drush config:set system.theme default duccinis_1984_olympics -y
ddev drush cr
```

See §2 "Theme Strategy" for the full Phase A/B/C adaptation plan.

### Phase 7: Validate Checkout Flow

After all modules and config are in place, validate end-to-end:

```bash
# Login as test user
ddev drush uli --uid=2 --uri=https://v4.ddev.site

# Manual checklist:
# [ ] Add to cart from /menu
# [ ] Pickup flow: confirm fulfillment time, no delivery address shown
# [ ] Delivery flow: confirm delivery address pane appears, radius validated
# [ ] Stripe review step: Payment Element renders
# [ ] Order placed successfully, confirmation email sent
# [ ] Order admin at /admin/commerce/orders
```

---

## 5. Open Questions / Risks

| Risk | Mitigation |
|------|-----------|
| DrupalCMS 2 may ship with a Drupal core version > 11.2 by the time migration starts | Pin `drupal/core` constraint in recipe `composer.json`; track DrupalCMS 2 release notes |
| Commerce 3.x compatibility with DrupalCMS 2 not yet confirmed | Monitor [drupal.org/project/commerce](https://www.drupal.org/project/commerce) issues; Commerce 4.x (if needed) may require checkout flow migration |
| Experience Builder checkout pages | Checkout routes (`/checkout/*`, `/cart`) are served by Commerce, not XB — no conflict expected, but test after applying XB |
| `commerce_stripe` Stripe Connect config | Stripe Connect account ID was also exposed in history — rotate or use a new account for V4 |

---

## 6. Prerequisites Complete ✅

- [x] ARCH-1 (#70) — Checkout progress bar block
- [x] ARCH-2 (#71) — Progress bar on /cart
- [x] ARCH-3 (#72) — Remove progress bar from Twig templates
- [x] ARCH-4 (#73) — Checkout header block
- [x] ARCH-5 (#74) — Twig template audit for block extraction candidates
