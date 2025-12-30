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

          // Set the cookie.
          Drupal.storeResolver.setStoreCookie(storeId);

          // Hide the modal.
          Drupal.storeResolver.hideModal();

          // Show success message.
          var storeName = $form.find('input[name="store_id"]:checked').parent().find('label').text();
          var message = '<div class="messages messages--status" role="contentinfo">' +
            '<div role="alert">' +
            '<h2 class="visually-hidden">Status message</h2>' +
            Drupal.t('You have selected @store', {'@store': storeName}) +
            '</div></div>';

          // Prepend message to main content area.
          if ($('.region-content').length) {
            $('.region-content').prepend(message);
          }

          return false;
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
