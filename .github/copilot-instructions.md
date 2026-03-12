---
name: Duccinis V4 — Project Instructions
description: Drupal 11 + Commerce restaurant ordering platform — architecture, conventions, and coding standards for AI agents
tags: [instructions, standards, drupal, commerce]
version: 2.0.0
---

# Duccinis V4 — Development Guidelines

**Duccinis V4** is a **fresh DrupalCMS 2 install** — the migration *destination* for Duccinis, a DC-area pizza restaurant chain. It is a complete rebuild on the DrupalCMS 2 scaffold (not an upgrade of V3). It supports multi-store pickup and delivery ordering with Stripe payment and real-time radius validation.

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

V4 is a **new DrupalCMS 2 site** — it was not upgraded from V3, it was built fresh. V3 is kept running as a read-only reference because it contains:

- Proven implementations of `store_fulfillment`, `store_resolver`, `back_to_cart_button`, and `commerce_reorder` that V4 has already ported or is in the process of porting.
- The `duccinis_1984_olympics` theme (Radix 6 / Bootstrap 5 / webpack.mix.js) that V4 shares.
- Historical config exports, migration runbooks, and issue documentation useful for tracing why specific decisions were made.

### Branching Strategy (issues #94–141)

> **⚠️ Non-negotiable branching rule for all migration issues.**

`migration_branch` lives in the **V4 codebase** (`/home/lee/ams_projects/2026/week-10/v2/duccinisv4/`). All issue work for the migration epic is committed here — V3 remains read-only source-of-reference only.

All feature branches for issues **#94 through #141** (the V3→V4 migration epic) **must target `migration_branch`**, not `master`.

| Rule | Detail |
|------|--------|
| **Active codebase** | V4 — `/home/lee/ams_projects/2026/week-10/v2/duccinisv4/` |
| **`migration_branch` location** | V4 repo (`git remote: micronugget/duccinisv3.git`) — **not** the V3 local directory |
| **Base branch** | Always branch off `migration_branch`: `git checkout migration_branch && git checkout -b issue/$N-<slug>` |
| **Merge target** | PRs and manual merges go into `migration_branch`, **never into `master` directly** |
| **`migration_branch` → `master`** | Only after the full migration epic is complete and reviewed |
| **Branch naming** | `issue/$N-<slug>` where `$N` is the issue number and `<slug>` is a short kebab-case title |

When the close-issue prompt creates a branch, use:
```bash
# Run this inside /home/lee/ams_projects/2026/week-10/v2/duccinisv4/
git checkout migration_branch && git checkout -b issue/$ISSUE-<slug>
```

Push to `migration_branch`, not `master`:
```bash
git push origin issue/$ISSUE-<slug>
# PR base: migration_branch
```

---

### Per-Issue Checklist (start of every issue)

Before writing any code for an issue:

1. **Read the issue** in `ISSUES.md` to understand the scope and acceptance criteria.
2. **Check V3** — locate the equivalent file(s) in V3 (`/home/lee/ams_projects/2025/week-43/v1/duccinisV3/`) to understand the existing implementation.
3. **Diff V3 vs V4** — identify what is missing, changed, or needs porting.
4. **Work only in V4** (`/home/lee/ams_projects/2026/week-10/v2/duccinisv4/`).
5. **Do not alter V3** under any circumstance.
6. **Branch off `migration_branch`** (see Branching Strategy above) — never off `master`.

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
| `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` | Compile theme assets (Radix 6 / webpack.mix.js) |
| `ddev drush uli --uid=3 --uri=https://duccinisv4.ddev.site` | Login link for test user Geena (uid=3, has saved Stripe cards) |

**DDEV site URL:** `https://duccinisv4.ddev.site` | PHP 8.3 | Drupal 11.2 | MariaDB 10.11

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
- Run `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` from the project root (root `package.json` has no `dev` script; must cd into theme)
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
- Test class location: `Kernel/` for unit-ish + service tests; `Functional/` for browser/form tests.

### Pre-commit Gates

- `ddev composer check-platform-reqs --no-dev` — verify no package in `composer.lock` requires a PHP version beyond 8.4
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
