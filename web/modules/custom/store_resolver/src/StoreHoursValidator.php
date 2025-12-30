<?php

namespace Drupal\store_resolver;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Service for validating store hours.
 */
class StoreHoursValidator {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new StoreHoursValidator.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * Checks if a store is currently open.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return bool
   *   TRUE if the store is open, FALSE otherwise.
   */
  public function isStoreOpen(StoreInterface $store) {
    // Check if store has store_hours field.
    if (!$store->hasField('store_hours')) {
      // If no hours field exists, assume store is always open.
      return TRUE;
    }

    $hours_field = $store->get('store_hours');
    if ($hours_field->isEmpty()) {
      // If hours not configured, assume store is always open.
      return TRUE;
    }

    // Get current day and time in store's timezone.
    $timezone = $store->getTimezone();
    $current_datetime = new \DateTime('now', new \DateTimeZone($timezone));
    $current_day = strtolower($current_datetime->format('l'));
    $current_time = $current_datetime->format('H:i');

    // Parse hours field (assuming format: day|open_time|close_time per line).
    // This is a simplified implementation. You may need to adjust based on
    // your actual field structure.
    foreach ($hours_field as $hour_item) {
      $value = $hour_item->value;
      if (!empty($value)) {
        // Parse the hours format.
        // Example format: "monday|09:00|17:00"
        $parts = explode('|', $value);
        if (count($parts) === 3) {
          [$day, $open_time, $close_time] = $parts;
          if (strtolower($day) === $current_day) {
            // Check if current time is within business hours.
            if ($current_time >= $open_time && $current_time <= $close_time) {
              return TRUE;
            }
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Gets the next available time for a store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return \DateTime|null
   *   The next available datetime, or NULL if cannot be determined.
   */
  public function getNextAvailableTime(StoreInterface $store) {
    // This is a placeholder for more complex logic.
    // In a full implementation, you would parse store hours and find the
    // next opening time.
    $timezone = $store->getTimezone();
    $next_time = new \DateTime('+1 hour', new \DateTimeZone($timezone));
    return $next_time;
  }

}
