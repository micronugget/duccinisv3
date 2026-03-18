<?php

/**
 * @file
 * Prepends the CurrentStoreBlock as the first component on the /menu Canvas page.
 *
 * Run with: ddev drush php:script scripts/add_store_selector_to_menu.php
 */

declare(strict_types=1);

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');

/** @var \Drupal\canvas\Entity\CanvasPage $page */
$page = $storage->load(2);
if (!$page) {
  echo 'ERROR: Canvas page 2 (Menu) not found.' . PHP_EOL;
  return;
}

$uuid_svc = \Drupal::service('uuid');
$existing = $page->get('components')->getValue();

// Check if already added.
foreach ($existing as $component) {
  if ($component['component_id'] === 'block.store_resolver_current_store') {
    echo 'Store selector block already present on the Menu page (UUID: ' . $component['uuid'] . '). Nothing to do.' . PHP_EOL;
    return;
  }
}

$store_selector_component = [
  'parent_uuid'       => NULL,
  'slot'              => NULL,
  'uuid'              => $uuid_svc->generate(),
  'component_id'      => 'block.store_resolver_current_store',
  'component_version' => NULL,
  'inputs'            => [
    'label'         => 'Select Your Store',
    'label_display' => '0',
  ],
  'label' => 'Store Selector',
];

// Prepend — store selector must appear before the product listings.
$updated = array_merge([$store_selector_component], $existing);
$page->set('components', $updated);
$page->save();

echo 'Done. Store selector block added as first component on Canvas page "Menu" (ID: 2).' . PHP_EOL;
echo 'Components count: ' . count($updated) . PHP_EOL;
