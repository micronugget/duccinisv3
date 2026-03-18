/**
 * @file
 * JS behavior for the "Add Items" modal.
 *
 * After Commerce's add-to-cart AJAX fires inside the dialog, the cart
 * block in the sidebar / header needs to refresh so the item count and
 * totals reflect the new addition. This behavior listens for Commerce's
 * 'cartUpdated' event (dispatched by commerce_cart.js after a successful
 * add-to-cart) and replaces the Commerce cart block via AJAX.
 *
 * It also exposes a tidy "Done" button inside the dialog that closes it
 * and scrolls the cart summary into view.
 *
 * Issue #11.1 rework — inline add-items modal.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.addItemsModal = {
    attach: function (context, settings) {

      // ── 1. Inject a "Done — Back to Checkout" close button at the top of
      //       the modal body every time the dialog is (re-)opened / AJAX-ed.
      once('add-items-modal-done-button', '.add-items-dialog .ui-dialog-content', context)
        .forEach(function (el) {
          var bar = document.createElement('div');
          bar.className = 'add-items-modal__done-bar';
          bar.innerHTML =
            '<button class="btn btn-success add-items-modal__done-btn js-add-items-close" type="button">' +
              Drupal.t('✓ Done Adding Items — Close') +
            '</button>';
          el.prepend(bar);
        });

      // ── 2. Close-button handler.
      once('add-items-close', '.js-add-items-close', context)
        .forEach(function (btn) {
          btn.addEventListener('click', function () {
            // Find and close the jQuery UI dialog wrapping the modal content.
            var $dialog = Drupal.jQuery('#add-items-modal');
            if ($dialog.length && $dialog.dialog('instance')) {
              $dialog.dialog('close');
            }
          });
        });

      // ── 3. After any add-to-cart AJAX inside the modal refreshes the page,
      //       re-attach so the Done button is injected into the new content.
      //       Commerce dispatches a custom jQuery event on the body after cart
      //       updates — we also listen for the standard Drupal AJAX 'success'.
      once('add-items-cart-listener', 'body', context)
        .forEach(function (body) {
          Drupal.jQuery(body).on('commerce:cartUpdated', function () {
            // Re-attach behaviors so the Done button reappears after AJAX swap.
            Drupal.attachBehaviors(
              document.querySelector('.add-items-dialog .ui-dialog-content') || body
            );
          });
        });

    }
  };

}(Drupal, drupalSettings, once));
