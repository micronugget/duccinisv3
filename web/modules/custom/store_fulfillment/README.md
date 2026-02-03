# Store Fulfillment Module

Provides pickup and delivery fulfillment methods with scheduling for multi-store restaurant ordering in Drupal Commerce.

## Features

- **Store Pickup Shipping Method**: Flat-rate or free pickup at store
- **Store Delivery Shipping Method**: Delivery with radius validation
- **Delivery Radius Validation**: Real-time validation of delivery addresses at checkout and order placement
- **Geofenced Delivery Areas**: Configurable radius per store using geodetic distance calculations
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

### 3. Configure Store Location and Delivery Radius

Each store must have location coordinates and a delivery radius configured for delivery validation to work:

#### Setting Store Coordinates

1. Navigate to Commerce > Configuration > Stores
2. Edit your store entity
3. Locate the **Store Location** field (geofield)
4. Enter coordinates in one of these formats:
   - **Latitude, Longitude**: `40.7128, -74.0060` (New York City)
   - **WKT Point**: `POINT(-74.0060 40.7128)`
   - Use the map widget to visually select a location (if geocoding provider configured)

#### Configuring Delivery Radius

1. In the same store edit form, find the **Delivery Radius** field
2. Enter the maximum delivery distance in **miles**
3. Examples:
   - Urban dense area: `3.0` miles
   - Suburban area: `10.0` miles
   - Rural area: `25.0` miles
4. The system uses geodetic distance (great-circle distance) to calculate actual travel distance from store to delivery address

**Note**: The delivery radius field accepts decimal values, so you can use precise distances like `5.5` miles. Pickup orders are always available regardless of customer location.

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

The module provides multi-layered delivery address validation to ensure orders are only accepted from customers within the configured delivery area:

#### Validation Flow

1. **Customer selects "Delivery to address"** in the Fulfillment Time checkout pane
2. **Real-time validation at checkout**: When delivery address is entered, the system:
   - Geocodes the delivery address to latitude/longitude coordinates
   - Retrieves the store's location from the `store_location` geofield
   - Calculates geodetic distance between store and delivery address
   - Compares distance to the store's configured `delivery_radius`
   - Displays validation message with actual distance information
3. **Validation feedback**: Customer sees one of these messages:
   - ✅ **Success**: "Your delivery address is within our service area (2.5 miles from store, maximum 5.0 miles)."
   - ❌ **Out of range**: "Sorry, your delivery address is outside our service area. You are 8.2 miles from the store (maximum delivery radius: 5.0 miles). Please select pickup instead or choose a different store."
   - ⚠️ **Geocoding error**: "Unable to verify your delivery address. Please ensure you have entered a valid address."
4. **Final validation at order placement**: An event subscriber (`OrderPlacementDeliveryRadiusValidator`) performs final validation before order is placed, preventing any bypass attempts

#### What Gets Validated

- **Delivery orders only**: Validation only applies when fulfillment method is "Delivery"
- **Pickup always available**: Pickup orders skip radius validation entirely
- **Address completeness**: System requires full delivery address (street, city, state, postal code)
- **Store configuration**: Validation skips if store has no delivery radius configured

#### Distance Calculation

The system uses the Haversine formula to calculate great-circle distance:
- Earth's radius: 3,959 miles (6,371 km)
- Returns straight-line distance "as the crow flies"
- More accurate than bounding box approximations
- Accounts for Earth's curvature for long distances

### Checkout Behavior

#### Fulfillment Method Selection

The **Fulfillment Time** checkout pane presents two primary options:

1. **Pickup at store**
   - Available for all customers regardless of location
   - No delivery address required (uses billing address only)
   - No radius validation performed
   - Customer collects order from selected store

2. **Delivery to address**
   - Requires entering delivery address in shipping information
   - Subject to delivery radius validation
   - Shows real-time validation feedback as address is entered
   - Customer cannot proceed if address is outside delivery area

#### User Interface Behavior

When customer selects delivery:
- A validation message appears below the fulfillment method selection
- Message updates dynamically as shipping address changes
- Red error box shows if address is outside radius with specific distance
- Green success message confirms address is within service area
- Form submission is blocked if validation fails

When customer selects pickup:
- No address validation is performed
- Customer can proceed regardless of their location
- Billing address is used for payment processing only

#### Error Messages

Users will see these specific error messages:

**Out of Delivery Range**:
```
Sorry, your delivery address is outside our service area. 
You are 8.2 miles from the store (maximum delivery radius: 5.0 miles). 
Please select pickup instead or choose a different store.
```

**Geocoding Failure**:
```
Unable to verify your delivery address. 
Please ensure you have entered a valid address.
```

**Missing Store Configuration**:
```
Unable to determine store location. Please contact support.
```

**Incomplete Address**:
```
Please provide a delivery address.
```

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

The delivery radius validation requires geocoding delivery addresses to coordinates. The module is designed to integrate with Drupal's Geocoder module.

### Installation Steps

1. **Install Geocoder module and provider**:
   ```bash
   ddev composer require drupal/geocoder
   ddev composer require geocoder-php/google-maps-provider
   # Or for free alternative:
   ddev composer require geocoder-php/nominatim-provider
   ```

2. **Enable Geocoder module**:
   ```bash
   ddev drush en geocoder
   ```

3. **Configure Geocoder provider** at `/admin/config/system/geocoder`:
   - Add a geocoder plugin (Google Maps, Nominatim, etc.)
   - For Google Maps, add your API key
   - Test the configuration with a sample address

4. **Implement geocoding in DeliveryRadiusValidator**:

   Update the `geocodeAddress()` method in `src/DeliveryRadiusValidator.php`:

   ```php
   protected function geocodeAddress(AddressInterface $address): ?array {
     $geocoder = \Drupal::service('geocoder');
     
     // Format address string.
     $address_string = sprintf('%s, %s, %s %s',
       $address->getAddressLine1(),
       $address->getLocality(),
       $address->getAdministrativeArea(),
       $address->getPostalCode()
     );

     try {
       $result = $geocoder->geocode($address_string, ['googlemaps']);
       if ($result->isEmpty()) {
         return NULL;
       }

       $coords = $result->first()->getCoordinates();
       return [
         'lat' => $coords->getLatitude(),
         'lon' => $coords->getLongitude(),
       ];
     }
     catch (\Exception $e) {
       \Drupal::logger('store_fulfillment')->error('Geocoding failed: @message', [
         '@message' => $e->getMessage(),
       ]);
       return NULL;
     }
   }
   ```

### Geocoder Provider Options

| Provider | Cost | Accuracy | Setup Complexity |
|----------|------|----------|------------------|
| Google Maps | Pay per request after free tier | Excellent | Medium (API key required) |
| Nominatim (OpenStreetMap) | Free | Good | Low (no API key) |
| Mapbox | Pay per request after free tier | Excellent | Medium (API key required) |
| HERE | Pay per request after free tier | Excellent | Medium (API key required) |

**Recommendation**: Start with Nominatim for development/testing (free, no API key). Use Google Maps or Mapbox for production (more accurate, better address parsing).

## Usage in Custom Code

### Validating Delivery Addresses

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

### Validating Delivery Addresses

Use the `store_fulfillment.delivery_radius_validator` service to validate addresses in custom code:

```php
/** @var \Drupal\store_fulfillment\DeliveryRadiusValidator $validator */
$validator = \Drupal::service('store_fulfillment.delivery_radius_validator');

/** @var \Drupal\commerce_store\Entity\StoreInterface $store */
$store = \Drupal::service('store_resolver.current_store')->getCurrentStore();

/** @var \Drupal\address\AddressInterface $delivery_address */
// Get address from order or form.

// Validate the address.
$result = $validator->validateDeliveryAddress($store, $delivery_address);

// Check validation result.
if ($result['valid']) {
  // Address is within delivery radius.
  \Drupal::messenger()->addMessage(t('Delivery available! Distance: @distance miles', [
    '@distance' => number_format($result['distance'], 2),
  ]));
}
else {
  // Address is outside delivery radius.
  \Drupal::messenger()->addError($result['message']);
  \Drupal::logger('my_module')->warning('Delivery rejected: @distance miles', [
    '@distance' => $result['distance'] ?? 'unknown',
  ]);
}
```

### Validation Result Structure

The `validateDeliveryAddress()` method returns an array with these keys:

```php
[
  'valid' => TRUE,              // Boolean: Address is within radius
  'message' => 'Success message', // String: User-friendly message
  'distance' => 3.45,             // Float: Distance in miles (NULL if not calculable)
]
```

### Check if Address is Within Delivery Range

```php
// Check if address is within delivery range
$calculator = \Drupal::service('store_fulfillment.delivery_radius_calculator');
$store = \Drupal::service('store_resolver.current_store')->getCurrentStore();
$in_range = $calculator->isWithinRadius($store, $delivery_address);
```

### Getting Order Fulfillment Data

Retrieve fulfillment information stored during checkout:

```php
/** @var \Drupal\commerce_order\Entity\OrderInterface $order */

// Get fulfillment method (pickup or delivery).
$method = $order->getData('fulfillment_method');
if ($method === 'delivery') {
  // This is a delivery order.
}
elseif ($method === 'pickup') {
  // This is a pickup order.
}

// Get fulfillment timing.
$type = $order->getData('fulfillment_type');
if ($type === 'scheduled') {
  $scheduled_time = $order->getData('scheduled_time');
  $datetime = new \DateTime($scheduled_time);
  // Use scheduled time for order preparation.
}
elseif ($type === 'asap') {
  // Prepare order immediately.
}
```

### Calculating Distance Directly

For custom distance calculations without validation:

```php
/** @var \Drupal\store_fulfillment\DeliveryRadiusCalculator $calculator */
$calculator = \Drupal::service('store_fulfillment.delivery_radius_calculator');

// Calculate distance between two coordinate pairs.
$distance = $calculator->calculateDistance(
  $lat1 = 40.7128,  // Store latitude
  $lon1 = -74.0060, // Store longitude
  $lat2 = 40.7589,  // Delivery latitude
  $lon2 = -73.9851  // Delivery longitude
);

// Returns distance in miles.
echo "Distance: " . number_format($distance, 2) . " miles";
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

The module provides these services for programmatic use:

### `store_fulfillment.order_validator`

Validates fulfillment times against store hours.

**Methods**:
- `isImmediateOrderAllowed(StoreInterface $store): bool` - Checks if ASAP orders are allowed
- `validateFulfillmentTime(OrderInterface $order, $requested_time): array` - Validates a fulfillment time
- `getNextAvailableSlot(StoreInterface $store): ?\DateTime` - Finds next available fulfillment time

### `store_fulfillment.delivery_radius_calculator`

Calculates geodetic distances between coordinates.

**Methods**:
- `calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float`
  - Returns distance in miles using Haversine formula
  - Accounts for Earth's curvature
  - Example: `$calculator->calculateDistance(40.7128, -74.0060, 40.7589, -73.9851)` → `3.21`

- `isWithinRadius(StoreInterface $store, AddressInterface $address): bool`
  - Checks if address is within store's delivery radius
  - Returns boolean: TRUE if within range, FALSE otherwise
  - Handles geocoding internally

### `store_fulfillment.delivery_radius_validator`

Validates delivery addresses with user-friendly feedback.

**Methods**:
- `validateDeliveryAddress(StoreInterface $store, AddressInterface $address): array`
  - Returns validation result with details
  - Array structure: `['valid' => bool, 'message' => string, 'distance' => float|null]`
  - Handles all edge cases (missing coordinates, geocoding failures, etc.)
  - Provides user-facing error messages

**Use Cases**:
- Checkout validation (already implemented in `FulfillmentTime` pane)
- Order placement validation (implemented in `OrderPlacementDeliveryRadiusValidator`)
- Custom forms requiring delivery validation
- Admin tools for testing store coverage areas

## Architecture

This module extends Commerce Shipping's plugin system:
- **OrderValidator service**: Core validation logic with timezone support
- **ShippingMethod plugins**: Define pickup and delivery methods
- **CheckoutPane plugin**: Time selection with dynamic validation
- **EventSubscriber**: Pre-order-placement validation gate
- **ConfigFormBase**: Admin settings management
- Uses store_resolver for store context and hours checking

## Troubleshooting

### Validation Not Working

**Problem**: Delivery radius validation is not being enforced.

**Solutions**:
1. **Check store configuration**:
   ```bash
   ddev drush config:get commerce_store.store.YOUR_STORE_ID delivery_radius
   ddev drush config:get commerce_store.store.YOUR_STORE_ID store_location
   ```
   Ensure both fields have values.

2. **Verify geocoding service**:
   - Test geocoding at `/admin/config/system/geocoder`
   - Check watchdog logs: `ddev drush watchdog:show --type=store_fulfillment`
   - Look for geocoding errors

3. **Check checkout pane is enabled**:
   - Navigate to Commerce > Configuration > Checkout flows
   - Ensure "Fulfillment Time" pane is enabled and positioned after "Shipping information"

### Geocoding Errors

**Problem**: "Unable to verify your delivery address" message appears for valid addresses.

**Common Causes & Solutions**:

1. **No geocoding provider configured**:
   ```bash
   ddev drush pm:list | grep geocoder
   ```
   If not enabled, install and configure (see Geocoding Setup section).

2. **API rate limits exceeded**:
   - Google Maps: Check quota at Google Cloud Console
   - Nominatim: Limit is 1 request/second, use caching
   - Solution: Implement caching of geocoded addresses

3. **Invalid API key** (Google Maps/Mapbox):
   - Verify API key is correct
   - Check API is enabled in provider console
   - Ensure billing is set up (if required)

4. **Address format issues**:
   - Some geocoding providers are picky about address formats
   - Try adding more specific information (apartment numbers, etc.)
   - Test with a known-good address to isolate issue

### Distance Calculation Seems Wrong

**Problem**: System says address is out of range but it should be within radius.

**Debugging Steps**:

1. **Check actual distance**:
   ```php
   $calculator = \Drupal::service('store_fulfillment.delivery_radius_calculator');
   $distance = $calculator->calculateDistance($store_lat, $store_lon, $delivery_lat, $delivery_lon);
   \Drupal::logger('debug')->info('Distance: @dist miles', ['@dist' => $distance]);
   ```

2. **Verify coordinates are correct**:
   - Store coordinates should match physical location
   - Plot coordinates on Google Maps: `https://www.google.com/maps?q=LAT,LON`
   - Common issue: latitude/longitude reversed

3. **Remember geodetic vs. road distance**:
   - System calculates straight-line distance "as the crow flies"
   - Road distance is typically 20-30% longer
   - Adjust delivery radius to account for actual routes

4. **Check radius units**:
   - Ensure delivery_radius field is in miles
   - System always calculates in miles internally

### Order Placement Fails with "Outside Service Area"

**Problem**: Order fails at final placement even though checkout validation passed.

**Causes**:
- Address was edited after checkout validation
- Customer bypassed client-side validation
- Race condition with multiple tabs/sessions

**Solution**: This is working as designed. The `OrderPlacementDeliveryRadiusValidator` event subscriber provides a final validation to prevent fraudulent orders. If this happens frequently:
1. Improve client-side UX to prevent address changes
2. Add warning messages about address finality
3. Log these events to identify patterns

### Debugging Validation Flow

Enable verbose logging:

```php
// In DeliveryRadiusValidator.php, add debug logging:
$this->logger->debug('Validating delivery address: @address for store @store', [
  '@address' => $address->getAddressLine1(),
  '@store' => $store->id(),
]);
$this->logger->debug('Store coords: @coords, Delivery coords: @delivery', [
  '@coords' => json_encode($store_coords),
  '@delivery' => json_encode($delivery_coords),
]);
$this->logger->debug('Distance: @distance, Max: @max', [
  '@distance' => $distance,
  '@max' => $max_radius,
]);
```

View logs:
```bash
ddev drush watchdog:show --type=store_fulfillment --severity=debug
```

## Testing

The module includes comprehensive automated tests covering all validation scenarios.

### Running All Tests

Run the complete test suite:
```bash
# Run all store_fulfillment tests
ddev drush test-run store_fulfillment

# Or using PHPUnit directly
ddev phpunit --group store_fulfillment
```

### Running Specific Test Classes

**Unit Tests** - DeliveryRadiusValidator service:
```bash
ddev phpunit web/modules/custom/store_fulfillment/tests/src/Kernel/DeliveryRadiusValidatorTest.php
```

**Kernel Tests** - Order placement validation:
```bash
ddev phpunit web/modules/custom/store_fulfillment/tests/src/Kernel/OrderPlacementDeliveryRadiusValidatorTest.php
```

**Functional Tests** - Full checkout flow:
```bash
ddev phpunit web/modules/custom/store_fulfillment/tests/src/Functional/DeliveryRadiusCheckoutTest.php
```

### Test Coverage

The test suite includes **33 test cases** covering:

#### DeliveryRadiusValidatorTest (13 tests)
- ✓ Address within delivery radius
- ✓ Address outside delivery radius  
- ✓ Address exactly at radius boundary
- ✓ Store without delivery radius configured
- ✓ Store without location coordinates
- ✓ Invalid/ungeodable delivery address
- ✓ Edge cases and error conditions

#### OrderPlacementDeliveryRadiusValidatorTest (12 tests)
- ✓ Delivery order within radius (allowed)
- ✓ Delivery order outside radius (blocked)
- ✓ Pickup order (validation skipped)
- ✓ Order without fulfillment method set
- ✓ Order without store assigned
- ✓ Order without shipping information
- ✓ Exception handling and logging

#### DeliveryRadiusCheckoutTest (8 tests)
- ✓ Complete checkout flow with delivery validation
- ✓ Real-time validation feedback in checkout pane
- ✓ Form submission blocked when validation fails
- ✓ Switching between pickup and delivery methods
- ✓ Address changes trigger re-validation
- ✓ Success messages displayed when valid

### Continuous Integration

Tests are automatically run on:
- Every pull request
- Every commit to main branch
- Before deployment to production

All tests must pass before code can be merged.

### Writing Custom Tests

To test custom integrations with the delivery radius system:

```php
namespace Drupal\Tests\my_module\Kernel;

use Drupal\Tests\store_fulfillment\Kernel\DeliveryRadiusTestBase;

class MyCustomTest extends DeliveryRadiusTestBase {
  
  public function testMyCustomValidation() {
    $validator = $this->container->get('store_fulfillment.delivery_radius_validator');
    $store = $this->createStore(['delivery_radius' => 5.0]);
    $address = $this->createAddress(['locality' => 'New York']);
    
    $result = $validator->validateDeliveryAddress($store, $address);
    $this->assertTrue($result['valid']);
  }
}
```

## Related Modules

- **store_resolver**: Required for store selection, context, and hours validation
- **commerce_shipping**: Required base for fulfillment methods
