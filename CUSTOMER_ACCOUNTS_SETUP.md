# Customer Accounts & Reordering Setup

## Changes Made

### 1. Updated Checkout Flow Configuration
**File:** `config/sync/commerce_checkout.commerce_checkout_flow.default.yml`

Changed the following settings to enable customer accounts:
- `guest_order_assign: true` - Orders are now assigned to existing users with matching email
- `guest_new_account: true` - User accounts are automatically created for guest checkouts
- `guest_new_account_notify: true` - Customers receive email with password reset link
- `allow_registration: true` - Customers can create accounts during checkout

### 2. Created Custom Reorder Module
**Location:** `web/modules/custom/commerce_reorder/`

This module adds reorder functionality with:
- **Reorder Controller** - Adds all items from a previous order to cart
- **Access Control** - Ensures only order owners can reorder
- **Views Integration** - Adds "Reorder" link to order list
- **Route** - `/user/{uid}/orders/{order_id}/reorder`

## Next Steps

### Import Configuration & Enable Module

Run these commands in your Drupal environment:

```bash
# Import the updated checkout flow configuration
drush config:import

# Enable the custom reorder module
drush pm:enable commerce_reorder

# Clear caches
drush cache:rebuild
```

If you don't have drush CLI access, you can:
1. Go to **Admin → Configuration → Development → Configuration synchronization → Import**
2. Import the configuration
3. Go to **Admin → Extend**
4. Enable the "Commerce Reorder" module
5. Clear caches at **Admin → Configuration → Development → Performance**

### Test the Customer Workflow

1. **Test Guest Checkout with Account Creation:**
   - Log out and visit your site
   - Add items to cart and proceed to checkout
   - You should see options to either:
     - Log in
     - Register a new account
     - Continue as guest
   - Complete checkout as guest
   - Check email for account activation link

2. **Test Existing Customer:**
   - Use the same email address for another order
   - The order should automatically be assigned to your account

3. **Test Order History:**
   - Log in as a customer
   - Visit `/user/orders` (or click "Orders" tab on user profile)
   - You should see your order history

4. **Test Reordering:**
   - On the order list, click the "Reorder" button/link
   - All items from that order should be added to your cart
   - Review cart and proceed to checkout

### Add Reorder Link to Order List View (Optional)

To add the "Reorder" button in the orders list:

1. Go to **Admin → Structure → Views → User orders → Edit**
2. Add a new field: "Commerce Order: Reorder link"
3. Configure display settings
4. Save the view

## How It Works

### Customer Account Flow

```
Guest Checkout → Order Placed → User Account Auto-Created → Email Sent
                                    ↓
                        Order Assigned to User Account
```

### For Returning Customers

```
Existing Customer Email → Order Automatically Assigned → No New Account Created
```

### Reordering

```
Customer Views Orders → Clicks "Reorder" → Items Added to Cart → Checkout
```

## Benefits

1. **No Duplicate Profiles** - Each customer has one account with all orders
2. **Easy Reordering** - One-click to add all items from previous order
3. **Order History** - Customers can view all past orders at `/user/{uid}/orders`
4. **Saved Addresses** - Billing/shipping addresses are saved with customer profile
5. **Saved Payment Methods** - Customers can save payment methods for faster checkout

## Permissions

The authenticated user role already has these permissions:
- `view own commerce_order` - View their own orders
- `access checkout` - Access checkout process
- `manage own commerce_payment_method` - Manage saved payment methods

## Configuration Files Changed

- `config/sync/commerce_checkout.commerce_checkout_flow.default.yml`

## New Module Files

- `web/modules/custom/commerce_reorder/commerce_reorder.info.yml`
- `web/modules/custom/commerce_reorder/commerce_reorder.routing.yml`
- `web/modules/custom/commerce_reorder/commerce_reorder.links.task.yml`
- `web/modules/custom/commerce_reorder/commerce_reorder.views.inc`
- `web/modules/custom/commerce_reorder/src/Controller/ReorderController.php`
- `web/modules/custom/commerce_reorder/src/Access/ReorderAccessCheck.php`
- `web/modules/custom/commerce_reorder/src/Plugin/views/field/ReorderLink.php`
