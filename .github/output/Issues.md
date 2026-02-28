# Checkout Redesign — GitHub Issues

> **Reference mockup:** `.github/output/checkout-order-information-mockup.html`
>
> All issues target the `order_information` step of the Commerce checkout flow.
> Theme: `web/themes/custom/duccinis_1984_olympics`
> Module: `web/modules/custom/store_fulfillment`

---

## Epic 1: Fulfillment Selector — Pill Toggle + Inline Context

Replace the radio-button fieldsets with a compact pill-toggle UI and contextual store/address display.

### Issue 1.1 — Pill-toggle component for Pickup / Delivery

**Labels:** `frontend`, `checkout`, `UX`

Replace the current `<fieldset>` with two radio inputs and verbose legend ("How would you like to receive your order?") with a pill-toggle component.

**Acceptance Criteria:**
- [ ] Pill-toggle renders two options: 🏪 Pickup, 🚗 Delivery
- [ ] Active pill has white background, subtle shadow, dark text; inactive is muted
- [ ] Toggle fires existing AJAX callback (`ajaxRefreshPane`) to swap checkout pane states
- [ ] Keyboard accessible (arrow keys, tab, enter/space)
- [ ] SCSS in `scss/components/_pill-toggle.scss`, reusable for other toggles

**Files:**
- `FulfillmentTime.php` → `buildPaneForm()` render array changes
- `scss/components/_pill-toggle.scss` (new)
- `templates/` Twig override if needed

---

### Issue 1.2 — Inline store address + open status (Pickup state)

**Labels:** `frontend`, `checkout`, `UX`

When Pickup is selected, show the resolved store address and open/closed status below the pill toggle.

**Acceptance Criteria:**
- [ ] Card shows 📍 icon, store address, open/closed dot indicator, "Change" link
- [ ] Store data sourced from `store_resolver.current_store` (falling back to order store)
- [ ] "Change" link triggers store-switcher (existing cookie-based resolver UX — or modal)
- [ ] Hidden when Delivery is selected
- [ ] Handles missing store hours gracefully (hides open/closed dot)

**Files:**
- `FulfillmentTime.php` → add store address render element
- `scss/components/_fulfillment-address.scss` (new)

---

### Issue 1.3 — ASAP / Schedule pill toggle (replaces "When" radios)

**Labels:** `frontend`, `checkout`, `UX`

Replace the "When would you like your order?" fieldset with a compact inline `When?` label + small pill toggle (ASAP | Schedule).

**Acceptance Criteria:**
- [ ] Renders inline below the fulfillment method toggle
- [ ] ASAP selected by default
- [ ] Selecting "Schedule" reveals the time-slot picker (Issue 1.4) with smooth expand
- [ ] Selecting "ASAP" hides the time-slot picker
- [ ] Re-uses the pill-toggle SCSS component from Issue 1.1 (smaller variant)

**Files:**
- `FulfillmentTime.php` → `buildPaneForm()` changes
- SCSS: small variant of pill-toggle

---

### Issue 1.4 — Time-slot chip grid grouped by day

**Labels:** `frontend`, `checkout`, `UX`, `performance`

Replace the flat `<select>` with 200+ options with a chip-grid time picker grouped by day.

**Acceptance Criteria:**
- [ ] Time slots grouped under day headings ("Today — Wed, Feb 18", "Tomorrow — Thu, Feb 19")
- [ ] Each slot is a chip (small button/label) in a responsive CSS Grid
- [ ] Selected chip gets brand-blue background, white text
- [ ] Grid auto-fills columns (`minmax(90px, 1fr)`)
- [ ] Expand/collapse animation (max-height + opacity transition)
- [ ] Only slots within store operating hours are shown
- [ ] Mobile: chips are large enough for thumb tapping (min 44px touch target)
- [ ] Falls back gracefully if no slots are available (message: "No time slots available")

**Files:**
- `FulfillmentTime.php` → restructure time slot render array
- `scss/components/_time-slots.scss` (new)
- `js/time-slot-toggle.js` if JS needed beyond CSS `:has()` and AJAX

---

## Epic 2: Delivery Address — Smooth Expand + Billing Consolidation

### Issue 2.1 — Delivery address expand/collapse transition

**Labels:** `frontend`, `checkout`, `UX`

When toggling from Pickup → Delivery, the delivery address section should appear with a smooth expand animation (not a hard show/hide).

**Acceptance Criteria:**
- [ ] Uses CSS transition (`max-height`, `opacity`) for expand/collapse
- [ ] The `#delivery-address-wrapper` div is always in the DOM (for AJAX), hidden via CSS when Pickup selected
- [ ] Transition duration ~300ms, ease timing
- [ ] No layout jump on expand (content height measured or max-height generous)
- [ ] Section label reads "Deliver to" (small, muted, uppercase)

**Files:**
- `DeliveryAddress.php` → wrapper markup/classes
- `FulfillmentTime.php` → AJAX callback adds/removes `.open` class
- `scss/components/_delivery-expand.scss` (new)

---

### Issue 2.2 — "Same address for billing" checkbox UX

**Labels:** `frontend`, `checkout`, `UX`

Refine the existing "My billing address is the same as my delivery address" checkbox.

**Acceptance Criteria:**
- [ ] Simplified label: "Same address for billing"
- [ ] Checkbox is checked by default
- [ ] Unchecking reveals a separate billing address form below (existing behavior)
- [ ] Checkbox styled with `accent-color` matching brand blue
- [ ] Works correctly with Stripe Payment Element's `collect_billing_information` setting

**Files:**
- `DeliveryAddress.php` → label text, default state
- SCSS: checkbox styling

---

## Epic 3: Payment Section — Clean Card UI

### Issue 3.1 — Remove redundant payment headings

**Labels:** `frontend`, `checkout`, `UX`

Remove the "Payment information" pane heading and "Payment method" sub-label. The card UI is self-explanatory.

**Acceptance Criteria:**
- [ ] No `<legend>`, `<h3>`, or label text above the payment section
- [ ] The section is wrapped in a card (`.checkout-section`) matching other sections
- [ ] Stripe Payment Element iframe renders cleanly inside the card

**Files:**
- Twig template override for `commerce-checkout-pane--payment-information.html.twig`
- Or: `store_fulfillment.module` `hook_form_alter` to unset heading elements

---

### Issue 3.2 — Saved card display + "Use a different card" link

**Labels:** `frontend`, `checkout`, `UX`

When a customer has a saved payment method, show it as a selected card row with brand icon, masked number, and expiry.

**Acceptance Criteria:**
- [ ] Saved card row: brand badge (Visa/MC/Amex), `•••• 4242`, expiry, checkmark
- [ ] Blue border + light blue background when selected
- [ ] "+ Use a different card" link below, which reveals the Stripe Payment Element
- [ ] For first-time customers (no saved card), show Stripe Payment Element directly
- [ ] Relies on Commerce Payment's stored payment method entities

**Files:**
- Custom Twig template or `hook_form_alter` for payment pane
- `scss/components/_saved-card.scss` (new)
- May need `PaymentInformation` pane extension or preprocess

---

## Epic 4: Order Summary Sidebar

### Issue 4.1 — Sticky order summary card

**Labels:** `frontend`, `checkout`, `UX`

Replace the default Commerce order summary with a styled sidebar card that sticks on scroll (desktop).

**Acceptance Criteria:**
- [ ] Sidebar is 340px wide on desktop, full-width stacked on mobile
- [ ] Uses `position: sticky; top: 1.5rem` on `md+` breakpoints
- [ ] Header: "Your Order" (Bebas Neue) + pink item-count badge
- [ ] Line items: quantity badge, item name, price
- [ ] Totals section: subtotal, delivery fee (if delivery), tax, grand total (bold, separated)

**Files:**
- Twig override for `commerce-checkout-order-summary.html.twig`
- `scss/components/_order-summary.scss` (new)

---

### Issue 4.2 — Coupon code field in sidebar

**Labels:** `frontend`, `checkout`, `UX`

Add an inline coupon/promotion code field inside the order summary card.

**Acceptance Criteria:**
- [ ] Input + "Apply" button, inline between items and totals
- [ ] AJAX submit to apply promotion to order
- [ ] Show applied coupon with remove link
- [ ] Error state for invalid codes
- [ ] Requires `commerce_promotion` module configuration

**Files:**
- Twig template or `hook_form_alter` to add coupon form to summary
- `scss/components/_coupon-row.scss` (new)
- May need new AJAX route or leverage existing Commerce promotion form

---

### Issue 4.3 — Single "Continue to Review" CTA

**Labels:** `frontend`, `checkout`, `UX`

Replace the default Drupal form submit button with a styled CTA inside the sidebar card.

**Acceptance Criteria:**
- [ ] Full-width button: "Continue to Review →"
- [ ] Bebas Neue uppercase typography, pink background (`#D62976`), white text
- [ ] Hover: darker pink; Active: slight scale-down
- [ ] This is the form's actual submit button (not a JS-only button)
- [ ] On mobile, button remains inside the summary card (no separate floating CTA)

**Files:**
- Twig template for summary or form alter to move/style actions
- `scss/components/_btn-continue.scss` (new)

---

## Epic 5: Checkout Layout & Chrome

### Issue 5.1 — Two-column grid layout (main + sidebar)

**Labels:** `frontend`, `checkout`, `layout`

Restructure the checkout page into a responsive two-column grid.

**Acceptance Criteria:**
- [ ] Mobile: single column, main content above sidebar
- [ ] Desktop (≥768px): `1fr 340px` grid
- [ ] Max-width 1120px, centered
- [ ] Consistent 1.5rem–2rem gap between sections

**Files:**
- `templates/commerce-checkout-form--default.html.twig` (new override)
- `scss/layout/_checkout-layout.scss` (new)

---

### Issue 5.2 — Progress bar (Cart → Order Details → Review → Complete)

**Labels:** `frontend`, `checkout`, `UX`

Add a horizontal step indicator at the top of the checkout page.

**Acceptance Criteria:**
- [ ] 4 steps: Cart, Order Details, Review, Complete
- [ ] Current step: dark text + pink underline bar
- [ ] Completed steps: green text + green underline
- [ ] Future steps: muted text + gray underline
- [ ] Steps are not clickable (no back-navigation via progress bar)
- [ ] Step state driven by current checkout step (`order_information`, `review`, `complete`)

**Files:**
- `templates/commerce-checkout-form--default.html.twig` → progress bar markup
- `scss/components/_checkout-progress.scss` (new)
- Preprocess function in `.theme` file to pass current step indicator

---

### Issue 5.3 — Remove all redundant pane heading wrappers

**Labels:** `frontend`, `checkout`, `cleanup`

Audit and remove all auto-generated `<legend>`, `<h3>`, `<details>` wrappers that Commerce/Drupal adds around checkout panes.

**Acceptance Criteria:**
- [ ] No visible "Fulfillment Time", "Delivery Address", "Payment information" headings
- [ ] Panes render as clean card sections with no fieldset chrome
- [ ] Achieved via Twig overrides or `hook_form_alter` (prefer Twig for theme layer)
- [ ] Section context provided by inline labels (e.g., "Deliver to", "When?") instead

**Files:**
- Twig template overrides for each pane
- `store_fulfillment.module` → form_alter cleanup if needed

---

### Issue 5.4 — Checkout header: title + "Back to cart" link

**Labels:** `frontend`, `checkout`, `UX`

Add a clean header row with "Checkout" title and "← Back to cart" link.

**Acceptance Criteria:**
- [ ] "Checkout" in Bebas Neue, uppercase, 1.75rem
- [ ] "← Back to cart" link aligned right, blue, 0.875rem
- [ ] Bottom border: 2px solid dark
- [ ] Link goes to `/cart`

**Files:**
- `templates/commerce-checkout-form--default.html.twig`
- SCSS in layout file

---

## Epic 6: Typography, Tokens & SCSS Architecture

### Issue 6.1 — CSS custom properties (design tokens)

**Labels:** `frontend`, `theme`, `architecture`

Establish a design-token system using CSS custom properties for the checkout (and eventually site-wide).

**Acceptance Criteria:**
- [ ] Variables defined in `:root` or theme SCSS variables file
- [ ] Tokens: `--duccini-blue`, `--duccini-pink`, `--duccini-gold`, `--duccini-orange`, `--duccini-red`, `--duccini-dark`, `--duccini-border`, `--duccini-bg`, `--radius`, `--radius-sm`, `--radius-pill`
- [ ] All checkout SCSS files reference tokens (no hardcoded colors)
- [ ] Documented in `scss/_variables.scss`

**Files:**
- `scss/_variables.scss` (update or create)

---

### Issue 6.2 — Add DM Sans web font

**Labels:** `frontend`, `theme`, `performance`

Add DM Sans (400, 500, 600, 700) as the body/UI font for checkout.

**Acceptance Criteria:**
- [ ] Self-hosted or Google Fonts with `font-display: swap`
- [ ] Added to `*.libraries.yml` for the checkout page (or globally)
- [ ] Falls back to `system-ui, -apple-system, sans-serif`
- [ ] Minimized CLS — preconnect hint for Google Fonts if external

**Files:**
- `duccinis_1984_olympics.libraries.yml`
- `duccinis_1984_olympics.info.yml` (if library attachment needed)

---

## Epic 7: Accessibility & QA

### Issue 7.1 — WCAG 2.1 AA audit of checkout redesign

**Labels:** `accessibility`, `QA`

Audit all checkout changes for accessibility compliance.

**Acceptance Criteria:**
- [ ] All interactive elements keyboard navigable
- [ ] Pill toggles have proper `role="radiogroup"` and `aria-label`
- [ ] Time-slot chips have `aria-checked` state
- [ ] Expand/collapse uses `aria-expanded` on trigger
- [ ] Color contrast meets 4.5:1 minimum for text, 3:1 for large text/UI
- [ ] Screen reader announces fulfillment method, time selection, and delivery address changes
- [ ] Focus management correct after AJAX pane swaps

**Files:**
- All template and SCSS files from Epics 1–6
- `js/` files for aria-attribute management

---

### Issue 7.2 — Cross-browser and responsive QA

**Labels:** `QA`, `testing`

Test the checkout redesign across target browsers and devices.

**Acceptance Criteria:**
- [ ] Chrome, Firefox, Safari, Edge (latest 2 versions)
- [ ] iOS Safari (iPhone 13+), Chrome Android
- [ ] Breakpoints: 375px, 480px, 768px, 1024px, 1440px
- [ ] Touch/swipe on time-slot chips
- [ ] Sticky sidebar doesn't overlap content on tablet
- [ ] `font-display: swap` verified (no FOUT regression)

---

## Summary Table

| Epic | Issues | Priority |
|------|--------|----------|
| 1. Fulfillment Selector | 1.1, 1.2, 1.3, 1.4 | **High** |
| 2. Delivery Address | 2.1, 2.2 | **High** |
| 3. Payment Section | 3.1, 3.2 | **Medium** |
| 4. Order Summary Sidebar | 4.1, 4.2, 4.3 | **Medium** |
| 5. Checkout Layout & Chrome | 5.1, 5.2, 5.3, 5.4 | **High** |
| 6. Typography & SCSS | 6.1, 6.2 | **Medium** |
| 7. Accessibility & QA | 7.1, 7.2 | **High** |

**Recommended implementation order:** Epic 6 → Epic 5 → Epic 1 → Epic 2 → Epic 3 → Epic 4 → Epic 7
