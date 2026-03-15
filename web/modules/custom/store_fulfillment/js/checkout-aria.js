/**
 * @file
 * ARIA live-region announcements and focus management for the checkout panes.
 *
 * Addresses WCAG 2.1 AA success criteria:
 *  - 4.1.3 Status Messages: AJAX pane swaps announce meaningful changes to
 *    screen readers via a persistent role="status" live region that survives
 *    DOM replacements.
 *  - 2.4.3 Focus Order: restores keyboard focus to the active radio after
 *    Drupal AJAX replaces #fulfillment-time-wrapper to prevent focus loss.
 *
 * The live region is appended to <body> once so it is never destroyed by
 * ReplaceCommand replacing the checkout pane wrappers.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Returns a persistent aria-live status region, creating it if needed.
   *
   * @returns {HTMLElement}
   */
  function getLiveRegion() {
    var existing = document.getElementById('checkout-aria-live');
    if (existing) {
      return existing;
    }
    var region = document.createElement('div');
    region.id = 'checkout-aria-live';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    // Visually hidden — Bootstrap / Drupal sr-only pattern.
    region.style.cssText = [
      'position:absolute',
      'width:1px',
      'height:1px',
      'padding:0',
      'margin:-1px',
      'overflow:hidden',
      'clip:rect(0,0,0,0)',
      'white-space:nowrap',
      'border:0',
    ].join(';');
    document.body.appendChild(region);
    return region;
  }

  /**
   * Announces a message to screen readers via the live region.
   *
   * @param {string} message
   */
  function announce(message) {
    var region = getLiveRegion();
    // Clear first so repeated identical messages still fire.
    region.textContent = '';
    // One rAF defer so AT picks up the textContent change as a live update.
    requestAnimationFrame(function () {
      region.textContent = message;
    });
  }

  Drupal.behaviors.checkoutAria = {
    attach: function (context) {
      // ── Fulfillment method announcements (Pickup / Delivery) ──────────────
      once('checkout-aria-method', '[name="fulfillment_time[fulfillment_method]"]', context)
        .forEach(function (radio) {
          radio.addEventListener('change', function () {
            if (this.value === 'delivery') {
              announce(Drupal.t('Delivery selected. Please enter your delivery address below.'));
            }
            else {
              announce(Drupal.t('Pickup selected.'));
            }
          });
        });

      // ── Fulfillment type announcements (ASAP / Schedule) ──────────────────
      once('checkout-aria-type', '[name="fulfillment_time[fulfillment_type]"]', context)
        .forEach(function (radio) {
          radio.addEventListener('change', function () {
            if (this.value === 'scheduled') {
              announce(Drupal.t('Schedule selected. Please choose a time slot below.'));
            }
            else {
              announce(Drupal.t('ASAP selected.'));
            }
          });
        });

      // ── Focus management after AJAX replacement ───────────────────────────
      // When context !== document, Drupal AJAX has just inserted new DOM
      // (a ReplaceCommand replaced the fulfillment-time-wrapper). The focused
      // element was destroyed, leaving focus on <body>. Restore it to the
      // currently-checked fulfillment_method radio so keyboard users can
      // continue without losing their place.
      if (context !== document) {
        var contextEl = (context instanceof Element) ? context : null; // phpcs:ignore Generic.PHP.UpperCaseConstant.Found
        if (contextEl) {
          var checkedMethod = contextEl.querySelector(
            '[name="fulfillment_time[fulfillment_method]"]:checked'
          );
          if (checkedMethod) {
            // Defer one frame so Drupal's own post-AJAX processing finishes
            // before we move focus.
            requestAnimationFrame(function () {
              checkedMethod.focus();
            });
          }
        }
      }
    }
  };

})(Drupal, once);
