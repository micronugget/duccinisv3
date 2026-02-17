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

### Epic #1: Order Timing Validation

The module validates order fulfillment times based on store hours and configurable business rules:

#### Order Validator Service

- Checks if store is open for immediate (ASAP) orders
- Validates scheduled fulfillment times against store hours
- Enforces minimum advance notice for all orders
- Respects maximum scheduling window
- Provides next available time slot when store is closed

#### Configuration-Driven

All timing rules are configurable via the admin UI:
- Minimum advance notice (e.g., 30 minutes)
- Maximum scheduling window (e.g., 14 days)
- Time slot intervals (15, 30, or 60 minutes)
- ASAP cutoff before closing

### Epic #2: Delivery Radius Validation

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

#### Order Timing Selection

After choosing fulfillment method, customers select timing:

1. **As soon as possible (ASAP)**
   - Available only when store is currently open
   - Subject to minimum advance notice
   - Disabled with helpful message when store is closed

2. **Schedule for later**
   - Dropdown shows available time slots
   - Filtered by store hours
   - Respects maximum scheduling window
   - Time slots generated at configured intervals

## Services

### DeliveryRadiusValidator

Validates delivery addresses against store delivery radius.

```php
$validator = \Drupal::service('store_fulfillment.delivery_radius_validator');
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
  // Result contains actual distance for logging/debugging.
  \Drupal::logger('my_module')->warning('Delivery rejected: @distance miles', [
    '@distance' => $result['distance'] ?? 'unknown',
  ]);
}
```

### OrderValidator

Validates order timing against store hours and business rules.

```php
$validator = \Drupal::service('store_fulfillment.order_validator');

// Check if ASAP orders are allowed.
if ($validator->isImmediateOrderAllowed($store)) {
  // Store is open, accept ASAP order.
}

// Validate a scheduled time.
$validation = $validator->validateFulfillmentTime($store, '2024-03-15 14:30:00');
if (!$validation['valid']) {
  // Time is not available.
  \Drupal::messenger()->addError($validation['message']);
}

// Get next available slot.
$next = $validator->getNextAvailableSlot($store);
if ($next) {
  $message = t('Next available: @time', ['@time' => $next->format('l, F j - g:i A')]);
}
```

## Event Subscribers

### OrderPlacementDeliveryRadiusValidator

Validates delivery radius at order placement (Epic #2).

- Event: `commerce_order.place.pre_transition`
- Priority: -100 (runs early)
- Validates delivery orders only
- Throws `\InvalidArgumentException` if address is out of range
- Logs validation failures

### OrderPlacementValidator

Validates order timing at order placement (Epic #1).

- Event: `commerce_order.place.pre_transition`
- Priority: -100 (runs early)
- Validates fulfillment times
- Prevents orders outside store hours
- Enforces minimum advance notice

## Geocoding Setup

The delivery radius validation requires a geocoding provider to convert addresses to coordinates.

### Option 1: Google Maps (Recommended for production)

1. Install geocoder and provider:
   ```bash
   ddev composer require drupal/geocoder
   ddev composer require geocoder-php/google-maps-provider
   ```

2. Enable geocoder:
   ```bash
   ddev drush en geocoder
   ```

3. Configure Google Maps API key in settings.php or via Geocoder module configuration

### Option 2: Nominatim (Free, OpenStreetMap)

1. Install geocoder and provider:
   ```bash
   ddev composer require drupal/geocoder
   ddev composer require geocoder-php/nominatim-provider
   ```

2. Configure Nominatim (free, no API key required)

**Note**: Until a geocoding provider is configured, the system will safely deny all delivery requests (fail-safe behavior).

## Troubleshooting

### Common Issues

#### Delivery Always Denied

**Symptom**: All delivery addresses are rejected, even those clearly within radius.

**Cause**: Geocoding provider not configured.

**Solution**:
1. Install and configure a geocoding provider (see "Geocoding Setup")
2. Check geocoder module configuration
3. Verify API keys if using Google Maps
4. Test geocoding manually with Address module

#### Store Hours Not Enforced

**Symptom**: Orders accepted outside store hours.

**Cause**: Store hours field not configured correctly.

**Solution**:
1. Check store entity has `store_hours` field
2. Verify format: `day|HH:MM|HH:MM` (24-hour time)
3. Ensure day names are lowercase
4. Check for typos in field values

#### Time Slots Not Showing

**Symptom**: Scheduled time dropdown is empty.

**Cause**: Store hours conflict with current time + minimum advance notice.

**Solution**:
1. Verify store hours are set
2. Check minimum advance notice setting isn't too long
3. Ensure maximum scheduling window is reasonable (7-14 days)
4. Review time slot interval setting

#### Distance Calculation Inaccurate

**Symptom**: Customers report incorrect distance calculations.

**Cause**: Store coordinates not set or incorrect.

**Solution**:
1. Verify store has `store_location` geofield populated
2. Check coordinates are in correct format (lat, lon)
3. Test with known addresses and distances
4. Remember: Distance is "as the crow flies", not driving distance

### Debugging

Check module logs:
```bash
ddev drush watchdog:show --type=store_fulfillment --severity=debug
```

Verify store configuration:
```bash
ddev drush config:get commerce_store.store.YOUR_STORE_ID delivery_radius
ddev drush config:get commerce_store.store.YOUR_STORE_ID store_location
```

Check if geocoder is available:
```bash
ddev drush pm:list | grep geocoder
```

## Testing

### Manual Testing Checklist

**Epic #1: Order Timing**
- [ ] ASAP orders work when store is open
- [ ] ASAP orders blocked when store is closed
- [ ] Scheduled orders respect store hours
- [ ] Minimum advance notice enforced
- [ ] Time slots filtered by store hours
- [ ] Overnight hours handled correctly

**Epic #2: Delivery Radius**
- [ ] Pickup always available (no radius check)
- [ ] Delivery within radius accepted
- [ ] Delivery outside radius rejected with clear message
- [ ] Distance shown in validation messages
- [ ] Geocoding failure handled gracefully

### Automated Testing

Run PHPUnit tests:
```bash
ddev phpunit web/modules/custom/store_fulfillment
```

Test suites:
- `DeliveryRadiusValidatorTest` - 12 test cases (Epic #2)
- `OrderValidatorTest` - Multiple test cases (Epic #1)
- `OrderPlacementDeliveryRadiusValidatorTest` - 10 test cases (Epic #2)
- `DeliveryRadiusCheckoutTest` - 11 functional tests (Epic #2)

## Architecture

### Module Structure

```
store_fulfillment/
├── config/
│   ├── install/
│   │   └── store_fulfillment.settings.yml
│   └── schema/
│       └── store_fulfillment.schema.yml
├── src/
│   ├── DeliveryRadiusCalculator.php       # Epic #2: Distance calculations
│   ├── DeliveryRadiusValidator.php        # Epic #2: Address validation
│   ├── OrderValidator.php                 # Epic #1: Timing validation
│   ├── EventSubscriber/
│   │   ├── OrderPlacementDeliveryRadiusValidator.php  # Epic #2
│   │   └── OrderPlacementValidator.php                # Epic #1
│   ├── Form/
│   │   └── StoreFulfillmentSettingsForm.php           # Epic #1
│   └── Plugin/
│       └── Commerce/
│           ├── CheckoutPane/
│           │   └── FulfillmentTime.php    # Integrated: Both epics
│           └── ShippingMethod/
│               ├── StorePickup.php
│               └── StoreDelivery.php
└── tests/
    ├── src/
    │   ├── Kernel/
    │   │   ├── DeliveryRadiusValidatorTest.php
    │   │   ├── OrderPlacementDeliveryRadiusValidatorTest.php
    │   │   └── OrderValidatorTest.php
    │   └── Functional/
    │       └── DeliveryRadiusCheckoutTest.php
    └── ...
```

### Service Dependencies

```yaml
services:
  # Epic #2: Delivery Radius Services
  store_fulfillment.delivery_radius_calculator:
    class: Drupal\store_fulfillment\DeliveryRadiusCalculator
    arguments: ['@entity_type.manager']

  store_fulfillment.delivery_radius_validator:
    class: Drupal\store_fulfillment\DeliveryRadiusValidator
    arguments:
      - '@store_fulfillment.delivery_radius_calculator'
      - '@string_translation'

  store_fulfillment.order_placement_delivery_radius_validator:
    class: Drupal\store_fulfillment\EventSubscriber\OrderPlacementDeliveryRadiusValidator
    arguments:
      - '@store_fulfillment.delivery_radius_validator'
      - '@logger.channel.store_fulfillment'
    tags:
      - { name: event_subscriber }

  # Epic #1: Order Timing Services
  store_fulfillment.order_validator:
    class: Drupal\store_fulfillment\OrderValidator
    arguments: ['@store_resolver.hours_validator', '@datetime.time', '@config.factory']

  store_fulfillment.order_placement_validator:
    class: Drupal\store_fulfillment\EventSubscriber\OrderPlacementValidator
    arguments: ['@store_fulfillment.order_validator', '@logger.factory']
    tags:
      - { name: event_subscriber }
```

## Integration Summary

This module successfully integrates two major features:

**Epic #1: Order Fulfillment Validation**
- Validates order timing against store hours
- Config-driven business rules
- ASAP and scheduled order support
- OrderValidator service and event subscriber

**Epic #2: Delivery Radius Validation**
- Validates delivery addresses against store radius
- Real-time checkout validation
- Geodetic distance calculations
- DeliveryRadiusValidator service and event subscriber

Both features work together in the FulfillmentTime checkout pane, providing comprehensive validation for both WHERE (delivery radius) and WHEN (order timing) customers can receive their orders.

## License

This module is proprietary software developed for Duccini's restaurant ordering system.
