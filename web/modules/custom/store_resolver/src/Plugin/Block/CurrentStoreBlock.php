<?php

namespace Drupal\store_resolver\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\store_resolver\StoreResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Current Store' block.
 *
 * @Block(
 *   id = "store_resolver_current_store",
 *   admin_label = @Translation("Current Store"),
 *   category = @Translation("Commerce")
 * )
 */
class CurrentStoreBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The store resolver service.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected $storeResolver;

  /**
   * Constructs a new CurrentStoreBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\store_resolver\StoreResolver $store_resolver
   *   The store resolver service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StoreResolver $store_resolver
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storeResolver = $store_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('store_resolver.current_store')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $current_store = $this->storeResolver->getCurrentStore();

    if ($current_store) {
      $store_name = $current_store->getName();

      // Get store address if available.
      $address_text = '';
      if ($current_store->hasField('address') && !$current_store->get('address')->isEmpty()) {
        $address = $current_store->get('address')->first();
        $address_text = sprintf(
          '%s, %s %s',
          $address->locality,
          $address->administrative_area,
          $address->postal_code
        );
      }

      $build['content'] = [
        '#theme' => 'store_resolver_current_store',
        '#store_name' => $store_name,
        '#address' => $address_text,
        '#change_url' => Url::fromRoute('store_resolver.select_store'),
      ];
    }
    else {
      // No store selected.
      $build['content'] = [
        '#theme' => 'store_resolver_no_store',
        '#select_url' => Url::fromRoute('store_resolver.select_store'),
      ];
    }

    // Cache per user session (due to cookie).
    $build['#cache'] = [
      'contexts' => ['cookies:' . StoreResolver::STORE_COOKIE_NAME],
      'max-age' => 0,
    ];

    return $build;
  }

}
