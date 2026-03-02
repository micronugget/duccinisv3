<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unarchives a product (menu item), restoring it to the menu.
 *
 * Sets field_archived = FALSE and saves. The ProductArchiveSubscriber
 * automatically re-publishes the product and all its variations via
 * PRODUCT_PRESAVE / PRODUCT_UPDATE, so the item reappears in menu
 * view blocks.
 */
#[Action(
  id: 'duccinis_unarchive_product',
  label: new TranslatableMarkup('Unarchive selected product'),
  type: 'commerce_product'
)]
class UnarchiveProduct extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $entity */
    $entity->set('field_archived', FALSE);
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
