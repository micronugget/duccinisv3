<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\EventSubscriber;

use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\duccinis_archive\ArchiveAuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Enforces published-state for archived Commerce products.
 *
 * When field_archived is set to TRUE the product is unpublished and all its
 * variations are unpublished. When field_archived transitions back to FALSE
 * the product and its variations are re-published.
 *
 * Also writes an entry to duccinis_archive_log via ArchiveAuditLogger whenever
 * the archived state changes, providing a complete audit trail of all
 * archive and unarchive actions regardless of how they were triggered
 * (AJAX endpoint, bulk action, or product edit form).
 */
class ProductArchiveSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ProductArchiveSubscriber.
   *
   * @param \Drupal\duccinis_archive\ArchiveAuditLogger $auditLogger
   *   The archive audit logger.
   */
  public function __construct(
    private readonly ArchiveAuditLogger $auditLogger,
  ) {}

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
   * status value is persisted in a single save operation. Also writes to
   * the archive audit log whenever the archived state actually changes.
   */
  public function onProductPresave(ProductEvent $event): void {
    $product = $event->getProduct();

    if (!$product->hasField('field_archived')) {
      return;
    }

    $is_archived = (bool) $product->get('field_archived')->value;

    if ($is_archived) {
      $product->setUnpublished();
      // Log only on state change (new products or flag just turned on).
      $was_archived = $product->original
        && (bool) $product->original->get('field_archived')->value;
      if (!$was_archived) {
        $this->auditLogger->log($product, 'archive');
      }
      return;
    }

    // Only re-publish when the archived flag is being turned off, not on every
    // save of an already-active product.
    $was_archived = $product->original
      && (bool) $product->original->get('field_archived')->value;

    if ($was_archived) {
      $product->setPublished();
      $this->auditLogger->log($product, 'unarchive');
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
