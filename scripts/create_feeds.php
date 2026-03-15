<?php

/**
 * @file
 * Creates Feeds feed entities for all product categories.
 *
 * Run with:
 *   ddev drush php:script scripts/create_feeds.php
 *
 * ----------------------------------------------------------------------
 * REUSE GUIDE — Adapting for a new restaurant chain
 * ----------------------------------------------------------------------
 *
 * 1. FEED TYPES ($feeds_to_create array)
 *    Each entry maps to a `feeds.feed_type.*` config entity that must
 *    already exist in config/sync and be imported into the DB.
 *    - 'type'  → machine name of the feed type (feeds.feed_type.<id>)
 *    - 'label' → human-readable name shown in /admin/content/feed
 *    - 'csv'   → filename in web/sites/default/files/
 *
 *    Convention: one *_product feed + one *_variations feed per category,
 *    both pointing at the same CSV file. The product feed creates the
 *    Commerce Product entity; the variations feed creates the Commerce
 *    Product Variation entities and links them to the product.
 *
 * 2. BASE URL ($base)
 *    Point to your site's public files URL. For DDEV local:
 *      https://<project>.ddev.site/sites/default/files/
 *    For production:
 *      https://<domain>/sites/default/files/
 *
 * 3. CSV FORMAT
 *    The CSV columns must match the custom_sources defined in the
 *    corresponding feeds.feed_type.*.yml config file. Typical columns:
 *      product_id, sku, price__number, <attribute_column>, stores
 *    See Commerce_Product_Importing.md for the full column reference.
 *
 * 4. IMPORT ORDER
 *    Always import *_product feeds FIRST, then *_variations feeds.
 *    Products must exist before variations can be linked to them.
 *
 *    Run all product feeds:
 *      for type in beverages_product buffalo_wings_product ...; do
 *        ddev drush feeds:import $type --import-disabled -y
 *      done
 *
 *    Then all variation feeds:
 *      for type in beverages_variations buffalo_wings_variations ...; do
 *        ddev drush feeds:import $type --import-disabled -y
 *      done
 *
 * 5. RE-RUNNING
 *    The script is idempotent — it skips any feed type that already has
 *    an entity in the DB. Safe to run multiple times.
 *
 * 6. ADDING A NEW CATEGORY
 *    a. Add a feeds.feed_type.<id>_product.yml and
 *       feeds.feed_type.<id>_variations.yml to config/sync (copy an
 *       existing one and adjust processor/mappings).
 *    b. Run `ddev drush cim -y` to activate the new feed type.
 *    c. Add an entry to $feeds_to_create below.
 *    d. Re-run this script: `ddev drush php:script scripts/create_feeds.php`
 *    e. Run `ddev drush feeds:import <id>_product -y` then
 *       `ddev drush feeds:import <id>_variations -y`.
 * ----------------------------------------------------------------------
 */

// Base URL for CSV files. Change this when deploying to production.
$base = 'https://duccinisv4.ddev.site/sites/default/files/';

// List of feed entities to create.
// Each entry: feed type machine name | human label | CSV filename.
$feeds_to_create = [
  // Beverages
  ['type' => 'beverages_product',            'label' => 'Beverages product',                   'csv' => 'beverages.csv'],
  ['type' => 'beverages_variations',          'label' => 'Beverages variation',                 'csv' => 'beverages.csv'],
  // Buffalo Wings
  ['type' => 'buffalo_wings_product',         'label' => 'Fresh Buffalo Wings product',         'csv' => 'buffalo_wings.csv'],
  ['type' => 'buffalo_wings_variations',      'label' => 'Fresh Buffalo Wings variation',       'csv' => 'buffalo_wings.csv'],
  // Desserts
  ['type' => 'desserts_product',              'label' => 'Desserts product',                    'csv' => 'desserts.csv'],
  ['type' => 'desserts_variations',           'label' => 'Desserts variation',                  'csv' => 'desserts.csv'],
  // Fresh Submarines
  ['type' => 'fresh_submarines_product',      'label' => 'Fresh submarines product',            'csv' => 'fresh_submarines.csv'],
  ['type' => 'fresh_submarines_variations',   'label' => 'Fresh submarines variation',          'csv' => 'fresh_submarines.csv'],
  // Fries, Sticks & Chips
  ['type' => 'fries_sticks_chips_product',    'label' => 'Fries, chips, and sticks product',   'csv' => 'fries_sticks_chips.csv'],
  ['type' => 'fries_sticks_chips_variations', 'label' => 'Fries, chips, and sticks variation', 'csv' => 'fries_sticks_chips.csv'],
  // Homemade Pasta
  ['type' => 'homemade_pasta_product',        'label' => 'Homemade pasta product',              'csv' => 'homemade_pasta.csv'],
  ['type' => 'homemade_pasta_variations',     'label' => 'Homemade pasta variation',            'csv' => 'homemade_pasta.csv'],
  // Pizza Delights
  ['type' => 'pizza_delights_product',        'label' => 'Pizza Delights Product',              'csv' => 'pizza_delights.csv'],
  ['type' => 'pizza_delights_variations',     'label' => 'Pizza Delights Variation',            'csv' => 'pizza_delights.csv'],
  // Pizza
  ['type' => 'pizza_product',                 'label' => 'Pizza Product',                       'csv' => 'pizza.csv'],
  ['type' => 'pizza_variations',              'label' => 'Pizza Variation',                     'csv' => 'pizza.csv'],
  // Salads
  ['type' => 'salads_product',                'label' => 'Salads product',                      'csv' => 'salads.csv'],
  ['type' => 'salads_variations',             'label' => 'Salads variation',                    'csv' => 'salads.csv'],
  // Side Orders
  ['type' => 'side_orders_product',           'label' => 'Side orders product',                 'csv' => 'side_orders.csv'],
  // Stromboli — no known variation type in V3; add variations entry if needed.
  // ['type' => 'stromboli_product',          'label' => 'Stromboli product',                   'csv' => 'stromboli.csv'],
  // ['type' => 'stromboli_variations',       'label' => 'Stromboli variation',                 'csv' => 'stromboli.csv'],
];

$storage = \Drupal::entityTypeManager()->getStorage('feeds_feed');
$created = 0;
$skipped = 0;

echo "=== Creating Feeds feed entities ===\n\n";

// Build a simple map of existing type -> fid using a direct DB query.
// (loadByProperties() triggers a feeds_item field query that fails before
// the field storage exists — use Database API instead.)
$existing_types = \Drupal::database()
  ->select('feeds_feed', 'f')
  ->fields('f', ['fid', 'type'])
  ->execute()
  ->fetchAllKeyed(1, 0); // type => fid

foreach ($feeds_to_create as $def) {
  if (isset($existing_types[$def['type']])) {
    echo "SKIP    [{$existing_types[$def['type']]}] {$def['label']} (already exists)\n";
    $skipped++;
    continue;
  }

  $feed = $storage->create([
    'type'   => $def['type'],
    'title'  => $def['label'],
    'source' => $base . $def['csv'],
    'status' => 1,
    'uid'    => 1,
  ]);
  $feed->save();
  echo "CREATED [{$feed->id()}] {$def['label']} → {$base}{$def['csv']}\n";
  $created++;
}

echo "\n=== Summary: {$created} created, {$skipped} skipped ===\n";
echo "\nAll feed entities:\n";
$rows = \Drupal::database()
  ->select('feeds_feed', 'f')
  ->fields('f', ['fid', 'type', 'source'])
  ->orderBy('fid')
  ->execute()
  ->fetchAll();
foreach ($rows as $row) {
  echo "  [{$row->fid}] {$row->type} → {$row->source}\n";
}

echo "\n=== Next steps ===\n";
echo "Import products FIRST, then variations:\n\n";

$product_types = array_filter($feeds_to_create, fn($d) => str_ends_with($d['type'], '_product'));
$variation_types = array_filter($feeds_to_create, fn($d) => str_ends_with($d['type'], '_variations'));

foreach ($product_types as $d) {
  echo "  ddev drush feeds:import {$d['type']} --import-disabled -y\n";
}
echo "\n";
foreach ($variation_types as $d) {
  echo "  ddev drush feeds:import {$d['type']} --import-disabled -y\n";
}
