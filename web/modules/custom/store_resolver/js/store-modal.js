/**
 * @file
 * Store selection modal functionality.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  // Store resolver utility functions - define FIRST before behaviors use them.
  Drupal.storeResolver = Drupal.storeResolver || {};

  /**
   * Cookie name constant.
   */
  Drupal.storeResolver.COOKIE_NAME = 'store_resolver_store_id';

  /**
   * Check if store cookie exists.
   */
  Drupal.storeResolver.hasStoreCookie = function () {
    return Drupal.storeResolver.getCookie(Drupal.storeResolver.COOKIE_NAME) !== null;
  };

  /**
   * Get cookie value by name.
   */
  Drupal.storeResolver.getCookie = function (name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  };

  /**
   * Set store cookie.
   */
  Drupal.storeResolver.setStoreCookie = function (storeId) {
    var expiryDate = new Date();
    expiryDate.setTime(expiryDate.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
    var expires = "expires=" + expiryDate.toUTCString();
    document.cookie = Drupal.storeResolver.COOKIE_NAME + "=" + storeId + ";" + expires + ";path=/;SameSite=Lax";
  };

  /**
   * Delete store cookie (for testing).
   */
  Drupal.storeResolver.deleteStoreCookie = function () {
    document.cookie = Drupal.storeResolver.COOKIE_NAME + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    console.log('Store cookie deleted. Reload the page to see the modal again.');
  };

  /**
   * Show the modal.
   */
  Drupal.storeResolver.showModal = function () {
    var $modal = $('#store-resolver-modal');
    if ($modal.length) {
      $modal.addClass('is-active');
      $('body').addClass('store-modal-open');
    }
  };

  /**
   * Hide the modal.
   */
  Drupal.storeResolver.hideModal = function () {
    var $modal = $('#store-resolver-modal');
    if ($modal.length) {
      $modal.removeClass('is-active');
      $('body').removeClass('store-modal-open');
    }
  };

  /**
   * Refresh blocks via AJAX after store selection.
   */
  Drupal.storeResolver.refreshBlocks = function (storeId, storeName) {
    console.log('Store Resolver: Refreshing blocks for store ID:', storeId);

    $.ajax({
      url: Drupal.url('store/ajax/refresh'),
      type: 'GET',
      data: { store_id: storeId },
      dataType: 'json',
      success: function (response) {
        console.log('Store Resolver: AJAX response received:', response);

        // Process Drupal AJAX commands manually to avoid dependency on full AJAX object.
        if (response && response.length) {
          for (var i = 0; i < response.length; i++) {
            var cmd = response[i];
            console.log('Store Resolver: Processing command:', cmd.command);

            // Handle insert/replace commands directly.
            if ((cmd.command === 'insert' || cmd.command === 'replace') && cmd.selector && cmd.data) {
              var $target = $(cmd.selector);
              console.log('Store Resolver: Target selector:', cmd.selector, 'Found:', $target.length);

              if ($target.length) {
                // For replace method, replace the entire element.
                if (cmd.method === 'replaceWith' || cmd.command === 'replace') {
                  $target.replaceWith(cmd.data);
                  console.log('Store Resolver: Replaced element with new content');
                } else {
                  // Default to html replacement.
                  $target.html(cmd.data);
                  console.log('Store Resolver: Updated element HTML');
                }

                // Re-attach Drupal behaviors to the new content.
                Drupal.attachBehaviors(document.body);
              } else {
                console.warn('Store Resolver: Target element not found:', cmd.selector);
              }
            }
          }
        }

        // Show success message.
        var message = '<div class="messages messages--status" role="contentinfo">' +
          '<div role="alert">' +
          '<h2 class="visually-hidden">Status message</h2>' +
          Drupal.t('You have selected @store', {'@store': storeName}) +
          '</div></div>';

        if ($('.region-content').length) {
          // Remove any existing store selection messages first.
          $('.region-content > .messages--status').remove();
          $('.region-content').prepend(message);
        }
      },
      error: function (xhr, status, error) {
        console.error('Store Resolver: Failed to refresh blocks:', error);
        console.error('Store Resolver: XHR status:', xhr.status);
        console.error('Store Resolver: Response text:', xhr.responseText);
        // Fallback: reload the page if AJAX fails.
        location.reload();
      }
    });
  };

  /**
   * Expose utility function to window for console testing.
   */
  if (typeof window !== 'undefined') {
    window.storeResolverReset = function () {
      Drupal.storeResolver.deleteStoreCookie();
    };
  }

  // NOW define the Drupal behavior that uses the above functions.
  Drupal.behaviors.storeResolverModal = {
    attach: function (context, settings) {
      // Only run once per page load using Drupal 9+ once() API.
      once('store-resolver-modal', 'body', context).forEach(function (element) {
        // Check if the store cookie exists.
        if (!Drupal.storeResolver.hasStoreCookie()) {
          // Show the modal after a brief delay to ensure page is loaded.
          setTimeout(function () {
            Drupal.storeResolver.showModal();
          }, 500);
        }
      });

      // Handle form submission via AJAX using Drupal 9+ once() API.
      once('store-resolver-submit', '.store-resolver-modal-form', context).forEach(function (form) {
        $(form).on('submit', function (e) {
          e.preventDefault();

          var $form = $(this);
          var storeId = $form.find('input[name="store_id"]:checked').val();

          if (!storeId) {
            alert(Drupal.t('Please select a store to continue.'));
            return false;
          }

          // Get store name for the success message.
          var storeName = $form.find('input[name="store_id"]:checked').parent().find('label').text();

          // Set the cookie.
          Drupal.storeResolver.setStoreCookie(storeId);

          // Hide the modal.
          Drupal.storeResolver.hideModal();

          // Refresh blocks via AJAX instead of page reload.
          Drupal.storeResolver.refreshBlocks(storeId, storeName);

          return false;
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
