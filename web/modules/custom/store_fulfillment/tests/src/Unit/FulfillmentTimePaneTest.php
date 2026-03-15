<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Unit;

use Drupal\profile\Entity\ProfileInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane\FulfillmentTime;
use Drupal\store_resolver\StoreResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Tests FulfillmentTime checkout pane improvements.
 *
 * Covers:
 * - isVisible() falls back to order store when resolver has no cookie context.
 * - resolveCustomerAddress() checks delivery_address_profile data first.
 *
 * @coversDefaultClass \Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane\FulfillmentTime
 * @group store_fulfillment
 */
class FulfillmentTimePaneTest extends UnitTestCase {

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  /**
   * Creates a FulfillmentTime partial mock with constructor disabled.
   *
   * All original method implementations are preserved; only the plugin
   * constructor is skipped so we can inject mocked properties directly.
   *
   * @param \Drupal\store_resolver\StoreResolver|null $storeResolver
   *   Mock store resolver, or NULL to skip injection.
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   Mock order, or NULL to skip injection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   Mock entity type manager, or NULL to skip injection.
   *
   * @return \Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane\FulfillmentTime
   *   The partial mock.
   */
  private function createPane(
    ?StoreResolver $storeResolver = NULL,
    ?OrderInterface $order = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
  ): FulfillmentTime {
    /** @var \Drupal\store_fulfillment\Plugin\Commerce\CheckoutPane\FulfillmentTime $pane */
    $pane = $this->getMockBuilder(FulfillmentTime::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    if ($storeResolver !== NULL) {
      $this->setProperty($pane, 'storeResolver', $storeResolver);
    }
    if ($order !== NULL) {
      // $order is declared on CheckoutPaneBase, so walk up via its class name.
      $this->setProperty($pane, 'order', $order, CheckoutPaneBase::class);
    }
    if ($entityTypeManager !== NULL) {
      // $entityTypeManager is redeclared on FulfillmentTime itself.
      $this->setProperty($pane, 'entityTypeManager', $entityTypeManager);
    }

    return $pane;
  }

  /**
   * Sets an inaccessible property on an object using reflection.
   *
   * @param object $object
   *   Target object.
   * @param string $property
   *   Property name.
   * @param mixed $value
   *   Value to set.
   * @param string|null $declaringClass
   *   The FQN of the class that declares the property, or NULL to use the
   *   runtime class of $object.
   */
  private function setProperty(object $object, string $property, mixed $value, ?string $declaringClass = NULL): void {
    $reflection = new \ReflectionClass($declaringClass ?? $object);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(TRUE);
    $prop->setValue($object, $value);
  }

  /**
   * Calls a protected method on an object using reflection.
   *
   * @param object $object
   *   Target object.
   * @param string $method
   *   Method name.
   * @param mixed ...$args
   *   Arguments to pass.
   *
   * @return mixed
   *   The return value of the method.
   */
  private function callProtected(object $object, string $method, mixed ...$args): mixed {
    $ref = new \ReflectionMethod($object, $method);
    $ref->setAccessible(TRUE);
    return $ref->invoke($object, ...$args);
  }

  // -----------------------------------------------------------------------
  // isVisible() tests
  // -----------------------------------------------------------------------

  /**
   * @covers ::isVisible
   */
  public function testIsVisibleReturnsTrueWhenResolverHasCurrentStore(): void {
    $storeResolver = $this->createMock(StoreResolver::class);
    $storeResolver->method('hasCurrentStore')->willReturn(TRUE);

    $order = $this->createMock(OrderInterface::class);
    // getStoreId() must not be consulted — hasCurrentStore() short-circuits.
    $order->expects($this->never())->method('getStoreId');

    $pane = $this->createPane($storeResolver, $order);
    $this->assertTrue($pane->isVisible());
  }

  /**
   * @covers ::isVisible
   */
  public function testIsVisibleFallsBackToOrderStoreIdWhenResolverHasNoCookieContext(): void {
    $storeResolver = $this->createMock(StoreResolver::class);
    $storeResolver->method('hasCurrentStore')->willReturn(FALSE);

    $order = $this->createMock(OrderInterface::class);
    $order->method('getStoreId')->willReturn(1);

    $pane = $this->createPane($storeResolver, $order);
    $this->assertTrue($pane->isVisible());
  }

  /**
   * @covers ::isVisible
   */
  public function testIsVisibleReturnsFalseWhenNeitherResolverNorOrderHasStore(): void {
    $storeResolver = $this->createMock(StoreResolver::class);
    $storeResolver->method('hasCurrentStore')->willReturn(FALSE);

    $order = $this->createMock(OrderInterface::class);
    $order->method('getStoreId')->willReturn(NULL);

    $pane = $this->createPane($storeResolver, $order);
    $this->assertFalse($pane->isVisible());
  }

  // -----------------------------------------------------------------------
  // resolveCustomerAddress() tests
  // -----------------------------------------------------------------------

  /**
   * @covers ::resolveCustomerAddress
   */
  public function testResolveCustomerAddressChecksDeliveryAddressProfileFirst(): void {
    // Build a minimal address item mock.
    $addressItem = $this->createMock(AddressItem::class);

    $addressField = $this->createMock(FieldItemListInterface::class);
    $addressField->method('isEmpty')->willReturn(FALSE);
    $addressField->method('first')->willReturn($addressItem);

    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->with('address')->willReturn(TRUE);
    $profile->method('get')->with('address')->willReturn($addressField);

    $profileStorage = $this->createMock(EntityStorageInterface::class);
    // Expect load() to be called with the specific profile ID.
    $profileStorage->expects($this->once())
      ->method('load')
      ->with(42)
      ->willReturn($profile);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($profileStorage);

    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')
      ->with('delivery_address_profile')
      ->willReturn(42);

    $pane = $this->createPane(NULL, $order, $entityTypeManager);
    $result = $this->callProtected($pane, 'resolveCustomerAddress');

    $this->assertSame($addressItem, $result);
  }

  /**
   * Returns NULL when no address source is available.
   *
   * When delivery_address_profile is absent the method should skip that branch
   * and return NULL when no other address source is available.
   *
   * @covers ::resolveCustomerAddress
   */
  public function testResolveCustomerAddressReturnsNullWhenNoSourceAvailable(): void {
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')->with('delivery_address_profile')->willReturn(NULL);
    $order->method('hasField')->with('shipments')->willReturn(FALSE);
    $order->method('getBillingProfile')->willReturn(NULL);
    $order->method('getCustomer')->willReturn(NULL);

    // entityTypeManager not needed when delivery_address_profile is absent
    // and all other sources are NULL — but we still need it to avoid property
    // access errors on the storage call that never happens.
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->never())->method('getStorage');

    $pane = $this->createPane(NULL, $order, $entityTypeManager);
    $result = $this->callProtected($pane, 'resolveCustomerAddress');

    $this->assertNull($result);
  }

}
