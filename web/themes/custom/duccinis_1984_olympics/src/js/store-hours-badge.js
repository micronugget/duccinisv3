/**
 * @file
 * store-hours-badge.js
 *
 * Drupal behavior that computes each store's live open/closed status and
 * updates the `.duccinis-location-card__badge` element accordingly.
 *
 * Reads `data-store-hours` attribute on the badge element:
 *   - "dc"  → Adams Morgan DC & 7th Street NW schedule
 *   - "va"  → Falls Church VA schedule
 *
 * Overnight close hours use a `close > 24` convention so that, e.g., 3 AM
 * is represented as hour 27. Hours before 6 AM are treated as the previous
 * calendar day's "extended" overnight window.
 *
 * Badge classes toggled on the element:
 *   open-badge--open    "#4ade80 dot, Open Now text"
 *   open-badge--late    "yellow dot, Open Late text (≥ 10 PM)"
 *   open-badge--closed  "#f87171 dot, Closed text"
 */

(function (Drupal) {
  'use strict';

  /**
   * Store schedule map.
   *
   * Keys are day-of-week (0 = Sunday). Values are [openHour, closeHour] where
   * closeHour > 24 means the store closes in the early morning of the next day.
   *
   * @type {Object<string, Object<number, [number, number]>>}
   */
  var STORE_HOURS = {
    dc: {
      0: [11, 26], // Sun  11 AM – 2 AM (next day)
      1: [11, 27], // Mon  11 AM – 3 AM
      2: [11, 27], // Tue
      3: [11, 27], // Wed
      4: [11, 27], // Thu
      5: [11, 29], // Fri  11 AM – 5 AM
      6: [11, 29], // Sat  11 AM – 5 AM
    },
    va: {
      0: [11, 21], // Sun  11 AM – 9 PM
      1: [11, 22], // Mon  11 AM – 10 PM
      2: [11, 22], // Tue
      3: [11, 22], // Wed
      4: [11, 22], // Thu
      5: [11, 23], // Fri  11 AM – 11 PM
      6: [11, 23], // Sat  11 AM – 11 PM
    },
  };

  /**
   * Returns the open/late/closed state for a given store schedule.
   *
   * @param {string} schedType - "dc" or "va"
   * @return {{ isOpen: boolean, isLate: boolean }}
   */
  function getOpenStatus(schedType) {
    var now = new Date();
    var h = now.getHours();
    var day = now.getDay();
    var sched = STORE_HOURS[schedType] || STORE_HOURS.dc;

    // Hours before 6 AM are treated as the previous day's overnight window.
    var isEarlyMorning = h < 6;
    var checkDay = isEarlyMorning ? (day === 0 ? 6 : day - 1) : day;
    var checkHour = isEarlyMorning ? h + 24 : h;

    var hours = sched[checkDay];
    var open = hours[0];
    var close = hours[1];
    var isOpen = checkHour >= open && checkHour < close;
    var isLate = isOpen && checkHour >= 22;

    return { isOpen: isOpen, isLate: isLate };
  }

  /**
   * Updates a single badge element's class and text to reflect live status.
   *
   * @param {HTMLElement} el - The .duccinis-location-card__badge element.
   */
  function updateBadge(el) {
    var schedType = el.dataset.storeHours || 'dc';
    var textEl = el.querySelector('.duccinis-location-card__badge-text');
    var status = getOpenStatus(schedType);

    el.classList.remove('open-badge--open', 'open-badge--late', 'open-badge--closed');

    if (status.isOpen && status.isLate) {
      el.classList.add('open-badge--late');
      if (textEl) { textEl.textContent = 'Open Late'; }
    }
    else if (status.isOpen) {
      el.classList.add('open-badge--open');
      if (textEl) { textEl.textContent = 'Open Now'; }
    }
    else {
      el.classList.add('open-badge--closed');
      if (textEl) { textEl.textContent = 'Closed'; }
    }
  }

  /**
   * Live open/closed store badge behavior.
   *
   * Runs once per attach; idempotent via data-hours-done guard.
   */
  Drupal.behaviors.storeHoursBadge = {
    attach(context) {
      var badges = context.querySelectorAll(
        '[data-store-hours]:not([data-hours-done])',
      );
      badges.forEach(function (el) {
        el.dataset.hoursDone = '1';
        updateBadge(el);
      });
    },
  };
}(Drupal));
