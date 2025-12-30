<?php

namespace Drupal\store_resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityRepositoryInterface;

/**
 * Service for resolving the current store context.
 */
class StoreResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The cookie name for storing selected store.
   */
  const STORE_COOKIE_NAME = 'store_resolver_store_id';

  /**
   * Constructs a new StoreResolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    AccountInterface $current_user,
    EntityRepositoryInterface $entity_repository
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Gets the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface|null
   *   The current store, or NULL if none is set.
   */
  public function getCurrentStore() {
    $store_id = $this->getCurrentStoreId();
    if ($store_id) {
      $store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);
      if ($store instanceof StoreInterface) {
        return $store;
      }
    }
    return NULL;
  }

  /**
   * Gets the current store ID.
   *
   * @return int|null
   *   The current store ID, or NULL if none is set.
   */
  public function getCurrentStoreId() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->cookies->has(self::STORE_COOKIE_NAME)) {
      return (int) $request->cookies->get(self::STORE_COOKIE_NAME);
    }
    return NULL;
  }

  /**
   * Sets the current store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store to set as current.
   *
   * @return $this
   */
  public function setCurrentStore(StoreInterface $store) {
    // Cookie will be set by the form submission handler.
    return $this;
  }

  /**
   * Gets all available stores.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface[]
   *   An array of stores.
   */
  public function getAvailableStores() {
    $storage = $this->entityTypeManager->getStorage('commerce_store');
    $stores = $storage->loadMultiple();
    return $stores;
  }

  /**
   * Checks if a store is currently selected.
   *
   * @return bool
   *   TRUE if a store is selected, FALSE otherwise.
   */
  public function hasCurrentStore() {
    return $this->getCurrentStoreId() !== NULL;
  }

}
