<?php

namespace Drupal\commerce_reorder\Controller;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles reordering of previous orders.
 */
class ReorderController extends ControllerBase {

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new ReorderController object.
   *
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(CartManagerInterface $cart_manager, CartProviderInterface $cart_provider, MessengerInterface $messenger) {
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('messenger')
    );
  }

  /**
   * Reorders items from a previous order.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order to reorder from.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the cart page.
   */
  public function reorder(AccountInterface $user, OrderInterface $commerce_order) {
    $store = $commerce_order->getStore();
    $order_items = $commerce_order->getItems();

    if (empty($order_items)) {
      $this->messenger->addWarning($this->t('This order has no items to reorder.'));
      return new RedirectResponse(Url::fromRoute('view.commerce_user_orders.order_page', [
        'user' => $user->id(),
      ])->toString());
    }

    // Get or create a cart for this store.
    $cart = $this->cartProvider->getCart('default', $store);
    if (!$cart) {
      $cart = $this->cartProvider->createCart('default', $store);
    }

    $added_count = 0;
    foreach ($order_items as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();

      // Skip if the purchased entity no longer exists or is not available.
      if (!$purchased_entity || !$purchased_entity->access('view')) {
        continue;
      }

      // Add the item to the cart.
      $this->cartManager->addEntity($cart, $purchased_entity, $order_item->getQuantity());
      $added_count++;
    }

    if ($added_count > 0) {
      $this->messenger->addStatus($this->t('Added @count item(s) from order @order_number to your cart.', [
        '@count' => $added_count,
        '@order_number' => $commerce_order->getOrderNumber(),
      ]));
    }
    else {
      $this->messenger->addWarning($this->t('None of the items from this order are currently available.'));
    }

    // Redirect to the cart.
    return new RedirectResponse(Url::fromRoute('commerce_cart.page')->toString());
  }

}
