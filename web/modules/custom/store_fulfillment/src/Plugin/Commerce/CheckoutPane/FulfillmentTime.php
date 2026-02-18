<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Radios;
use Drupal\Core\Url;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 *   wrapper_element = "container",
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
   * The delivery radius validator service.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  protected DeliveryRadiusValidator $deliveryRadiusValidator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->storeResolver = $container->get('store_resolver.current_store');
    $instance->hoursValidator = $container->get('store_resolver.hours_validator');
    $instance->orderValidator = $container->get('store_fulfillment.order_validator');
    $instance->configFactory = $container->get('config.factory');
    $instance->deliveryRadiusValidator = $container->get('store_fulfillment.delivery_radius_validator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $store = $this->storeResolver->getCurrentStore() ?? $this->order->getStore();

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

    // Get previously selected fulfillment method if any.
    $default_fulfillment_method = $this->order->getData('fulfillment_method') ?? 'pickup';

    // Build pickup label with store address.
    $pickup_label = $this->t('Pickup at store');
    if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $address = $store->get('address')->first();
      $address_parts = array_filter([
        $address->getAddressLine1(),
        $address->getLocality(),
        $address->getAdministrativeArea(),
        $address->getPostalCode(),
      ]);
      if ($address_parts) {
        $pickup_label = $this->t('Pickup at @address', [
          '@address' => implode(', ', $address_parts),
        ]);
      }
    }

    // Resolve customer delivery address and build dynamic delivery label.
    $delivery_label = $this->t('Delivery to address');
    $customer_address = $this->resolveCustomerAddress();
    $delivery_radius_result = NULL;
    if ($customer_address) {
      $customer_address_parts = array_filter([
        $customer_address->getAddressLine1(),
        $customer_address->getLocality(),
        $customer_address->getAdministrativeArea(),
        $customer_address->getPostalCode(),
      ]);
      if ($customer_address_parts) {
        $delivery_label = $this->t('Delivery to @address', [
          '@address' => implode(', ', $customer_address_parts),
        ]);
        // Validate against delivery radius.
        $delivery_radius_result = $this->deliveryRadiusValidator->validateDeliveryAddress($store, $customer_address);
      }
    }

    // Add fulfillment method selection as pill-toggle.
    $pane_form['fulfillment_method'] = [
      '#type' => 'radios',
      '#options' => [
        'pickup' => $this->t('🏪 Pickup'),
        'delivery' => $this->t('🚗 Delivery'),
      ],
      '#default_value' => $default_fulfillment_method,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxRefreshPane'],
        'wrapper' => 'fulfillment-time-wrapper',
        'event' => 'change',
      ],
      '#weight' => -20,
      '#attributes' => [
        'class' => ['pill-toggle'],
      ],
    ];

    // Wrapper for AJAX updates.
    $pane_form['#prefix'] = '<div id="fulfillment-time-wrapper">';
    $pane_form['#suffix'] = '</div>';

    // Get current selection (may be from AJAX or form state).
    $selected_method = $form_state->getValue(['fulfillment_time', 'fulfillment_method']) ?? $default_fulfillment_method;

    // Show contextual address below the pill toggle.
    if ($selected_method === 'pickup') {
      $pane_form['fulfillment_address'] = [
        '#markup' => $this->buildPickupAddressCard($store, $is_open),
        '#weight' => -18,
      ];
    }
    elseif ($selected_method === 'delivery' && $customer_address) {
      $pane_form['fulfillment_address'] = [
        '#markup' => '<div class="fulfillment-address"><span class="addr-icon">📍</span> ' . $delivery_label . '</div>',
        '#weight' => -18,
      ];
    }

    // Show out-of-radius notice with pickup information.
    if ($delivery_radius_result !== NULL && !$delivery_radius_result['valid']) {
      $pane_form['out_of_radius_notice'] = [
        '#markup' => $this->buildOutOfRadiusMessage($store, $delivery_radius_result),
        '#weight' => -19,
      ];
    }

    // Validate delivery address if delivery is selected.
    if ($selected_method === 'delivery') {
      $validation_message = $this->validateDeliveryRadius($store, $form_state, $complete_form);
      if ($validation_message) {
        $pickup_suggestion = $this->buildPickupSuggestion($store);
        $full_message = $validation_message;
        if ($pickup_suggestion) {
          $full_message .= '<br>' . $pickup_suggestion;
        }
        $pane_form['delivery_validation_message'] = [
          '#markup' => '<div class="messages messages--error">' . $full_message . '</div>',
          '#weight' => -15,
        ];
      }
    }

    // When? label + ASAP/Schedule pill toggle.
    $pane_form['when_row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['when-row'],
      ],
      '#weight' => -10,
    ];
    $pane_form['when_row']['when_label'] = [
      '#markup' => '<span class="when-label">' . $this->t('When?') . '</span>',
    ];
    $pane_form['when_row']['fulfillment_type'] = [
      '#type' => 'radios',
      '#options' => [
        'asap' => $this->t('ASAP'),
        'scheduled' => $this->t('Schedule'),
      ],
      '#default_value' => $is_open ? 'asap' : 'scheduled',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['pill-toggle', 'pill-toggle--sm'],
      ],
      '#parents' => ['fulfillment_time', 'fulfillment_type'],
    ];

    // Disable ASAP option if store is closed.
    if (!$is_open) {
      $pane_form['when_row']['fulfillment_type']['asap'] = [
        '#disabled' => TRUE,
        '#description' => $this->t('Not available - store is closed'),
      ];
    }

    // Determine if schedule section should be open initially.
    $default_type = $is_open ? 'asap' : 'scheduled';
    $current_type = $form_state->getValue(['fulfillment_time', 'fulfillment_type']) ?? $default_type;
    $is_scheduled = ($current_type === 'scheduled');

    // Generate time slot options using configuration.
    $time_slots = $this->generateTimeSlots($store, $is_open);

    if (!empty($time_slots)) {
      $time_slots_classes = ['time-slots'];
      if ($is_scheduled) {
        $time_slots_classes[] = 'open';
      }

      $pane_form['scheduled_time'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select a time slot'),
        '#title_display' => 'invisible',
        '#options' => $time_slots,
        '#default_value' => $this->order->getData('scheduled_time'),
        '#required' => FALSE,
        '#process' => [
          [Radios::class, 'processRadios'],
          [self::class, 'processTimeSlotChipGrid'],
        ],
        '#time_slot_timezone' => $store->getTimezone(),
        '#weight' => 0,
        '#prefix' => '<div class="' . implode(' ', $time_slots_classes) . '" id="time-slots-wrapper">',
        '#suffix' => '</div>',
      ];
    }
    else {
      $time_slots_open = $is_scheduled ? ' open' : '';
      $pane_form['scheduled_time'] = [
        '#markup' => '<div class="time-slots' . $time_slots_open . '" id="time-slots-wrapper"><p class="time-slots__empty">' . $this->t('No time slots available') . '</p></div>',
        '#weight' => 0,
      ];
    }

    // Attach the time slot chip grid library.
    $pane_form['#attached']['library'][] = 'store_fulfillment/time_slots';

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
  public function ajaxRefreshPane(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#fulfillment-time-wrapper', $form['fulfillment_time']));
    // Also refresh the delivery_address pane so it appears/disappears
    // when the user toggles between pickup and delivery.
    if (isset($form['delivery_address'])) {
      $response->addCommand(new ReplaceCommand('#delivery-address-wrapper', $form['delivery_address']));
    }
    // Refresh the payment_information pane because billing_information is
    // conditionally removed for delivery orders via hook_form_alter.
    if (isset($form['payment_information'])) {
      $response->addCommand(new ReplaceCommand('#edit-payment-information', $form['payment_information']));
    }
    return $response;
  }

  /**
   * #process callback: Groups radio options into day-labeled chip grids.
   *
   * Runs after Radios::processRadios() to restructure the flat radio list
   * into day-grouped containers with chip styling for each slot.
   *
   * @param array $element
   *   The radios form element with radio children already created.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The modified element with day-grouped chip grids.
   */
  public static function processTimeSlotChipGrid(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $timezone = $element['#time_slot_timezone'] ?? 'America/New_York';
    $tz = new \DateTimeZone($timezone);
    $now = new \DateTime('now', $tz);
    $today_str = $now->format('Y-m-d');
    $tomorrow_str = (clone $now)->modify('+1 day')->format('Y-m-d');

    // Collect radio children grouped by date.
    $groups = [];
    $child_keys = [];
    foreach (Element::children($element) as $key) {
      $date = substr((string) $key, 0, 10);
      $groups[$date][$key] = $element[$key];
      $child_keys[] = $key;
    }

    // Remove original children from element.
    foreach ($child_keys as $key) {
      unset($element[$key]);
    }

    // Re-add as grouped day containers with chip styling.
    foreach ($groups as $date => $radios) {
      $dt = \DateTime::createFromFormat('Y-m-d', $date, $tz);
      if (!$dt) {
        continue;
      }

      if ($date === $today_str) {
        $label = t('Today — @date', ['@date' => $dt->format('D, M j')]);
      }
      elseif ($date === $tomorrow_str) {
        $label = t('Tomorrow — @date', ['@date' => $dt->format('D, M j')]);
      }
      else {
        $label = $dt->format('D, M j');
      }

      $day_key = 'day_' . str_replace('-', '_', $date);
      $element[$day_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['time-slots-day']],
      ];
      $element[$day_key]['heading'] = [
        '#markup' => '<div class="time-slots-day-label">' . $label . '</div>',
      ];
      $element[$day_key]['grid'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['time-slots-grid']],
      ];

      foreach ($radios as $key => $radio) {
        if (!isset($radio['#wrapper_attributes'])) {
          $radio['#wrapper_attributes'] = [];
        }
        if (!isset($radio['#wrapper_attributes']['class'])) {
          $radio['#wrapper_attributes']['class'] = [];
        }
        $radio['#wrapper_attributes']['class'][] = 'time-slot-chip';
        $element[$day_key]['grid'][$key] = $radio;
      }
    }

    return $element;
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
    // Resolve delivery address using the same logic as resolveCustomerAddress.
    $address = $this->resolveCustomerAddress();

    if (!$address) {
      // No address available yet (billing info may not have been submitted).
      // Return early to allow form to continue.
      return NULL;
    }

    // Validate the address against the delivery radius.
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
    // Show this pane if a store is selected via cookie or the order already
    // has a store assigned (checkout always has an order with a store).
    return $this->storeResolver->hasCurrentStore() || $this->order->getStoreId();
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);

    if (empty($values) || !is_array($values)) {
      return;
    }

    $store = $this->storeResolver->getCurrentStore() ?? $this->order->getStore();
    if (!$store) {
      $form_state->setError($pane_form, $this->t('Please select a store before continuing.'));
      return;
    }

    // Validate fulfillment method is selected.
    if (empty($values['fulfillment_method'])) {
      $form_state->setError($pane_form['fulfillment_method'], $this->t('Please select a fulfillment method.'));
      return;
    }

    // If delivery is selected, validate the delivery address.
    if ($values['fulfillment_method'] === 'delivery') {
      $error_message = $this->validateDeliveryRadius($store, $form_state, $complete_form);
      if ($error_message) {
        $pickup_suggestion = $this->buildPickupSuggestion($store);
        if ($pickup_suggestion) {
          $error_message .= ' ' . $pickup_suggestion;
        }
        $form_state->setError($pane_form['fulfillment_method'], $error_message);
      }
    }

    $fulfillment_type = $values['fulfillment_type'] ?? NULL;

    // Validate ASAP orders.
    if ($fulfillment_type === 'asap') {
      if (!$this->orderValidator->isImmediateOrderAllowed($store)) {
        $form_state->setError(
          $pane_form['when_row']['fulfillment_type'],
          $this->t('Store is currently closed. Please schedule your order for a future time.')
        );
      }
    }

    // Validate scheduled orders.
    if ($fulfillment_type === 'scheduled') {
      if (empty($values['scheduled_time'])) {
        $form_state->setError(
          $pane_form['scheduled_time'],
          $this->t('Please select a fulfillment time.')
        );
        return;
      }

      // Temporarily set order data for validation.
      $this->order->setData('fulfillment_type', 'scheduled');
      $this->order->setData('scheduled_time', $values['scheduled_time']);

      // Validate the scheduled time.
      $validation_result = $this->orderValidator->validateFulfillmentTime(
        $this->order,
        $values['scheduled_time']
      );

      if (!$validation_result['valid']) {
        $form_state->setError(
          $pane_form['scheduled_time'],
          $validation_result['message']
        );
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
      else {
        // Clear stale scheduled_time when switching to asap.
        $this->order->unsetData('scheduled_time');
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
    $config = $this->configFactory->get('store_fulfillment.settings');
    $timezone = $store->getTimezone();
    $now = new \DateTime('now', new \DateTimeZone($timezone));

    // Get configuration values.
    $min_advance_notice = $config->get('minimum_advance_notice') ?? 30;
    $max_scheduling_window = $config->get('maximum_scheduling_window') ?? 14;
    $time_slot_interval = $config->get('time_slot_interval') ?? 15;

    // DEBUG: Log configuration
    \Drupal::logger('store_fulfillment')->debug('generateTimeSlots - Config: min_advance=@min, max_window=@max, interval=@int, is_open=@open', [
      '@min' => $min_advance_notice,
      '@max' => $max_scheduling_window,
      '@int' => $time_slot_interval,
      '@open' => $is_open ? 'true' : 'false',
    ]);

    // Determine start time.
    if ($is_open) {
      $start_time = clone $now;
      $start_time->modify("+{$min_advance_notice} minutes");
    }
    else {
      $start_time = $this->orderValidator->getNextAvailableSlot($store);
      if (!$start_time) {
        // If no available time found, start from tomorrow.
        $start_time = clone $now;
        $start_time->modify('+1 day');
        $start_time->setTime(9, 0, 0);
      }
    }

    // DEBUG: Log start time
    \Drupal::logger('store_fulfillment')->debug('generateTimeSlots - Start time: @time', [
      '@time' => $start_time->format('Y-m-d H:i:s'),
    ]);

    // Generate slots for the configured window.
    $end_date = clone $now;
    $end_date->modify("+{$max_scheduling_window} days");

    $current = clone $start_time;
    // Round to next interval.
    $minutes = (int) $current->format('i');
    $rounded_minutes = (int) (ceil($minutes / $time_slot_interval) * $time_slot_interval);
    if ($rounded_minutes >= 60) {
      $current->modify('+1 hour');
      $rounded_minutes = 0;
    }
    $current->setTime((int) $current->format('H'), $rounded_minutes, 0);

    // Generate slots, filtering by store hours.
    $slot_count = 0;
    $max_slots = 200;
    $checked_count = 0;

    while ($current <= $end_date && $slot_count < $max_slots) {
      $checked_count++;
      // Check if this time slot falls within store hours.
      if ($this->isTimeWithinStoreHours($store, $current)) {
        $key = $current->format('Y-m-d H:i:s');
        $display = rtrim($current->format('g:ia'), 'm');
        $slots[$key] = $display;
        $slot_count++;
      }

      // Increment by configured interval.
      $current->modify("+{$time_slot_interval} minutes");

      // Safety: prevent infinite loop
      if ($checked_count > 10000) {
        \Drupal::logger('store_fulfillment')->error('generateTimeSlots - Too many iterations, breaking loop');
        break;
      }
    }

    // DEBUG: Log results
    \Drupal::logger('store_fulfillment')->debug('generateTimeSlots - Generated @count slots from @checked checks', [
      '@count' => $slot_count,
      '@checked' => $checked_count,
    ]);

    if (empty($slots)) {
      return [];
    }

    return $slots;
  }

  /**
   * Checks if a time falls within store operating hours.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param \DateTime $datetime
   *   The datetime to check.
   *
   * @return bool
   *   TRUE if within hours, FALSE otherwise.
   */
  protected function isTimeWithinStoreHours($store, \DateTime $datetime): bool {
    // If store hours field doesn't exist, assume always open (9 AM - 9 PM).
    if (!$store->hasField('store_hours')) {
      $time = $datetime->format('H:i');
      return $time >= '09:00' && $time < '21:00';
    }

    $hours_field = $store->get('store_hours');
    // If store hours field exists but is empty, assume always open (9 AM - 9 PM).
    if ($hours_field->isEmpty()) {
      $time = $datetime->format('H:i');
      return $time >= '09:00' && $time < '21:00';
    }

    $day = strtolower($datetime->format('l'));
    $time = $datetime->format('H:i');

    foreach ($hours_field as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        // Handle multi-line format: split by newlines first.
        $lines = preg_split('/\r\n|\r|\n/', $value);
        foreach ($lines as $line) {
          $line = trim($line);
          if (empty($line)) {
            continue;
          }

          $parts = explode('|', $line);
          if (count($parts) === 3) {
            [$hour_day, $open_time, $close_time] = $parts;
            if (strtolower($hour_day) === $day) {
              // Check if time is within business hours.
              if ($close_time < $open_time) {
                // Overnight hours. For overnight, opening is >= and closing is <.
                if ($time >= $open_time || $time < $close_time) {
                  return TRUE;
                }
              }
              else {
                // Normal hours. Use < for close time since store hours mean "open until".
                if ($time >= $open_time && $time < $close_time) {
                  return TRUE;
                }
              }
            }
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Resolves the customer's delivery address from available sources.
   *
   * Checks order shipments, billing profile, and user's customer profile.
   *
   * @return \Drupal\address\AddressInterface|null
   *   The customer's address, or NULL if not available.
   */
  protected function resolveCustomerAddress() {
    // Check dedicated delivery address profile (from DeliveryAddress pane).
    $delivery_profile_id = $this->order->getData('delivery_address_profile');
    if ($delivery_profile_id) {
      $profile = $this->entityTypeManager->getStorage('profile')->load($delivery_profile_id);
      if ($profile && $profile->hasField('address') && !$profile->get('address')->isEmpty()) {
        return $profile->get('address')->first();
      }
    }

    // Check order shipments for shipping profile address.
    if ($this->order->hasField('shipments') && !$this->order->get('shipments')->isEmpty()) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = $this->order->get('shipments')->first()->entity;
      if ($shipment && $shipment->hasField('shipping_profile')) {
        $profile = $shipment->getShippingProfile();
        if ($profile && $profile->hasField('address') && !$profile->get('address')->isEmpty()) {
          return $profile->get('address')->first();
        }
      }
    }

    // Check order billing profile.
    $billing_profile = $this->order->getBillingProfile();
    if ($billing_profile && $billing_profile->hasField('address') && !$billing_profile->get('address')->isEmpty()) {
      return $billing_profile->get('address')->first();
    }

    // Check logged-in user's default customer profile.
    $customer = $this->order->getCustomer();
    if ($customer && !$customer->isAnonymous()) {
      $profiles = $this->entityTypeManager->getStorage('profile')->loadByProperties([
        'uid' => $customer->id(),
        'type' => 'customer',
        'is_default' => TRUE,
        'status' => TRUE,
      ]);
      if ($profiles) {
        $profile = reset($profiles);
        if ($profile->hasField('address') && !$profile->get('address')->isEmpty()) {
          return $profile->get('address')->first();
        }
      }
    }

    return NULL;
  }

  /**
   * Builds a comprehensive out-of-radius message with pickup details.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param array $validation_result
   *   The delivery radius validation result.
   *
   * @return string
   *   HTML markup for the out-of-radius message.
   */
  protected function buildOutOfRadiusMessage($store, array $validation_result): string {
    $parts = [$validation_result['message']];
    $pickup_suggestion = $this->buildPickupSuggestion($store);
    if ($pickup_suggestion) {
      $parts[] = $pickup_suggestion;
    }
    return '<div class="alert alert-danger">' . implode('<br>', $parts) . '</div>';
  }

  /**
   * Builds a rich pickup address card with open/closed status.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param bool $is_open
   *   Whether the store is currently open.
   *
   * @return string
   *   HTML markup for the pickup address card.
   */
  protected function buildPickupAddressCard($store, bool $is_open): string {
    $street = '';
    $locality_line = '';

    if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
      $address = $store->get('address')->first();
      $street = $address->getAddressLine1() ?: '';
      $locality_parts = array_filter([
        $address->getLocality(),
        $address->getAdministrativeArea(),
        $address->getPostalCode(),
      ]);
      $locality_line = implode(', ', $locality_parts);
    }

    // Open/closed indicator.
    $status_class = $is_open ? 'open-dot--open' : 'open-dot--closed';
    $status_text = $is_open ? $this->t('Open now') : $this->t('Closed');

    // Build today's hours if available.
    $hours = $this->getStoreTodayHours($store);
    $hours_fragment = $hours ? ' &middot; ' . $this->t('Today @hours', ['@hours' => $hours]) : '';

    $html = '<div class="fulfillment-address">';
    $html .= '<span class="addr-icon">📍</span>';
    $html .= '<div class="fulfillment-address__info">';
    $html .= '<div class="fulfillment-address__street">' . htmlspecialchars($street, ENT_QUOTES, 'UTF-8') . '</div>';

    // Status dot + locality.
    $html .= '<div class="fulfillment-address__meta">';
    $html .= '<span class="open-dot ' . $status_class . '"></span>';
    $html .= '<span>' . $status_text . '</span>';
    if ($locality_line) {
      $html .= ' &middot; ' . htmlspecialchars($locality_line, ENT_QUOTES, 'UTF-8');
    }
    $html .= $hours_fragment;
    $html .= '</div>';

    $html .= '</div>';

    // "Change" link to store selection.
    $change_url = Url::fromRoute('store_resolver.select_store')->toString();
    $html .= '<a href="' . htmlspecialchars($change_url, ENT_QUOTES, 'UTF-8') . '" class="fulfillment-address__change">' . $this->t('Change') . '</a>';

    $html .= '</div>';

    return $html;
  }

  /**
   * Builds a pickup suggestion with store address and hours.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return string|null
   *   Pickup suggestion text, or NULL if store address unavailable.
   */
  protected function buildPickupSuggestion($store): ?string {
    if (!$store->hasField('address') || $store->get('address')->isEmpty()) {
      return NULL;
    }

    $address = $store->get('address')->first();
    $address_parts = array_filter([
      $address->getAddressLine1(),
      $address->getLocality(),
      $address->getAdministrativeArea(),
      $address->getPostalCode(),
    ]);
    if (!$address_parts) {
      return NULL;
    }

    $suggestion = (string) $this->t('You can pick up your order at @address instead.', [
      '@address' => implode(', ', $address_parts),
    ]);
    $today_hours = $this->getStoreTodayHours($store);
    if ($today_hours) {
      $suggestion .= ' ' . (string) $this->t("Today's hours: @hours.", [
        '@hours' => $today_hours,
      ]);
    }

    return $suggestion;
  }

  /**
   * Gets today's store hours formatted for display.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return string|null
   *   Formatted hours string (e.g., "9:00 AM - 5:00 PM"), or NULL.
   */
  protected function getStoreTodayHours($store): ?string {
    if (!$store->hasField('store_hours') || $store->get('store_hours')->isEmpty()) {
      return NULL;
    }

    $timezone = $store->getTimezone();
    $now = new \DateTime('now', new \DateTimeZone($timezone));
    $today = strtolower($now->format('l'));

    foreach ($store->get('store_hours') as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        $lines = preg_split('/\r\n|\r|\n/', $value);
        foreach ($lines as $line) {
          $line = trim($line);
          if (empty($line)) {
            continue;
          }
          $parts = explode('|', $line);
          if (count($parts) === 3) {
            [$day, $open_time, $close_time] = $parts;
            if (strtolower($day) === $today) {
              $open = \DateTime::createFromFormat('H:i', $open_time);
              $close = \DateTime::createFromFormat('H:i', $close_time);
              if ($open && $close) {
                return $open->format('g:i A') . ' - ' . $close->format('g:i A');
              }
              return $open_time . ' - ' . $close_time;
            }
          }
        }
      }
    }

    return NULL;
  }

}
