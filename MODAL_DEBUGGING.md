# Store Modal Debugging Guide

## The Most Likely Issue: You Already Have a Cookie!

The modal **only appears when NO cookie exists**. If you've already selected a store (even in previous testing), the cookie persists for 30 days.

## Quick Fix: Delete the Cookie

### Method 1: Use the Test Page (Easiest!)

I've created a diagnostic test page:

1. Open: `static_html/current-menu/test-modal.html` in your browser
2. Click **"Check Cookie"** button
3. If it says "Cookie EXISTS" → Click **"Delete Cookie"** button
4. Refresh the page
5. **Expected**: Modal should appear automatically after 0.5 seconds

### Method 2: Browser DevTools

1. Press `F12` (or right-click → Inspect)
2. Go to **Application** tab (Chrome) or **Storage** tab (Firefox)
3. Expand **Cookies** in left sidebar
4. Click on your domain (e.g., `http://yoursite.ddev.site`)
5. Find cookie named: `store_resolver_store_id`
6. Right-click → **Delete**
7. Refresh the page
8. **Expected**: Modal appears

### Method 3: Browser Console

1. Press `F12`
2. Go to **Console** tab
3. Type: `storeResolverReset()`
4. Press Enter
5. Refresh page
6. **Expected**: Modal appears

### Method 4: Incognito/Private Mode (Always Works!)

1. Open **Incognito/Private window**:
   - Chrome: `Ctrl+Shift+N` (Windows) or `Cmd+Shift+N` (Mac)
   - Firefox: `Ctrl+Shift+P` (Windows) or `Cmd+Shift+P` (Mac)
2. Navigate to your site
3. **Expected**: Modal appears (no cookies in incognito)

## How to Know If It's Working

### When Modal SHOULD Appear:
- ✅ First-time visitor (no cookie)
- ✅ Cookie was deleted
- ✅ Cookie expired (after 30 days)
- ✅ Using Incognito/Private mode

### When Modal SHOULD NOT Appear:
- ❌ Store already selected (cookie exists)
- ❌ Within 30 days of last selection
- ❌ Same browser session after selecting store

## Diagnostic Test Page Features

Open `static_html/current-menu/test-modal.html` to access:

### Cookie Controls
- **Check Cookie** - Shows if cookie exists and its value
- **Delete Cookie** - Removes the store selection cookie
- **Set Cookie to Store 1** - Manually sets cookie for testing

### Modal Controls
- **Show Modal** - Manually displays the modal
- **Hide Modal** - Manually hides the modal
- **Check if Modal Exists** - Verifies modal HTML is on page

### JavaScript Check
- **Check JavaScript Objects** - Verifies all code loaded correctly

## Verifying Modal Works on Live Site

### Test 1: No Cookie = Modal Appears
```bash
# In browser console:
document.cookie = 'store_resolver_store_id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;'
location.reload()
```
**Expected**: Modal appears after 0.5 seconds

### Test 2: Modal Blocks Interaction
When modal is visible:
- Try clicking outside → Nothing happens ✅
- Try scrolling → Page doesn't scroll ✅
- Try clicking links → Can't click through modal ✅

### Test 3: Select Store = Modal Disappears
1. Select a store radio button
2. Click "Continue to Order"
3. **Expected**:
   - Modal disappears
   - Can interact with page
   - Cookie is set (check DevTools → Application → Cookies)

### Test 4: Refresh = Modal Stays Hidden
1. After selecting store, refresh page
2. **Expected**: Modal does NOT appear

### Test 5: Reset Works
```javascript
// In console:
storeResolverReset()
```
**Expected**: "Store cookie deleted. Reload the page to see the modal again."

Then refresh page:
**Expected**: Modal appears again

## Common Issues & Solutions

### Issue: "Modal never appears"
**Cause**: Cookie already exists from previous session
**Solution**: Delete cookie using any method above

### Issue: "Modal appears but doesn't look right"
**Cause**: CSS not loaded or cached old version
**Solution**:
```bash
ddev drush cr
# Then hard refresh browser: Ctrl+Shift+R
```

### Issue: "Modal appears but I can still click through it"
**Cause**: CSS z-index or display issue
**Solution**: Check browser console for CSS errors

### Issue: "Console says 'Drupal.storeResolver is undefined'"
**Cause**: JavaScript not loaded or old version cached
**Solution**:
```bash
ddev drush cr
# Then hard refresh: Ctrl+Shift+R or use Incognito
```

### Issue: "storeResolverReset() says 'not defined'"
**Cause**: JavaScript not loaded yet
**Solution**: Wait for page to fully load, then try again

## Technical Details

### Cookie Information
- **Name**: `store_resolver_store_id`
- **Value**: Store ID (1, 2, 3, etc.)
- **Duration**: 30 days
- **Path**: `/` (site-wide)
- **SameSite**: Lax

### JavaScript Behavior
1. Page loads
2. After 0.5 seconds, JavaScript checks for cookie
3. If NO cookie: Add `is-active` class to `#store-resolver-modal`
4. CSS changes modal from `display: none` to `display: flex`
5. Modal becomes visible, blocking page

### CSS Classes
- `#store-resolver-modal` - Modal container (hidden by default)
- `#store-resolver-modal.is-active` - Modal visible (display: flex)
- `body.store-modal-open` - Body overflow hidden (prevents scrolling)

## Files to Check

- **JavaScript**: `web/modules/custom/store_resolver/js/store-modal.js`
- **CSS**: `web/modules/custom/store_resolver/css/store-modal.css`
- **Template**: `web/modules/custom/store_resolver/templates/store-resolver-modal.html.twig`

## Still Not Working?

If none of the above helps:

1. Open `test-modal.html` in browser
2. Click "Check JavaScript" button
3. Take a screenshot of all three test panels
4. Check browser console (F12 → Console) for errors
5. Check Network tab (F12 → Network) - verify `store-modal.js` loads (status 200)

The test page will show exactly what's wrong:
- ✅ All green checkmarks = Everything loaded correctly
- ❌ Red X = Something is missing or broken
