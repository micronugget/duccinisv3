# Store Hours Configuration Guide

This guide explains how to configure store operating hours for the order fulfillment system.

---

## Quick Start

1. Navigate to **Commerce → Configuration → Stores** (`/admin/commerce/config/stores`)
2. Edit your store
3. Find the **Store Hours** field
4. Enter hours using the format: `day|opening_time|closing_time`
5. Save

---

## Format Specification

### Basic Format

```
day|opening_time|closing_time
```

- **day:** Full day name in lowercase (monday, tuesday, etc.)
- **opening_time:** 24-hour format (HH:MM)
- **closing_time:** 24-hour format (HH:MM)

### Examples

**Single Day:**
```
monday|09:00|17:00
```
*Store opens at 9:00 AM and closes at 5:00 PM on Mondays*

**Full Week:**
```
monday|09:00|17:00
tuesday|09:00|17:00
wednesday|09:00|17:00
thursday|09:00|17:00
friday|09:00|21:00
saturday|10:00|18:00
sunday|11:00|16:00
```

**Extended Friday Hours:**
```
friday|09:00|21:00
```
*Open until 9:00 PM on Fridays*

---

## Special Cases

### Overnight Hours (Late Night Store)

For stores that close after midnight, the closing time will be earlier than the opening time:

```
friday|22:00|02:00
saturday|22:00|02:00
```

**How this works:**
- Friday entry means: Open Friday 10:00 PM, close Saturday 2:00 AM
- Saturday entry means: Open Saturday 10:00 PM, close Sunday 2:00 AM

### Closed Days

Simply **omit** days when the store is closed. For example, if closed on Sundays:

```
monday|09:00|17:00
tuesday|09:00|17:00
wednesday|09:00|17:00
thursday|09:00|17:00
friday|09:00|17:00
saturday|10:00|18:00
```
*(No Sunday entry = closed on Sundays)*

### 24-Hour Operation

To indicate a store never closes on a specific day:

```
monday|00:00|23:59
```

Or for true 24-hour:
```
monday|00:00|00:00
```

---

## Common Patterns

### Standard Business Hours (Mon-Fri 9-5)
```
monday|09:00|17:00
tuesday|09:00|17:00
wednesday|09:00|17:00
thursday|09:00|17:00
friday|09:00|17:00
```

### Restaurant Hours (Different Weekend Hours)
```
monday|11:00|22:00
tuesday|11:00|22:00
wednesday|11:00|22:00
thursday|11:00|22:00
friday|11:00|23:00
saturday|10:00|23:00
sunday|10:00|21:00
```

### Retail with Extended Weekend
```
monday|10:00|18:00
tuesday|10:00|18:00
wednesday|10:00|18:00
thursday|10:00|18:00
friday|10:00|20:00
saturday|09:00|20:00
sunday|11:00|17:00
```

---

## Validation Rules

### ✅ Valid Entries

- `monday|09:00|17:00` ← Correct
- `friday|22:00|02:00` ← Overnight hours OK
- `sunday|00:00|23:59` ← 24-hour OK

### ❌ Invalid Entries (Will be ignored)

- `Monday|09:00|17:00` ← Capitalized day
- `mon|09:00|17:00` ← Abbreviated day
- `monday|9am|5pm` ← Non-24-hour format
- `monday 09:00-17:00` ← Wrong separator
- `monday|09:00` ← Missing closing time

---

## How It Affects Orders

### Immediate/ASAP Orders

**Allowed when:**
- Current time is within store hours
- At least 15 minutes before closing (configurable)

**Example:**
- Store hours: `monday|09:00|17:00`
- Current time: Monday 4:30 PM
- ASAP cutoff: 15 minutes before close
- **Result:** ASAP orders blocked after 4:45 PM

### Scheduled Orders

**Allowed when:**
- Scheduled time is within store hours
- At least 30 minutes in future (configurable)
- No more than 14 days ahead (configurable)

**Example:**
- Store hours: `tuesday|10:00|18:00`
- Scheduled for: Tuesday 2:00 PM
- **Result:** ✅ Allowed (within hours)

**Example - Rejected:**
- Store hours: `tuesday|10:00|18:00`
- Scheduled for: Tuesday 9:00 AM
- **Result:** ❌ Rejected (before opening)

---

## Timezone Considerations

Store hours are always evaluated in the **store's configured timezone**.

**Store Settings:**
- Timezone: `America/New_York` (EST/EDT)
- Hours: `monday|09:00|17:00`

**Customer in California (PST):**
- When it's 6:00 AM PST → 9:00 AM EST
- Store just opened ✅
- Customer can place ASAP order

**System automatically handles:**
- Daylight Saving Time changes
- International customers
- Multi-timezone deployments

---

## Testing Your Configuration

### Quick Test Checklist

1. **Save your hours** in the store edit form
2. **Check store status** on the frontend
3. **Test ASAP order** during business hours
4. **Test ASAP order** after hours (should be rejected)
5. **Test scheduled order** for tomorrow during business hours
6. **Test scheduled order** for tomorrow at 3:00 AM (should be rejected)

### Using Browser Console

Open Developer Tools (F12) and check for store hours:

```javascript
// This will be available if store hours are loaded
console.log(drupalSettings.store_resolver);
```

---

## Troubleshooting

### Problem: ASAP orders always rejected

**Possible causes:**
1. Store hours not set
2. Current time outside configured hours
3. Within cutoff period before closing
4. Wrong timezone configured

**Solution:**
1. Verify hours are entered correctly
2. Check store timezone setting
3. Verify current server time matches expected

### Problem: Store shows as "Always Open"

**Cause:** No store hours configured (empty field)

**Solution:** Enter at least one day's hours

### Problem: Overnight hours not working

**Check format:**
- ✅ Correct: `friday|22:00|02:00` (closing < opening)
- ❌ Wrong: `friday|22:00|26:00` (invalid time)

**Remember:** Overnight hours span two calendar days

---

## Advanced Configuration

### Multiple Store Types

If you have different store types (online, physical), each can have its own hours:

**Online Store:**
```
monday|00:00|23:59
tuesday|00:00|23:59
...
```
*(24/7 online ordering)*

**Physical Store:**
```
monday|09:00|17:00
tuesday|09:00|17:00
...
```
*(Regular business hours for pickup)*

### Seasonal Hours

For temporary hour changes (holidays, etc.), edit the store and update hours:

**Example - Holiday Hours:**
```
monday|10:00|15:00
tuesday|10:00|15:00
wednesday|closed
thursday|10:00|15:00
friday|10:00|15:00
```

**Remember:** Update these back after the holiday period!

---

## Related Settings

Store hours work with these system settings (admin only):

**Path:** `/admin/config/services/store-fulfillment`

- **Minimum Advance Notice:** Default 30 minutes
- **Maximum Scheduling Window:** Default 14 days
- **ASAP Cutoff Before Closing:** Default 15 minutes

---

## Need Help?

If you encounter issues:

1. **Check the format** - Most issues are formatting errors
2. **Verify timezone** - Ensure store timezone is correct
3. **Test systematically** - Use the test checklist above
4. **Check logs** - Admin can check Drupal logs for validation errors

---

**Last Updated:** February 4, 2026
**Module:** Store Resolver
**Version:** 1.0
