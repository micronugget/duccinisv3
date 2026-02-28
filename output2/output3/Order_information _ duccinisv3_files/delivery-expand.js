/**
 * @file
 * Animate delivery-expand wrapper after AJAX replacement.
 *
 * When FulfillmentTime AJAX replaces #delivery-address-wrapper, the new HTML
 * arrives with or without .open already set. To trigger a CSS transition we
 * need the browser to paint the collapsed state first, then add .open.
 * This behavior handles that by deferring the .open class by one frame.
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
            });
          }
        });
    }
  };
})(Drupal, once);
