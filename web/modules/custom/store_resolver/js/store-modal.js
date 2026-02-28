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
    return Drupal.storeResolver.getCookie(Drupal.storeResolver.COOKIE_NAME) !== NULL;
  };

  /**
   * Get cookie value by name.
   */
  Drupal.storeResolver.getCookie = function (name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') { c = c.substring(1, c.length);
      }
      if (c.indexOf(nameEQ) === 0) { return c.substring(nameEQ.length, c.length);
      }
    }
    return NULL;
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
   * Refresh blocks via AJAX after store selection using Drupal's AJAX API.
   *
   * @param {string} storeId
   *   The selected store ID.
   * @param {string} storeName
   *   The selected store name for the success message.
   */
  Drupal.storeResolver.refreshBlocks = function (storeId, storeName) {
    // Create a temporary element to serve as the AJAX trigger.
    // This is the standard Drupal way to programmatically trigger AJAX.
    var $tempElement = $('<div id="store-resolver-ajax-trigger"></div>').appendTo('body');

    // Create a Drupal AJAX object using the standard API.
    var ajaxSettings = {
      url: Drupal.url('store/ajax/refresh') + '?store_id=' + encodeURIComponent(storeId),
      base: 'store-resolver-ajax-trigger',
      element: $tempElement[0],
      progress: { type: 'none' }
    };

    // Create the AJAX object using Drupal's AJAX API.
    var ajax = Drupal.ajax(ajaxSettings);

    // Store the original success handler.
    var originalSuccess = ajax.success;

    // Override the success handler to add our custom logic after Drupal processes commands.
    ajax.success = function (response, status) {
      // Call the original success handler first - this processes AJAX commands.
      originalSuccess.call(this, response, status);

      // Show success message after AJAX commands are processed.
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

      // Clean up the temporary element.
      $tempElement.remove();
    };

    // Store the original error handler.
    var originalError = ajax.error;

    // Override the error handler.
    ajax.error = function (xmlhttprequest, uri, customMessage) {
      console.error('Store Resolver: AJAX error:', customMessage);
      // Clean up the temporary element.
      $tempElement.remove();
      // Fallback: reload the page if AJAX fails.
      location.reload();
    };

    // Execute the AJAX request.
    ajax.execute();
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

      // Handle "Change store" link clicks - show modal instead of navigating away.
      once('store-resolver-change', '.current-store__change-link', context).forEach(function (link) {
        $(link).on('click', function (e) {
          e.preventDefault();
          Drupal.storeResolver.showModal();
          return FALSE;
        });
      });

      // Handle form submission via AJAX using Drupal 9+ once() API.
      once('store-resolver-submit', '.store-resolver-modal-form', context).forEach(function (form) {
        $(form).on('submit', function (e) {
          e.preventDefault();

          var $form = $(this);
          var storeId = $form.find('input[name="store_id"]:checked').val();

          if (!storeId) {
            alert(Drupal.t('Please select a store to continue.'));
            return FALSE;
          }

          // Get store name for the success message.
          var storeName = $form.find('input[name="store_id"]:checked').parent().find('label').text();

          // Set the cookie.
          Drupal.storeResolver.setStoreCookie(storeId);

          // Hide the modal.
          Drupal.storeResolver.hideModal();

          // Refresh blocks via AJAX instead of page reload.
          Drupal.storeResolver.refreshBlocks(storeId, storeName);

          return FALSE;
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
