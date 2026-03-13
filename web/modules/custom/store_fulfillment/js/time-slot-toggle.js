/**
 * @file
 * Toggle expand/collapse of the time-slot chip grid.
 *
 * Listens on the fulfillment_type radio buttons (ASAP / Schedule) and toggles
 * the `.open` class on #time-slots-wrapper so the CSS transition runs.
 *
 * Also manages aria-expanded on both the wrapper and the containing fieldset
 * so that assistive technologies announce the expand/collapse state change
 * (WCAG 2.1 AA 4.1.2 — Name, Role, Value).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.timeSlotToggle = {
    attach: function (context) {
      once('time-slot-toggle', '[name="fulfillment_time[fulfillment_type]"]', context)
        .forEach(function (radio) {
          radio.addEventListener('change', function () {
            var isScheduled = this.value === 'scheduled';
            var wrapper = document.getElementById('time-slots-wrapper');
            if (wrapper) {
              wrapper.classList.toggle('open', isScheduled);
              // aria-expanded on the wrapper itself tells AT whether the
              // region is expanded.
              wrapper.setAttribute('aria-expanded', isScheduled ? 'true' : 'false');
            }
            // Also update aria-expanded on the fieldset that contains the
            // ASAP / Schedule pills — it has aria-controls="time-slots-wrapper"
            // set from PHP, so aria-expanded completes the disclosure pattern.
            var fieldset = radio.closest('fieldset');
            if (fieldset) {
              fieldset.setAttribute('aria-expanded', isScheduled ? 'true' : 'false');
            }
          });
        });
    }
  };
})(Drupal, once);
