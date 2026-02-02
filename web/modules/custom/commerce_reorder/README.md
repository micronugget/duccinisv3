# Commerce Reorder

## Overview

The Commerce Reorder module adds reorder functionality to Drupal Commerce orders, allowing customers to quickly reorder items from their previous orders. This module provides a convenient way for users to repurchase products without having to search for them again.

## Features

- **One-Click Reordering**: Customers can reorder all items from a previous order with a single click
- **Smart Item Validation**: Automatically checks if products are still available before adding them to cart
- **Access Control**: Ensures only order owners or administrators can reorder
- **Views Integration**: Provides a Views field plugin for displaying reorder links in order lists
- **User Feedback**: Provides clear messages about successful additions and unavailable items
- **Multi-Store Support**: Works seamlessly with Drupal Commerce's multi-store functionality

## Requirements

- Drupal Core: ^11
- Commerce Order module (drupal:commerce_order)
- Commerce Cart module (drupal:commerce_cart)

## Installation

1. Place the module in your `web/modules/custom/commerce_reorder` directory
2. Enable the module using Drush:
   ```bash
   drush en commerce_reorder
   ```
   Or through the Drupal admin interface at `/admin/modules`

## Usage

### For Customers

Once the module is enabled, customers will see a "Reorder" option when viewing their past orders:

1. Navigate to your order history (typically at `/user/{user_id}/orders`)
2. Click on an order to view its details
3. Click the "Reorder" tab or button
4. All available items from that order will be added to your cart
5. You'll be redirected to the cart page to review and complete your order

### For Site Administrators

#### Adding Reorder Links to Views

The module provides a Views field plugin that can be added to any order listing:

1. Edit your order view (e.g., the user orders view)
2. Add a new field
3. Search for "Reorder link" (commerce_reorder_link)
4. Configure the field display options as needed
5. Save the view

The reorder link will automatically:
- Hide for draft orders
- Check access permissions
- Display as a styled button

## Architecture

### Components

#### 1. ReorderController (`src/Controller/ReorderController.php`)

The main controller that handles the reorder process:

- **Route**: `/user/{user}/orders/{commerce_order}/reorder`
- **Method**: `reorder(AccountInterface $user, OrderInterface $commerce_order)`
- **Process**:
  1. Retrieves all items from the specified order
  2. Gets or creates a cart for the order's store
  3. Iterates through order items and validates product availability
  4. Adds available items to the cart with their original quantities
  5. Provides user feedback about added/unavailable items
  6. Redirects to the cart page

**Key Features**:
- Validates that purchased entities still exist and are accessible
- Handles empty orders gracefully
- Tracks the count of successfully added items
- Provides appropriate success or warning messages

#### 2. ReorderAccessCheck (`src/Access/ReorderAccessCheck.php`)

Custom access control for the reorder functionality:

- **Checks**:
  - User owns the order (customer ID matches logged-in user)
  - User parameter in route matches logged-in user
  - OR user has 'administer commerce_order' permission
- **Caching**: Properly implements cache dependencies for both order and account

#### 3. ReorderLink Views Field Plugin (`src/Plugin/views/field/ReorderLink.php`)

Views integration for displaying reorder links:

- **Plugin ID**: `commerce_reorder_link`
- **Features**:
  - Renders a styled button link to the reorder route
  - Automatically hides for draft orders
  - Checks access permissions before displaying
  - Applies CSS classes: `button`, `button--small`, `button--primary`

### Configuration Files

#### commerce_reorder.routing.yml

Defines the reorder route with:
- Path parameters for user and order entities
- Custom access callback
- Entity parameter conversion

#### commerce_reorder.links.task.yml

Adds a "Reorder" tab to the order view page:
- Appears on the order detail page
- Weight: 20 (appears after other tabs)
- Base route: `entity.commerce_order.user_view`

## Permissions

The module respects existing Commerce permissions:

- **Order Owners**: Can reorder their own orders
- **Administrators**: Users with `administer commerce_order` permission can reorder any order

No additional permissions are required.

## User Experience Flow

1. **Customer views order history**
   - Sees list of past orders with reorder buttons/links

2. **Customer clicks "Reorder"**
   - Module validates access
   - Retrieves all items from the selected order

3. **Item Validation**
   - Checks if each product still exists
   - Verifies product is accessible (published, in stock, etc.)
   - Skips unavailable items

4. **Cart Update**
   - Gets existing cart or creates new one for the order's store
   - Adds available items with original quantities
   - Uses Commerce Cart Manager for proper cart handling

5. **User Feedback**
   - Success message: "Added X item(s) from order #Y to your cart"
   - Warning if no items available: "None of the items from this order are currently available"
   - Warning if order is empty: "This order has no items to reorder"

6. **Redirect to Cart**
   - Customer can review items
   - Modify quantities if needed
   - Proceed to checkout

## Technical Notes

### Service Dependencies

The ReorderController uses:
- `commerce_cart.cart_manager`: For adding items to cart
- `commerce_cart.cart_provider`: For getting/creating carts
- `messenger`: For user feedback messages

### Entity Handling

- Uses proper entity parameter conversion in routing
- Validates entity access before operations
- Properly handles missing or deleted entities

### Multi-Store Compatibility

- Respects the store context from the original order
- Creates/uses carts specific to the order's store
- Ensures items are added to the correct store's cart

## Troubleshooting

### Reorder button not appearing

- Check that the user owns the order or has admin permissions
- Verify the order is not in 'draft' state
- Ensure the module is enabled
- Clear Drupal cache

### Items not being added to cart

- Verify products still exist and are published
- Check product availability and stock levels
- Review product access permissions
- Check for any custom cart validation rules

### Access denied errors

- Confirm the user is logged in
- Verify the user owns the order
- Check that the order ID in the URL is correct

## Development

### Extending the Module

You can extend this module by:

1. **Adding event subscribers** to modify reorder behavior
2. **Altering the access check** via custom access checks
3. **Customizing the Views field** by extending the ReorderLink plugin
4. **Adding hooks** to modify items before they're added to cart

### Example: Hook into the reorder process

```php
/**
 * Implements hook_commerce_cart_entity_add().
 */
function mymodule_commerce_cart_entity_add(OrderInterface $cart, PurchasableEntityInterface $entity, $quantity, OrderItemInterface $order_item) {
  // Custom logic when items are added during reorder
}
```

## Maintainers

This is a custom module developed for the Duccinis project.

## License

This module follows the same license as the Drupal project.

## Support

For issues or questions about this module, please contact the site development team.
