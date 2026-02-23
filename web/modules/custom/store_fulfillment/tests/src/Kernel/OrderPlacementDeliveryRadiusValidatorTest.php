<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\store_fulfillment\DeliveryRadiusValidator;
use Drupal\store_fulfillment\EventSubscriber\OrderPlacementDeliveryRadiusValidator;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests the OrderPlacementDeliveryRadiusValidator event subscriber.
 *
 * @group store_fulfillment
 * @coversDefaultClass \Drupal\store_fulfillment\EventSubscriber\OrderPlacementDeliveryRadiusValidator
 */
class OrderPlacementDeliveryRadiusValidatorTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'physical',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_product',
    'commerce_shipping',
    'geofield',
    'geocoder',
    'store_resolver',
    'store_fulfillment',
  ];

  /**
   * Test store entity (overrides parent's untyped $store).
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * Test logger channel (LoggerInterface mock).
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Logger factory mock wrapping $logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_shipping_method');
    $this->installEntitySchema('commerce_shipment');
    $this->installEntitySchema('profile');
    $this->installConfig(['commerce_product', 'commerce_order', 'commerce_shipping']);

    // Install store_fulfillment's delivery_radius field directly (skipping
    // geofield store_location which is not needed for these tests).
    if (!\Drupal\field\Entity\FieldStorageConfig::loadByName('commerce_store', 'delivery_radius')) {
      $field_storage = \Drupal\field\Entity\FieldStorageConfig::create([
        'field_name' => 'delivery_radius',
        'entity_type' => 'commerce_store',
        'type' => 'decimal',
        'settings' => ['precision' => 10, 'scale' => 2],
      ]);
      $field_storage->save();
      \Drupal\field\Entity\FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'online',
        'label' => 'Delivery Radius',
        'default_value' => [['value' => 10.00]],
      ])->save();
    }

    // Configure the default order type to support shipments, then create the
    // shipments field on the order entity (mirrors ShippingKernelTestBase).
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')->createField($field_definition);

    // Create test store.
    $this->store = Store::create([
      'type' => 'online',
      'name' => 'Test Store',
      'mail' => 'test@example.com',
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'San Francisco',
        'postal_code' => '94102',
        'address_line1' => '123 Market St',
      ],
      'timezone' => 'America/Los_Angeles',
      'delivery_radius' => 10.0,
    ]);
    $this->store->save();

    // Create mock logger channel and factory.
    $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
    $this->loggerFactory = $this->getMockBuilder(LoggerChannelFactoryInterface::class)->getMock();
    $this->loggerFactory->method('get')->willReturn($this->logger);
  }

  /**
   * Tests order placement succeeds with address in radius.
   *
   * @covers ::onOrderPlace
   */
  public function testOrderPlacementSucceedsWithAddressInRadius(): void {
    $order = $this->createTestOrder('delivery', TRUE);

    // Create validator that returns valid result.
    $radius_validator = $this->createMockValidator(TRUE, 5.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    // Create mock event.
    $event = $this->createMockWorkflowEvent($order);

    // Logger should log success.
    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('validated successfully'),
        $this->anything()
      );

    // This should not throw an exception.
    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests order placement fails with address outside radius.
   *
   * @covers ::onOrderPlace
   */
  public function testOrderPlacementFailsWithAddressOutsideRadius(): void {
    $order = $this->createTestOrder('delivery', TRUE);

    // Create validator that returns invalid result.
    $radius_validator = $this->createMockValidator(FALSE, 15.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    // Create mock event.
    $event = $this->createMockWorkflowEvent($order);

    // Logger should log warning.
    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        $this->stringContains('validation failed'),
        $this->anything()
      );

    // This should throw InvalidArgumentException.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('outside our service area');

    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests pickup orders are not validated.
   *
   * @covers ::onOrderPlace
   */
  public function testPickupOrdersAreNotValidated(): void {
    $order = $this->createTestOrder('pickup', TRUE);

    // Create validator that would fail validation.
    $radius_validator = $this->createMockValidator(FALSE, 15.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    // Create mock event.
    $event = $this->createMockWorkflowEvent($order);

    // Logger should not be called for pickup orders.
    $this->logger->expects($this->never())->method('info');
    $this->logger->expects($this->never())->method('warning');
    $this->logger->expects($this->never())->method('error');

    // This should not throw an exception (pickup bypasses validation).
    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests validation when order has no store.
   *
   * @covers ::onOrderPlace
   */
  public function testValidationFailsWhenOrderHasNoStore(): void {
    $order = $this->createTestOrder('delivery', TRUE);
    $order->set('store_id', NULL);
    $order->save();

    $radius_validator = $this->createMockValidator(TRUE, 5.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    $event = $this->createMockWorkflowEvent($order);

    // Logger should log error.
    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('no store assigned'),
        $this->anything()
      );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('No store assigned to order');

    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests validation when order has no shipping profile.
   *
   * @covers ::onOrderPlace
   */
  public function testValidationFailsWhenOrderHasNoShippingProfile(): void {
    $order = $this->createTestOrder('delivery', FALSE);

    $radius_validator = $this->createMockValidator(TRUE, 5.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    $event = $this->createMockWorkflowEvent($order);

    // Logger should log warning about no address resolved.
    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        $this->stringContains('no address could be resolved'),
        $this->anything()
      );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('No delivery address provided');

    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests validation when shipping profile has no address.
   *
   * @covers ::onOrderPlace
   */
  public function testValidationFailsWhenShippingProfileHasNoAddress(): void {
    $order = $this->createTestOrder('delivery', TRUE);

    // Get shipment and clear address.
    $shipment = $order->get('shipments')->entity;
    $shipping_profile = $shipment->getShippingProfile();
    $shipping_profile->set('address', NULL);
    $shipping_profile->save();

    $radius_validator = $this->createMockValidator(TRUE, 5.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);

    $event = $this->createMockWorkflowEvent($order);

    // Logger should log warning about no address resolved.
    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        $this->stringContains('no address could be resolved'),
        $this->anything()
      );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('No delivery address provided');

    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests that appropriate logging occurs.
   *
   * @covers ::onOrderPlace
   */
  public function testProperLoggingOccurs(): void {
    $order = $this->createTestOrder('delivery', TRUE);
    $radius_validator = $this->createMockValidator(TRUE, 5.0);
    $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);
    $event = $this->createMockWorkflowEvent($order);

    // Verify info log includes order ID and distance.
    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('validated successfully'),
        $this->callback(function ($context) use ($order) {
          return isset($context['@order_id']) &&
            isset($context['@distance']) &&
            $context['@order_id'] == $order->id();
        })
      );

    $event_subscriber->onOrderPlace($event);
  }

  /**
   * Tests event subscriber is registered correctly.
   *
   * @covers ::getSubscribedEvents
   */
  public function testEventSubscriberRegistration(): void {
    $events = OrderPlacementDeliveryRadiusValidator::getSubscribedEvents();

    $this->assertArrayHasKey('commerce_order.place.pre_transition', $events);
    $this->assertEquals(['onOrderPlace', -100], $events['commerce_order.place.pre_transition']);
  }

  /**
   * Tests validation bypassed for non-delivery orders.
   *
   * @covers ::onOrderPlace
   */
  public function testValidationBypassedForNonDeliveryOrders(): void {
    // Test various non-delivery fulfillment methods.
    $methods = ['pickup', 'carryout', NULL, ''];

    foreach ($methods as $method) {
      $order = $this->createTestOrder($method, FALSE);

      // Validator should never be called.
      $radius_validator = $this->getMockBuilder(DeliveryRadiusValidator::class)
        ->disableOriginalConstructor()
        ->getMock();
      $radius_validator->expects($this->never())->method('validateDeliveryAddress');

      $event_subscriber = new OrderPlacementDeliveryRadiusValidator($radius_validator, $this->loggerFactory);
      $event = $this->createMockWorkflowEvent($order);

      // Should not throw exception.
      $event_subscriber->onOrderPlace($event);
    }
  }

  /**
   * Creates a mock validator with predetermined result.
   *
   * @param bool $is_valid
   *   Whether the validation should succeed.
   * @param float $distance
   *   The distance to return.
   *
   * @return \Drupal\store_fulfillment\DeliveryRadiusValidator
   *   The mocked validator.
   */
  protected function createMockValidator(bool $is_valid, float $distance): DeliveryRadiusValidator {
    $validator = $this->getMockBuilder(DeliveryRadiusValidator::class)
      ->disableOriginalConstructor()
      ->getMock();

    $result = [
      'valid' => $is_valid,
      'message' => $is_valid
        ? 'Your delivery address is within our service area.'
        : 'Sorry, your delivery address is outside our service area.',
      'distance' => $distance,
    ];

    $validator->method('validateDeliveryAddress')->willReturn($result);

    return $validator;
  }

  /**
   * Creates a test order with specified fulfillment method.
   *
   * @param string|null $fulfillment_method
   *   The fulfillment method ('delivery', 'pickup', or NULL).
   * @param bool $add_shipment
   *   Whether to add a shipment with shipping profile.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The test order.
   */
  protected function createTestOrder(?string $fulfillment_method, bool $add_shipment): OrderInterface {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'customer@example.com',
      'store_id' => $this->store->id(),
      'order_items' => [],
    ]);

    if ($fulfillment_method) {
      $order->setData('fulfillment_method', $fulfillment_method);
    }

    $order->save();

    if ($add_shipment) {
      // Create shipping profile with address.
      $profile = Profile::create([
        'type' => 'customer',
        'address' => [
          'country_code' => 'US',
          'administrative_area' => 'CA',
          'locality' => 'San Francisco',
          'postal_code' => '94103',
          'address_line1' => '456 Mission St',
        ],
      ]);
      $profile->save();

      // Create shipment.
      $shipment = Shipment::create([
        'type' => 'default',
        'order_id' => $order->id(),
        'title' => 'Shipment',
        'amount' => new Price('5.00', 'USD'),
        'shipping_profile' => $profile,
      ]);
      $shipment->save();

      $order->set('shipments', [$shipment]);
      $order->save();
    }

    return $order;
  }

  /**
   * Creates a mock workflow transition event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return \Drupal\state_machine\Event\WorkflowTransitionEvent
   *   The mock event.
   */
  protected function createMockWorkflowEvent(OrderInterface $order): WorkflowTransitionEvent {
    $event = $this->getMockBuilder(WorkflowTransitionEvent::class)
      ->disableOriginalConstructor()
      ->getMock();

    $event->method('getEntity')->willReturn($order);

    return $event;
  }

}
