---
description: "Use when customizing Commerce PaymentInformation pane, Stripe Payment Element integration, saved payment methods, payment AJAX, billing ownership, or the 'Use a different card' UX. Covers the Stripe PE offsite checkout architecture and patterns that differ from inline gateway conventions."
applyTo: ["web/modules/custom/store_fulfillment/**", "web/themes/custom/duccinis_1984_olympics/**"]
---

# Commerce — Stripe Payment Element & Saved Cards Patterns

## Stripe PE Is Offsite — Card Form Is on `review`, Not `order_information`

`stripe_payment_element` extends `OffsitePaymentGatewayBase`. The Stripe card
entry iframe renders on the **`review` step** inside the `StripeReview` checkout
pane, **not** on `order_information`.

Consequences:
- Rebuilding `#edit-payment-information` via AJAX on `order_information` is
  correct and expected. No Stripe iframe appears there — this is by design.
- AJAX rebuilds on `order_information` update the saved-card radio UI and the
  billing address form. The Stripe card entry itself is always deferred to
  `review`.
- `StripeReview` shows the Stripe Payment Element iframe only when
  `$order->get('payment_method')->isEmpty()` — i.e., no saved card is selected.

## "Use a Different Card" UX Flow

When the user selects the gateway option (non-numeric `option_id`, e.g. `stripe`):

1. The radio `change` event fires → `PaymentInformation::ajaxRefresh` rebuilds
   `#edit-payment-information`.
2. `PaymentInformation::submitPaneForm()` detects `SupportsStoredPaymentMethodsInterface`
   and the non-numeric option — it sets `payment_method = NULL` on the order.
3. On the `review` step, `StripeReview` sees `payment_method` is empty and
   renders `showPaymentForm: true` in the Stripe Payment Element config.

**There is no inline Stripe iframe on `order_information`.** Clicking
"+ Use a different card" → continuing to review IS the intended UX.

## AJAX `change` Edge Case — Already-Checked Radio

Drupal AJAX binds to the `change` event on radio inputs. If the Stripe gateway
radio is **already checked** when the user clicks the `+ Use a different card`
label, no `change` fires (the browser skips it when the value doesn't actually
change). `saved-card-fix.js` handles this:

```js
// src/js/saved-card-fix.js
once('saved-card-new-link', '.saved-card-item--new label', context).forEach(
  function (label) {
    label.addEventListener('click', function () {
      const radio = document.getElementById(label.getAttribute('for'));
      if (radio && radio.checked) {
        $(radio).trigger('change'); // Force jQuery trigger for Drupal AJAX
      }
    });
  }
);
```

This behavior is attached from `store_fulfillment_form_alter()` via
`$form['payment_information']['#attached']['library'][] = 'duccinis_1984_olympics/saved-card-fix'`.

**Do not** move this to a global library — it has no effect on pages without
saved cards and relies on the form having `#edit-payment-information`.

## Library Load Order — `drupalSettings` Dependency Is Required

`saved-card-fix.js` MUST declare `core/drupalSettings` as a dependency in
`libraries.yml`:

```yaml
saved-card-fix:
  js:
    src/js/saved-card-fix.js: {}
  dependencies:
    - core/drupal
    - core/drupalSettings   # ← REQUIRED — ensures load after Drupal AJAX binds
    - core/jquery
    - core/once
```

Removing `core/drupalSettings` changes the JS asset aggregation order. Drupal
AJAX bindings may not yet be registered when `saved-card-fix.js` runs, causing
the `$(radio).trigger('change')` call to fire with no AJAX handler attached.
Symptom: clicking "Use a different card" does nothing, no network request.

## Billing Ownership — Delivery vs. Pickup

- **Delivery orders:** `DeliveryAddress` pane owns billing. The billing form
  inside `PaymentInformation` must be removed via `hook_form_alter`:

  ```php
  if ($method === 'delivery' && isset($form['payment_information']['billing_information'])) {
    unset($form['payment_information']['billing_information']);
  }
  ```

- **Pickup orders:** `PaymentInformation` renders its own `billing_information`
  sub-form normally.

`PaymentInformation` must be refreshed on pickup/delivery toggle so the billing
form appears/disappears. Include it in the AJAX multi-replace:

```php
$response->addCommand(new ReplaceCommand('#edit-payment-information', $form['payment_information']));
```

## `#after_build` for Payment Method Radio Metadata

When stamping card data onto individual radio child elements, always use
`#after_build`, never `#process`. See `store-fulfillment.instructions.md` for
the full rule and explanation.

The callback must be a global procedural function (not a method) because
`#after_build` callbacks are serialized in the form cache:

```php
// In hook_form_alter():
$radios['#saved_card_data'] = $cards;
$radios['#after_build'][] = 'store_fulfillment_payment_radios_after_build';

// The callback:
function store_fulfillment_payment_radios_after_build(array $element, FormStateInterface $form_state): array {
  // $element['1'], $element['2'] etc. exist here — Radios::processRadios() ran first.
  foreach ($element['#saved_card_data'] as $id => $card) {
    if (isset($element[$id])) {
      $element[$id]['#card_data'] = $card;
    }
  }
  return $element;
}
```

## Stripe Connect — Payment Method Entities

Stripe card fields on `PaymentMethodInterface` entities use `stripe_*` field
names, not Commerce core's generic `card_*` names. Always check Stripe fields
first:

```php
// ✅ Correct order: Stripe fields first, then generic Commerce fields.
if ($pm->hasField('stripe_card_type') && !$pm->get('stripe_card_type')->isEmpty()) {
  $brand    = $pm->get('stripe_card_type')->getString();
  $last4    = $pm->get('stripe_card_number')->getString();
  $expMonth = $pm->get('stripe_card_exp_month')->getString();
  $expYear  = $pm->get('stripe_card_exp_year')->getString();
}
elseif ($pm->hasField('card_type') && !$pm->get('card_type')->isEmpty()) {
  // Fallback for non-Stripe gateways.
}
```

## First-Time Customers (No Saved Card)

When no stored payment methods exist for the current user, `#options` on the
`payment_method` radios element contains only the gateway option (e.g. `stripe`).
`store_fulfillment_payment_radios_after_build` returns without stamping anything,
so `form-element--radio.html.twig` falls through to the standard Radix radio
component. No saved-card UI renders — the Stripe iframe on the `review` step
handles payment entry directly.

There is **no special case** needed for first-time customers. The standard
Commerce/Stripe PE flow handles it without modification.
