<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\Controller;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides JSON AJAX endpoints for archiving and unarchiving products.
 *
 * Both routes require the 'administer commerce_product' permission and a
 * valid X-CSRF-Token request header (see duccinis_archive.routing.yml).
 * Callers obtain a token from GET /session/token before making requests.
 *
 * Example usage:
 * @code
 * fetch('/admin/duccinis/archive/42', {
 *   method: 'POST',
 *   headers: { 'X-CSRF-Token': drupalSettings.token },
 * });
 * @endcode
 */
class ArchiveController extends ControllerBase {

  /**
   * Archives a Commerce product (menu item).
   *
   * Sets field_archived = TRUE and persists the entity. The
   * ProductArchiveSubscriber automatically unpublishes the product and all
   * its variations, removing the item from all menu view blocks.
   *
   * POST /admin/duccinis/archive/{commerce_product}
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $commerce_product
   *   The product to archive, resolved via route parameter upcasting.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 JSON response with keys: status, product_id, title, published.
   */
  public function archive(ProductInterface $commerce_product): JsonResponse {
    $commerce_product->set('field_archived', TRUE);
    $commerce_product->save();

    return new JsonResponse([
      'status'     => 'archived',
      'product_id' => (int) $commerce_product->id(),
      'title'      => $commerce_product->getTitle(),
      'published'  => $commerce_product->isPublished(),
    ]);
  }

  /**
   * Unarchives a Commerce product, restoring it to the menu.
   *
   * Sets field_archived = FALSE and persists the entity. The
   * ProductArchiveSubscriber automatically re-publishes the product and all
   * its variations, making the item visible in menu view blocks again.
   *
   * POST /admin/duccinis/unarchive/{commerce_product}
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $commerce_product
   *   The product to unarchive, resolved via route parameter upcasting.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 JSON response with keys: status, product_id, title, published.
   */
  public function unarchive(ProductInterface $commerce_product): JsonResponse {
    $commerce_product->set('field_archived', FALSE);
    $commerce_product->save();

    return new JsonResponse([
      'status'     => 'active',
      'product_id' => (int) $commerce_product->id(),
      'title'      => $commerce_product->getTitle(),
      'published'  => $commerce_product->isPublished(),
    ]);
  }

}
