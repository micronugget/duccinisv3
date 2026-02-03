<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment;

use Drupal\address\AddressInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for validating delivery addresses against store radius.
 */
class DeliveryRadiusValidator {

  use StringTranslationTrait;

  /**
   * The delivery radius calculator service.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusCalculator
   */
  protected DeliveryRadiusCalculator $calculator;

  /**
   * Constructs a new DeliveryRadiusValidator.
   *
   * @param \Drupal\store_fulfillment\DeliveryRadiusCalculator $calculator
   *   The delivery radius calculator service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(DeliveryRadiusCalculator $calculator, TranslationInterface $string_translation) {
    $this->calculator = $calculator;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Validates if a delivery address is within the store's delivery radius.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param \Drupal\address\AddressInterface $address
   *   The delivery address to validate.
   *
   * @return array
   *   An associative array with the following keys:
   *   - valid: (bool) TRUE if the address is valid for delivery, FALSE otherwise.
   *   - message: (string) User-friendly message explaining the validation result.
   *   - distance: (float|null) Distance in miles, or NULL if not calculable.
   */
  public function validateDeliveryAddress(StoreInterface $store, AddressInterface $address): array {
    // Check if store has delivery radius configured.
    if (!$store->hasField('delivery_radius')) {
      return [
        'valid' => TRUE,
        'message' => (string) $this->t('No delivery radius restriction configured for this store.'),
        'distance' => NULL,
      ];
    }

    $radius_field = $store->get('delivery_radius');
    if ($radius_field->isEmpty()) {
      return [
        'valid' => TRUE,
        'message' => (string) $this->t('No delivery radius restriction configured for this store.'),
        'distance' => NULL,
      ];
    }

    $max_radius = (float) $radius_field->value;

    // Get store coordinates.
    $store_coords = $this->getStoreCoordinates($store);
    if (!$store_coords) {
      return [
        'valid' => FALSE,
        'message' => (string) $this->t('Unable to determine store location. Please contact support.'),
        'distance' => NULL,
      ];
    }

    // Geocode delivery address.
    $delivery_coords = $this->getAddressCoordinates($address);
    if (!$delivery_coords) {
      return [
        'valid' => FALSE,
        'message' => (string) $this->t('Unable to verify your delivery address. Please ensure you have entered a valid address.'),
        'distance' => NULL,
      ];
    }

    // Calculate distance.
    $distance = $this->calculator->calculateDistance(
      $store_coords['lat'],
      $store_coords['lon'],
      $delivery_coords['lat'],
      $delivery_coords['lon']
    );

    // Validate against radius.
    if ($distance <= $max_radius) {
      return [
        'valid' => TRUE,
        'message' => (string) $this->t('Your delivery address is within our service area (@distance miles from store, maximum @max_radius miles).', [
          '@distance' => number_format($distance, 2),
          '@max_radius' => number_format($max_radius, 2),
        ]),
        'distance' => $distance,
      ];
    }

    return [
      'valid' => FALSE,
      'message' => (string) $this->t('Sorry, your delivery address is outside our service area. You are @distance miles from the store (maximum delivery radius: @max_radius miles). Please select pickup instead or choose a different store.', [
        '@distance' => number_format($distance, 2),
        '@max_radius' => number_format($max_radius, 2),
      ]),
      'distance' => $distance,
    ];
  }

  /**
   * Gets coordinates for a store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   *
   * @return array|null
   *   Array with 'lat' and 'lon' keys, or NULL if not available.
   */
  protected function getStoreCoordinates(StoreInterface $store): ?array {
    // Check if store has geocoded location field.
    if ($store->hasField('store_location') && !$store->get('store_location')->isEmpty()) {
      $location = $store->get('store_location')->first();
      return [
        'lat' => (float) $location->lat,
        'lon' => (float) $location->lon,
      ];
    }

    // Fallback: geocode store address.
    if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
      $address = $store->get('address')->first();
      return $this->geocodeAddress($address);
    }

    return NULL;
  }

  /**
   * Gets coordinates for a delivery address.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address to geocode.
   *
   * @return array|null
   *   Array with 'lat' and 'lon' keys, or NULL if not available.
   */
  protected function getAddressCoordinates(AddressInterface $address): ?array {
    return $this->geocodeAddress($address);
  }

  /**
   * Geocodes an address to coordinates.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address to geocode.
   *
   * @return array|null
   *   Array with 'lat' and 'lon' keys, or NULL on failure.
   */
  protected function geocodeAddress(AddressInterface $address): ?array {
    // This is a simplified implementation.
    // In production, use the Geocoder module for actual geocoding.
    // For now, return NULL to indicate geocoding needs implementation.

    // Example using Geocoder module (requires proper setup):
    // $geocoder = \Drupal::service('geocoder');
    // $address_string = sprintf('%s, %s, %s %s',
    //   $address->getAddressLine1(),
    //   $address->getLocality(),
    //   $address->getAdministrativeArea(),
    //   $address->getPostalCode()
    // );
    // $result = $geocoder->geocode($address_string, ['googlemaps']);
    // if ($result->isEmpty()) {
    //   return NULL;
    // }
    // $coords = $result->first()->getCoordinates();
    // return ['lat' => $coords->getLatitude(), 'lon' => $coords->getLongitude()];

    return NULL;
  }

}
