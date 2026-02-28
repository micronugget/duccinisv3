---
name: Duccinis V3 — Project Instructions
description: Drupal 11 + Commerce restaurant ordering platform — architecture, conventions, and coding standards for AI agents
tags: [instructions, standards, drupal, commerce]
version: 2.0.0
---

# Duccinis V3 — Development Guidelines

**Duccinis V3** is a Drupal 11 + Drupal Commerce restaurant ordering site for Duccinis, a DC-area pizza chain. It supports multi-store pickup and delivery ordering with Stripe payment and real-time radius validation.

---

## Environment Setup

**All CLI commands must be prefixed with `ddev`. No exceptions.**

| Command | Purpose |
|---|---|
| `ddev drush cr` | Clear caches — required after hook/service/module/template changes |
| `ddev drush cex` | Export config to code — **always run before committing** |
| `ddev drush cim -y` | Import config from code |
| `ddev composer require …` | Install PHP packages |
| `ddev phpunit` | Run PHPUnit test suite (all store_fulfillment tests) |
| `ddev exec phpcs --standard=Drupal …` | PHP CodeSniffer |
| `ddev exec phpstan …` | Static analysis |
| `ddev npm run dev` | Compile theme assets (Radix 6 / webpack.mix.js) |
| `ddev drush uli --uid=3 --uri=https://duccinisv3.ddev.site` | Login link for test user Geena (uid=3, has saved Stripe cards) |

**DDEV site URL:** `https://duccinisv3.ddev.site` | PHP 8.3 | Drupal 11.2 | MariaDB 10.11

Read `.github/copilot-terminal-guide.md` for reliable terminal command patterns (always use `2>&1`, echo markers, `| head -50` for verbose output).

---

## Architecture

### Stores

| ID | Name | Delivery Radius |
|---|---|---|
| 1 | Adams Morgan DC | 4 mi |
| 2 | Arlington VA | 4.5 mi |
| 3 | 7th Street NW | 4 mi |

Stores have `delivery_radius` and `store_location` (geofield lat/lon) fields. Geocoding uses Nominatim (OpenStreetMap) — no API key required.

### Custom Modules (`web/modules/custom/`)

| Module | Purpose |
|---|---|
| `store_fulfillment` | **Core business logic.** Checkout panes (`FulfillmentTime`, `DeliveryAddress`), shipping method plugins (`StorePickup`, `StoreDelivery`), delivery radius validation (Haversine + Nominatim geocoding), store hours enforcement, order placement event subscribers. |
| `store_resolver` | Multi-store context resolution. Required dependency of `store_fulfillment`. |
| `back_to_cart_button` | "Return to Menu" button on cart with AJAX cart-block refresh. |
| `commerce_reorder` | Reorder previous orders functionality. |
| `duccinis_feeds_fix` | Fixes Feeds module for multi-variation product CSV imports. |

### Key Services

| Service ID | Class | Purpose |
|---|---|---|
| `store_fulfillment.order_validator` | `OrderValidator` | Store hours & fulfillment time validation |
| `store_fulfillment.delivery_radius_calculator` | `DeliveryRadiusCalculator` | Haversine-formula distance calculation |
| `store_fulfillment.delivery_radius_validator` | `DeliveryRadiusValidator` | Geocodes address + validates radius with user-friendly messages |

### Checkout Flow (`order_information` step)

1. `contact_information` (weight 1)
2. `fulfillment_time` (weight 2) — ASAP vs scheduled, pickup vs delivery toggle; AJAX refreshes both `#delivery-address-wrapper` AND `#edit-payment-information`
3. `delivery_address` (weight 5) — hidden until delivery is selected; includes "billing same as delivery" checkbox
4. `payment_information` (weight 10) — Stripe Payment Element (offsite); saved-card display

### Payment Architecture

- **Gateway:** `stripe_payment_element` plugin — **offsite** flow. The Stripe card entry form renders on the `review` step, NOT on `order_information`.
- **Saved cards:** Displayed via `form-element--radio.html.twig` and the `saved-card` SDC component; CSS-only selection state (no JS needed for visual state).
- **`#after_build` callback:** `store_fulfillment_payment_radios_after_build()` in `store_fulfillment.module` stamps `#card_data` on child radio elements. Uses `#after_build` (NOT `#process` — using `#process` would break `Radios::processRadios()` and produce zero child elements).
- **"Use a different card":** `saved-card-fix.js` (library `duccinis_1984_olympics/saved-card-fix`) forces `change` event on the hidden Stripe radio when its label is clicked.

### Theme

**Active theme:** `duccinis_1984_olympics` — Radix 6.x / Bootstrap 5 / webpack.mix.js

- Build output: `web/themes/custom/duccinis_1984_olympics/build/`
- Run `ddev npm run dev` from the project root (corepack-enabled inside DDEV)
- **SDC:** `components/saved-card/` — compiled via npm build
- Key files:
  - `templates/form/form-element--radio.html.twig` — renders saved-card rows and "Use a different card" UI
  - `templates/form/input--radio.html.twig` — delegates to `radix:input` component
  - `includes/form.theme` — `duccinis_1984_olympics_preprocess_form_element()` injects `card_data`, `is_new_card`, `element_id` Twig variables

---

## Code Standards

### PHP

- `declare(strict_types=1);` at the top of **every** new PHP file — non-negotiable.
- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards).
- Services use constructor injection. Never call `\Drupal::service()` inside a service class.
- All code inside `web/modules/custom/` and `web/themes/custom/` — never modify `web/core/` or `vendor/`.

### Configuration Management

- Config belongs in code. After any UI configuration change, run `ddev drush cex` and commit.
- Checkout pane order lives in `config/sync/commerce_checkout.commerce_checkout_flow.default.yml`.
- After importing config: always `ddev drush cr`.

### Testing

- Run: `ddev phpunit` (uses `phpunit.xml` at project root, suite points to `store_fulfillment/tests/`)
- `SIMPLETEST_BASE_URL=https://duccinisv3.ddev.site`, `SIMPLETEST_DB=mysql://db:db@db/db`
- New functionality should include Kernel or Functional tests under `store_fulfillment/tests/src/`.
- Test class location: `Kernel/` for unit-ish + service tests; `Functional/` for browser/form tests.

### Pre-commit Gates

- `ddev phpunit` — all tests pass
- `ddev exec phpcs --standard=Drupal` — no coding standard errors
- `ddev drush cex` — config exported and committed

---

## Project-Specific Conventions

### Order Data Storage

Fulfillment metadata is stored directly on the `$order` entity (not fields):

```php
$order->getData('fulfillment_method')        // 'pickup' | 'delivery'
$order->getData('fulfillment_type')          // 'asap' | 'scheduled'
$order->getData('scheduled_time')            // 'Y-m-d H:i:s'
$order->getData('delivery_address_profile')  // profile entity ID (int)
```

### AJAX in Checkout Panes

When a single AJAX callback must refresh multiple checkout sections, return an explicit `AjaxResponse` with multiple `ReplaceCommand` instances — do NOT rely on Commerce's default AJAX which replaces only the triggering pane's wrapper.

### Conditional Checkout Pane Visibility

Panes that are conditionally shown/hidden via AJAX **must** implement `isVisible()` returning `TRUE`. Use a `display:none` CSS wrapper + empty `#markup` placeholder when the pane should be hidden — this prevents `Element::getVisibleChildren()` from removing the wrapper div from the DOM and breaking AJAX targeting.

### PaymentInformation Billing Ownership

`store_fulfillment_form_alter()` removes `billing_information` from the `PaymentInformation` pane when delivery is selected. The `DeliveryAddress` pane owns billing for delivery orders. For pickup orders, `PaymentInformation` renders its billing form normally.

---

## Specialized Agents

Check `.github/agents/` before implementing complex features.

| Agent | File | When to Use |
|---|---|---|
| Drupal Developer | `developer_drupal.md` | PHP modules, hooks, services, config, Commerce |
| Themer | `themer.agent.md` | Radix/Bootstrap changes, Twig, SCSS, SDC components |
| Tester | `tester.md` | PHPUnit, PHPStan, Nightwatch, test writing |
| DBA | `database-administrator.md` | Schema & query optimization |
| Architect | `architect.md` | Task decomposition, workflow orchestration |

Full agent directory: `.github/AGENT_DIRECTORY.md`
