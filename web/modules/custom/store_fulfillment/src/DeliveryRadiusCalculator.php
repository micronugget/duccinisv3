<?php

namespace Drupal\store_fulfillment;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\address\AddressInterface;
use Drupal\geocoder\GeocoderInterface;

/**
 * Service for calculating delivery radius.
 */
class DeliveryRadiusCalculator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The geocoder service.
   *
   * @var \Drupal\geocoder\GeocoderInterface
   */
  protected GeocoderInterface $geocoder;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs a new DeliveryRadiusCalculator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The geocoder service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GeocoderInterface $geocoder, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->geocoder = $geocoder;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Checks if an address is within the delivery radius of a store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store entity.
   * @param \Drupal\address\AddressInterface $delivery_address
   *   The delivery address.
   *
   * @return bool
   *   TRUE if the address is within delivery radius, FALSE otherwise.
   */
  public function isWithinRadius(StoreInterface $store, AddressInterface $delivery_address) {
    // Get store's delivery radius setting.
    if (!$store->hasField('delivery_radius')) {
      // If no radius configured, allow all deliveries.
      return TRUE;
    }

    $radius_field = $store->get('delivery_radius');
    if ($radius_field->isEmpty()) {
      return TRUE;
    }

    $max_radius = (float) $radius_field->value;

    // Get store address coordinates.
    $store_coords = $this->getStoreCoordinates($store);
    if (!$store_coords) {
      // Cannot determine, allow delivery.
      return TRUE;
    }

    // Geocode delivery address to get coordinates.
    $delivery_coords = $this->geocodeAddress($delivery_address);
    if (!$delivery_coords) {
      // Cannot determine, deny delivery to be safe.
      return FALSE;
    }

    // Calculate distance between points.
    $distance = $this->calculateDistance(
      $store_coords['lat'],
      $store_coords['lon'],
      $delivery_coords['lat'],
      $delivery_coords['lon']
    );

    return $distance <= $max_radius;
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
  protected function getStoreCoordinates(StoreInterface $store) {
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
   * Geocodes an address to coordinates.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address to geocode.
   *
   * @return array|null
   *   Array with 'lat' and 'lon' keys, or NULL on failure.
   */
  protected function geocodeAddress(AddressInterface $address) {
    $address_string = implode(', ', array_filter([
      $address->getAddressLine1(),
      $address->getLocality(),
      $address->getAdministrativeArea(),
      $address->getPostalCode(),
    ]));

    if (empty(trim($address_string))) {
      return NULL;
    }

    $provider = $this->entityTypeManager->getStorage('geocoder_provider')->load('nominatim');
    if (!$provider) {
      $this->loggerFactory->get('store_fulfillment')->error('Nominatim geocoder provider entity not found.');
      return NULL;
    }

    try {
      $result = $this->geocoder->geocode($address_string, [$provider]);
      if ($result && !$result->isEmpty()) {
        $coords = $result->first()->getCoordinates();
        return [
          'lat' => $coords->getLatitude(),
          'lon' => $coords->getLongitude(),
        ];
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('store_fulfillment')->warning('Geocoding failed for address @address: @error', [
        '@address' => $address_string,
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Calculates distance between two coordinates using Haversine formula.
   *
   * @param float $lat1
   *   Latitude of first point.
   * @param float $lon1
   *   Longitude of first point.
   * @param float $lat2
   *   Latitude of second point.
   * @param float $lon2
   *   Longitude of second point.
   *
   * @return float
   *   Distance in miles.
   */
  public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    // Miles.
    $earth_radius = 3959;

    $lat_delta = deg2rad($lat2 - $lat1);
    $lon_delta = deg2rad($lon2 - $lon1);

    $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
      cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
      sin($lon_delta / 2) * sin($lon_delta / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c;
  }

}
