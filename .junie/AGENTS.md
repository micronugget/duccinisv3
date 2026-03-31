# Duccinis V4 — Agent Guidelines

**Duccinis V4** is a **fresh DrupalCMS 2 install** — the migration *destination* for Duccinis, a DC-area pizza restaurant chain. It is a complete rebuild on the DrupalCMS 2 scaffold (not an upgrade of V3). It supports multi-store pickup and delivery ordering with Stripe payment and real-time radius validation.

## Quick-Start Checklist

Before acting on any task:

- [ ] Read this file fully before writing any code.
- [ ] For store_fulfillment changes, read `.junie/instructions/store-fulfillment.md`.
- [ ] For theme changes, read `.junie/instructions/theme-duccinis-1984-olympics.md`.
- [ ] For PHPUnit test changes, read `.junie/instructions/store-fulfillment-tests.md`.
- [ ] For Stripe/payment changes, read `.junie/instructions/commerce-stripe-payment-element.md`.
- [ ] Read `.junie/terminal-guide.md` for reliable terminal command patterns.
- [ ] All `ddev` commands run inside V4 directory only — never in V3.
- [ ] Run `ddev drush cex` before committing any config-touching change.

---

## Migration Context

> **⚠️ Read this section before starting any issue or task.**

**V4 is the migration destination.** It is a greenfield DrupalCMS 2 install. All new development happens here. The source being migrated *from* — **Duccinis V3** — lives at:

```
/home/lee/ams_projects/2025/week-43/v1/duccinisV3
```

V3 DDEV site: `https://duccinisv3.ddev.site` | PHP 8.4 | Drupal 11.2 | MariaDB 10.11

### Migration Rules

| Rule | Detail |
|------|--------|
| **V3 is READ-ONLY** | Never modify, commit to, or run destructive commands against V3. It is the migration *source* — a reference only, not a development target. |
| **V4 is the active codebase** | All code, config, and commits go into V4 (`/home/lee/ams_projects/2026/week-10/v2/duccinisv4/`). |
| **Copying FROM V3 is allowed** | Read any V3 file to understand the old implementation, copy code/config/templates into V4, or diff V3 vs V4 when resolving an issue. |
| **No `ddev` commands in V3** | Do not run `ddev drush`, `ddev composer`, or any write command inside V3's directory. All `ddev` commands run in V4 only. |

### Why V3 Exists as a Reference

V3 contains proven implementations of `store_fulfillment`, `store_resolver`, `back_to_cart_button`, and `commerce_reorder`, plus the `duccinis_1984_olympics` theme (Radix 6 / Bootstrap 5) that V4 shares. Historical config exports, migration runbooks, and issue documentation useful for tracing why specific decisions were made.

### Branching Strategy

> **⚠️ The base branch depends on the issue number.**

| Issue range | Epic | Branch off | PR target | Notes |
|-------------|------|------------|-----------|-------|
| **#94 – #141** | V3→V4 migration epic | `migration_branch` | `migration_branch` | Local-only; not pushed to GitHub as a standalone remote branch |
| **All others** | FP epics, standalone fixes, docs | `master` | `master` | Standard GitHub flow |

> **⚠️ `migration_branch` does not exist on GitHub.** Use it only as the local base for issues #94–141. Attempting `gh pr create --base migration_branch` against the remote will fail.

#### For issues #94–141 (migration epic)

```bash
git checkout migration_branch && git checkout -b issue/$N-<slug>
```
Merge target: `migration_branch` — never `master` directly.

#### For all other issues

```bash
git checkout master && git pull origin master && git checkout -b issue/$N-<slug>
```
PR target: `master` on GitHub via `gh pr create --base master`.

**Branch naming:** `issue/$N-<slug>` where `$N` is the issue number and `<slug>` is a short kebab-case title.

---

### Per-Issue Checklist

Before writing any code for an issue:

1. **Read the issue** in `ISSUES.md` to understand the scope and acceptance criteria.
2. **Check V3** — locate the equivalent file(s) in V3 to understand the existing implementation.
3. **Diff V3 vs V4** — identify what is missing, changed, or needs porting.
4. **Work only in V4.**
5. **Do not alter V3** under any circumstance.
6. **Branch off the correct base** (see Branching Strategy above).

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
| `ddev exec vendor/bin/phpcs --standard=Drupal …` | PHP CodeSniffer |
| `ddev exec vendor/bin/phpstan …` | Static analysis |
| `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` | Compile theme assets (Radix 6 / webpack.mix.js) |
| `ddev drush uli --uid=3 --uri=https://duccinisv4.ddev.site` | Login link for test user Geena (uid=3, has saved Stripe cards) |

**DDEV site URL:** `https://duccinisv4.ddev.site` | PHP 8.3 | Drupal 11.2 | MariaDB 10.11

Read `.junie/terminal-guide.md` for reliable terminal command patterns (always use `2>&1`, echo markers, `| head -50` for verbose output).

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
- **`#after_build` callback:** `store_fulfillment_payment_radios_after_build()` in `store_fulfillment.module` stamps `#card_data` on child radio elements. Uses `#after_build` (NOT `#process`).
- **"Use a different card":** `saved-card-fix.js` (library `duccinis_1984_olympics/saved-card-fix`) forces `change` event on the hidden Stripe radio when its label is clicked.

### Theme

**Active theme:** `duccinis_1984_olympics` — Radix 6.x / Bootstrap 5 / webpack.mix.js

- Build output: `web/themes/custom/duccinis_1984_olympics/build/`
- Always build from inside theme: `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"`
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
- `SIMPLETEST_BASE_URL=https://duccinisv4.ddev.site`, `SIMPLETEST_DB=mysql://db:db@db/db`
- New functionality should include Kernel or Functional tests under `store_fulfillment/tests/src/`.
- Test class location: `Kernel/` for service tests; `Functional/` for browser/form tests.
- Both Kernel and Functional tests must run inside DDEV (not bare PHP).

### Pre-commit Gates

```bash
ddev composer check-platform-reqs --no-dev   # verify PHP version compatibility
ddev phpunit                                  # all tests pass
ddev exec vendor/bin/phpcs --standard=Drupal  # no coding standard errors
ddev drush cex                                # config exported and committed
```

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

### Visual QA Note — UID=1 Admin Toolbar

When logged in as User 1 (site admin), Drupal renders the Gin/admin toolbar adding ~39px top offset. This can obscure the fixed `.site-nav` bar. Smoke test with `ddev exec curl -sk` (anonymous) or log out before visual QA.

---

## Domain Instructions

Read these before working in their respective areas:

| Area | File |
|---|---|
| `store_fulfillment` module | `.junie/instructions/store-fulfillment.md` |
| PHPUnit tests for store_fulfillment | `.junie/instructions/store-fulfillment-tests.md` |
| Stripe Payment Element / saved cards | `.junie/instructions/commerce-stripe-payment-element.md` |
| `duccinis_1984_olympics` theme | `.junie/instructions/theme-duccinis-1984-olympics.md` |

---

## Custom Slash Commands

| Command | Usage | Purpose |
|---|---|---|
| `/close-issue` | `/close-issue issue=194` | Full flow: fetch issue → branch → implement → test → commit → push → PR → close |
| `/add-checkout-pane` | `/add-checkout-pane className=LoyaltyPoints pluginId=loyalty_points purpose="track loyalty points"` | Scaffold a new Commerce CheckoutPaneBase plugin with tests |
