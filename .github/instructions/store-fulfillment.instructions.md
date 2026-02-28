---
description: "Use when writing or modifying code in store_fulfillment — Commerce checkout panes, shipping plugins, delivery radius validation, AJAX multi-pane patterns, event subscribers, and payment radios. Covers patterns that differ from standard Drupal/Commerce conventions."
applyTo: "web/modules/custom/store_fulfillment/**"
---

# Store Fulfillment Module — Coding Patterns

## Checkout Pane Service Injection

Commerce checkout pane plugins extend `CheckoutPaneBase`. **Do not use constructor injection** — the parent constructor signature is fixed by the plugin system. Always use the `create()` factory pattern:

```php
public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
  $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
  $instance->deliveryRadiusValidator = $container->get('store_fulfillment.delivery_radius_validator');
  $instance->storeResolver           = $container->get('store_resolver.current_store');
  return $instance;
}
```

Standalone services (`OrderValidator`, `DeliveryRadiusValidator`, etc.) **do** use constructor injection normally.

## Store Resolution — Always Use Fallback

The cookie-based store resolver fails without a browser session (Drush, tests, CLI). Always fall back to the order's store:

```php
$store = $this->storeResolver->getCurrentStore() ?? $this->order->getStore();
```

## `isVisible()` — Conditionally Shown Panes Must Return TRUE

Panes shown/hidden via AJAX must always return `TRUE` from `isVisible()`. Returning `FALSE` or making the pane inaccessible causes Commerce's `CheckoutFlowWithPanesBase::buildForm()` to call `Element::getVisibleChildren()` which removes the wrapper `<div>` from the DOM entirely, breaking AJAX targeting.

Instead, control visibility inside `buildPaneForm()`:

```php
public function isVisible(): bool {
  return TRUE; // Always in DOM for AJAX to work.
}

public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
  $pane_form['#prefix'] = '<div id="delivery-address-wrapper" class="delivery-expand' . ($this->isDeliverySelected($form_state) ? ' open' : '') . '">';
  $pane_form['#suffix'] = '</div>';

  if (!$this->isDeliverySelected($form_state)) {
    // Empty placeholder keeps the wrapper in the DOM.
    $pane_form['placeholder'] = ['#markup' => ''];
    return $pane_form;
  }
  // ... full form build
}
```

## Delivery State Detection — Check Form State First

Always check `$form_state` before `$order->getData()` so AJAX rebuilds reflect the current radio selection before it's persisted:

```php
protected function isDeliverySelected(?FormStateInterface $form_state = NULL): bool {
  if ($form_state) {
    $method = $form_state->getValue(['fulfillment_time', 'fulfillment_method']);
    if (!$method) {
      // Fallback for partial validation (e.g. #limit_validation_errors buttons).
      $method = $form_state->getUserInput()['fulfillment_time']['fulfillment_method'] ?? NULL;
    }
    if ($method) {
      return $method === 'delivery';
    }
  }
  return $this->order->getData('fulfillment_method') === 'delivery';
}
```

## AJAX — Multi-Pane Refresh Pattern

When a single AJAX callback must update more than one section, return an explicit `AjaxResponse` with individual `ReplaceCommand` calls. Do **not** rely on Commerce's default AJAX (it only replaces the triggering pane's wrapper):

```php
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

public function ajaxRefreshPane(array $form, FormStateInterface $form_state): AjaxResponse {
  $response = new AjaxResponse();
  $response->addCommand(new ReplaceCommand('#fulfillment-time-wrapper', $form['fulfillment_time']));
  $response->addCommand(new ReplaceCommand('#delivery-address-wrapper', $form['delivery_address']));
  $response->addCommand(new ReplaceCommand('#edit-payment-information', $form['payment_information']));
  return $response;
}
```

## validate/submit Guard for Hidden Panes

When a pane may have rendered a placeholder (because delivery wasn't selected at build time), guard both `validatePaneForm` and `submitPaneForm` by checking the render array, **not** `isDeliverySelected()`:

```php
public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
  // Check render array first — form_state may disagree with build-time state.
  if (!isset($pane_form['profile']['#inline_form'])) {
    return;
  }
  if (!$this->isDeliverySelected($form_state)) {
    return;
  }
  // ... validation logic
}
```

## Order Data Storage

Fulfillment state is stored as order data, not as field values:

```php
// Write
$order->setData('fulfillment_method', 'delivery');   // 'pickup' | 'delivery'
$order->setData('fulfillment_type', 'scheduled');    // 'asap' | 'scheduled'
$order->setData('scheduled_time', '2026-02-25 18:30:00'); // Y-m-d H:i:s
$order->setData('delivery_address_profile', $profile->id()); // int

// Clear stale data when switching modes
$order->unsetData('scheduled_time');

// Read
$method = $order->getData('fulfillment_method');
```

## Delivery Address Resolution Chain

Both `FulfillmentTime::resolveCustomerAddress()` and `OrderPlacementDeliveryRadiusValidator::resolveDeliveryAddress()` must follow *the same* four-step fallback:

1. `$order->getData('delivery_address_profile')` → load profile entity
2. Shipping profile from `$order->get('shipments')` (first shipment)
3. `$order->getBillingProfile()`
4. Customer's default profile (`profile` storage, `uid`, `isDefault = 1`)

Always keep these two methods in sync.

## Validation Result Shape

All validation methods return a consistent array — never a bare boolean:

```php
// DeliveryRadiusValidator::validateDeliveryAddress()
return ['valid' => bool, 'message' => string, 'distance' => float|null];

// OrderValidator::validateFulfillmentTime()
return ['valid' => bool, 'message' => string];
```

Check `$result['valid']` before using `$result['message']`.

## Event Subscribers — Order Placement

Subscribe to `commerce_order.place.pre_transition`. Block placement by throwing `\InvalidArgumentException` (Commerce's state machine catches this and cancels the transition):

```php
public static function getSubscribedEvents(): array {
  return [
    'commerce_order.place.pre_transition' => ['onOrderPlace', -100],
  ];
}

public function onOrderPlace(WorkflowTransitionEvent $event): void {
  $order = $event->getEntity();
  if ($order->getData('fulfillment_method') !== 'delivery') {
    return;
  }
  // ... validate, then:
  throw new \InvalidArgumentException($validation_result['message']);
}
```

Priority `-100` ensures placement validators run **after** Commerce's own placement logic finishes.

## `#after_build` vs `#process` on Radios

**Never** add to `#process` on a pre-existing radios element. At `hook_form_alter` time, `#process` doesn't exist on the element, so `$element['#process'][] = ...` creates a brand-new array overwriting Drupal's defaults via `$element += $element_info`. This drops `Radios::processRadios()` and produces zero child radio elements.

Instead, always use `#after_build`, which runs after all `#process` callbacks:

```php
// ✅ Correct
$radios['#after_build'][] = 'store_fulfillment_payment_radios_after_build';

// ❌ Never do this
$radios['#process'][] = 'store_fulfillment_payment_radios_process';
```

## Logger Channel

Always log to the `'store_fulfillment'` channel:

```php
$this->loggerFactory->get('store_fulfillment')->error('...');
```

## Config Keys

Module settings live at `store_fulfillment.settings`:

| Key | Default | Description |
|---|---|---|
| `minimum_advance_notice` | 30 | Minutes ahead required for scheduled orders |
| `maximum_scheduling_window` | 14 | Max days ahead orders can be scheduled |
| `time_slot_interval` | 15 | Minutes between available time slots |

```php
$config = $this->configFactory->get('store_fulfillment.settings');
$min_advance = $config->get('minimum_advance_notice') ?? 30;
```

## Services Reference

| ID | Class | Key Dependency |
|---|---|---|
| `store_fulfillment.delivery_radius_calculator` | `DeliveryRadiusCalculator` | `@geocoder` |
| `store_fulfillment.delivery_radius_validator` | `DeliveryRadiusValidator` | `@store_fulfillment.delivery_radius_calculator` |
| `store_fulfillment.order_validator` | `OrderValidator` | `@store_resolver.hours_validator` |
| `store_fulfillment.order_placement_validator` | `OrderPlacementValidator` | event_subscriber tag |
| `store_fulfillment.order_placement_delivery_radius_validator` | `OrderPlacementDeliveryRadiusValidator` | event_subscriber tag |
