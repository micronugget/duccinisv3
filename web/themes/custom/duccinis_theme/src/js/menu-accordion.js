(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.menuAccordion = {
    attach: function (context) {
      once('menu-accordion', '.menu-accordion', context).forEach(function () {
        // Bootstrap 5 accordion behavior is driven by data attributes.
        // This behavior is intentionally minimal as a future hook point.
      });
    }
  };
})(Drupal, once);
