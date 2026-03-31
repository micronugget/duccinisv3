# Store Fulfillment Test Conventions

> Use when writing or modifying PHPUnit tests for store_fulfillment —
> Kernel vs Functional distinction, base class selection, module lists,
> mock patterns for DeliveryRadiusCalculator/Nominatim geocoding,
> the `skip_advance_notice` parameter, and SIMPLETEST_* requirements.

## How to Run

```bash
ddev phpunit
```

`phpunit.xml` at project root points the `store_fulfillment` suite to
`web/modules/custom/store_fulfillment/tests/`. The required env vars are
baked in — never set them manually.

| Env var | Value |
|---|---|
| `SIMPLETEST_BASE_URL` | `https://duccinisv4.ddev.site` |
| `SIMPLETEST_DB` | `mysql://db:db@db/db` |
| `SYMFONY_DEPRECATIONS_HELPER` | `disabled` |

Both Kernel and Functional tests **must** run inside DDEV (not bare PHP) because
the DB connection string refers to the `db` Docker hostname.

---

## Kernel vs Functional — How to Choose

| Question | Answer → Use |
|---|---|
| Testing a service method (validator, calculator)? | `Kernel` |
| Testing event subscriber logic in isolation? | `Kernel` |
| Testing a checkout form, AJAX, rendered HTML? | `Functional` |
| Need a real browser session / assertSession? | `Functional` |

### Kernel base class

```php
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

class MyServiceTest extends CommerceKernelTestBase {
```

`CommerceKernelTestBase` bootstraps Commerce entity schemas. You still **must**
call `installEntitySchema()` and `installConfig()` for every entity/config you
need — nothing is auto-installed beyond what the base class declares.

### Functional base class

```php
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

class MyCheckoutTest extends CommerceBrowserTestBase {
  protected $defaultTheme = 'stark'; // required — always set this
```

`CommerceBrowserTestBase` provides `$this->store` and `$this->adminUser` out of
the box. Always set `$defaultTheme = 'stark'` to avoid pulling in the full
custom theme (slow, fragile) for functional tests.

---

## Required `$modules` Lists

### Kernel tests for delivery / order services

```php
protected static $modules = [
  'profile',
  'state_machine',
  'entity_reference_revisions',
  'geofield',
  'geocoder',
  'store_resolver',
  'store_fulfillment',
];
```

Add `'commerce_order'` and `'commerce_number_pattern'` when creating `Order`
entities. Add `'commerce_product'`, `'commerce_shipping'`, `'physical'` only
when the test actually needs shipments.

### Functional tests

```php
protected static $modules = [
  'commerce_cart',
  'commerce_checkout',
  'commerce_payment',
  'commerce_payment_example',
  'commerce_product',
  'store_fulfillment',
];
```

Add `'store_resolver'` when the checkout pane calls `store_resolver.current_store`.

---

## Installing Custom Fields in Kernel Tests

`store_fulfillment_install()` creates the `delivery_radius` and `store_location`
fields programmatically. In Kernel tests the install hook **does not run
automatically** — call it explicitly:

```php
protected function setUp(): void {
  parent::setUp();
  $this->installConfig(['store_fulfillment']);
  \Drupal::moduleHandler()->loadInclude('store_fulfillment', 'install');
  store_fulfillment_install();
}
```

For `OrderValidatorTest` (which only needs `store_hours`), create just that field
manually to avoid pulling in the `geofield` dependency.

---

## Mocking `DeliveryRadiusCalculator` and Nominatim Geocoding

Never let tests call Nominatim. The geocoder makes HTTP requests that will
time out or return wrong data in CI.

### Pattern 1 — mock `calculateDistance` only (fast, most common)

```php
$calculator = $this->getMockBuilder(DeliveryRadiusCalculator::class)
  ->disableOriginalConstructor()
  ->onlyMethods(['calculateDistance'])
  ->getMock();
$calculator->method('calculateDistance')->willReturn(5.0);

$this->validator = new DeliveryRadiusValidator(
  $calculator,
  $this->container->get('string_translation'),
  $this->container->get('geocoder'),
  $this->container->get('logger.factory'),
  $this->container->get('entity_type.manager'),
);
```

### Pattern 2 — mock `getStoreCoordinates` + `getAddressCoordinates`

When you need to test "store coords unavailable" or "address cannot be geocoded":

```php
protected function createValidatorWithMockedGeocoding(
  bool $store_has_coords,
  bool $address_has_coords,
  float $distance
): DeliveryRadiusValidator {
  $calculator = $this->getMockBuilder(DeliveryRadiusCalculator::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['calculateDistance'])
    ->getMock();
  $calculator->method('calculateDistance')->willReturn($distance);

  $validator = $this->getMockBuilder(DeliveryRadiusValidator::class)
    ->setConstructorArgs([
      $calculator,
      $this->container->get('string_translation'),
      $this->container->get('geocoder'),
      $this->container->get('logger.factory'),
      $this->container->get('entity_type.manager'),
    ])
    ->onlyMethods(['getStoreCoordinates', 'getAddressCoordinates'])
    ->getMock();

  $validator->method('getStoreCoordinates')
    ->willReturn($store_has_coords ? ['lat' => 37.7749, 'lon' => -122.4194] : NULL);
  $validator->method('getAddressCoordinates')
    ->willReturn($address_has_coords ? ['lat' => 37.7849, 'lon' => -122.4094] : NULL);

  return $validator;
}
```

### Pattern 3 — mock entire `DeliveryRadiusValidator` (event subscriber tests)

```php
protected function createMockValidator(bool $is_valid, float $distance): DeliveryRadiusValidator {
  $validator = $this->getMockBuilder(DeliveryRadiusValidator::class)
    ->disableOriginalConstructor()
    ->getMock();

  $validator->method('validateDeliveryAddress')->willReturn([
    'valid'    => $is_valid,
    'message'  => $is_valid ? 'Within service area.' : 'Outside service area.',
    'distance' => $distance,
  ]);

  return $validator;
}
```

---

## Mocking `AddressInterface`

```php
$address = $this->getMockBuilder(\Drupal\address\AddressInterface::class)->getMock();
$address->method('getCountryCode')->willReturn('US');
$address->method('getAdministrativeArea')->willReturn('DC');
$address->method('getLocality')->willReturn('Washington');
$address->method('getPostalCode')->willReturn('20009');
$address->method('getAddressLine1')->willReturn('2415 18th St NW');
```

---

## The `skip_advance_notice` Parameter

`OrderValidator::validateFulfillmentTime()` accepts a third bool parameter:

```php
validateFulfillmentTime(OrderInterface $order, $requested_time, bool $skip_advance_notice = FALSE)
```

This exists because the advance-notice check must be **skipped at order placement
time** — by the time the payment gateway round-trip completes, the scheduled
time may fall within the 30-minute window that was valid at checkout.

- Checkout validation: `skip_advance_notice = FALSE` (default)
- Placement event subscriber: `skip_advance_notice = TRUE`

Test both paths explicitly:

```php
// Checkout path — advance notice required.
$result = $this->orderValidator->validateFulfillmentTime($order, $timestamp, FALSE);
$this->assertFalse($result['valid']);

// Placement path — advance notice skipped.
$result = $this->orderValidator->validateFulfillmentTime($order, $timestamp, TRUE);
$this->assertTrue($result['valid']);
```

---

## Logger Mocking in Event Subscriber Tests

```php
$this->logger = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();
$this->loggerFactory = $this->getMockBuilder(\Drupal\Core\Logger\LoggerChannelFactoryInterface::class)->getMock();
$this->loggerFactory->method('get')->willReturn($this->logger);

$this->logger->expects($this->once())
  ->method('info')
  ->with($this->stringContains('validated successfully'), $this->anything());
```

---

## Creating a `WorkflowTransitionEvent` Mock

```php
use Drupal\state_machine\Event\WorkflowTransitionEvent;

protected function createMockWorkflowEvent(OrderInterface $order): WorkflowTransitionEvent {
  $event = $this->getMockBuilder(WorkflowTransitionEvent::class)
    ->disableOriginalConstructor()
    ->getMock();
  $event->method('getEntity')->willReturn($order);
  return $event;
}
```

---

## Event Subscriber Priority — Test Registration

Always verify the event priority, not just that the event is subscribed:

```php
public function testEventSubscriberRegistration(): void {
  $events = OrderPlacementDeliveryRadiusValidator::getSubscribedEvents();
  $this->assertArrayHasKey('commerce_order.place.pre_transition', $events);
  // Priority -100: after Commerce placement logic, before order is finalised.
  $this->assertEquals(['onOrderPlace', -100], $events['commerce_order.place.pre_transition']);
}
```

---

## Shipment Fixture Setup (Kernel)

```php
$order_type = OrderType::load('default');
$order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
$order_type->save();

$field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
$this->container->get('commerce.configurable_field_manager')->createField($field_definition);

$profile = Profile::create([
  'type'    => 'customer',
  'address' => ['country_code' => 'US', 'address_line1' => '456 Mission St', ...],
]);
$profile->save();

$shipment = Shipment::create([
  'type'             => 'default',
  'order_id'         => $order->id(),
  'title'            => 'Shipment',
  'amount'           => new Price('5.00', 'USD'),
  'shipping_profile' => $profile,
]);
$shipment->save();
$order->set('shipments', [$shipment]);
$order->save();
```

---

## Test Grouping

All tests must carry `@group store_fulfillment`. Functional tests covering a
specific issue may add a second group:

```php
/**
 * @group store_fulfillment
 * @group payment
 */
```

---

## Validation Result Shape Assertions

Always assert the **full shape** of every validation result, not just the `valid` flag:

```php
$this->assertArrayHasKey('valid', $result);
$this->assertArrayHasKey('message', $result);
$this->assertArrayHasKey('distance', $result);
$this->assertIsBool($result['valid']);
$this->assertIsString($result['message']);
$this->assertTrue($result['distance'] === NULL || is_float($result['distance']));
```
