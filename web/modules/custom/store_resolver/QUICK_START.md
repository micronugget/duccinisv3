# Store Resolver Modal - Quick Start Guide

## 5-Minute Setup

### Step 1: Clear Cache
```bash
cd /home/lee/ams_projects/2025/week-43/v1/duccinisV3
vendor/bin/drush cr
```

### Step 2: Verify Module is Enabled
```bash
vendor/bin/drush pm:list | grep store_resolver
```

If not enabled:
```bash
vendor/bin/drush en store_resolver -y
vendor/bin/drush cr
```

### Step 3: Place the Modal Block

1. Go to: **`/admin/structure/block`**
2. Find your active theme section (e.g., "Duccinis 1984 Olympics")
3. Click **"Place block"** in the **"Content"** region
4. Search for: **"Store Selection Modal"**
5. Click **"Place block"**
6. Configure settings (or use defaults):
   - Title: *Leave blank or set display to none*
   - Visibility: *Show on all pages (default)*
7. Click **"Save block"**

### Step 4: Verify You Have Multiple Stores

```bash
vendor/bin/drush sqlq "SELECT store_id, name FROM commerce_store_field_data"
```

If you only see one store, create more:
1. Go to: **`/admin/commerce/config/stores`**
2. Click **"Add store"**
3. Create at least 2-3 stores with different names

### Step 5: Test in Incognito Mode

1. Open browser in **Incognito/Private mode**: `Ctrl+Shift+N` (Chrome)
2. Navigate to your site: `http://yoursite.local` (or whatever your local URL is)
3. **Expected**: Modal should appear automatically after ~0.5 seconds
4. Select a store and click **"Continue to Order"**
5. **Expected**: Modal closes, you can interact with the page
6. Refresh the page
7. **Expected**: Modal does NOT appear (cookie is set)

## Testing the Reset Function

1. On your site, press **F12** to open DevTools
2. Go to **Console** tab
3. Type: `storeResolverReset()`
4. Press **Enter**
5. **Expected**: Message appears: *"Store cookie deleted. Reload the page to see the modal again."*
6. Refresh the page
7. **Expected**: Modal appears again

## Common Issues

### "Modal doesn't appear"
```bash
# Clear cache
vendor/bin/drush cr

# Verify block is placed
# Go to /admin/structure/block and look for "Store Selection Modal"
```

### "JavaScript errors in console"
1. Press F12
2. Check Console tab for red errors
3. Look for 404 errors on `.js` or `.css` files
4. If found, clear cache: `vendor/bin/drush cr`

### "Modal appears but stores aren't showing"
1. Verify stores exist: Go to `/admin/commerce/config/stores`
2. Check that stores are published/active
3. Clear cache: `vendor/bin/drush cr`

### "Cookie not persisting"
1. Check browser allows cookies
2. Check you're not in "Block all cookies" mode
3. Try different browser

## Quick Reference

**Cookie Name**: `store_resolver_store_id`
**Cookie Duration**: 30 days
**Reset Command**: `storeResolverReset()`
**Modal Delay**: 500ms (0.5 seconds)

## What's Next?

After setup works:
1. Read **`TESTING.md`** for comprehensive testing procedures
2. Read **`IMPLEMENTATION_SUMMARY.md`** for technical details
3. Customize styling in `css/store-modal.css`
4. Customize text in `templates/store-resolver-modal.html.twig`

## Development Workflow

```bash
# Make changes to code
vim web/modules/custom/store_resolver/js/store-modal.js

# Clear cache
vendor/bin/drush cr

# Test with reset
# In browser console: storeResolverReset()

# Repeat
```

## File Locations

- **JavaScript**: `web/modules/custom/store_resolver/js/store-modal.js`
- **CSS**: `web/modules/custom/store_resolver/css/store-modal.css`
- **Template**: `web/modules/custom/store_resolver/templates/store-resolver-modal.html.twig`
- **Block**: `web/modules/custom/store_resolver/src/Plugin/Block/StoreSelectionModalBlock.php`

## Getting Help

1. **Console errors?** Press F12 → Console tab
2. **Cookie issues?** F12 → Application → Cookies
3. **Can't find block?** Check `/admin/structure/block`
4. **Module not working?** Run `vendor/bin/drush cr`

---

**That's it!** You should now have a working blocking modal that forces store selection.

For detailed testing procedures, see **TESTING.md**.
