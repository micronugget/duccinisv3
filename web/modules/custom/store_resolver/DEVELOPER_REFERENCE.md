# Store Hours Field - Developer Quick Reference

## Field Access

```php
// Get store hours field
$store = \Drupal::entityTypeManager()
  ->getStorage('commerce_store')
  ->load($store_id);

if ($store->hasField('store_hours')) {
  $hours = $store->get('store_hours');

  foreach ($hours as $hour_item) {
    $value = $hour_item->value;
    // Example: "monday|09:00|17:00"
  }
}
```

## Format Parsing

```php
// Parse a single line
$line = "monday|09:00|17:00";
$parts = explode('|', $line);
if (count($parts) === 3) {
  [$day, $open_time, $close_time] = $parts;
  // day = "monday", open_time = "09:00", close_time = "17:00"
}

// Parse multi-line value
$value = $hour_item->value;
$lines = preg_split('/\r\n|\r|\n/', $value);
foreach ($lines as $line) {
  $line = trim($line);
  if (empty($line)) continue;

  $parts = explode('|', $line);
  if (count($parts) === 3) {
    [$day, $open, $close] = $parts;
    // Process each day's hours
  }
}
```

## Check if Store is Open (Use Service)

```php
// Inject the service
use Drupal\store_resolver\StoreHoursValidator;

class MyService {
  protected $hoursValidator;

  public function __construct(StoreHoursValidator $hours_validator) {
    $this->hoursValidator = $hours_validator;
  }
}

// Use in code
if ($this->hoursValidator->isStoreOpen($store)) {
  // Store is currently open
}
```

## Validate Order Time (Use OrderValidator)

```php
// Inject the service
use Drupal\store_fulfillment\OrderValidator;

class MyService {
  protected $orderValidator;

  public function __construct(OrderValidator $order_validator) {
    $this->orderValidator = $order_validator;
  }
}

// Validate ASAP order
$result = $this->orderValidator->validateFulfillmentTime($order, NULL);
if ($result['valid']) {
  // ASAP order allowed
} else {
  // Show error: $result['message']
}

// Validate scheduled order
$scheduled_time = new \DateTime('+2 hours');
$result = $this->orderValidator->validateFulfillmentTime(
  $order,
  $scheduled_time->getTimestamp()
);
```

## Time Comparison Logic

```php
// Normal hours (e.g., 09:00-17:00)
if ($close_time > $open_time) {
  // Check: current_time >= open AND current_time < close
  if ($current_time >= $open_time && $current_time < $close_time) {
    // Within hours
  }
}

// Overnight hours (e.g., 22:00-02:00)
if ($close_time < $open_time) {
  // Check: current_time >= open OR current_time < close
  if ($current_time >= $open_time || $current_time < $close_time) {
    // Within hours (spans midnight)
  }
}
```

## Service Injection Examples

### In a controller:

```php
namespace Drupal\mymodule\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\store_resolver\StoreHoursValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MyController extends ControllerBase {

  protected $hoursValidator;

  public function __construct(StoreHoursValidator $hours_validator) {
    $this->hoursValidator = $hours_validator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('store_resolver.hours_validator')
    );
  }

  public function checkStore($store_id) {
    $store = $this->entityTypeManager()
      ->getStorage('commerce_store')
      ->load($store_id);

    $is_open = $this->hoursValidator->isStoreOpen($store);

    return [
      '#markup' => $is_open ? 'Open' : 'Closed',
    ];
  }
}
```

### In a custom service:

```yaml
# mymodule.services.yml
services:
  mymodule.my_service:
    class: Drupal\mymodule\MyService
    arguments: ['@store_resolver.hours_validator', '@datetime.time']
```

```php
// src/MyService.php
namespace Drupal\mymodule;

use Drupal\store_resolver\StoreHoursValidator;
use Drupal\Component\Datetime\TimeInterface;

class MyService {

  protected $hoursValidator;
  protected $time;

  public function __construct(
    StoreHoursValidator $hours_validator,
    TimeInterface $time
  ) {
    $this->hoursValidator = $hours_validator;
    $this->time = $time;
  }

  public function myMethod($store) {
    if ($this->hoursValidator->isStoreOpen($store)) {
      // Do something
    }
  }
}
```

## Test Examples

```php
// In a kernel test
protected function setUp(): void {
  parent::setUp();

  $this->installEntitySchema('commerce_store');
  $this->installConfig(['store_resolver']);

  // Create test store with hours
  $store = Store::create([
    'type' => 'online',
    'name' => 'Test Store',
    'timezone' => 'America/New_York',
    'store_hours' => [
      ['value' => 'monday|09:00|17:00'],
      ['value' => 'friday|22:00|02:00'], // Overnight
    ],
  ]);
  $store->save();
}

public function testStoreHours() {
  $validator = $this->container->get('store_resolver.hours_validator');
  $is_open = $validator->isStoreOpen($this->store);
  $this->assertIsBool($is_open);
}
```

## Field Configuration

```php
// Programmatically create field (already done in store_resolver.install)
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$field_storage = FieldStorageConfig::create([
  'field_name' => 'store_hours',
  'entity_type' => 'commerce_store',
  'type' => 'string_long',
  'cardinality' => -1,
]);
$field_storage->save();

$field = FieldConfig::create([
  'field_storage' => $field_storage,
  'bundle' => 'online',
  'label' => 'Store Hours',
  'description' => 'Format: day|open_time|close_time',
]);
$field->save();
```

## Common Patterns

### Get Today's Hours

```php
$timezone = new \DateTimeZone($store->getTimezone());
$now = new \DateTime('now', $timezone);
$today = strtolower($now->format('l')); // "monday", "tuesday", etc.

$hours_field = $store->get('store_hours');
foreach ($hours_field as $hour_item) {
  $lines = preg_split('/\r\n|\r|\n/', $hour_item->value);
  foreach ($lines as $line) {
    $parts = explode('|', trim($line));
    if (count($parts) === 3 && strtolower($parts[0]) === $today) {
      $opening_time = $parts[1];
      $closing_time = $parts[2];
      break 2;
    }
  }
}
```

### Calculate Minutes Until Opening

```php
$next_slot = $this->orderValidator->getNextAvailableSlot($store);
if ($next_slot) {
  $now = new \DateTime('now', new \DateTimeZone($store->getTimezone()));
  $interval = $now->diff($next_slot);
  $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

  // $minutes = time until store opens
}
```

## Configuration Settings

```php
// Get fulfillment settings
$config = \Drupal::config('store_fulfillment.settings');

$min_advance_notice = $config->get('minimum_advance_notice') ?? 30;
$max_scheduling_window = $config->get('maximum_scheduling_window') ?? 14;
$asap_cutoff = $config->get('asap_cutoff_before_closing') ?? 15;

// Set configuration (in update hook or config form)
$config = \Drupal::configFactory()
  ->getEditable('store_fulfillment.settings');
$config->set('minimum_advance_notice', 45);
$config->save();
```

## Debugging

```php
// Log store hours for debugging
$hours_field = $store->get('store_hours');
$hours_data = [];
foreach ($hours_field as $hour_item) {
  $hours_data[] = $hour_item->value;
}
\Drupal::logger('mymodule')->debug('Store hours: @hours', [
  '@hours' => print_r($hours_data, TRUE),
]);

// Check current time in store timezone
$timezone = new \DateTimeZone($store->getTimezone());
$now = new \DateTime('now', $timezone);
\Drupal::logger('mymodule')->debug('Current time in store timezone: @time', [
  '@time' => $now->format('Y-m-d H:i:s T'),
]);
```

## Service IDs

```yaml
# Available services
store_resolver.current_store          # StoreResolver
store_resolver.hours_validator        # StoreHoursValidator
store_fulfillment.order_validator     # OrderValidator
store_fulfillment.delivery_radius_calculator  # DeliveryRadiusCalculator
```

## Field Machine Names

- Entity Type: `commerce_store`
- Bundle: `online`
- Field Name: `store_hours`
- Field Type: `string_long`

## Quick Links

- Module: `web/modules/custom/store_resolver`
- Tests: `web/modules/custom/store_fulfillment/tests/`
- Config: `config/sync/field.*.commerce_store.store_hours.yml`
- Guide: `web/modules/custom/store_resolver/STORE_HOURS_GUIDE.md`
