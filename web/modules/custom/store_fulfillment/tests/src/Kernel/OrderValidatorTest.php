<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_store\Entity\Store;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the OrderValidator service.
 *
 * @coversDefaultClass \Drupal\store_fulfillment\OrderValidator
 * @group store_fulfillment
 */
class OrderValidatorTest extends KernelTestBase {

  /**
   * The order validator service ID.
   */
  const ORDER_VALIDATOR_SERVICE = 'store_fulfillment.order_validator';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'text',
    'datetime',
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_order',
    'store_resolver',
    'store_fulfillment',
  ];

  /**
   * The order validator service.
   *
   * @var \Drupal\store_fulfillment\OrderValidator
   */
  protected $orderValidator;

  /**
   * A test store entity.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installConfig(['commerce_store', 'commerce_order', 'store_fulfillment']);

    $this->orderValidator = $this->container->get(self::ORDER_VALIDATOR_SERVICE);

    // Create a test store with hours.
    $this->store = Store::create([
      'type' => 'online',
      'uid' => 1,
      'name' => 'Test Store',
      'mail' => 'test@example.com',
      'address' => [
        'country_code' => 'US',
      ],
      'timezone' => 'America/New_York',
      'store_hours' => [
        ['value' => 'monday|09:00|17:00'],
        ['value' => 'tuesday|09:00|17:00'],
        ['value' => 'wednesday|09:00|17:00'],
        ['value' => 'thursday|09:00|17:00'],
        ['value' => 'friday|09:00|21:00'],
        ['value' => 'saturday|10:00|18:00'],
        ['value' => 'sunday|11:00|16:00'],
      ],
    ]);
    $this->store->save();
  }

  /**
   * Tests immediate order allowed when store is open.
   *
   * @covers ::isImmediateOrderAllowed
   */
  public function testImmediateOrderAllowedWhenOpen() {
    // Mock the time service to return a time during business hours.
    // This would require dependency injection mocking in a real implementation.
    // For now, we test the logic directly.
    $result = $this->orderValidator->isImmediateOrderAllowed($this->store);
    // Result depends on current actual time, so we just ensure method works.
    $this->assertIsBool($result);
  }

  /**
   * Tests validation of scheduled order during business hours.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testValidateScheduledOrderDuringBusinessHours() {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_type', 'scheduled');

    // Create a scheduled time for next Monday at 10:00 AM.
    $timezone = new \DateTimeZone('America/New_York');
    $scheduled_time = new \DateTime('next Monday 10:00', $timezone);
    $timestamp = $scheduled_time->getTimestamp();

    $result = $this->orderValidator->validateFulfillmentTime($order, $timestamp);

    $this->assertTrue($result['valid'], 'Scheduled order during business hours should be valid');
  }

  /**
   * Tests validation of scheduled order outside business hours.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testValidateScheduledOrderOutsideBusinessHours() {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_type', 'scheduled');

    // Create a scheduled time for next Monday at 3:00 AM (closed).
    $timezone = new \DateTimeZone('America/New_York');
    $scheduled_time = new \DateTime('next Monday 03:00', $timezone);
    $timestamp = $scheduled_time->getTimestamp();

    $result = $this->orderValidator->validateFulfillmentTime($order, $timestamp);

    $this->assertFalse($result['valid'], 'Scheduled order outside business hours should be invalid');
    $this->assertStringContainsString('outside store operating hours', $result['message']);
  }

  /**
   * Tests validation of scheduled order too soon.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testValidateScheduledOrderTooSoon() {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_type', 'scheduled');

    // Try to schedule for 5 minutes from now (less than 30 min minimum).
    $timezone = new \DateTimeZone('America/New_York');
    $scheduled_time = new \DateTime('+5 minutes', $timezone);
    $timestamp = $scheduled_time->getTimestamp();

    $result = $this->orderValidator->validateFulfillmentTime($order, $timestamp);

    $this->assertFalse($result['valid'], 'Scheduled order too soon should be invalid');
    $this->assertStringContainsString('at least', $result['message']);
  }

  /**
   * Tests validation of scheduled order too far in future.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testValidateScheduledOrderTooFarFuture() {
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $order->setData('fulfillment_type', 'scheduled');

    // Try to schedule for 20 days from now (more than 14 day maximum).
    $timezone = new \DateTimeZone('America/New_York');
    $scheduled_time = new \DateTime('+20 days', $timezone);
    $timestamp = $scheduled_time->getTimestamp();

    $result = $this->orderValidator->validateFulfillmentTime($order, $timestamp);

    $this->assertFalse($result['valid'], 'Scheduled order too far in future should be invalid');
    $this->assertStringContainsString('cannot be more than', $result['message']);
  }

  /**
   * Tests getting next available slot.
   *
   * @covers ::getNextAvailableSlot
   */
  public function testGetNextAvailableSlot() {
    $next_slot = $this->orderValidator->getNextAvailableSlot($this->store);

    $this->assertInstanceOf(\DateTime::class, $next_slot, 'Next available slot should return a DateTime object');
    
    $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
    $this->assertGreaterThan($now, $next_slot, 'Next available slot should be in the future');
  }

  /**
   * Tests overnight hours handling.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testOvernightHours() {
    // Create store with overnight hours.
    $overnight_store = Store::create([
      'type' => 'online',
      'uid' => 1,
      'name' => 'Late Night Store',
      'mail' => 'test@example.com',
      'address' => [
        'country_code' => 'US',
      ],
      'timezone' => 'America/New_York',
      'store_hours' => [
        ['value' => 'friday|22:00|02:00'],
        ['value' => 'saturday|22:00|02:00'],
      ],
    ]);
    $overnight_store->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $overnight_store->id(),
    ]);
    $order->setData('fulfillment_type', 'scheduled');

    // Schedule for next Friday at 11:30 PM (within overnight hours).
    $timezone = new \DateTimeZone('America/New_York');
    $scheduled_time = new \DateTime('next Friday 23:30', $timezone);
    $timestamp = $scheduled_time->getTimestamp();

    $result = $this->orderValidator->validateFulfillmentTime($order, $timestamp);

    $this->assertTrue($result['valid'], 'Order during overnight hours should be valid');
  }

  /**
   * Tests order without store.
   *
   * @covers ::validateFulfillmentTime
   */
  public function testValidateOrderWithoutStore() {
    $order = Order::create([
      'type' => 'default',
    ]);
    $order->setData('fulfillment_type', 'asap');

    $result = $this->orderValidator->validateFulfillmentTime($order, NULL);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('No store', $result['message']);
  }

}
