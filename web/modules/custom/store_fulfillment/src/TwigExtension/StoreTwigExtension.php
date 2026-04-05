<?php

declare(strict_types=1);

namespace Drupal\store_fulfillment\TwigExtension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing store data functions for templates.
 */
class StoreTwigExtension extends AbstractExtension {

  /**
   * Constructs a StoreTwigExtension.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('store_fulfillment_stores', [$this, 'getStores']),
    ];
  }

  /**
   * Returns an array of store data suitable for the locations Twig component.
   *
   * Each item contains: number, name, address_line1, address_line2,
   * directions_url, phone, phone_href, store_type.
   *
   * @return array
   *   Array of store data arrays, ordered by store ID.
   */
  public function getStores(): array {
    $stores = $this->entityTypeManager
      ->getStorage('commerce_store')
      ->loadMultiple();

    // Sort by store ID for consistent ordering.
    ksort($stores);

    $store_type_map = [
      'Adams Morgan DC' => 'dc',
      'Arlington VA'    => 'va',
      '7th Street NW'   => 'dc',
    ];

    $items = [];
    $index = 1;
    foreach ($stores as $store) {
      $label = $store->label();
      $phone_raw = '';
      if ($store->hasField('field_phone') && !$store->get('field_phone')->isEmpty()) {
        $phone_raw = $store->get('field_phone')->value;
      }

      // Format E.164 (+12025551234) to display format ((202) 555-1234).
      $phone_display = $phone_raw;
      $phone_href = 'tel:' . preg_replace('/[^+\d]/', '', $phone_raw);
      if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $phone_raw, $m)) {
        $phone_display = '(' . $m[1] . ') ' . $m[2] . '-' . $m[3];
      }

      // Build Google Maps directions URL from store address.
      $address = $store->getAddress();
      $directions_url = '';
      if ($address) {
        $query = implode(' ', array_filter([
          $address->getAddressLine1(),
          $address->getLocality(),
          $address->getAdministrativeArea(),
          $address->getPostalCode(),
        ]));
        $directions_url = 'https://maps.google.com/?q=' . rawurlencode($query);
      }

      $items[] = [
        'number'        => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'name'          => $label,
        'address_line1' => $address ? $address->getAddressLine1() : '',
        'address_line2' => $address ? trim($address->getLocality() . ', ' . $address->getAdministrativeArea() . ' ' . $address->getPostalCode()) : '',
        'directions_url' => $directions_url,
        'phone'         => $phone_display,
        'phone_href'    => $phone_href,
        'store_type'    => $store_type_map[$label] ?? 'dc',
      ];
      $index++;
    }

    return $items;
  }

}
