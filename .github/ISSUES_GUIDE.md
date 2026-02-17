# GitHub Issues Creation Guide

## Overview

Two epics with detailed sub-issues ready for GitHub import:

---

## Epic #1: Time-Based Order Fulfillment
**File:** `.github/ISSUE_EPIC_1.md`
**Story Points:** 13
**Sub-Issues:** 6

### Quick Copy/Paste for GitHub:

**Epic Title:** `[EPIC] Time-Based Order Fulfillment with Store Hours Validation`

**Labels:** `epic`, `priority:high`, `feature`

**Content:** Copy entire content from `ISSUE_EPIC_1.md`

### Sub-Issues to Create:

1. **[Epic #1.1] Order Validator Service** - 3 pts - @drupal-developer
2. **[Epic #1.2] Checkout Pane - Dynamic Form** - 5 pts - @drupal-developer
3. **[Epic #1.3] Order Placement Event Subscriber** - 2 pts - @drupal-developer
4. **[Epic #1.4] Admin Configuration Form** - 2 pts - @drupal-developer
5. **[Epic #1.5] Automated Testing** - 3 pts - @tester
6. **[Epic #1.6] Documentation** - 1 pt - @technical-writer

---

## Epic #2: Delivery Radius Validation
**File:** `.github/EPIC_2_BRIEF.md`
**Story Points:** 8
**Sub-Issues:** 7

### Quick Copy/Paste for GitHub:

**Epic Title:** `[EPIC] Delivery Radius Validation for Scheduled Orders`

**Labels:** `epic`, `priority:high`, `feature`, `commerce`

**Content:** Copy entire content from `EPIC_2_BRIEF.md`

### Sub-Issues to Create:

1. **[Epic #2.1] Shipping Method Validation** - 3 pts - @drupal-developer
2. **[Epic #2.2] AJAX Checkout Validation** - 3 pts - @drupal-developer
3. **[Epic #2.3] Admin UI Enhancement** - 2 pts - @themer
4. **[Epic #2.4] Performance Optimization** - 2 pts - @performance-engineer
5. **[Epic #2.5] Alternative Store Finder** - 2 pts - @drupal-developer
6. **[Epic #2.6] Automated Testing** - 3 pts - @tester
7. **[Epic #2.7] Documentation** - 1 pt - @technical-writer

---

## Commands to Execute

```bash
# Navigate to project
cd /home/lee/ams_projects/2025/week-43/v1/duccinisV3

# Commit the issue templates
git add .github/ISSUE_EPIC_1.md .github/EPIC_2_BRIEF.md .github/ISSUES_GUIDE.md
git commit -m "docs: Add Epic #1 and Epic #2 GitHub issue templates"
git push origin master
```

---

## Next Steps (Manual - GitHub UI)

### 1. Create Epic #1

1. Go to: https://github.com/micronugget/duccinisv3/issues/new
2. Title: `[EPIC] Time-Based Order Fulfillment with Store Hours Validation`
3. Paste content from `.github/ISSUE_EPIC_1.md`
4. Labels: `epic`, `priority:high`, `feature`
5. Create issue → Note the issue number (e.g., #1)

### 2. Create Epic #1 Sub-Issues

For each sub-issue (1.1 through 1.6):

1. Create new issue
2. Title format: `[Epic #1.1] Order Validator Service`
3. Reference parent: `Part of #1` (use actual epic number)
4. Copy acceptance criteria from epic
5. Labels: `enhancement`, `priority:high`
6. Assign to appropriate developer

### 3. Create Epic #2

1. Same process as Epic #1
2. Use `.github/EPIC_2_BRIEF.md` content
3. Labels: `epic`, `priority:high`, `feature`, `commerce`

### 4. Create Epic #2 Sub-Issues

For each sub-issue (2.1 through 2.7):

1. Follow same pattern as Epic #1 sub-issues
2. Reference Epic #2 issue number

---

## Project Board Setup (Optional)

Create a GitHub Project board with columns:

- **Backlog** - All sub-issues start here
- **Ready** - Issues ready to work (dependencies met)
- **In Progress** - Active development
- **Review** - Code review/testing
- **Done** - Merged and deployed

---

## Assessment: Configuration Import Status

### ✅ GOOD NEWS - No Data Lost!

Based on `STORE_MODULES_STATUS.md`:

1. ✅ **Custom modules exist:** `web/modules/custom/store_resolver/` and `store_fulfillment/`
2. ✅ **Configuration exported:** All configs in `config/sync/`
3. ✅ **Modules enabled:** Listed in `core.extension.yml`
4. ✅ **Fields created:** `store_hours`, `delivery_radius`, `store_location`, `products`

### What You DID Correctly:

You ran `ddev drush cex` (config export) which saved:
- Field definitions
- Module enablement status
- Commerce Shipping configs

### What Still Needs Configuration:

⚠️ **Actual store data** (content, not config):

1. Go to `/admin/commerce/config/stores`
2. Edit each store and fill in:
   - Store Hours (e.g., `monday|09:00|17:00`)
   - Delivery Radius (e.g., `10.00`)
   - Store Location (geocoded coordinates)
   - Available Products (optional)

3. Create shipping methods at `/admin/commerce/config/shipping-methods/add`

This is **content configuration** (stored in database), not **exported configuration** (YAML files).

---

## Conclusion

✅ **All custom code is safe**
✅ **All configuration is exported**
✅ **Ready to create GitHub issues**
✅ **No data lost**

⚠️ **Still needed:** Store-specific data entry (one-time setup per store)

---

**Next Action:** Copy/paste epic content to GitHub issues!
