# Store Fulfillment Module

Provides pickup and delivery fulfillment methods with scheduling for multi-store restaurant ordering in Drupal Commerce.

## Features

- **Store Pickup Shipping Method**: Flat-rate or free pickup at store
- **Store Delivery Shipping Method**: Delivery with radius validation
- **Delivery Radius Calculation**: Uses geofield/geocoder to validate delivery areas
- **Fulfillment Time Selection**: Checkout pane for ASAP or scheduled orders
- **Store Hours Integration**: Enforces scheduling when store is closed
- **Order Validation**: Validates fulfillment times before order placement
- **Configurable Settings**: Admin form to control scheduling parameters
- **Free Delivery Threshold**: Configurable minimum order for free delivery

## Requirements

- Drupal 10 or 11
- Commerce 3.x
- Commerce Shipping module
- **store_resolver** custom module
- geofield module
- geocoder module

## Installation

1. Ensure store_resolver module is installed first
2. Install Commerce Shipping: `ddev composer require drupal/commerce_shipping`
3. Enable Commerce Shipping: `ddev drush en commerce_shipping`
4. Enable Store Fulfillment: `ddev drush en store_fulfillment`
5. Clear cache: `ddev drush cr`
6. The module will add fields to commerce_store:
   - `delivery_radius`: Maximum delivery radius in miles
   - `store_location`: Geofield for store coordinates

## Configuration

### 1. Configure Fulfillment Settings

Go to: **Administration > Commerce > Configuration > Store Fulfillment** (`/admin/commerce/config/store-fulfillment`)

Configure the following settings:

- **Minimum advance notice**: Minimum time in minutes between order placement and scheduled fulfillment (default: 30 minutes)
- **Maximum scheduling window**: Maximum number of days in advance that orders can be scheduled (default: 14 days)
- **ASAP cutoff before closing**: Stop accepting ASAP orders this many minutes before store closes (default: 15 minutes)
- **Time slot interval**: Interval between available time slots (15, 30, or 60 minutes)

### 2. Configure Store Hours

For each store entity:
1. Navigate to **Commerce > Configuration > Stores**
2. Edit your store
3. Set the **Store Hours** field using the format: `day|open_time|close_time`
   - Example: `monday|09:00|17:00`
   - For overnight hours: `friday|22:00|02:00`

**Example Store Hours Configuration:**
```
monday|09:00|17:00
tuesday|09:00|17:00
wednesday|09:00|17:00
thursday|09:00|17:00
friday|09:00|21:00
saturday|10:00|18:00
sunday|11:00|16:00
```

### 3. Configure Store Location

For each store:
1. Edit the store entity
2. Set the **Store Location** geofield with coordinates
3. Set the **Delivery Radius** (in miles)

### 4. Create Shipping Methods

Go to Commerce > Configuration > Shipping methods

#### Store Pickup

1. Add shipping method
2. Plugin: "Store Pickup"
3. Configure:
   - Rate label: "Pickup at Store"
   - Rate amount: $0.00 (or your fee)

#### Store Delivery

1. Add shipping method
2. Plugin: "Store Delivery"
3. Configure:
   - Rate label: "Delivery"
   - Delivery fee: $5.00 (or your fee)
   - Free delivery minimum: $50.00 (optional)

### 5. Configure Checkout Flow

Go to Commerce > Configuration > Checkout flows > Default

1. Add the **Fulfillment Time** pane
2. Recommended step: "Order information"
3. Position it after shipping information

### 6. Enable Separate Delivery Address

Commerce Shipping automatically provides a separate shipping address field during checkout, distinct from billing address.

## How It Works

### Order Fulfillment Time Validation

The module enforces store operating hours through a three-layer validation system:

#### 1. Checkout Pane Validation
The **Fulfillment Time** checkout pane (`FulfillmentTime.php`):
- Checks if selected store is currently open using `OrderValidator::isImmediateOrderAllowed()`
- If store is closed:
  - Disables "ASAP" option with visual feedback
  - Shows next available opening time
  - Forces customer to schedule for a future time
- Generates time slots based on:
  - Configured time slot interval (15, 30, or 60 minutes)
  - Store operating hours
  - Minimum advance notice
  - Maximum scheduling window
- Validates selected time against store hours before form submission

#### 2. Order Validator Service
The **OrderValidator** service (`store_fulfillment.order_validator`):
- `isImmediateOrderAllowed(StoreInterface $store)`: Checks if ASAP orders are currently allowed
  - Verifies store is open
  - Applies ASAP cutoff time before closing
  - Returns TRUE/FALSE
- `validateFulfillmentTime(OrderInterface $order, $requested_time)`: Validates fulfillment time
  - For immediate orders: ensures store is open
  - For scheduled orders: validates against:
    - Minimum advance notice (default: 30 minutes)
    - Maximum scheduling window (default: 14 days)
    - Store operating hours (including overnight hours)
  - Returns validation result with error messages
- `getNextAvailableSlot(StoreInterface $store)`: Finds next available fulfillment time
  - If store open: current time + minimum advance notice
  - If store closed: next opening time + minimum advance notice

#### 3. Order Placement Event Subscriber
The **OrderPlacementValidator** event subscriber:
- Subscribes to `commerce_order.place.pre_transition` event
- Performs final validation before order is placed
- Throws `\InvalidArgumentException` if validation fails
- Logs validation failures to `commerce_order` log channel
- Prevents invalid orders from being placed

### Delivery Radius Validation

The delivery shipping method:
1. Gets the customer's delivery address from checkout
2. Geocodes it to coordinates
3. Calculates distance from selected store
4. Only offers delivery if within configured radius

### Store Hours Handling

#### Standard Hours
Format: `day|open_time|close_time`
- Example: `monday|09:00|17:00` (9 AM to 5 PM)

#### Overnight Hours
For stores open past midnight:
- Example: `friday|22:00|02:00` (10 PM to 2 AM next day)
- The validator correctly handles time spans across midnight

#### Timezone Awareness
All time calculations are performed in the store's configured timezone, ensuring accurate validation across different regions.

### Order Data Storage

Fulfillment preferences are stored in order data:
```php
$order->getData('fulfillment_type'); // 'asap' or 'scheduled'
$order->getData('scheduled_time'); // Timestamp if scheduled (Y-m-d H:i:s format)
```

## Geocoding Setup

The delivery radius calculation requires geocoding. To implement:

1. Install Geocoder providers: `ddev composer require geocoder-php/google-maps-provider`
2. Configure Geocoder module with your preferred provider (Google Maps, Nominatim, etc.)
3. Update `DeliveryRadiusCalculator::geocodeAddress()` to use your geocoder service

Example implementation in `DeliveryRadiusCalculator.php`:
```php
protected function geocodeAddress(AddressInterface $address) {
  $geocoder = \Drupal::service('geocoder');
  $address_string = sprintf('%s, %s, %s %s',
    $address->getAddressLine1(),
    $address->getLocality(),
    $address->getAdministrativeArea(),
    $address->getPostalCode()
  );

  $result = $geocoder->geocode($address_string, ['googlemaps']);
  if ($result->isEmpty()) {
    return NULL;
  }

  $coords = $result->first()->getCoordinates();
  return [
    'lat' => $coords->getLatitude(),
    'lon' => $coords->getLongitude()
  ];
}
```

## Usage in Custom Code

```php
// Check if address is within delivery range
$calculator = \Drupal::service('store_fulfillment.delivery_radius_calculator');
$store = \Drupal::service('store_resolver.current_store')->getCurrentStore();
$in_range = $calculator->isWithinRadius($store, $delivery_address);

## Usage in Custom Code

### Validate Fulfillment Time Programmatically

```php
// Get services
$order_validator = \Drupal::service('store_fulfillment.order_validator');
$store = \Drupal::service('store_resolver.current_store')->getCurrentStore();

// Check if immediate orders are allowed
if ($order_validator->isImmediateOrderAllowed($store)) {
  // Store is open and accepting ASAP orders
}

// Validate a specific fulfillment time
$order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);
$scheduled_time = strtotime('+2 hours');
$result = $order_validator->validateFulfillmentTime($order, $scheduled_time);

if ($result['valid']) {
  // Time is valid
} else {
  // Show error: $result['message']
}

// Get next available slot
$next_slot = $order_validator->getNextAvailableSlot($store);
if ($next_slot) {
  $formatted_time = $next_slot->format('l, F j, Y - g:i A');
}
```

### Check if Address is Within Delivery Range

```php
// Check if address is within delivery range
$calculator = \Drupal::service('store_fulfillment.delivery_radius_calculator');
$store = \Drupal::service('store_resolver.current_store')->getCurrentStore();
$in_range = $calculator->isWithinRadius($store, $delivery_address);

// Get order fulfillment data
$type = $order->getData('fulfillment_type');
$scheduled_time = $order->getData('scheduled_time');
```

### Programmatically Set Order Fulfillment Data

```php
// Set ASAP fulfillment
$order->setData('fulfillment_type', 'asap');
$order->save();

// Set scheduled fulfillment
$order->setData('fulfillment_type', 'scheduled');
$order->setData('scheduled_time', '2026-02-10 14:30:00');
$order->save();
```

## Troubleshooting

### Issue: ASAP Option Always Disabled

**Symptoms:** The "As soon as possible" option is always greyed out even during business hours.

**Possible Causes:**
1. Store hours not configured correctly
2. ASAP cutoff time is too aggressive
3. Current time is within cutoff period before closing

**Solutions:**
```bash
# Check store hours configuration
ddev drush config:get commerce_store.commerce_store.YOUR_STORE_ID store_hours

# Adjust ASAP cutoff at /admin/commerce/config/store-fulfillment
# Set to 0 to allow ASAP until closing time

# Clear cache
ddev drush cr
```

### Issue: No Time Slots Available

**Symptoms:** The scheduled time dropdown shows "No available time slots"

**Possible Causes:**
1. Store has no hours configured
2. Maximum scheduling window is too short
3. Time slot interval doesn't align with store hours

**Solutions:**
```bash
# Verify store hours are set
ddev drush config:get commerce_store.commerce_store.YOUR_STORE_ID store_hours

# Increase maximum scheduling window
# Go to /admin/commerce/config/store-fulfillment
# Set "Maximum scheduling window" to 30 days

# Clear cache
ddev drush cr
```

### Issue: Order Placement Fails with Validation Error

**Symptoms:** Error message: "Order placement failed: [validation message]"

**Possible Causes:**
1. Time slot became invalid between form display and submission
2. Store hours changed after time was selected
3. User took too long to complete checkout

**Solutions:**
```bash
# Check recent validation failures in logs
ddev drush watchdog:show --type=commerce_order --severity=3

# Verify validation is working correctly
ddev phpunit web/modules/custom/store_fulfillment/tests/src/Kernel/OrderValidatorTest.php

# Adjust minimum advance notice if too restrictive
# Go to /admin/commerce/config/store-fulfillment
```

### Issue: Overnight Hours Not Working

**Symptoms:** Validation fails for times like 11 PM - 2 AM

**Possible Causes:**
1. Store hours format incorrect for overnight spans
2. Timezone issues

**Solutions:**
```yaml
# Correct overnight hours format:
friday|22:00|02:00  # Opens Friday 10 PM, closes Saturday 2 AM

# Incorrect format:
friday|22:00|26:00  # Don't use hours > 24

# Verify timezone
ddev drush config:get commerce_store.commerce_store.YOUR_STORE_ID timezone
```

### Issue: Timezone Problems

**Symptoms:** Validation incorrectly reports store as closed/open

**Solutions:**
```bash
# Set correct timezone for store
ddev drush config:set commerce_store.commerce_store.YOUR_STORE_ID timezone 'America/New_York'

# Verify system timezone
ddev exec date

# Check PHP timezone
ddev exec php -i | grep "Default timezone"
```

### Debug Mode

Enable detailed logging for troubleshooting:

```php
// In settings.php or settings.local.php
$config['system.logging']['error_level'] = 'verbose';

// Watch logs in real-time
ddev drush watchdog:tail
```

## Testing

### Run Automated Tests

```bash
# Run kernel tests for OrderValidator
ddev phpunit web/modules/custom/store_fulfillment/tests/src/Kernel/OrderValidatorTest.php

# Run all store_fulfillment tests
ddev phpunit --group store_fulfillment

# Run PHPStan static analysis
ddev phpstan analyse web/modules/custom/store_fulfillment

# Check coding standards
ddev exec phpcs --standard=Drupal web/modules/custom/store_fulfillment/src
```

### Manual Testing Checklist

- [ ] Store open → ASAP option enabled
- [ ] Store closed → ASAP option disabled with message
- [ ] Schedule order during open hours → Success
- [ ] Schedule order during closed hours → Validation error
- [ ] Schedule order < 30 min advance → Validation error
- [ ] Schedule order > 14 days → Validation error
- [ ] Switch stores during checkout → Form updates correctly
- [ ] Overnight hours (22:00-02:00) → Validation correct
- [ ] ASAP cutoff time enforced → ASAP disabled 15 min before close
- [ ] Order placement with invalid time → Blocked with error message

## API Services

- `store_fulfillment.order_validator`: Validates fulfillment times against store hours
- `store_fulfillment.delivery_radius_calculator`: Calculate delivery distances

## Architecture

This module extends Commerce Shipping's plugin system:
- **OrderValidator service**: Core validation logic with timezone support
- **ShippingMethod plugins**: Define pickup and delivery methods
- **CheckoutPane plugin**: Time selection with dynamic validation
- **EventSubscriber**: Pre-order-placement validation gate
- **ConfigFormBase**: Admin settings management
- Uses store_resolver for store context and hours checking

## Related Modules

- **store_resolver**: Required for store selection, context, and hours validation
- **commerce_shipping**: Required base for fulfillment methods
