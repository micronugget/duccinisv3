---
description: "Scaffold a new Commerce CheckoutPaneBase plugin for store_fulfillment — generates the plugin class, services.yml entry, and a Kernel test stub."
name: "Add Checkout Pane"
argument-hint: "Pane name (e.g. LoyaltyPoints), plugin ID (e.g. loyalty_points), brief purpose"
agent: "agent"
---

Scaffold a new Commerce `CheckoutPaneBase` plugin for the `store_fulfillment` module.

Follow the patterns in [.github/instructions/store-fulfillment.instructions.md](../instructions/store-fulfillment.instructions.md) and [.github/instructions/store-fulfillment-tests.instructions.md](../instructions/store-fulfillment-tests.instructions.md) exactly.

## Inputs

The user will provide (ask if any are missing):

- **Class name** — PascalCase, e.g. `LoyaltyPoints`
- **Plugin ID** — snake_case, e.g. `loyalty_points`
- **Purpose** — one sentence describing what this pane does
- **Is conditionally shown/hidden via AJAX?** — yes/no (default: no)
- **Services needed** — list any `store_fulfillment.*` or other Drupal services this pane needs

## What to generate

### 1. Plugin class

File: `web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/{ClassName}.php`

Requirements:
- `declare(strict_types=1);` at top
- Annotation: `@CommerceCheckoutPane(id = "{plugin_id}", label = @Translation("…"), default_step = "order_information", wrapper_element = "container")`
- Extend `CheckoutPaneBase`, implement `CheckoutPaneInterface`
- **Use `create()` factory pattern** — never add services to the constructor
- `isVisible()`: return `TRUE` always (even if conditionally shown — visibility is controlled in `buildPaneForm`)
- If conditionally shown/hidden via AJAX:
  - `buildPaneForm()` must wrap output in `#prefix`/`#suffix` with a named `id="..."` wrapper div
  - When content should be hidden, render `$pane_form['placeholder'] = ['#markup' => ''];` and return early — never use `#access = FALSE` or return `[]`
- `validatePaneForm()`: guard with `if (!isset($pane_form['some_key'])) { return; }` before any logic
- `submitPaneForm()`: same guard pattern; store state via `$this->order->setData()`

### 2. Register services (if new services are needed)

Add to `web/modules/custom/store_fulfillment/store_fulfillment.services.yml` only if the pane introduces a new service class. Panes themselves are plugins — they do not need service entries. Only standalone service classes need entries.

### 3. Config export reminder

After implementation, remind the user to register the pane in the checkout flow config and export:

```
ddev drush cex
```

The checkout flow config lives at:
`config/sync/commerce_checkout.commerce_checkout_flow.default.yml`

### 4. Kernel test stub

File: `web/modules/custom/store_fulfillment/tests/src/Kernel/{ClassName}Test.php`

Requirements:
- Extend `CommerceKernelTestBase`
- `@group store_fulfillment`
- `protected static $modules` — include the minimum set (see test instructions)
- `setUp()`: call `$this->installConfig(['store_fulfillment'])`, load the install include and call `store_fulfillment_install()` if the pane touches `delivery_radius` or `store_location` fields
- At least three test methods:
  1. Test that `buildPaneForm()` returns the expected element keys when active
  2. Test that `validatePaneForm()` skips gracefully when the pane rendered a placeholder (guard path)
  3. Test the `submitPaneForm()` data storage (assert `$order->getData(...)` after submit)
- Mock any geocoding or external services — never make real HTTP calls

### 5. Functional test stub

File: `web/modules/custom/store_fulfillment/tests/src/Functional/{ClassName}CheckoutTest.php`

Requirements:
- Extend `CommerceBrowserTestBase`
- `protected $defaultTheme = 'stark';` — always set this
- `@group store_fulfillment`
- `protected static $modules` — include `commerce_cart`, `commerce_checkout`, `commerce_product`, `store_fulfillment`, plus `store_resolver` if the pane calls `store_resolver.current_store`
- `setUp()`: create a minimal product variation + product attached to `$this->store` so checkout is accessible
- At least three test methods:
  1. Test that the pane renders on `GET /checkout/{order_id}/order_information` — assert HTTP 200 and a key element or text string that uniquely identifies this pane
  2. If conditionally shown/hidden via AJAX: test that the AJAX wrapper div (`#<plugin_id>-wrapper`) is present in the initial DOM even when hidden; test that toggling the trigger selector shows/hides the pane content
  3. Test a full form submit path — fill the pane fields, submit, reload the order entity, and assert `$order->getData('{plugin_id}_*')` was persisted correctly

## Output format

Generate the files one at a time in this order:
1. Plugin PHP class
2. Kernel test class
3. Functional test class
4. Any services.yml additions (diff-style, show only the new block)
5. A short checklist of manual steps (config registration, cache clear, test run command)

Do not generate markdown documentation files.
