<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\store_resolver\StoreHoursValidator;

/**
 * Service for validating order fulfillment times against store hours.
 */
class OrderValidator {

  /**
   * The store hours validator service.
   *
   * @var \Drupal\store_resolver\StoreHoursValidator
   */
  protected $hoursValidator;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new OrderValidator.
   *
   * @param \Drupal\store_resolver\StoreHoursValidator $hours_validator
   *   The store hours validator service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    StoreHoursValidator $hours_validator,
    TimeInterface $time,
    ConfigFactoryInterface $config_factory
  ) {
    $this->hoursValidator = $hours_validator;
    $this->time = $time;
    $this->configFactory = $config_factory;
  }

  /**
   * Validates fulfillment time for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   * @param int|string|null $requested_time
   *   The requested fulfillment time (timestamp or datetime string).
   *   NULL for immediate orders.
   *
   * @return array
   *   Validation result with keys:
   *   - 'valid': (bool) Whether the time is valid.
   *   - 'message': (string) Error message if invalid.
   */
  public function validateFulfillmentTime(OrderInterface $order, $requested_time = NULL): array {
    $store = $order->getStore();
    if (!$store) {
      return [
        'valid' => FALSE,
        'message' => 'No store associated with order.',
      ];
    }

    $fulfillment_type = $order->getData('fulfillment_type');

    // Validate immediate/ASAP orders.
    if ($fulfillment_type === 'asap' || $requested_time === NULL) {
      if (!$this->isImmediateOrderAllowed($store)) {
        return [
          'valid' => FALSE,
          'message' => 'Store is currently closed. Immediate orders are not allowed.',
        ];
      }
      return ['valid' => TRUE, 'message' => ''];
    }

    // Validate scheduled orders.
    if ($fulfillment_type === 'scheduled' && $requested_time) {
      return $this->validateScheduledTime($store, $requested_time);
    }

    return [
      'valid' => FALSE,
      'message' => 'Invalid fulfillment type or missing requested time.',
    ];
  }

  /**
   * Validates a scheduled fulfillment time.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param int|string $requested_time
   *   The requested time (timestamp or datetime string).
   *
   * @return array
   *   Validation result array.
   */
  protected function validateScheduledTime(StoreInterface $store, $requested_time): array {
    $config = $this->configFactory->get('store_fulfillment.settings');
    $min_advance_notice = $config->get('minimum_advance_notice') ?? 30;
    $max_scheduling_window = $config->get('maximum_scheduling_window') ?? 14;

    // Convert requested time to DateTime object.
    $timezone = new \DateTimeZone($store->getTimezone());
    if (is_numeric($requested_time)) {
      $requested_datetime = new \DateTime('@' . $requested_time);
      $requested_datetime->setTimezone($timezone);
    }
    else {
      try {
        $requested_datetime = new \DateTime($requested_time, $timezone);
      }
      catch (\Exception $e) {
        return [
          'valid' => FALSE,
          'message' => 'Invalid datetime format.',
        ];
      }
    }

    // Get current time in store timezone.
    $now = new \DateTime('now', $timezone);
    $min_time = clone $now;
    $min_time->modify("+{$min_advance_notice} minutes");
    $max_time = clone $now;
    $max_time->modify("+{$max_scheduling_window} days");

    // Check if time is too soon.
    // Use <= to allow times exactly at minimum advance notice.
    if ($requested_datetime <= $min_time) {
      return [
        'valid' => FALSE,
        'message' => "Scheduled time must be at least {$min_advance_notice} minutes in the future.",
      ];
    }

    // Check if time is too far in the future.
    if ($requested_datetime > $max_time) {
      return [
        'valid' => FALSE,
        'message' => "Scheduled time cannot be more than {$max_scheduling_window} days in the future.",
      ];
    }

    // Check if scheduled time falls within store hours.
    if (!$this->isTimeWithinStoreHours($store, $requested_datetime)) {
      return [
        'valid' => FALSE,
        'message' => 'Scheduled time is outside store operating hours.',
      ];
    }

    return ['valid' => TRUE, 'message' => ''];
  }

  /**
   * Checks if immediate orders are allowed.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return bool
   *   TRUE if immediate orders are allowed, FALSE otherwise.
   */
  public function isImmediateOrderAllowed(StoreInterface $store): bool {
    // Check if store is currently open.
    if (!$this->hoursValidator->isStoreOpen($store)) {
      return FALSE;
    }

    // Check ASAP cutoff time before closing.
    $config = $this->configFactory->get('store_fulfillment.settings');
    $asap_cutoff = $config->get('asap_cutoff_before_closing') ?? 15;

    if ($asap_cutoff > 0) {
      $timezone = new \DateTimeZone($store->getTimezone());
      $now = new \DateTime('now', $timezone);
      $current_time = $now->format('H:i');

      // Get store hours for today.
      $closing_time = $this->getClosingTimeToday($store);
      if ($closing_time) {
        // Calculate cutoff time.
        $closing_datetime = clone $now;
        [$hour, $minute] = explode(':', $closing_time);
        $closing_datetime->setTime((int) $hour, (int) $minute);
        $cutoff_datetime = clone $closing_datetime;
        $cutoff_datetime->modify("-{$asap_cutoff} minutes");

        // If current time is past cutoff, disallow ASAP.
        if ($now >= $cutoff_datetime && $now < $closing_datetime) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Gets the next available fulfillment slot.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return \DateTime|null
   *   The next available datetime, or NULL if cannot be determined.
   */
  public function getNextAvailableSlot(StoreInterface $store): ?\DateTime {
    $timezone = new \DateTimeZone($store->getTimezone());
    $now = new \DateTime('now', $timezone);
    $config = $this->configFactory->get('store_fulfillment.settings');
    $min_advance_notice = $config->get('minimum_advance_notice') ?? 30;

    // If store is currently open, return current time + min advance notice.
    if ($this->hoursValidator->isStoreOpen($store)) {
      $next_slot = clone $now;
      $next_slot->modify("+{$min_advance_notice} minutes");
      return $next_slot;
    }

    // Store is closed, find next opening time.
    return $this->findNextOpeningTime($store, $now);
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
  protected function isTimeWithinStoreHours(StoreInterface $store, \DateTime $datetime): bool {
    if (!$store->hasField('store_hours')) {
      return TRUE;
    }

    $hours_field = $store->get('store_hours');
    if ($hours_field->isEmpty()) {
      return TRUE;
    }

    $day = strtolower($datetime->format('l'));
    $time = $datetime->format('H:i');

    foreach ($hours_field as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        $parts = explode('|', $value);
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

    return FALSE;
  }

  /**
   * Gets the closing time for today.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return string|null
   *   The closing time in H:i format, or NULL if not found.
   */
  protected function getClosingTimeToday(StoreInterface $store): ?string {
    if (!$store->hasField('store_hours')) {
      return NULL;
    }

    $hours_field = $store->get('store_hours');
    if ($hours_field->isEmpty()) {
      return NULL;
    }

    $timezone = new \DateTimeZone($store->getTimezone());
    $now = new \DateTime('now', $timezone);
    $today = strtolower($now->format('l'));

    foreach ($hours_field as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        $parts = explode('|', $value);
        if (count($parts) === 3) {
          [$day, $open_time, $close_time] = $parts;
          if (strtolower($day) === $today) {
            return $close_time;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Finds the next opening time for a store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param \DateTime $from_time
   *   The starting time.
   *
   * @return \DateTime|null
   *   The next opening datetime, or NULL if not found within 7 days.
   */
  protected function findNextOpeningTime(StoreInterface $store, \DateTime $from_time): ?\DateTime {
    if (!$store->hasField('store_hours')) {
      return NULL;
    }

    $hours_field = $store->get('store_hours');
    if ($hours_field->isEmpty()) {
      return NULL;
    }

    // Parse store hours into array.
    $hours_by_day = [];
    foreach ($hours_field as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        $parts = explode('|', $value);
        if (count($parts) === 3) {
          [$day, $open_time, $close_time] = $parts;
          $hours_by_day[strtolower($day)] = [
            'open' => $open_time,
            'close' => $close_time,
          ];
        }
      }
    }

    // Search for next opening time within next 7 days.
    $current = clone $from_time;
    for ($i = 0; $i < 7; $i++) {
      if ($i > 0) {
        $current->modify('+1 day');
        $current->setTime(0, 0, 0);
      }

      $day = strtolower($current->format('l'));
      if (isset($hours_by_day[$day])) {
        $open_time = $hours_by_day[$day]['open'];
        [$hour, $minute] = explode(':', $open_time);
        $opening_datetime = clone $current;
        $opening_datetime->setTime((int) $hour, (int) $minute);

        // Ensure opening time is after from_time.
        if ($opening_datetime > $from_time) {
          $config = $this->configFactory->get('store_fulfillment.settings');
          $min_advance_notice = $config->get('minimum_advance_notice') ?? 30;
          $opening_datetime->modify("+{$min_advance_notice} minutes");
          return $opening_datetime;
        }
      }
    }

    return NULL;
  }

}
