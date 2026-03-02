/**
 * @file
 * Fires GA4 / GTM dataLayer events when a user clicks a done-step back-link
 * in the checkout progress bar.
 *
 * Event pushed to window.dataLayer:
 *   {
 *     event:     'checkout_step_back',
 *     from_step: 'review',          // the step the user is leaving
 *     to_step:   'order_information', // the step they navigated back to
 *     order_id:  '27',              // Commerce order ID (string)
 *   }
 *
 * Data attributes emitted by
 * duccinis_1984_olympics_preprocess_commerce_checkout_form() in commerce.theme:
 *   data-funnel-event, data-from-step, data-to-step, data-order-id
 */

((Drupal, once) => {
  Drupal.behaviors.checkoutProgressAnalytics = {
    attach(context) {
      once('checkout-progress-analytics', '[data-funnel-event]', context)
        .forEach((el) => {
          el.addEventListener('click', () => {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
              event: el.dataset.funnelEvent,
              from_step: el.dataset.fromStep,
              to_step: el.dataset.toStep,
              order_id: el.dataset.orderId,
            });
          });
        });
    },
  };
})(Drupal, once);
