<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Archives a product (menu item).
 *
 * Sets field_archived = TRUE and saves. The ProductArchiveSubscriber
 * automatically unpublishes the product and all its variations via
 * PRODUCT_PRESAVE / PRODUCT_UPDATE, so the item disappears from all
 * menu view blocks without any view-level filter.
 */
#[Action(
  id: 'duccinis_archive_product',
  label: new TranslatableMarkup('Archive selected product'),
  type: 'commerce_product'
)]
class ArchiveProduct extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $entity */
    $entity->set('field_archived', TRUE);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $object */
    $result = $object
      ->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
