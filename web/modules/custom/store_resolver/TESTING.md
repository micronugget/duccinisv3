# Store Resolver Modal - Testing Guide

This guide explains how to test the store selection modal functionality during development.

## Overview

The store resolver modal is a blocking full-page overlay that forces users to select a store before they can interact with the site. The selection is stored in a browser cookie for 30 days.

## Setup Instructions

1. **Enable the module** (if not already enabled):
   ```bash
   vendor/bin/drush en store_resolver -y
   vendor/bin/drush cr
   ```

2. **Place the Modal Block**:
   - Go to: `/admin/structure/block`
   - Click "Place block" in any region (preferably "Content" or "Header")
   - Find "Store Selection Modal" and click "Place block"
   - Configure visibility settings if needed (or leave default to show on all pages)
   - Save the block

3. **Ensure you have multiple stores**:
   - Go to: `/admin/commerce/config/stores`
   - Verify you have at least 2-3 stores created
   - If not, create test stores with names like "Downtown Store", "Uptown Store", etc.

4. **Clear caches**:
   ```bash
   vendor/bin/drush cr
   ```

## Testing the Modal

### Test 1: First-Time Visitor (No Cookie)

**Expected Behavior**: Modal appears automatically, blocking all page interaction.

**Steps**:
1. Open your browser in **Incognito/Private mode** (or clear cookies first)
2. Navigate to your site's homepage
3. **Expected**: After ~0.5 seconds, the modal should appear with a dark overlay
4. Try clicking outside the modal → Should NOT close (blocking modal)
5. Try pressing ESC key → Should NOT close
6. Select a store and click "Continue to Order"
7. **Expected**: Modal disappears, success message shows, page is interactive

### Test 2: Returning Visitor (Cookie Exists)

**Expected Behavior**: Modal does NOT appear.

**Steps**:
1. After completing Test 1, stay in the same browser session
2. Navigate to different pages on the site
3. **Expected**: Modal should NOT appear on any page
4. Close browser and reopen to the same site
5. **Expected**: Modal should still NOT appear (cookie persists)

### Test 3: Cookie Expiration

**Expected Behavior**: Modal reappears after cookie expires.

The cookie is set to expire after **30 days**. To test expiration without waiting:
1. Open browser Developer Tools (F12)
2. Go to Application/Storage → Cookies
3. Find cookie named `store_resolver_store_id`
4. Delete it or change expiration to past date
5. Refresh page
6. **Expected**: Modal should appear again

### Test 4: Multiple Stores Display

**Expected Behavior**: All active stores appear as radio options.

**Steps**:
1. Clear cookies (see Method 1 below)
2. Reload page
3. **Expected**: Each store should appear with:
   - Store name (bold)
   - Status: "Open Now" (green) or "Currently Closed" (red)
   - Location (city, state)
4. Click different radio buttons → They should select properly
5. First store should be pre-selected by default

### Test 5: Form Validation

**Expected Behavior**: Cannot submit without selection (though one is pre-selected).

**Steps**:
1. Open browser Dev Tools → Console
2. Run: `document.querySelector('input[name="store_id"]:checked').checked = false`
3. Try clicking "Continue to Order"
4. **Expected**: Browser validation message: "Please select one of these options"

## Development Testing Tools

### Method 1: Clear Cookie via Browser DevTools

1. Press **F12** to open DevTools
2. Go to **Application** tab (Chrome) or **Storage** tab (Firefox)
3. Expand **Cookies** in the left sidebar
4. Click on your domain
5. Find `store_resolver_store_id` cookie
6. Right-click → Delete
7. Refresh the page

### Method 2: Clear Cookie via JavaScript Console

1. Press **F12** to open DevTools
2. Go to **Console** tab
3. Run this command:
   ```javascript
   storeResolverReset()
   ```
4. **Expected output**: `Store cookie deleted. Reload the page to see the modal again.`
5. Refresh the page

### Method 3: Clear Cookie via Drupal Function

While on the site, open the browser console and run:
```javascript
Drupal.storeResolver.deleteStoreCookie()
location.reload()
```

### Method 4: Use Incognito/Private Mode

- **Chrome**: Ctrl+Shift+N (Windows/Linux) or Cmd+Shift+N (Mac)
- **Firefox**: Ctrl+Shift+P (Windows/Linux) or Cmd+Shift+P (Mac)
- **Safari**: Cmd+Shift+N (Mac)

Each new incognito window starts with no cookies.

## Debugging

### Check if Cookie is Set

Open console and run:
```javascript
console.log(document.cookie)
```

Look for: `store_resolver_store_id=1` (or 2, 3, etc.)

### Check Current Store Selection

```javascript
console.log(Drupal.storeResolver.getCookie('store_resolver_store_id'))
```

### Manually Set Cookie (for testing specific stores)

```javascript
Drupal.storeResolver.setStoreCookie(2)  // Set to store ID 2
location.reload()
```

### Force Modal to Show (even with cookie)

```javascript
Drupal.storeResolver.showModal()
```

### Force Modal to Hide

```javascript
Drupal.storeResolver.hideModal()
```

## Common Issues & Solutions

### Issue: Modal doesn't appear even without cookie

**Solutions**:
1. Clear Drupal cache: `vendor/bin/drush cr`
2. Check if block is placed: `/admin/structure/block`
3. Check browser console for JavaScript errors
4. Verify module is enabled: `vendor/bin/drush pm:list | grep store_resolver`

### Issue: Modal appears but can't be dismissed

**This is expected behavior!** The modal is intentionally blocking. Users MUST select a store.

### Issue: Cookie not being set

**Check**:
1. Browser console for errors
2. Browser settings allow cookies
3. Not in "block all cookies" mode
4. Check Developer Tools → Application → Cookies

### Issue: Styling looks broken

**Solutions**:
1. Clear cache: `vendor/bin/drush cr`
2. Check if CSS file loaded: View Page Source → search for `store-modal.css`
3. Check browser console for 404 errors on CSS file

## Testing Checklist

- [ ] Modal appears for first-time visitors
- [ ] Modal does NOT appear when cookie exists
- [ ] Can select different stores
- [ ] "Continue" button dismisses modal
- [ ] Success message appears after selection
- [ ] Cookie persists across page loads
- [ ] Cookie persists after browser restart
- [ ] Store status (Open/Closed) displays correctly
- [ ] Store location information displays
- [ ] Modal is fully blocking (can't click through)
- [ ] Modal is responsive on mobile devices
- [ ] Keyboard navigation works (Tab, Space to select)
- [ ] `storeResolverReset()` function works in console

## Quick Reset for Repeated Testing

Create a browser bookmark with this JavaScript:
```javascript
javascript:(function(){document.cookie='store_resolver_store_id=;expires=Thu,01 Jan 1970 00:00:00 UTC;path=/';location.reload();})()
```

Click the bookmark to instantly clear the cookie and reload the page.

## Notes for Production

- The modal timeout is set to **500ms** (0.5 seconds) - adjust in `js/store-modal.js` if needed
- Cookie expires after **30 days** - adjust in `js/store-modal.js` if needed
- The cookie name is `store_resolver_store_id` - change constant in both PHP and JS if needed
- Modal has z-index of **10000** to ensure it's above all other content

## Additional Resources

- Module code: `web/modules/custom/store_resolver/`
- JavaScript: `web/modules/custom/store_resolver/js/store-modal.js`
- CSS: `web/modules/custom/store_resolver/css/store-modal.css`
- Template: `web/modules/custom/store_resolver/templates/store-resolver-modal.html.twig`
