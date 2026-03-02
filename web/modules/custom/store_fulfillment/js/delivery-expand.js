/**
 * @file
 * Animate delivery-expand wrapper after AJAX replacement.
 *
 * When FulfillmentTime AJAX replaces #delivery-address-wrapper, the new HTML
 * arrives with or without .open already set. To trigger a CSS transition we
 * need the browser to paint the collapsed state first, then add .open.
 * This behavior handles that by deferring the .open class by one frame.
 *
 * Also manages aria-expanded on the wrapper so assistive technologies
 * announce the delivery section expand/collapse state
 * (WCAG 2.1 AA 4.1.2 — Name, Role, Value).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.deliveryExpandTransition = {
    attach: function (context) {
      once('delivery-expand', '.delivery-expand', context)
        .forEach(function (el) {
          if (el.classList.contains('open')) {
            // Remove .open, force a reflow, then re-add so the transition fires.
            el.classList.remove('open');
            // Force reflow.
            void el.offsetHeight;
            requestAnimationFrame(function () {
              el.classList.add('open');
              // Announce expanded state to AT after transition starts.
              el.setAttribute('aria-expanded', 'true');
            });
          }
          else {
            el.setAttribute('aria-expanded', 'false');
          }
        });
    }
  };
})(Drupal, once);
