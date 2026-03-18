<?php

declare(strict_types=1);

namespace Drupal\Tests\store_resolver\Kernel;

use Drupal\store_resolver\StoreResolver;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests that StoreResolver reads and resolves stores from the request cookie.
 *
 * Verifies the three cookie-related methods that back the modal
 * "current store" logic:
 *   - getCurrentStoreId() returns NULL when no cookie is present.
 *   - getCurrentStoreId() returns the integer store ID when the cookie is set.
 *   - getCurrentStore() loads the correct store entity from the cookie value.
 *   - getCurrentStore() returns NULL for a non-existent store ID.
 *   - hasCurrentStore() mirrors the above correctly.
 *
 * @group store_resolver
 * @group store_resolver_cookie
 *
 * @covers \Drupal\store_resolver\StoreResolver::getCurrentStoreId
 * @covers \Drupal\store_resolver\StoreResolver::getCurrentStore
 * @covers \Drupal\store_resolver\StoreResolver::hasCurrentStore
 */
class StoreResolverCookieReadTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'store_resolver',
  ];

  /**
   * The StoreResolver service under test.
   *
   * @var \Drupal\store_resolver\StoreResolver
   */
  protected StoreResolver $storeResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storeResolver = $this->container->get('store_resolver.current_store');
  }

  /**
   * Tests that getCurrentStoreId() returns NULL when no cookie is present.
   */
  public function testGetCurrentStoreIdReturnsNullWithoutCookie(): void {
    $this->assertNull($this->storeResolver->getCurrentStoreId());
  }

  /**
   * Tests that hasCurrentStore() returns FALSE when no cookie is present.
   */
  public function testHasCurrentStoreReturnsFalseWithoutCookie(): void {
    $this->assertFalse($this->storeResolver->hasCurrentStore());
  }

  /**
   * Tests that getCurrentStore() returns NULL when no cookie is present.
   */
  public function testGetCurrentStoreReturnsNullWithoutCookie(): void {
    $this->assertNull($this->storeResolver->getCurrentStore());
  }

  /**
   * Tests that getCurrentStoreId() reads the integer ID from the cookie.
   */
  public function testGetCurrentStoreIdReadsFromCookie(): void {
    $this->setCookieOnRequest((string) $this->store->id());

    $this->assertSame((int) $this->store->id(), $this->storeResolver->getCurrentStoreId());
  }

  /**
   * Tests that hasCurrentStore() returns TRUE when the cookie is set.
   */
  public function testHasCurrentStoreReturnsTrueWhenCookieSet(): void {
    $this->setCookieOnRequest((string) $this->store->id());

    $this->assertTrue($this->storeResolver->hasCurrentStore());
  }

  /**
   * Tests that getCurrentStore() returns the correct entity when cookie is set.
   */
  public function testGetCurrentStoreLoadsCorrectEntityFromCookie(): void {
    $this->setCookieOnRequest((string) $this->store->id());

    $resolved = $this->storeResolver->getCurrentStore();

    $this->assertNotNull($resolved);
    $this->assertEquals($this->store->id(), $resolved->id());
    $this->assertEquals($this->store->getName(), $resolved->getName());
  }

  /**
   * Tests that getCurrentStore() returns NULL for a non-existent store ID.
   *
   * Proves that a stale or forged cookie value does not cause an error and
   * correctly falls back to NULL (so the UI can show a "no store selected"
   * state rather than crashing).
   */
  public function testGetCurrentStoreReturnsNullForInvalidCookieValue(): void {
    $this->setCookieOnRequest('99999');

    // getCurrentStoreId() will return 99999, but no store entity exists with
    // that ID, so getCurrentStore() must return NULL.
    $this->assertNull($this->storeResolver->getCurrentStore());
  }

  /**
   * Tests switching stores by updating the cookie value.
   *
   * Demonstrates the fix for the "store change doesn't persist" bug: once the
   * cookie is updated (as done by the JS setStoreCookie() call or PHP
   * setcookie() in the form handler), the service returns the new store.
   */
  public function testGetCurrentStoreReflectsUpdatedCookieValue(): void {
    // Create a second store to switch to.
    $second_store = $this->createStore('Second Store', 'USD');

    // Start with the first store selected.
    $this->setCookieOnRequest((string) $this->store->id());
    $this->assertEquals($this->store->id(), $this->storeResolver->getCurrentStore()->id());

    // Simulate the cookie being updated to the second store.
    $this->setCookieOnRequest((string) $second_store->id());
    $this->assertEquals($second_store->id(), $this->storeResolver->getCurrentStore()->id());
  }

  /**
   * Helper: injects a store cookie value into the kernel test request.
   *
   * The StoreResolver reads cookies from the Symfony Request object on the
   * request stack, so we mutate the ParameterBag directly.
   *
   * @param string $store_id
   *   The raw cookie value to set.
   */
  private function setCookieOnRequest(string $store_id): void {
    $this->container
      ->get('request_stack')
      ->getCurrentRequest()
      ->cookies
      ->set(StoreResolver::STORE_COOKIE_NAME, $store_id);
  }

}
