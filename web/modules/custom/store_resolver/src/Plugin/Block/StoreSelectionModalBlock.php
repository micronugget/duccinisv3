<?php

namespace Drupal\store_resolver\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\store_resolver\StoreResolver;
use Drupal\store_resolver\StoreHoursValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Store Selection Modal' block.
 *
 * @Block(
 *   id = "store_resolver_modal_block",
 *   admin_label = @Translation("Store Selection Modal"),
 *   category = @Translation("Commerce")
 * )
 */
class StoreSelectionModalBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The store resolver service.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected $storeResolver;

  /**
   * The store hours validator service.
   *
   * @var \Drupal\store_resolver\StoreHoursValidator
   */
  protected $hoursValidator;

  /**
   * Constructs a new StoreSelectionModalBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\store_resolver\StoreResolver $store_resolver
   *   The store resolver service.
   * @param \Drupal\store_resolver\StoreHoursValidator $hours_validator
   *   The store hours validator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StoreResolver $store_resolver,
    StoreHoursValidator $hours_validator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storeResolver = $store_resolver;
    $this->hoursValidator = $hours_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('store_resolver.current_store'),
      $container->get('store_resolver.hours_validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $stores = $this->storeResolver->getAvailableStores();

    if (empty($stores)) {
      return [];
    }

    $store_options = [];
    foreach ($stores as $store) {
      $store_id = $store->id();
      $store_name = $store->getName();

      // Check if store is currently open.
      $is_open = $this->hoursValidator->isStoreOpen($store);
      $status_text = $is_open ? $this->t('Open Now') : $this->t('Currently Closed');
      $status_class = $is_open ? 'open' : 'closed';

      // Get address information.
      $location_text = '';
      if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
        $address = $store->get('address')->first();
        $location_text = $this->t('@city, @state', [
          '@city' => $address->locality,
          '@state' => $address->administrative_area,
        ]);
      }

      $store_options[] = [
        'id' => $store_id,
        'name' => $store_name,
        'status' => $status_text,
        'status_class' => $status_class,
        'location' => $location_text,
      ];
    }

    $build = [
      '#theme' => 'store_resolver_modal',
      '#stores' => $store_options,
      '#attached' => [
        'library' => [
          'store_resolver/store_modal',
        ],
      ],
    ];

    // Disable caching to ensure modal shows when cookie is absent.
    $build['#cache'] = [
      'contexts' => ['cookies:' . StoreResolver::STORE_COOKIE_NAME],
      'max-age' => 0,
    ];

    return $build;
  }

}
