# Store Fulfillment Module

Provides pickup and delivery fulfillment methods with scheduling for multi-store restaurant ordering in Drupal Commerce.

## Features

- **Store Pickup Shipping Method**: Flat-rate or free pickup at store
- **Store Delivery Shipping Method**: Delivery with radius validation
- **Delivery Radius Calculation**: Uses geofield/geocoder to validate delivery areas
- **Fulfillment Time Selection**: Checkout pane for ASAP or scheduled orders
- **Store Hours Integration**: Enforces scheduling when store is closed
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
3. Enable Commerce Shipping: `drush en commerce_shipping`
4. Enable Store Fulfillment: `drush en store_fulfillment`
5. The module will add fields to commerce_store:
   - `delivery_radius`: Maximum delivery radius in miles
   - `store_location`: Geofield for store coordinates

## Configuration

### 1. Configure Store Location

For each store:
1. Edit the store entity
2. Set the **Store Location** geofield with coordinates
3. Set the **Delivery Radius** (in miles)

### 2. Create Shipping Methods

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

### 3. Configure Checkout Flow

Go to Commerce > Configuration > Checkout flows > Default

1. Add the **Fulfillment Time** pane
2. Recommended step: "Order information"
3. Position it after shipping information

### 4. Enable Separate Delivery Address

Commerce Shipping automatically provides a separate shipping address field during checkout, distinct from billing address.

## How It Works

### Delivery Radius Validation

The delivery shipping method:
1. Gets the customer's delivery address from checkout
2. Geocodes it to coordinates
3. Calculates distance from selected store
4. Only offers delivery if within configured radius

### Store Hours Enforcement

The Fulfillment Time checkout pane:
1. Checks if selected store is currently open
2. If closed, disables "ASAP" option
3. Forces customer to schedule for future time
4. Generates time slots in 15-minute intervals

### Order Data Storage

Fulfillment preferences are stored in order data:
```php
$order->getData('fulfillment_type'); // 'asap' or 'scheduled'
$order->getData('scheduled_time'); // Timestamp if scheduled
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

// Get order fulfillment data
$type = $order->getData('fulfillment_type');
$scheduled_time = $order->getData('scheduled_time');
```

## API Services

- `store_fulfillment.delivery_radius_calculator`: Calculate delivery distances

## Architecture

This module extends Commerce Shipping's plugin system:
- **ShippingMethod plugins**: Define pickup and delivery methods
- **CheckoutPane plugin**: Add time selection to checkout
- Uses store_resolver for store context

## Related Modules

- **store_resolver**: Required for store selection and context
- **commerce_shipping**: Required base for fulfillment methods
