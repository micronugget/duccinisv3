<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\store_fulfillment\OrderValidator;
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
   * The order validator service.
   *
   * @var \Drupal\store_fulfillment\OrderValidator
   */
  protected $orderValidator;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->storeResolver = $container->get('store_resolver.current_store');
    $instance->hoursValidator = $container->get('store_resolver.hours_validator');
    $instance->deliveryRadiusValidator = $container->get('store_fulfillment.delivery_radius_validator');
    $instance->orderValidator = $container->get('store_fulfillment.order_validator');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $store = $this->storeResolver->getCurrentStore();

    if (!$store) {
      $pane_form['message'] = [
        '#markup' => '<div class="alert alert-warning">' . $this->t('Please select a store before continuing.') . '</div>',
      ];
      return $pane_form;
    }

    $is_open = $this->orderValidator->isImmediateOrderAllowed($store);
    $config = $this->configFactory->get('store_fulfillment.settings');

    // Show store status message.
    if (!$is_open) {
      $next_available = $this->orderValidator->getNextAvailableSlot($store);
      $message = $this->t('Store is currently closed. Please schedule your order for a future time.');
      if ($next_available) {
        $message = $this->t('Store is currently closed. Next available time: @time', [
          '@time' => $next_available->format('l, F j, Y - g:i A'),
        ]);
      }
      $pane_form['store_closed_notice'] = [
        '#markup' => '<div class="alert alert-warning">' . $message . '</div>',
        '#weight' => -10,
      ];
    }

    // Add fulfillment method selection (Epic #2: Delivery/Pickup choice).
    $order = $this->order;
    $order_data = $order->getData();
    $default_method = $order_data['fulfillment_method'] ?? 'pickup';

    $pane_form['fulfillment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Fulfillment Method'),
      '#options' => [
        'pickup' => $this->t('Pickup at store'),
        'delivery' => $this->t('Delivery to address'),
      ],
      '#default_value' => $default_method,
      '#required' => TRUE,
      '#weight' => -5,
      '#ajax' => [
        'callback' => [$this, 'ajaxRefreshPane'],
        'wrapper' => 'fulfillment-time-wrapper',
      ],
    ];

    $pane_form['fulfillment_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('When do you want your order?'),
      '#options' => [
        'asap' => $this->t('As soon as possible'),
        'scheduled' => $this->t('Schedule for later'),
      ],
      '#default_value' => $order_data['fulfillment_type'] ?? 'asap',
      '#required' => TRUE,
      '#weight' => 0,
    ];

    // Help text for minimum advance notice.
    $min_advance = $config->get('minimum_advance_notice') ?? 30;
    $pane_form['help_text'] = [
      '#markup' => '<div class="help-text">' . $this->t('Orders must be placed at least @minutes minutes in advance.', [
        '@minutes' => $min_advance,
      ]) . '</div>',
      '#weight' => 1,
    ];

    $pane_form['scheduled_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Select time'),
      '#options' => $this->generateTimeSlots($store),
      '#default_value' => $order_data['scheduled_time'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="fulfillment_time[fulfillment_type]"]' => ['value' => 'scheduled'],
        ],
        'required' => [
          ':input[name="fulfillment_time[fulfillment_type]"]' => ['value' => 'scheduled'],
        ],
      ],
      '#weight' => 2,
    ];

    $pane_form['#prefix'] = '<div id="fulfillment-time-wrapper">';
    $pane_form['#suffix'] = '</div>';

    return $pane_form;
  }

  /**
   * AJAX callback to refresh the pane.
   */
  public function ajaxRefreshPane(array &$form, FormStateInterface $form_state) {
    return $form['fulfillment_time'];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue('fulfillment_time');
    $store = $this->storeResolver->getCurrentStore();

    if (!$store) {
      $form_state->setError($pane_form, $this->t('Please select a store first.'));
      return;
    }

    $fulfillment_method = $values['fulfillment_method'] ?? 'pickup';
    $fulfillment_type = $values['fulfillment_type'] ?? 'asap';

    // Epic #2: Validate delivery radius if delivery method is selected.
    if ($fulfillment_method === 'delivery') {
      $this->validateDeliveryRadius($pane_form, $form_state, $store);
    }

    // Epic #1: Validate timing based on store hours and order validator.
    if ($fulfillment_type === 'asap') {
      if (!$this->orderValidator->isImmediateOrderAllowed($store)) {
        $form_state->setError($pane_form['fulfillment_type'], $this->t('ASAP orders are not available at this time. Please schedule your order.'));
      }
    }
    else {
      $scheduled_time = $values['scheduled_time'] ?? NULL;
      if (empty($scheduled_time)) {
        $form_state->setError($pane_form['scheduled_time'], $this->t('Please select a time for your scheduled order.'));
        return;
      }

      $validation = $this->orderValidator->validateFulfillmentTime($store, $scheduled_time);
      if (!$validation['valid']) {
        $form_state->setError($pane_form['scheduled_time'], $validation['message']);
      }
    }
  }

  /**
   * Validates delivery address is within store's delivery radius.
   *
   * Epic #2 functionality.
   */
  protected function validateDeliveryRadius(array &$pane_form, FormStateInterface $form_state, $store) {
    $order = $this->order;
    
    // Get shipping profile with delivery address.
    $shipping_profile = $order->get('shipping_information')->entity ?? NULL;
    if (!$shipping_profile) {
      $form_state->setError($pane_form['fulfillment_method'], $this->t('Please enter your delivery address before selecting delivery.'));
      return;
    }

    $address = $shipping_profile->get('address')->first();
    if (!$address) {
      $form_state->setError($pane_form['fulfillment_method'], $this->t('Please enter a complete delivery address.'));
      return;
    }

    // Validate address is within delivery radius.
    $result = $this->deliveryRadiusValidator->validateDeliveryAddress($store, $address);
    if (!$result['valid']) {
      $form_state->setError($pane_form['fulfillment_method'], $result['message']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue('fulfillment_time');
    $order = $this->order;
    
    $order_data = $order->getData();
    $order_data['fulfillment_method'] = $values['fulfillment_method'] ?? 'pickup';
    $order_data['fulfillment_type'] = $values['fulfillment_type'] ?? 'asap';
    
    if ($values['fulfillment_type'] === 'scheduled') {
      $order_data['scheduled_time'] = $values['scheduled_time'] ?? '';
    }
    else {
      unset($order_data['scheduled_time']);
    }
    
    $order->setData($order_data);
  }

  /**
   * Generates time slot options for scheduling.
   *
   * Epic #1: Config-driven with store hours validation.
   */
  protected function generateTimeSlots($store) {
    $config = $this->configFactory->get('store_fulfillment.settings');
    $min_advance = $config->get('minimum_advance_notice') ?? 30;
    $max_days = $config->get('maximum_scheduling_window') ?? 14;
    $interval = $config->get('time_slot_interval') ?? 15;

    $slots = [];
    $now = new \DateTime('now', new \DateTimeZone($store->getTimezone()));
    $now->modify("+{$min_advance} minutes");
    
    // Round up to next interval.
    $minutes = (int) $now->format('i');
    $remainder = $minutes % $interval;
    if ($remainder > 0) {
      $now->modify('+' . ($interval - $remainder) . ' minutes');
    }
    $now->setTime((int) $now->format('H'), (int) $now->format('i'), 0);

    $end = clone $now;
    $end->modify("+{$max_days} days");

    $current = clone $now;
    while ($current <= $end) {
      // Check if time is within store hours.
      if ($this->isTimeWithinStoreHours($current, $store)) {
        $key = $current->format('Y-m-d H:i:s');
        $label = $current->format('l, F j, Y - g:i A');
        $slots[$key] = $label;
      }
      
      $current->modify("+{$interval} minutes");
    }

    return $slots;
  }

  /**
   * Checks if a given time is within store operating hours.
   *
   * Epic #1 functionality.
   */
  protected function isTimeWithinStoreHours(\DateTime $time, $store): bool {
    $day = strtolower($time->format('l'));
    $time_str = $time->format('H:i');

    try {
      return $this->hoursValidator->isStoreOpen($store, $day, $time_str);
    }
    catch (\Exception $e) {
      // If validation fails, allow the time (fail open).
      return TRUE;
    }
  }

}
