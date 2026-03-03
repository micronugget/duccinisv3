<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Writes archive and unarchive actions to the duccinis_archive_log table.
 *
 * Injected into ProductArchiveSubscriber so that every archive/unarchive
 * event — regardless of whether it was triggered via the AJAX endpoint,
 * a bulk action, or the product edit form — is recorded with the acting
 * user and a Unix timestamp.
 */
class ArchiveAuditLogger {

  /**
   * Constructs an ArchiveAuditLogger.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Logs an archive or unarchive action for a product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product being archived or unarchived.
   * @param string $action
   *   Either 'archive' or 'unarchive'.
   */
  public function log(ProductInterface $product, string $action): void {
    $this->connection->insert('duccinis_archive_log')
      ->fields([
        'product_id'    => (int) $product->id(),
        'product_title' => (string) $product->getTitle(),
        'action'        => $action,
        'uid'           => (int) $this->currentUser->id(),
        'timestamp'     => $this->time->getRequestTime(),
      ])
      ->execute();
  }

}
