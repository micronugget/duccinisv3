/**
 * @file
 * Ensures "Use a different card" triggers AJAX even when the stripe radio
 * is already checked.
 *
 * Drupal AJAX binds to the `change` event on radio inputs. If the "stripe"
 * gateway radio is already the checked option when the user clicks the
 * "+ Use a different card" label, no `change` fires (nothing actually
 * changed). This behavior detects that edge case and programmatically
 * triggers the jQuery change event so Drupal AJAX runs as expected.
 *
 * Reproduces when: page loads with no prior saved-card selection but the
 * Drupal form state or Commerce default has pre-selected the stripe option,
 * OR when the user navigates back to the checkout page mid-session.
 */
(function (Drupal, $, once) {
  'use strict';

  Drupal.behaviors.savedCardNewCardLink = {
    attach(context) {
      once('saved-card-new-link', '.saved-card-item--new label', context).forEach(
        function (label) {
          label.addEventListener('click', function () {
            const radioId = label.getAttribute('for');
            if (!radioId) {
              return;
            }
            const radio = document.getElementById(radioId);
            if (!radio) {
              return;
            }

            if (radio.checked) {
              // The target radio is ALREADY checked — the browser fires no
              // native `change` event when you click a label for an already-
              // checked radio. Force-trigger via jQuery so Drupal's AJAX
              // handler (which is jQuery-bound) picks it up.
              $(radio).trigger('change');
            }
            // If NOT already checked the label click will check it and the
            // browser fires `change` naturally — no intervention needed.
          });
        },
      );
    },
  };
})(Drupal, jQuery, once);
