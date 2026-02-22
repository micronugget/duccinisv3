# [EPIC] Saved Payment Cards & AJAX Payment Method Switching — Issue #26

**Status:** In Progress — AJAX now confirmed working (Feb 21, 2026)
**Labels:** `epic`, `priority:high`, `feature`, `commerce`, `checkout`
**Story Points:** 21
**Branch:** (current working branch)

---

## Summary

Implement saved credit card display and "Use a different card" functionality in the Drupal Commerce checkout payment pane. Users with stored Stripe payment methods should see their saved cards with brand/last4/expiry, select between them, and switch to a new card entry flow — all without a full page reload.

### Current State (Feb 21, 2026)

- ✅ Saved cards display with brand badge (VISA), masked number (•••• 4242), and expiry
- ✅ Card selection via styled radio labels with CSS `:checked` state
- ✅ "+ Use a different card" triggers AJAX and renders billing address selector
- ✅ AJAX diagnostic panel confirms: drupalSettings, DOM elements, `data-once`, `Drupal.ajax.instances`, label/input association, CSS — all passing
- ⚠️ Billing address dropdown shows 7 duplicate "22 M Street NE" entries (profile cleanup needed)
- ⚠️ End-to-end order completion not yet tested (saved card → review → payment)
- ⚠️ End-to-end order completion not yet tested (new card → review → Stripe Elements → payment)
- ⚠️ Diagnostic JS still attached (must remove before merge)
- ⚠️ Mobile/responsive testing not done
- ⚠️ Accessibility audit not done

---

## Sub-Issues

---

### #26.1 — Clean Up Duplicate Billing Profiles

**Assignee:** `@drupal-developer` (`developer_drupal.agent.md`)
**Story Points:** 2
**Priority:** High
**Labels:** `bug`, `data-cleanup`, `commerce`

#### Description

The billing address selector rendered after clicking "+ Use a different card" shows 7 entries all reading "22 M Street NE" for user Geena (uid 3). These are duplicate `customer` profile entities in the `profile` table. The duplicates cause confusion and should be deduplicated.

#### Acceptance Criteria

- [ ] Identify all duplicate `customer` profiles for uid 3 (and other users if affected)
- [ ] Consolidate to one canonical billing profile per unique address per user
- [ ] Update any `commerce_order.billing_profile` references to point to the surviving profile
- [ ] Update any `commerce_payment_method.billing_profile` references
- [ ] Verify the address dropdown shows only distinct addresses after cleanup
- [ ] Consider adding a unique constraint or dedup hook to prevent future duplicates

#### Files to Investigate

- `profile` entity storage — query for uid 3, bundle `customer`
- `commerce_order` table — `billing_profile__target_id` column
- `commerce_payment_method` table — `billing_profile__target_id` column

#### Handoff

→ **On completion:** Hand off to `@tester` for verification (#26.6)

---

### #26.2 — End-to-End: Complete Order with Saved Card

**Assignee:** `@tester` (`tester.agent.md`)
**Story Points:** 3
**Priority:** High
**Labels:** `testing`, `e2e`, `commerce`, `stripe`

#### Description

Verify the full checkout flow when a user selects an existing saved card (not clicking "+ Use a different card"). The flow is:

1. `/checkout/{order_id}/order_information` — saved card is pre-selected (radio checked)
2. Click "Continue to review"
3. `/checkout/{order_id}/review` — `StripeReview` pane checks `$order->get('payment_method')->isEmpty()`
4. Since a saved payment method IS set, Stripe should charge that method directly (no Stripe Elements iframe)
5. Order completes successfully

#### Acceptance Criteria

- [ ] Log in as Geena (uid 3) — `ddev drush uli --uid=3 --uri=https://duccinisv3.ddev.site`
- [ ] Add an item to cart and proceed to checkout
- [ ] On `order_information` step: confirm a saved Visa •••• 4242 is pre-selected
- [ ] Click "Continue to review" — no errors
- [ ] On `review` step: confirm order summary is correct and no Stripe iframe appears
- [ ] Complete order — confirm order status transitions correctly
- [ ] Verify Stripe test dashboard shows the payment against the saved PaymentMethod
- [ ] Check Drupal order admin (`/admin/commerce/orders`) — order has correct payment method reference

#### Test Environment

- DDEV local: `https://duccinisv3.ddev.site`
- Stripe test mode (test keys)
- Test card: Visa 4242 4242 4242 4242

#### Handoff

→ **On PASS:** Mark complete, update this file
→ **On FAIL:** File bug back to `@drupal-developer` with reproduction steps, assign #26.2-BUG

---

### #26.3 — End-to-End: Complete Order with New Card

**Assignee:** `@tester` (`tester.agent.md`)
**Story Points:** 3
**Priority:** High
**Labels:** `testing`, `e2e`, `commerce`, `stripe`

#### Description

Verify the full checkout flow when a user clicks "+ Use a different card". The flow is:

1. `/checkout/{order_id}/order_information` — click "+ Use a different card"
2. AJAX refreshes payment pane — billing address selector appears
3. Select or enter a billing address
4. Click "Continue to review"
5. `/checkout/{order_id}/review` — `StripeReview` pane should detect `payment_method` is empty
6. `showPaymentForm` should be `true` → Stripe Payment Element iframe renders
7. User enters new card details in Stripe iframe
8. Complete order

#### Acceptance Criteria

- [ ] "+ Use a different card" click triggers AJAX (spinner appears, pane refreshes)
- [ ] Billing address selector renders with valid address(es)
- [ ] Selecting an existing address works (profile renders below dropdown)
- [ ] "+ Enter a new address" option shows an inline address form
- [ ] "Continue to review" proceeds without error
- [ ] Review step shows Stripe Payment Element iframe
- [ ] Entering test card `4242 4242 4242 4242` with future expiry and any CVC succeeds
- [ ] Order completes, new payment method is saved to customer profile
- [ ] Stripe test dashboard shows the new PaymentIntent

#### Dependencies

- #26.1 (duplicate profiles cleaned up for cleaner dropdown)

#### Handoff

→ **On PASS:** Mark complete, hand off to `@themer` for polish (#26.4)
→ **On FAIL:** File bug to `@drupal-developer` with steps + screenshots

---

### #26.4 — UI Polish: Saved Card Component & Payment Pane

**Assignee:** `@themer` (`themer.agent.md`)
**Story Points:** 3
**Priority:** Medium
**Labels:** `frontend`, `ui`, `sdc`, `scss`

#### Description

Polish the saved card display and payment method switching UX. The current implementation is functional but needs visual refinement across breakpoints.

#### Tasks

1. **Saved card selected state** — Verify `:checked` sibling selector drives visible border/background change across all browsers
2. **"+ Use a different card" hover/focus states** — Should look interactive (underline, color shift)
3. **AJAX loading state** — When switching payment methods, confirm Bootstrap spinner renders from the theme's `ajaxProgressThrobber` override
4. **Billing address dropdown** — Style the `select.available-profiles` to match the theme's form style
5. **Responsive testing** — Verify saved card rows stack properly on mobile (< 576px), tablet, desktop
6. **Transition/animation** — Smooth reveal of billing address section after AJAX replace (optional, CSS-only)

#### Files to Edit

- `web/themes/custom/duccinis_1984_olympics/components/saved-card/saved-card.scss`
- `web/themes/custom/duccinis_1984_olympics/templates/form/form-element--radio.html.twig` (if structure changes needed)
- `web/themes/custom/duccinis_1984_olympics/src/scss/` (global form styles)

#### Build Command

```bash
cd web/themes/custom/duccinis_1984_olympics && ddev npm run dev
```

#### Acceptance Criteria

- [ ] Saved cards visually distinguish selected vs unselected state
- [ ] "+ Use a different card" has clear hover/focus/active states
- [ ] AJAX throbber renders during payment method switch
- [ ] Billing address dropdown and rendered profile match theme styling
- [ ] Layout works at 320px, 576px, 768px, 1024px, 1200px widths
- [ ] No horizontal scroll at any breakpoint
- [ ] `ddev npm run build` produces no errors

#### Handoff

→ **On completion:** Hand off to `@tester` for visual regression check (#26.6)

---

### #26.5 — Accessibility Audit: Payment Method Selection

**Assignee:** `@themer` (`themer.agent.md`) + `@tester` (`tester.agent.md`)
**Story Points:** 2
**Priority:** Medium
**Labels:** `a11y`, `accessibility`, `wcag`

#### Description

The saved card radios are `visually-hidden` with custom `<label>` overlays. This pattern must be verified for keyboard navigation and screen reader compatibility.

#### Audit Checklist

- [ ] **Keyboard navigation:** Tab cycles through all 3 radio options (card 1, card 2, new card)
- [ ] **Space/Enter:** Selects the focused radio and triggers AJAX change event
- [ ] **Screen reader:** Each radio announces its label text (e.g., "VISA ending in 4242, expires 3/2033")
- [ ] **Focus indicator:** Visible focus ring on the active card row (not hidden by `visually-hidden`)
- [ ] **ARIA:** `role`, `aria-checked`, `aria-label` attributes are correct (or native radio semantics suffice)
- [ ] **Color contrast:** Brand badge text meets WCAG AA (4.5:1 ratio)
- [ ] **Reduced motion:** No animation issues with `prefers-reduced-motion`

#### Tools

- Keyboard-only navigation test (no mouse)
- axe DevTools browser extension
- NVDA or VoiceOver screen reader

#### Files That May Need Changes

- `web/themes/custom/duccinis_1984_olympics/templates/form/form-element--radio.html.twig` — add `aria-label` to labels if screen reader context is insufficient
- `web/themes/custom/duccinis_1984_olympics/components/saved-card/saved-card.scss` — focus-visible styles

#### Handoff

- `@themer` performs initial audit and fixes Twig/SCSS
- `@tester` verifies fixes with screen reader + axe

---

### #26.6 — Comprehensive QA: Full Regression & Cross-Browser

**Assignee:** `@tester` (`tester.agent.md`)
**Story Points:** 3
**Priority:** High
**Labels:** `testing`, `qa`, `regression`, `cross-browser`

#### Description

Final QA pass covering all saved card functionality, checkout flow, and regressions.

#### Test Matrix

| Scenario | Chrome | Firefox | Safari | Mobile Chrome | Mobile Safari |
|----------|--------|---------|--------|---------------|---------------|
| Saved cards display correctly | ☐ | ☐ | ☐ | ☐ | ☐ |
| Card selection (radio switch) | ☐ | ☐ | ☐ | ☐ | ☐ |
| "+ Use different card" AJAX | ☐ | ☐ | ☐ | ☐ | ☐ |
| Billing address dropdown | ☐ | ☐ | ☐ | ☐ | ☐ |
| Checkout with saved card | ☐ | ☐ | ☐ | ☐ | ☐ |
| Checkout with new card | ☐ | ☐ | ☐ | ☐ | ☐ |
| Pickup order (no delivery) | ☐ | ☐ | ☐ | ☐ | ☐ |
| Delivery order | ☐ | ☐ | ☐ | ☐ | ☐ |

#### Regression Tests

- [ ] Existing checkout flow (no saved cards — new customer) still works
- [ ] Fulfillment time pane (pickup/delivery toggle) unaffected
- [ ] Delivery address pane shows/hides correctly
- [ ] Coupon redemption pane functional
- [ ] Contact information pane functional
- [ ] Admin order view shows correct payment data

#### Dependencies

- #26.1 (profile cleanup)
- #26.2, #26.3 (E2E tests pass)
- #26.4 (UI polish complete)
- #26.5 (a11y fixes applied)

#### Handoff

→ **On all PASS:** Hand off to `@architect` for merge approval
→ **On FAIL:** Route bugs to appropriate agent per the bug table below

---

### #26.7 — Remove Diagnostic JS & Clean Up

**Assignee:** `@drupal-developer` (`developer_drupal.agent.md`)
**Story Points:** 1
**Priority:** High (before merge)
**Labels:** `cleanup`, `tech-debt`

#### Description

Remove the temporary diagnostic behavior and library before merging to main.

#### Tasks

- [ ] Remove `payment-ajax-diagnostic` library from `duccinis_1984_olympics.libraries.yml`
- [ ] Remove diagnostic library attachment from `store_fulfillment.module`
- [ ] Delete `src/js/diagnostics/payment-ajax-diagnostic.js`
- [ ] `ddev drush cr` — verify no errors
- [ ] Confirm checkout page loads without diagnostic panel
- [ ] Verify AJAX still works after removal (the diagnostic was read-only, but confirm)

#### Files to Modify

- `web/themes/custom/duccinis_1984_olympics/duccinis_1984_olympics.libraries.yml` — remove `payment-ajax-diagnostic` entry
- `web/modules/custom/store_fulfillment/store_fulfillment.module` — remove diagnostic library line
- `web/themes/custom/duccinis_1984_olympics/src/js/diagnostics/payment-ajax-diagnostic.js` — delete file

#### Handoff

→ **On completion:** Hand off to `@tester` for final smoke test (#26.6)

---

### #26.8 — Documentation: Saved Card Architecture

**Assignee:** `@technical-writer` (`technical-writer.agent.md`)
**Story Points:** 1
**Priority:** Low
**Labels:** `documentation`

#### Description

Document the saved card display architecture for future maintainers, covering the data flow from Stripe payment methods through form alter → after_build → preprocess → Twig template.

#### Deliverables

- [ ] Architecture diagram (text/Mermaid) showing: `store_fulfillment_form_alter()` → `#after_build` callback → `preprocess_form_element()` → `form-element--radio.html.twig`
- [ ] Document the SDC component (`saved-card`) and its relationship to the Twig template
- [ ] Document the `StripeReview` pane's `showPaymentForm` logic (empty payment_method → show Stripe iframe)
- [ ] Note the `visually-hidden` radio + custom label pattern and why it was chosen
- [ ] Update project README or add a `docs/SAVED_CARDS.md`

#### Source Files to Reference

| File | Purpose |
|------|---------|
| `store_fulfillment.module` | Form alter + after_build (injects card data) |
| `includes/form.theme` | Preprocess (exposes vars to Twig) |
| `templates/form/form-element--radio.html.twig` | Card row rendering |
| `components/saved-card/` | SDC component (SCSS styles) |
| `StripeReview.php` | Review pane (Stripe iframe decision) |
| `PaymentInformation.php` | AJAX callback for payment method switch |

#### Handoff

→ **On completion:** Hand off to `@architect` for review

---

## Execution Order & Dependency Graph

```
Phase 1 — Backend & Data (parallel)
  #26.1 @drupal-developer  Clean up duplicate profiles
  #26.7 @drupal-developer  Remove diagnostic JS (can be deferred to Phase 3)

Phase 2 — End-to-End Testing (sequential, after Phase 1)
  #26.2 @tester            E2E: saved card checkout
  #26.3 @tester            E2E: new card checkout

Phase 3 — Polish & Accessibility (parallel, after Phase 2 passes)
  #26.4 @themer            UI polish (responsive, states, transitions)
  #26.5 @themer + @tester  Accessibility audit & fixes

Phase 4 — Final QA (after Phase 3)
  #26.6 @tester            Full regression & cross-browser
  #26.7 @drupal-developer  Remove diagnostic (if not done in Phase 1)

Phase 5 — Documentation & Merge
  #26.8 @technical-writer  Architecture docs
  MERGE @architect         Final review & merge approval
```

## Bug Routing Table

When bugs are found during testing, route to the appropriate agent:

| Bug Type | Route To | Agent File |
|----------|----------|------------|
| PHP error / form logic | `@drupal-developer` | `developer_drupal.agent.md` |
| AJAX / JS behavior | `@drupal-developer` | `developer_drupal.agent.md` |
| Styling / layout | `@themer` | `themer.agent.md` |
| Stripe integration | `@drupal-developer` | `developer_drupal.agent.md` |
| Performance regression | `@performance-engineer` | `performance-engineer.agent.md` |
| Security concern | `@security-specialist` | `security-specialist.agent.md` |
| Template / Twig | `@themer` | `themer.agent.md` |

---

## Handoff Chain Summary

```
@drupal-developer (#26.1 profiles)
    ↓
@tester (#26.2 saved card E2E)
    ↓
@tester (#26.3 new card E2E)
    ↓ (parallel)
@themer (#26.4 polish)          @themer + @tester (#26.5 a11y)
    ↓                                ↓
    └──────────┬─────────────────────┘
               ↓
@tester (#26.6 full QA)
               ↓
@drupal-developer (#26.7 cleanup)
               ↓
@technical-writer (#26.8 docs)
               ↓
@architect (merge approval)
```
