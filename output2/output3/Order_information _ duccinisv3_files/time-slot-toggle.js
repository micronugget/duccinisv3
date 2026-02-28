/**
 * @file
 * Toggle expand/collapse of the time-slot chip grid.
 *
 * Listens on the fulfillment_type radio buttons (ASAP / Schedule) and toggles
 * the `.open` class on #time-slots-wrapper so the CSS transition runs.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.timeSlotToggle = {
    attach: function (context) {
      once('time-slot-toggle', '[name="fulfillment_time[fulfillment_type]"]', context)
        .forEach(function (radio) {
          radio.addEventListener('change', function () {
            var wrapper = document.getElementById('time-slots-wrapper');
            if (wrapper) {
              wrapper.classList.toggle('open', this.value === 'scheduled');
            }
          });
        });
    }
  };
})(Drupal, once);
