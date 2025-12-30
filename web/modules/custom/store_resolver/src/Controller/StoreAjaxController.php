<?php

namespace Drupal\store_resolver\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for AJAX store operations.
 */
class StoreAjaxController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a StoreAjaxController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Returns refreshed block content after store selection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function refreshBlocks(Request $request) {
    $response = new AjaxResponse();

    // Get the store ID from the query parameter (passed by JavaScript).
    $store_id = $request->query->get('store_id');

    if ($store_id) {
      // Load the store directly.
      $store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);

      if ($store) {
        $store_name = $store->getName();

        // Get store address if available.
        $address_text = '';
        if ($store->hasField('address') && !$store->get('address')->isEmpty()) {
          $address = $store->get('address')->first();
          $address_text = sprintf(
            '%s, %s %s',
            $address->locality,
            $address->administrative_area,
            $address->postal_code
          );
        }

        // Build the block content directly (matching CurrentStoreBlock::build()).
        $build = [
          '#theme' => 'store_resolver_current_store',
          '#store_name' => $store_name,
          '#address' => $address_text,
          '#change_url' => Url::fromRoute('store_resolver.select_store'),
          '#cache' => ['max-age' => 0],
        ];

        $html = $this->renderer->renderRoot($build);

        // Replace the current store block content using the unique ID.
        $response->addCommand(new ReplaceCommand(
          '#current-store-display',
          $html
        ));
      }
    }

    return $response;
  }

}
