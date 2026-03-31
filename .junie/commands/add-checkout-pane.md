---
description: Scaffold a new Commerce CheckoutPaneBase plugin for store_fulfillment — class, services.yml entry, Kernel test, Functional test
---

Scaffold a new Commerce `CheckoutPaneBase` plugin for the `store_fulfillment` module.

Follow the patterns in `.junie/instructions/store-fulfillment.md` and `.junie/instructions/store-fulfillment-tests.md` exactly.

## Inputs

Ask if any are missing:

- **Class name** — `$className` (PascalCase, e.g. `LoyaltyPoints`)
- **Plugin ID** — `$pluginId` (snake_case, e.g. `loyalty_points`)
- **Purpose** — `$purpose` (one sentence describing what this pane does)
- **Is conditionally shown/hidden via AJAX?** — `$ajaxConditional` (yes/no, default: no)
- **Services needed** — any `store_fulfillment.*` or other Drupal services this pane needs

## What to generate

### 1. Plugin class

File: `web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/$className.php`

Requirements:
- `declare(strict_types=1);` at top
- Annotation: `@CommerceCheckoutPane(id = "$pluginId", label = @Translation("…"), default_step = "order_information", wrapper_element = "container")`
- Extend `CheckoutPaneBase`, implement `CheckoutPaneInterface`
- **Use `create()` factory pattern** — never add services to the constructor
- `isVisible()`: return `TRUE` always (visibility is controlled in `buildPaneForm`)
- If `$ajaxConditional` is yes:
  - `buildPaneForm()` must wrap output in `#prefix`/`#suffix` with a named `id="..."` wrapper div
  - When content should be hidden: render `$pane_form['placeholder'] = ['#markup' => ''];` and return early — never use `#access = FALSE` or return `[]`
- `validatePaneForm()`: guard with `if (!isset($pane_form['some_key'])) { return; }` before any logic
- `submitPaneForm()`: same guard pattern; store state via `$this->order->setData()`

### 2. Register services (if new services are needed)

Add to `web/modules/custom/store_fulfillment/store_fulfillment.services.yml` only if a new standalone service class is introduced. Pane plugins do not get service entries.

### 3. Kernel test stub

File: `web/modules/custom/store_fulfillment/tests/src/Kernel/${className}Test.php`

Requirements:
- Extend `CommerceKernelTestBase`
- `@group store_fulfillment`
- `protected static $modules` — minimum set per `.junie/instructions/store-fulfillment-tests.md`
- `setUp()`: call `$this->installConfig(['store_fulfillment'])`, load the install include and call `store_fulfillment_install()` if the pane touches `delivery_radius` or `store_location` fields
- At least three test methods:
  1. Test that `buildPaneForm()` returns the expected element keys when active
  2. Test that `validatePaneForm()` skips gracefully when the pane rendered a placeholder (guard path)
  3. Test the `submitPaneForm()` data storage (assert `$order->getData(...)` after submit)
- Mock any geocoding or external services — never make real HTTP calls

### 4. Functional test stub

File: `web/modules/custom/store_fulfillment/tests/src/Functional/${className}CheckoutTest.php`

Requirements:
- Extend `CommerceBrowserTestBase`
- `protected $defaultTheme = 'stark';` — always set this
- `@group store_fulfillment`
- `protected static $modules` — include `commerce_cart`, `commerce_checkout`, `commerce_product`, `store_fulfillment`, plus `store_resolver` if calling `store_resolver.current_store`
- `setUp()`: create a minimal product variation + product attached to `$this->store`
- At least three test methods:
  1. Test that the pane renders on `GET /checkout/{order_id}/order_information` — assert HTTP 200 and a key element
  2. If `$ajaxConditional` is yes: test that the AJAX wrapper div is present in the initial DOM even when hidden; test that toggling the trigger shows/hides the pane content
  3. Test a full form submit path — fill pane fields, submit, reload the order entity, assert `$order->getData(...)` was persisted

## Output format

Generate files in this order:
1. Plugin PHP class
2. Kernel test class
3. Functional test class
4. Any services.yml additions (show only the new block)
5. A short checklist of manual steps (config registration, cache clear, test run command)

Do not generate markdown documentation files.

## Config registration reminder

After implementation, register the pane in the checkout flow config and export:

```bash
# Edit the checkout flow config or use drush config:set, then:
ddev drush cex
ddev drush cr
```

Checkout flow config: `config/sync/commerce_checkout.commerce_checkout_flow.default.yml`

## Verify

```bash
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --filter=$className --colors=never 2>&1 | tail -20
```
