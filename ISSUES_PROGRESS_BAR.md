# Checkout Progress Bar — Open Issues & Backlog

**Branch:** `issue/31-checkout-progress-bar`
**Last updated:** 2026-03-02
**Status:** Progress bar implemented. The items below are known weaknesses to address before Epic 5 is considered production-ready.

---

## Current State (as of commit 5921408a)

- ✅ 4-step bar: Cart → Order Details → Review → Complete
- ✅ Done steps linkable (Cart + earlier checkout steps); active/future are non-clickable
- ✅ `aria-current="step"` on active; `aria-disabled="true"` on future steps
- ✅ `:focus-visible` ring for keyboard navigation
- ✅ Funnel-aware: back-links suppressed after order is placed (complete step)
- ⚠️ Visual affordance for clickable vs non-clickable steps is **not clear enough** (see issues below)

---

## Issues to Resolve

### ISSUE-PB-1 — Visual affordance: clickable done-steps look identical to future steps

**Priority:** High
**Labels:** `UX`, `accessibility`, `frontend`

**Problem:** At a glance a user cannot tell that Cart (and Order Details, when on Review) are live links they can navigate back to. The green color helps, but the underline is suppressed and there is no cursor or pointer-affordance cue until hover. On mobile there is no hover state at all.

**Expected behaviour:** Clickable done-steps should feel obviously interactive — as obviously tappable on mobile as a button. Non-clickable steps should feel inert.

**Proposed fixes (pick one or combine):**
- Add a subtle underline (`text-decoration: underline; text-underline-offset: 4px`) to `a.step.done` only — familiar link affordance.
- Add a left-arrow glyph (`‹`) before the step label on done steps (communicates "go back" direction).
- Add a distinct hover/active background tint behind done steps (`background: rgba($done-color, 0.07)` on the step cell).
- On mobile, show a persistent bottom border in a slightly brighter green for done steps to compensate for no hover.

---

### ISSUE-PB-2 — Active step text contrast against white background

**Priority:** Medium
**Labels:** `accessibility`, `WCAG`, `frontend`

**Problem:** The active step uses `$body-color` (`#1a1a1a`) on white. While technically passing WCAG AA contrast, the pink/magenta underline bar is only 3px tall and does not read as "you are here" unless the user already understands the pattern.

**Expected behaviour:** The active step should be unmistakably the current page — bold weight alone is not enough at small viewport widths.

**Proposed fixes:**
- Increase the underline bar height for the active step (e.g. 4–5px vs 3px for done/future).
- Add `font-weight: 800` or a slight text-shadow on `.step.active` so it pops above its neighbours.
- Consider a filled pill/capsule pill background behind the active step label (matches many e-commerce patterns: Amazon, Shopify default).

---

### ISSUE-PB-3 — Future steps should read as locked/disabled, not just gray

**Priority:** Medium
**Labels:** `UX`, `frontend`

**Problem:** Future steps (Review, Complete when on Order Details) are muted gray, but a user who has never seen a checkout progress bar may try tapping them and be confused when nothing happens. `aria-disabled="true"` is correct for AT, but sighted users get no affordance.

**Proposed fixes:**
- Add `cursor: not-allowed` to `div.step` (future/non-interactive steps) so on desktop the cursor changes on hover.
- Optionally add a lock icon (`🔒` or SVG) beside future step labels — common in multi-step wizard patterns.
- Add a tooltip/title attribute: `title="{{ 'Complete this step first'|t }}"` on the `div.step` element.

---

### ISSUE-PB-4 — No visual separator between steps

**Priority:** Low
**Labels:** `frontend`, `design`

**Problem:** Steps flow horizontally with `gap: 0` and rely solely on the underline bars and text to imply separation. On narrow viewports (360px wide phones) the 4 labels compress and become very small (currently `0.7rem`).

**Proposed fixes:**
- Add `>` chevron separators between steps via `::before` pseudo-elements on `.step + .step`.
- Or switch to a vertical stacking layout below `sm` breakpoint and show only the current step's label with "Step 2 of 4" numeric indicator.
- Investigate whether `0.7rem` causes WCAG 1.4.4 issues at default browser zoom on 320px-wide viewports.

---

### ISSUE-PB-5 — Analytics / funnel tracking not wired

**Priority:** High (marketing / roadmap)
**Labels:** `analytics`, `marketing`, `future`

**Problem:** Back-navigation clicks on the progress bar are untraceable. If a user clicks "Cart" from the Review step to remove an item, that funnel abandonment is invisible in analytics. Google Analytics 4 / Segment will see the page transition but not that it originated from the progress bar.

**Expected behaviour:**
- Each `a.step` click should fire a GA4 / GTM event: `checkout_step_back` with properties: `from_step`, `to_step`, `order_id`.
- This enables funnel analysis: "23% of users who reach Review click back to Order Details — what are they changing?"

**Implementation approach:**
- Add `data-analytics-event="checkout_step_back"` and `data-from-step="{{ step_id }}"` / `data-to-step="{{ step.id }}"` to `a.step` elements (requires passing `step_id` to Twig).
- Write a small Drupal behavior (`Drupal.behaviors.checkoutProgressAnalytics`) that reads these data attributes and pushes to `window.dataLayer`.
- Wire the preprocess to forward `current_step_id` to the Twig template as a variable.

---

### ISSUE-PB-6 — Progress bar absent from login-redirect checkout flow

**Priority:** Low
**Labels:** `checkout`, `edge-case`

**Problem:** When an anonymous user proceeds to checkout and is redirected through the login/register step (a different checkout step ID not in `$step_order`), the preprocess function falls back to `$current_index = 0` silently. The bar still renders but may show incorrect state.

**Expected behaviour:** Test the bar on an anonymous checkout path and confirm state correctness.

---

## Acceptance Criteria for "Progress Bar — Phase 2"

When all of the above High/Medium issues are addressed, the bar should meet these criteria:

- [ ] A first-time user on a phone (no hover) can tell at a glance which steps are navigable and which are locked — without reading the labels.
- [ ] A keyboard-only user can tab to each done-step link, see a visible focus indicator, and activate it.
- [ ] Clicking a done-step link fires a measurable event in the analytics layer.
- [ ] The active step is unmistakably "current page" — no ambiguity at 320px viewport width.
- [ ] WCAG 2.1 AA audit passes for the entire bar (contrast, focus, labels, cursor).

---

## References

- [Issue #31](https://github.com/micronugget/duccinisv3/issues/31) — original progress bar story
- [Mockup](`.github/output/checkout-order-information-mockup.html`)
- [WCAG 2.1 — 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum)
- [WCAG 2.1 — 2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG21/Understanding/focus-visible)
- [WCAG 2.1 — 2.4.4 Link Purpose](https://www.w3.org/WAI/WCAG21/Understanding/link-purpose-in-context)

---

> **Note (2026-03-02):** PB-1 through PB-5 have been superseded by the block-first
> architecture planning in [ISSUES_BLOCKS_AND_CANVAS.md](ISSUES_BLOCKS_AND_CANVAS.md).
> See ARCH-7 (visual affordance) and ARCH-8 (analytics). The block sprint (ARCH-1)
> must land before those issues can be addressed, as the progress bar will move out
> of Twig templates and into a `CheckoutProgressBarBlock` plugin.
