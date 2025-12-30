<?php

/**
 * @file
 * Provide views data for commerce_reorder module.
 */

/**
 * Implements hook_views_data_alter().
 */
function commerce_reorder_views_data_alter(array &$data) {
  $data['commerce_order']['reorder_link'] = [
    'title' => t('Reorder link'),
    'help' => t('Provides a link to reorder the items from this order.'),
    'field' => [
      'id' => 'commerce_reorder_link',
    ],
  ];
}
