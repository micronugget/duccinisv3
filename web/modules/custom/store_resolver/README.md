# Store Resolver Module

Provides store selection and context management for multi-store ordering in Drupal Commerce.

## Features

- **Blocking Modal Store Selection**: Full-page modal that forces store selection on first visit
- **Cookie-based Persistence**: Remembers the last selected store for 30 days
- **Current Store Block**: Displays the currently selected store with option to change
- **Store Selection Modal Block**: Blocking overlay for required store selection
- **Store Hours Validation**: Shows store open/closed status and validates ordering times
- **Product Availability Filtering**: Supports store-specific product catalogs
- **Automatic Modal Display**: Modal automatically appears when no store cookie is detected

## Installation

1. Enable the module: `drush en store_resolver`
2. The module will automatically add required fields to commerce_store entities:
   - `store_hours`: Store operating hours
   - `products`: Store-specific product availability

## Configuration

### Store Hours Format

Edit each store and configure the `store_hours` field using the format:
```
day|open_time|close_time
```

Example:
```
monday|09:00|17:00
tuesday|09:00|17:00
wednesday|09:00|17:00
thursday|09:00|17:00
friday|09:00|20:00
saturday|10:00|20:00
sunday|12:00|18:00
```

### Store-Specific Products

To limit products to specific stores:
1. Edit the store entity
2. In the "Available Products" field, select which products this store carries
3. Leave empty to allow all products

## Usage

### Setup the Blocking Modal (Recommended)

1. **Place the Modal Block**:
   - Go to Structure > Block layout (`/admin/structure/block`)
   - Click "Place block" in any region (Content or Header recommended)
   - Find "Store Selection Modal" and place it
   - Configure visibility if needed, or show on all pages
   - Save

2. **How it works**:
   - Modal automatically displays on page load if no cookie exists
   - Blocks all interaction with the page until store is selected
   - Stores selection in cookie for 30 days
   - Modal won't show again until cookie expires or is deleted

3. **Testing**:
   - See `TESTING.md` for complete testing instructions
   - Quick test: Open in incognito mode to see modal
   - Reset cookie: Run `storeResolverReset()` in browser console

### Alternative: Display the Store Selection Form

Create a link or button to: `/store/select`

This provides a non-blocking form page for store selection.

### Display Current Store Block

1. Go to Structure > Block layout
2. Place the "Current Store" block in your desired region
3. The block will show:
   - Current store name and address (if selected)
   - Link to change store
   - Or prompt to select a store if none chosen

### Access Store in Custom Code

```php
$store_resolver = \Drupal::service('store_resolver.current_store');

// Get current store
$store = $store_resolver->getCurrentStore();

// Check if store is selected
if ($store_resolver->hasCurrentStore()) {
  // Do something
}

// Check if store is open
$hours_validator = \Drupal::service('store_resolver.hours_validator');
if ($hours_validator->isStoreOpen($store)) {
  // Store is open
}
```

## API Services

- `store_resolver.current_store`: Main service for store resolution
- `store_resolver.hours_validator`: Service for validating store hours

## Requirements

- Drupal 10 or 11
- Commerce 3.x
- Commerce Store module

## Related Modules

Works with **store_fulfillment** module for pickup/delivery and scheduling features.
