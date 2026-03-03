<?php

declare(strict_types=1);

namespace Drupal\duccinis_archive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin page: archive audit log + order history for archived products.
 *
 * Route: GET /admin/duccinis/archive-history.
 *
 * Displays two sections:
 *   1. Archive audit log — every archive/unarchive action with user and time.
 *   2. Order history — order items referencing currently-archived products,
 *      each row styled with an "Archived" indicator so staff can identify
 *      which historical orders contained items no longer on the menu.
 */
class ArchiveHistoryController extends ControllerBase {

  /**
   * Constructs an ArchiveHistoryController.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    private readonly Connection $connection,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the archive history admin page.
   *
   * @return array
   *   A render array.
   */
  public function build(): array {
    $build = [];

    $build['audit_log'] = $this->buildAuditLog();
    $build['order_history'] = $this->buildOrderHistory();

    return $build;
  }

  /**
   * Builds the audit log section.
   */
  private function buildAuditLog(): array {
    $section = [
      '#type'  => 'details',
      '#title' => $this->t('Archive audit log'),
      '#open'  => TRUE,
    ];

    $rows = [];

    $log_exists = $this->connection->schema()->tableExists('duccinis_archive_log');

    if ($log_exists) {
      $results = $this->connection->select('duccinis_archive_log', 'l')
        ->fields('l')
        ->orderBy('timestamp', 'DESC')
        ->range(0, 200)
        ->execute()
        ->fetchAll();

      foreach ($results as $entry) {
        $user_url = Url::fromRoute('entity.user.canonical', ['user' => $entry->uid]);
        $user_link = $entry->uid
          ? Link::fromTextAndUrl('uid ' . $entry->uid, $user_url)->toString()
          : $this->t('anonymous');

        $product_url = Url::fromRoute('entity.commerce_product.edit_form', ['commerce_product' => $entry->product_id]);
        $product_link = Link::fromTextAndUrl($entry->product_title, $product_url)->toString();

        $action_label = $entry->action === 'archive'
          ? '<span class="badge badge--error">' . $this->t('Archived') . '</span>'
          : '<span class="badge badge--success">' . $this->t('Unarchived') . '</span>';

        $rows[] = [
          $this->dateFormatter->format((int) $entry->timestamp, 'short'),
          ['data' => ['#markup' => $product_link]],
          ['data' => ['#markup' => $action_label]],
          ['data' => ['#markup' => $user_link]],
        ];
      }
    }

    $section['table'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Time'),
        $this->t('Product'),
        $this->t('Action'),
        $this->t('User'),
      ],
      '#rows'  => $rows,
      '#empty' => $this->t('No archive actions recorded yet.'),
    ];

    return $section;
  }

  /**
   * Builds the order history section showing orders with archived products.
   */
  private function buildOrderHistory(): array {
    $section = [
      '#type'  => 'details',
      '#title' => $this->t('Order history — archived products'),
      '#open'  => TRUE,
    ];

    $rows = [];

    // Collect IDs of all currently-archived products.
    $archived_product_ids = $this->entityTypeManager
      ->getStorage('commerce_product')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_archived', TRUE)
      ->execute();

    if (!empty($archived_product_ids)) {
      // Get all variation IDs for those products.
      $variation_ids = $this->entityTypeManager
        ->getStorage('commerce_product_variation')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('product_id', $archived_product_ids, 'IN')
        ->execute();

      if (!empty($variation_ids)) {
        // Get order item IDs for those variations.
        $order_item_ids = $this->entityTypeManager
          ->getStorage('commerce_order_item')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('purchased_entity', $variation_ids, 'IN')
          ->sort('order_id', 'DESC')
          ->range(0, 200)
          ->execute();

        /** @var \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items */
        $order_items = $this->entityTypeManager
          ->getStorage('commerce_order_item')
          ->loadMultiple($order_item_ids);

        foreach ($order_items as $item) {
          $order = $item->getOrder();
          if (!$order) {
            continue;
          }

          $order_url = Url::fromRoute('entity.commerce_order.canonical', ['commerce_order' => $order->id()]);
          $order_link = Link::fromTextAndUrl('#' . $order->id(), $order_url)->toString();

          $archived_badge = ' <span class="badge badge--error" title="' . $this->t('This product is currently archived and not on the menu.') . '">' . $this->t('Archived') . '</span>';
          $title_cell = $item->label() . $archived_badge;

          $placed = $order->get('placed')->value;
          $placed_formatted = $placed
            ? $this->dateFormatter->format((int) $placed, 'short')
            : $this->t('—');

          $rows[] = [
            ['data' => ['#markup' => $order_link]],
            ['data' => ['#markup' => $title_cell]],
            (string) $item->getQuantity(),
            $placed_formatted,
            $order->getState()->getLabel(),
          ];
        }
      }
    }

    $section['table'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Order'),
        $this->t('Item (archived)'),
        $this->t('Qty'),
        $this->t('Placed'),
        $this->t('Order state'),
      ],
      '#rows'  => $rows,
      '#empty' => $this->t('No orders contain currently-archived products.'),
    ];

    return $section;
  }

}
