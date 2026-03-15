<?php

/**
 * @file
 * Creates the /menu Canvas page content entity with product_variations blocks.
 *
 * Run with: ddev drush php:script scripts/create_menu_page.php
 */

declare(strict_types=1);

use Drupal\canvas\Entity\Page;

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$existing = $storage->loadByProperties(['title' => 'Menu']);
if (!empty($existing)) {
  echo 'Menu Canvas page already exists (ID: ' . reset($existing)->id() . '). Nothing to do.' . PHP_EOL;
  return;
}

$uuid_svc = \Drupal::service('uuid');

// Map display_id => label, ordered to group nicely on the page.
$displays = [
  'pizza'               => 'Pizza',
  'salads'              => 'Salads',
  'specials'            => 'Specials',
  'side_orders'         => 'Side Orders',
  'beverages'           => 'Beverages',
  'fresh_buffalo_wings' => 'Buffalo Wings',
  'fresh_submarines'    => 'Fresh Submarines',
  'famous_stromboli'    => 'Famous Stromboli',
  'homemade_pasta'      => 'Homemade Pasta',
  'gyro_special'        => 'Gyro Special',
  'desserts'            => 'Desserts',
  'menus_extras'        => 'Menu Extras',
];

$components = [];
foreach ($displays as $display_id => $label) {
  $components[] = [
    'parent_uuid'       => NULL,
    'slot'              => NULL,
    'uuid'              => $uuid_svc->generate(),
    'component_id'      => 'block.views_block.product_variations-' . $display_id,
    'component_version' => NULL,
    'inputs'            => [
      'label'         => $label,
      'label_display' => '0',
    ],
    'label'             => $label,
  ];
}

$page = Page::create([
  'title'      => 'Menu',
  'status'     => 1,
  'path'       => [['alias' => '/menu']],
  'components' => $components,
]);
$page->save();

echo 'Created Canvas page "Menu" (ID: ' . $page->id() . ') at /menu with ' . count($components) . ' view block components.' . PHP_EOL;
