# Analytics — Checkout Funnel Events

Duccinis V3 pushes custom events to `window.dataLayer` (GTM data layer) to
track back-navigation behaviour in the checkout funnel.

---

## Event: `checkout_step_back`

Fired whenever a user clicks a **done-step back-link** in the checkout progress
bar (Cart → Order Details → Review).

### Payload

| Key         | Type   | Example values                                         | Notes                                        |
|-------------|--------|--------------------------------------------------------|----------------------------------------------|
| `event`     | string | `"checkout_step_back"`                                 | GTM trigger key                              |
| `from_step` | string | `"review"`, `"order_information"`                      | The step the user is currently on            |
| `to_step`   | string | `"cart"`, `"order_information"`, `"review"`            | The step the user clicked back to            |
| `order_id`  | string | `"27"`                                                 | Commerce order entity ID (string-cast)       |

### Example push

```js
window.dataLayer.push({
  event:     'checkout_step_back',
  from_step: 'review',
  to_step:   'order_information',
  order_id:  '27',
});
```

### Cart step payload example

```js
window.dataLayer.push({
  event:     'checkout_step_back',
  from_step: 'order_information',
  to_step:   'cart',
  order_id:  '27',
});
```

---

## Implementation

- **Data attributes** are emitted server-side by
  `CheckoutProgressBarBlock::buildSteps()` in
  `web/modules/custom/store_fulfillment/src/Plugin/Block/CheckoutProgressBarBlock.php`
  and rendered by the SDC component
  `web/themes/custom/duccinis_1984_olympics/components/checkout-progress/checkout-progress.twig`.
- **JS behavior** lives in
  `web/themes/custom/duccinis_1984_olympics/src/js/checkout-progress-analytics.js`
  (library: `duccinis_1984_olympics/checkout-progress-analytics`).
- Library is attached via `CheckoutProgressBarBlock::build()` — **only** on pages
  where the block renders — so there is no global JS bloat.

---

## GTM Setup

### Trigger — `checkout_step_back`

1. In GTM → **Triggers** → New.
2. Type: **Custom Event**.
3. Event name: `checkout_step_back` (exact match).
4. Name: `Trigger — Checkout Step Back`.
5. Save.

### GA4 Event Tag

1. In GTM → **Tags** → New.
2. Tag Type: **Google Analytics: GA4 Event**.
3. Configuration tag: your GA4 Configuration tag.
4. Event name: `checkout_step_back`.
5. Event parameters:

| Parameter name | Value (GTM variable)       |
|----------------|----------------------------|
| `from_step`    | `{{DLV - from_step}}`      |
| `to_step`      | `{{DLV - to_step}}`        |
| `order_id`     | `{{DLV - order_id}}`       |

6. Trigger: `Trigger — Checkout Step Back`.
7. Name: `GA4 — Checkout Step Back`.
8. Save and publish.

### Data Layer Variables (GTM)

Create three **Data Layer Variable** variables:

| GTM variable name   | Data Layer Variable Name | Default value |
|---------------------|--------------------------|---------------|
| `DLV - from_step`   | `from_step`              | `(undefined)` |
| `DLV - to_step`     | `to_step`                | `(undefined)` |
| `DLV - order_id`    | `order_id`               | `(undefined)` |

---

## GA4 Exploration / Reporting

In GA4 → **Explore** → Funnel Exploration:
- Use the `checkout_step_back` event as a step or segment condition.
- Filter by `to_step = order_information` to measure Review → Order Details drop.
- Filter by `to_step = cart` to measure checkout abandonment via the cart link.

---

## Future Events (Backlog)

| Event name              | Description                                    |
|-------------------------|------------------------------------------------|
| `checkout_step_view`    | Page-level: which step is the user on?         |
| `checkout_abandoned`    | Session-scoped: user left without completing   |
| `fulfillment_selected`  | Pickup vs Delivery toggle (FulfillmentTime pane)|
