/**
 * @file
 * Archive toggle — Drupal behavior.
 *
 * Toggles visibility of archived menu items (.is-archived) within
 * the menu accordion. Reads labels from data attributes so the
 * button text updates with the toggle state.
 *
 * Markup contract:
 *   <button class="archive-toggle"
 *           aria-pressed="false"
 *           data-label-show="Show archived"
 *           data-label-hide="Hide archived">
 *     <span class="archive-toggle__text">Show archived</span>
 *   </button>
 *
 * The toggle adds/removes .menu-accordion__body--show-archived on
 * the nearest .menu-accordion__body ancestor (or sibling container).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.archiveToggle = {
    attach: function (context) {
      var toggles = once('archive-toggle', '.archive-toggle', context);

      for (var i = 0; i < toggles.length; i++) {
        toggles[i].addEventListener('click', handleToggle);
      }
    }
  };

  /**
   * Handles archive toggle click.
   *
   * @param {Event} event
   */
  function handleToggle(event) {
    var btn = event.currentTarget;
    var pressed = btn.getAttribute('aria-pressed') === 'true';
    var newPressed = !pressed;

    // Update ARIA state.
    btn.setAttribute('aria-pressed', String(newPressed));

    // Update label text.
    var labelEl = btn.querySelector('.archive-toggle__text');
    if (labelEl) {
      labelEl.textContent = newPressed
        ? (btn.getAttribute('data-label-hide') || 'Hide archived')
        : (btn.getAttribute('data-label-show') || 'Show archived');
    }

    // Toggle the show class on the target container.
    var container = findArchiveContainer(btn);
    if (container) {
      if (newPressed) {
        container.classList.add('menu-accordion__body--show-archived');
      }
      else {
        container.classList.remove('menu-accordion__body--show-archived');
      }
    }
  }

  /**
   * Finds the archive container (.menu-accordion__body) relative to the toggle.
   *
   * Walks up from the button to find the nearest accordion body, or looks for
   * a sibling element with that class.
   *
   * @param {HTMLElement} btn
   * @return {HTMLElement|null}
   */
  function findArchiveContainer(btn) {
    // Check if the toggle is inside the accordion body.
    var ancestor = btn.closest('.menu-accordion__body');
    if (ancestor) {
      return ancestor;
    }

    // Check if the toggle wrapper is a sibling of the accordion collapse.
    var wrapper = btn.closest('.menu-accordion__archive-toggle-wrapper');
    if (wrapper && wrapper.nextElementSibling) {
      var body = wrapper.nextElementSibling.querySelector('.menu-accordion__body');
      if (body) {
        return body;
      }
    }

    // Fallback: nearest .menu-accordion scope.
    var accordion = btn.closest('.menu-accordion');
    if (accordion) {
      return accordion.querySelector('.menu-accordion__body');
    }

    return null;
  }

})(Drupal, once);
