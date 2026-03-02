<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\EventSubscriber;

use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Enforces published-state for archived Commerce products.
 *
 * When field_archived is set to TRUE the product is unpublished and all its
 * variations are unpublished. When field_archived transitions back to FALSE
 * the product and its variations are re-published.
 *
 * This happens at the entity level — before any view query runs — so no view
 * filter is required: the entity module's QueryAccessHandlerBase already
 * injects status = 1 into every view query for non-admin users.
 */
class ProductArchiveSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ProductEvents::PRODUCT_PRESAVE => 'onProductPresave',
      ProductEvents::PRODUCT_INSERT  => 'onProductPostSave',
      ProductEvents::PRODUCT_UPDATE  => 'onProductPostSave',
    ];
  }

  /**
   * Sets the product's own published status when the archived flag changes.
   *
   * Runs before the product is written to the database, so the correct
   * status value is persisted in a single save operation.
   */
  public function onProductPresave(ProductEvent $event): void {
    $product = $event->getProduct();

    if (!$product->hasField('field_archived')) {
      return;
    }

    $is_archived = (bool) $product->get('field_archived')->value;

    if ($is_archived) {
      $product->setUnpublished();
      return;
    }

    // Only re-publish when the archived flag is being turned off, not on every
    // save of an already-active product.
    $was_archived = $product->original
      && (bool) $product->original->get('field_archived')->value;

    if ($was_archived) {
      $product->setPublished();
    }
  }

  /**
   * Propagates the published status change to all child variations.
   *
   * Runs after the parent product is saved. Variations are separate entities
   * and must be saved individually so that the entity query access system
   * (which filters on commerce_product_variation.status) reflects the
   * archived state in view queries.
   */
  public function onProductPostSave(ProductEvent $event): void {
    $product = $event->getProduct();

    if (!$product->hasField('field_archived')) {
      return;
    }

    $is_archived = (bool) $product->get('field_archived')->value;
    $was_archived = $product->original
      ? (bool) $product->original->get('field_archived')->value
      : FALSE;

    // Nothing to propagate if the archived flag did not change.
    if ($is_archived === $was_archived) {
      return;
    }

    foreach ($product->getVariations() as $variation) {
      if ($is_archived) {
        $variation->setUnpublished();
      }
      else {
        $variation->setPublished();
      }
      $variation->save();
    }
  }

}
