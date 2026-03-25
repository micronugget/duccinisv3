<?php

/**
 * @file
 * Populates the 'Home' Canvas page (ID 1) with all 9 homepage sections.
 *
 * The canvas_page #1 ("Home") is the site front page (/page/1 → /home).
 * This script sets its component tree to match the prototype in
 * output/prototype/index.html.
 *
 * Canvas stores only scalar props registered in prop_field_definitions.
 * Complex array-of-object data (locations, menu items, stats, reviews)
 * is rendered from hardcoded defaults in each SDC Twig template.
 *
 * Run with: ddev drush php:script scripts/create_homepage_pattern.php
 */

declare(strict_types=1);

use Drupal\Component\Uuid\Php as UuidGenerator;

$uuid = new UuidGenerator();

// Clean up stale test patterns if they exist.
$patternStorage = \Drupal::entityTypeManager()->getStorage('pattern');
foreach (['homepage_test', 'homepage'] as $stale_id) {
  if ($stale = $patternStorage->load($stale_id)) {
    $stale->delete();
    echo "Deleted stale '$stale_id' pattern.\n";
  }
}

// Load existing Home canvas_page.
$pageStorage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $pageStorage->load(1);
if (!$page) {
  echo "ERROR: canvas_page #1 not found.\n";
  exit(1);
}
echo "Updating canvas_page #1: '{$page->label()}'\n";

$component_tree = [

  // ── 1. Hero ──────────────────────────────────────────────────────────────
  // All props are scalar strings → fully Canvas-editable.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-hero',
    'component_version' => 'bc7a460a746a52bc',
    'inputs' => [
      'eyebrow'             => "Washington DC & Arlington \xC2\xB7 Since 1988",
      'headline_l1'         => 'JUMBO',
      'headline_l2'         => 'SLICE',
      'headline_l3'         => 'PIZZA',
      'subline'             => "Home of DC\xE2\x80\x99s Original Oversized Slice",
      'description'         => "Fresh-made daily, from scratch, with the finest ingredients. Three DMV locations open late \xE2\x80\x94 so you\xE2\x80\x99re never without a perfect slice.",
      'cta_primary_label'   => 'Order Online',
      'cta_primary_url'     => '/menu',
      'cta_secondary_label' => 'Find a Location',
      'cta_secondary_url'   => '#locations',
    ],
  ],

  // ── 2. Ticker ─────────────────────────────────────────────────────────────
  // items: multi-value string (cardinality: -1) → Canvas-editable.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-ticker',
    'component_version' => '00cfae2e293cdaab',
    'inputs' => [
      'items' => [
        'Fresh Made Daily',
        'NY-Style Jumbo Slice',
        'Open Late Every Night',
        '3 DMV Locations',
        'Since 1988',
        "DC\xE2\x80\x99s Original",
        'Pickup & Delivery',
        "Gyros \xC2\xB7 Wings \xC2\xB7 Stromboli",
      ],
    ],
  ],

  // ── 3. Locations Section ──────────────────────────────────────────────────
  // Scalar header props → Canvas-editable.
  // locations (array of objects) → Twig template default data.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-locations-section',
    'component_version' => '5fe27d652f5eebc2',
    'inputs' => [
      'section_label' => 'Three Locations',
      'headline_l1'   => 'FIND',
      'headline_l2'   => 'YOUR SLICE',
      'cta_label'     => "Order Pickup or Delivery \xE2\x86\x92",
      'cta_url'       => '/menu',
    ],
  ],

  // ── 4. Order Band ─────────────────────────────────────────────────────────
  // All props are scalar strings → fully Canvas-editable.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-order-band',
    'component_version' => 'afa72b67525b3e6d',
    'inputs' => [
      'headline_l1' => 'HUNGRY',
      'headline_l2' => 'NOW?',
      'description' => "Browse our full menu \xE2\x80\x94 pizza by the slice or whole pie, subs, strombolis, wings, gyros, salads \xE2\x80\x94 and order for pickup or delivery.",
      'cta_label'   => 'View the Menu',
      'cta_url'     => '/menu',
      'ghost_text'  => 'MENU',
    ],
  ],

  // ── 5. Menu Preview ───────────────────────────────────────────────────────
  // Scalar header props → Canvas-editable.
  // items (array of objects) → Twig template default data.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-menu-preview',
    'component_version' => '5fe27d652f5eebc2',
    'inputs' => [
      'section_label' => 'What We Serve',
      'headline_l1'   => 'THE',
      'headline_l2'   => 'MENU',
      'cta_label'     => "Full Menu \xE2\x86\x92",
      'cta_url'       => '/menu',
    ],
  ],

  // ── 6. Stripe Separator ───────────────────────────────────────────────────
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-stripe-separator',
    'component_version' => 'db454c33ef6529fc',
    'inputs' => [
      'height' => '5px',
    ],
  ],

  // ── 7. Story Section ──────────────────────────────────────────────────────
  // Scalar + paragraphs (cardinality: -1) → Canvas-editable.
  // stats (array of objects) → Twig template default data.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-story-section',
    'component_version' => '5e17b0b4da478b35',
    'inputs' => [
      'section_label' => 'Our Story',
      'headline_l1'   => 'SINCE',
      'headline_l2'   => '1988',
      'paragraphs'    => [
        "Duccini\xE2\x80\x99s is credited with developing Washington DC\xE2\x80\x99s very first New York-style jumbo slice pizza. Since 1988, we\xE2\x80\x99ve intentionally kept our footprint small to stay laser-focused on what matters most: flavor, quality, and community.",
        "Everything is made from scratch every single day, using only the freshest ingredients. Friendly faces, no waiting, and a slice big enough to fold in half \xE2\x80\x94 that\xE2\x80\x99s the Duccini\xE2\x80\x99s promise. DC\xE2\x80\x99s best slice, every time.",
      ],
    ],
  ],

  // ── 8. Stripe Separator (second) ──────────────────────────────────────────
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-stripe-separator',
    'component_version' => 'db454c33ef6529fc',
    'inputs' => [
      'height' => '5px',
    ],
  ],

  // ── 9. Reviews Section ────────────────────────────────────────────────────
  // Scalar header props → Canvas-editable.
  // reviews (array of objects) → Twig template default data.
  [
    'uuid'              => $uuid->generate(),
    'component_id'      => 'sdc.duccinis_1984_olympics.duccinis-reviews-section',
    'component_version' => '61846648123a830e',
    'inputs' => [
      'section_label' => 'What People Say',
      'headline_l1'   => 'REAL',
      'headline_l2'   => 'REVIEWS',
    ],
  ],

];

$page->set('components', $component_tree);
$page->setNewRevision(FALSE);

$violations = $page->validate();
if (count($violations) > 0) {
  echo "VALIDATION ERRORS:\n";
  foreach ($violations as $v) {
    echo '  - ' . $v->getPropertyPath() . ': ' . $v->getMessage() . "\n";
  }
  exit(1);
}

$page->save();
echo "Saved canvas_page #1 with " . count($component_tree) . " sections.\n";
echo "UUID: " . $page->uuid() . "\n";
echo "Front page: " . \Drupal::config('system.site')->get('page.front') . "\n";
