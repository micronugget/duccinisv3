<?php

namespace Drupal\commerce_reorder\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display a reorder link.
 *
 * @ViewsField("commerce_reorder_link")
 */
class ReorderLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $order = $this->getEntity($values);

    if (!$order instanceof OrderInterface) {
      return [];
    }

    // Don't show reorder link for draft orders.
    if ($order->getState()->getId() === 'draft') {
      return [];
    }

    $user = $order->getCustomer();
    if (!$user) {
      return [];
    }

    $url = Url::fromRoute('commerce_reorder.reorder', [
      'user' => $user->id(),
      'commerce_order' => $order->id(),
    ]);

    // Check access.
    if (!$url->access()) {
      return [];
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Reorder'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button', 'button--small', 'button--primary'],
      ],
    ];
  }

}
