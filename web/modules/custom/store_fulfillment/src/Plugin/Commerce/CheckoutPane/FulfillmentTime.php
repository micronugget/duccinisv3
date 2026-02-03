<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\store_resolver\StoreResolver;
use Drupal\store_resolver\StoreHoursValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a checkout pane for selecting fulfillment time.
 *
 * @CommerceCheckoutPane(
 *   id = "fulfillment_time",
 *   label = @Translation("Fulfillment Time"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class FulfillmentTime extends CheckoutPaneBase {

  /**
   * The store resolver service.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected $storeResolver;

  /**
   * The store hours validator service.
   *
   * @var \Drupal\store_resolver\StoreHoursValidator
   */
  protected $hoursValidator;

  /**
   * The delivery radius validator service.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  protected DeliveryRadiusValidator $deliveryRadiusValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->storeResolver = $container->get('store_resolver.current_store');
    $instance->hoursValidator = $container->get('store_resolver.hours_validator');
    $instance->deliveryRadiusValidator = $container->get('store_fulfillment.delivery_radius_validator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $store = $this->storeResolver->getCurrentStore();

    if (!$store) {
      $pane_form['message'] = [
        '#markup' => $this->t('Please select a store before continuing.'),
      ];
      return $pane_form;
    }

    $is_open = $this->hoursValidator->isStoreOpen($store);

    // Get previously selected fulfillment method if any.
    $default_fulfillment_method = $this->order->getData('fulfillment_method') ?? 'pickup';

    // Add fulfillment method selection (delivery vs pickup).
    $pane_form['fulfillment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('How would you like to receive your order?'),
      '#options' => [
        'pickup' => $this->t('Pickup at store'),
        'delivery' => $this->t('Delivery to address'),
      ],
      '#default_value' => $default_fulfillment_method,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxRefreshPane'],
        'wrapper' => 'fulfillment-time-wrapper',
        'event' => 'change',
      ],
      '#weight' => -20,
    ];

    // Wrapper for AJAX updates.
    $pane_form['#prefix'] = '<div id="fulfillment-time-wrapper">';
    $pane_form['#suffix'] = '</div>';

    // Get current selection (may be from AJAX or form state).
    $selected_method = $form_state->getValue(['fulfillment_time', 'fulfillment_method']) ?? $default_fulfillment_method;

    // Validate delivery address if delivery is selected.
    if ($selected_method === 'delivery') {
      $validation_message = $this->validateDeliveryRadius($store, $form_state, $complete_form);
      if ($validation_message) {
        $pane_form['delivery_validation_message'] = [
          '#markup' => '<div class="messages messages--error">' . $validation_message . '</div>',
          '#weight' => -15,
        ];
      }
    }

    $pane_form['fulfillment_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('When would you like your order?'),
      '#options' => [
        'asap' => $this->t('As soon as possible'),
        'scheduled' => $this->t('Schedule for later'),
      ],
      '#default_value' => $is_open ? 'asap' : 'scheduled',
      '#required' => TRUE,
      '#weight' => -10,
    ];

    if (!$is_open) {
      $pane_form['store_closed_notice'] = [
        '#markup' => '<div class="messages messages--warning">' .
          $this->t('The selected store is currently closed. Please schedule your order for a future time.') .
          '</div>',
        '#weight' => -12,
      ];
      // Force scheduled option when store is closed.
      $pane_form['fulfillment_type']['#default_value'] = 'scheduled';
      $pane_form['fulfillment_type']['asap']['#disabled'] = TRUE;
    }

    // Generate time slot options.
    $time_slots = $this->generateTimeSlots($store, $is_open);

    $pane_form['scheduled_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Select time'),
      '#options' => $time_slots,
      '#states' => [
        'visible' => [
          ':input[name="fulfillment_time[fulfillment_type]"]' => ['value' => 'scheduled'],
        ],
        'required' => [
          ':input[name="fulfillment_time[fulfillment_type]"]' => ['value' => 'scheduled'],
        ],
      ],
      '#weight' => 0,
    ];

    return $pane_form;
  }

  /**
   * AJAX callback to refresh the pane when fulfillment method changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The fulfillment time pane form element.
   */
  public function ajaxRefreshPane(array $form, FormStateInterface $form_state): array {
    return $form['fulfillment_time'];
  }

  /**
   * Validates delivery address is within store radius.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form array.
   *
   * @return string|null
   *   Error message if validation fails, NULL otherwise.
   */
  protected function validateDeliveryRadius($store, FormStateInterface $form_state, array $complete_form): ?string {
    // Try to get shipping address from the shipping information pane.
    $shipping_profile = NULL;

    // Check if order has shipments with shipping profile.
    if ($this->order->hasField('shipments') && !$this->order->get('shipments')->isEmpty()) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $this->order->get('shipments')->first()->entity;
      if ($shipment && $shipment->hasField('shipping_profile')) {
        $shipping_profile = $shipment->getShippingProfile();
      }
    }

    // If no shipping profile on order yet, try to get from form state.
    if (!$shipping_profile) {
      // The shipping pane may not have been submitted yet.
      // Return early to allow form to continue.
      return NULL;
    }

    // Get address from shipping profile.
    if (!$shipping_profile->hasField('address') || $shipping_profile->get('address')->isEmpty()) {
      return (string) $this->t('Please provide a delivery address.');
    }

    /** @var \Drupal\address\AddressInterface $address */
    $address = $shipping_profile->get('address')->first();

    // Validate the address.
    $validation_result = $this->deliveryRadiusValidator->validateDeliveryAddress($store, $address);

    if (!$validation_result['valid']) {
      return $validation_result['message'];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // Only show this pane if a store has been selected.
    return $this->storeResolver->hasCurrentStore();
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    if (empty($values) || !is_array($values)) {
      return;
    }

    // Validate fulfillment method is selected.
    if (empty($values['fulfillment_method'])) {
      $form_state->setError($pane_form['fulfillment_method'], $this->t('Please select a fulfillment method.'));
      return;
    }

    // If delivery is selected, validate the delivery address.
    if ($values['fulfillment_method'] === 'delivery') {
      $store = $this->storeResolver->getCurrentStore();
      $error_message = $this->validateDeliveryRadius($store, $form_state, $complete_form);
      if ($error_message) {
        $form_state->setError($pane_form['fulfillment_method'], $error_message);
      }
    }

    // If scheduled is selected, ensure a time is chosen.
    if (isset($values['fulfillment_type']) && $values['fulfillment_type'] === 'scheduled') {
      if (empty($values['scheduled_time'])) {
        $form_state->setError($pane_form['scheduled_time'], $this->t('Please select a fulfillment time.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    // Guard against null values (pane might not have been shown/submitted).
    if (empty($values) || !is_array($values)) {
      return;
    }

    // Store fulfillment method in order data.
    if (isset($values['fulfillment_method'])) {
      $this->order->setData('fulfillment_method', $values['fulfillment_method']);
    }

    // Store fulfillment time in order data.
    if (isset($values['fulfillment_type'])) {
      $this->order->setData('fulfillment_type', $values['fulfillment_type']);

      if ($values['fulfillment_type'] === 'scheduled' && !empty($values['scheduled_time'])) {
        $this->order->setData('scheduled_time', $values['scheduled_time']);
      }
    }
  }

  /**
   * Generates time slot options for scheduling.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param bool $is_open
   *   Whether the store is currently open.
   *
   * @return array
   *   Array of time slot options.
   */
  protected function generateTimeSlots($store, $is_open) {
    $slots = [];
    $timezone = $store->getTimezone();
    $now = new \DateTime('now', new \DateTimeZone($timezone));

    // Start from current time + 30 minutes if open, or next opening if closed.
    if ($is_open) {
      $start_time = clone $now;
      $start_time->modify('+30 minutes');
    }
    else {
      $start_time = $this->hoursValidator->getNextAvailableTime($store);
    }

    // Generate slots for the next 7 days.
    $end_date = clone $start_time;
    $end_date->modify('+7 days');

    $current = clone $start_time;
    // Round to next 15-minute interval.
    $minutes = (int) $current->format('i');
    $rounded_minutes = ceil($minutes / 15) * 15;
    $current->setTime((int) $current->format('H'), $rounded_minutes, 0);

    while ($current <= $end_date) {
      $key = $current->format('Y-m-d H:i:s');
      $display = $current->format('l, F j, Y - g:i A');
      $slots[$key] = $display;

      // Increment by 15 minutes.
      $current->modify('+15 minutes');

      // Limit to reasonable number of options.
      if (count($slots) >= 100) {
        break;
      }
    }

    return $slots;
  }

}
