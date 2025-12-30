<?php

namespace Drupal\commerce_reorder\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check for reordering orders.
 */
class ReorderAccessCheck implements AccessInterface {

  /**
   * Checks access to the reorder route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user from the route.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(Route $route, AccountInterface $account, AccountInterface $user, OrderInterface $commerce_order) {
    // Check if the user owns the order or has admin permission.
    if ($commerce_order->getCustomerId() == $account->id() && $user->id() == $account->id()) {
      return AccessResult::allowed()
        ->addCacheableDependency($commerce_order)
        ->addCacheableDependency($account);
    }

    // Allow administrators to reorder.
    if ($account->hasPermission('administer commerce_order')) {
      return AccessResult::allowed()
        ->addCacheableDependency($commerce_order)
        ->addCacheableDependency($account);
    }

    return AccessResult::forbidden()
      ->addCacheableDependency($commerce_order)
      ->addCacheableDependency($account);
  }

}
