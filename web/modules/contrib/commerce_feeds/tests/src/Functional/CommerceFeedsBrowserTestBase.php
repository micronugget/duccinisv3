<?php

namespace Drupal\Tests\commerce_feeds\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\feeds\Traits\FeedCreationTrait;
use Drupal\Tests\feeds\Traits\FeedsCommonTrait;
use Drupal\feeds\FeedTypeInterface;

/**
 * Provides a base class for Commerce Feeds functional tests.
 */
abstract class CommerceFeedsBrowserTestBase extends CommerceBrowserTestBase {

  use FeedCreationTrait;
  use FeedsCommonTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'feeds',
    'commerce_feeds',
    'commerce_product',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge(parent::getAdministratorPermissions(), [
      'administer feeds',
      'access feed overview',
    ]);
  }

  /**
   * Returns the absolute directory path of the Commerce Feeds module.
   *
   * @return string
   *   The absolute path to the Feeds module.
   */
  protected function absolutePath() {
    return $this->absolute() . '/' . $this->container->get('extension.list.module')->getPath('commerce_feeds');
  }

  /**
   * Creates a feed type for importing product variations.
   *
   * @return \Drupal\feeds\FeedTypeInterface
   *   The created feed type.
   */
  protected function createProductVariationFeedType(): FeedTypeInterface {
    return $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'sku' => 'sku',
      'title' => 'title',
      'price' => 'price',
    ], [
      'processor' => 'entity:commerce_product_variation',
      'processor_configuration' => [
        'authorize' => FALSE,
        'values' => [
          'type' => 'default',
        ],
      ],
      'mappings' => [
        [
          'target' => 'sku',
          'map' => ['value' => 'sku'],
          'unique' => ['value' => TRUE],
        ],
        [
          'target' => 'title',
          'map' => ['value' => 'title'],
        ],
        [
          'target' => 'feeds_item',
          'map' => ['guid' => 'sku'],
        ],
        [
          'target' => 'price',
          'map' => ['number' => 'price'],
          'settings' => [
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
  }

  /**
   * Creates a feed type for importing products.
   *
   * @return \Drupal\feeds\FeedTypeInterface
   *   The created feed type.
   */
  protected function createProductFeedType(): FeedTypeInterface {
    return $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'sku' => 'sku',
      'store' => 'store',
    ], [
      'processor' => 'entity:commerce_product',
      'processor_configuration' => [
        'authorize' => FALSE,
        'values' => [
          'type' => 'default',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'variations',
          'map' => ['target_id' => 'sku'],
          'settings' => [
            'reference_by' => 'sku',
          ],
        ],
        [
          'target' => 'stores',
          'map' => ['target_id' => 'store'],
          'settings' => [
            'reference_by' => 'name',
          ],
        ],
      ]),
    ]);
  }

}
