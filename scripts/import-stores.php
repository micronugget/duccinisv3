<?php

/**
 * @file
 * Import V3 store entities into V4.
 *
 * Reads scripts/data/v3-stores-export.json and creates/updates all three
 * Duccinis store entities with their delivery_radius, store_location,
 * store_hours, and address fields.
 *
 * Usage (from V4 project root):
 *   ddev drush php:script scripts/import-stores.php
 *
 * Idempotent: existing stores are updated, not duplicated.
 * Issue: #10.3 — Import store data into V4.
 */

declare(strict_types=1);

$data_file = __DIR__ . '/data/v3-stores-export.json';
if (!file_exists($data_file)) {
  echo "ERROR: $data_file not found.\n";
  exit(1);
}

$stores_data = json_decode(file_get_contents($data_file), TRUE);
if (!$stores_data) {
  echo "ERROR: Failed to parse $data_file.\n";
  exit(1);
}

foreach ($stores_data as $data) {
  // Skip meta-only entries.
  if (!isset($data['id'])) {
    continue;
  }

  $store_id = (int) $data['id'];
  $store = \Drupal\commerce_store\Entity\Store::load($store_id);

  if ($store) {
    echo "Updating existing store #{$store_id}: {$data['name']}\n";
  }
  else {
    echo "Creating store #{$store_id}: {$data['name']}\n";
    $store = \Drupal\commerce_store\Entity\Store::create([
      'type' => $data['type'],
      'uid' => $data['uid'],
      'uuid' => $data['uuid'],
    ]);
  }

  $store->setName($data['name']);
  $store->setEmail($data['mail']);
  $store->setDefaultCurrencyCode($data['default_currency']);
  $store->set('timezone', $data['timezone']);

  // Address.
  $store->setAddress([
    'country_code'       => $data['address']['country_code'],
    'administrative_area' => $data['address']['administrative_area'],
    'locality'           => $data['address']['locality'],
    'postal_code'        => $data['address']['postal_code'],
    'address_line1'      => $data['address']['address_line1'],
    'address_line2'      => $data['address']['address_line2'] ?? '',
  ]);

  // Custom fields.
  if ($store->hasField('delivery_radius')) {
    $store->set('delivery_radius', $data['delivery_radius']);
  }
  else {
    echo "  WARNING: delivery_radius field missing on store entity — skipping.\n";
  }

  if ($store->hasField('store_location')) {
    $store->set('store_location', $data['store_location']['wkt']);
  }
  else {
    echo "  WARNING: store_location field missing on store entity — skipping.\n";
  }

  if ($store->hasField('store_hours')) {
    $store->set('store_hours', $data['store_hours']);
  }
  else {
    echo "  WARNING: store_hours field missing on store entity — skipping.\n";
  }

  $store->save();
  echo "  Saved store #{$store_id} OK.\n";
}

echo "\nDone. Run 'ddev drush cr' if any display issues appear.\n";
