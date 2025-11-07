# Commerce Product & Variation Importing (Feeds + Commerce)

This guide captures the final, working process for importing Duccini’s Fresh Submarines products and variations using Feeds and Drupal Commerce.

Use this as a repeatable runbook when you need to re-import in the future.


## Overview
- You have two Feeds:
  - Product feed (Feed/3): creates/updates Commerce Products and sets product-only fields (stores). It does not touch the product’s `variations` field.
  - Variation feed (Feed/1): creates/updates Commerce Product Variations and links them to their product via the variation base field `product_id`.
- Import order: Products first, then Variations.
- The Product feed is configured to skip validation so products can be created without variations. The Variation feed will attach the variations right after.


## CSV expectations
- Source file for both feeds: `web/sites/default/files/fresh_submarines.csv`
- Columns:
  - `product_id` → Product title (e.g., "Pepperoni Sausage")
  - `sku` → Variation SKU (unique) (e.g., `sub_medium_pepperoni_sausage`)
  - `price__number` → Variation price (numeric) (e.g., `8.99`)
  - `sandwich_size_x` → Attribute value by name (e.g., `Medium 8"` or `X-Large 12"`)
  - `stores` → Store IDs pipe-delimited (e.g., `1|2|3`)

Note: The Gopher intentionally has only one row/size.


## Final feed configurations (essentials)

### 1) Product feed: `fresh_submarines_product` (Feed/3)
- Processor: `entity:commerce_product`
- Product type: `fresh_submarines`
- Mappings:
  - Title ← `product_id` (unique)
  - Stores ← `stores` (reference by Store ID, no autocreate)
- Important flags:
  - `skip_hash_check: true` (optional optimization)
  - `skip_validation: true` (required so products can be created without variations)
- What it does NOT do:
  - No mapping to `variations`. This is critical to prevent Feeds from clearing/overwriting variations per CSV row.

### 2) Variation feed: `fresh_submarines` (Feed/1)
- Processor: `entity:commerce_product_variation`
- Variation type: `submarine_sandwich`
- Mappings:
  - SKU ← `sku` (unique)
  - Title ← `product_id` (optional; for human readability)
  - Price ← `price__number` (currency: USD)
  - Attribute "Sandwich size" ← `sandwich_size_x` (reference by name, autocreate enabled, bundle `sandwich_size`)
  - Product base field `product_id` (entity reference) ← `product_id` (reference by Product title, autocreate disabled)


## Standard run sequence
1) Import configuration and clear caches
   - `ddev drush cim -y`
   - `ddev drush cr`

2) Optional clean slate (for deterministic tests)
   - Delete existing `fresh_submarines` products (this also deletes linked variations).
   - Reset both feeds’ tracking (Feed/3 Products and Feed/1 Variations) using the UI “Reset” or by setting `imported` and `item_count` to 0 in the `feeds_feed` table.
   - `ddev drush cr`

3) Import in this order
   - Run Product feed (Feed/3) to create products and set `stores`.
   - Run Variation feed (Feed/1) to create/update variations and link them to products via base field `product_id`.

4) Verify
   - Visit several product pages (e.g., Pepperoni Sausage, Italian Meat Balls). You should see both Medium 8" and X‑Large 12" options (where applicable).
   - Re-run the Product feed; variations must remain intact (since the Product feed no longer maps `variations`).


## Troubleshooting quick reference
- Message: “There are no new items.”
  - Cause: Feeds item hashes indicate nothing changed.
  - Fix: Reset the feed (set imported/item_count to 0 or use UI Reset). Optionally set `skip_hash_check: true` on the Product feed to always treat items as changed.

- Validation warning: “Variations (variations): This value should not be null.” during Product feed
  - Cause: Product creation attempted validation with no variations attached.
  - Fix: Ensure Product feed has `skip_validation: true` and no mapping to `variations`.

- Only one size shows per product after import
  - Cause: Product feed mapped `variations` and Feeds cleared/replaced values per row.
  - Fix: Remove the `variations` mapping entirely from the Product feed and let the Variation feed attach variations via the base field `product_id`.

- Duplicate products
  - Cause: Missing or incorrect unique mapping on Product feed.
  - Fix: Ensure Product Title mapping from `product_id` is marked unique.

- Variation updates create duplicates or don’t update
  - Cause: Variation feed lacks a unique field.
  - Fix: Ensure SKU is mapped and marked unique on the Variation feed.

- “Please select a field to reference by.” (stores or product references)
  - Stores reference: Choose “ID” (Store ID) because the CSV uses numeric IDs like `1|2|3`.
  - Product reference from Variation feed: Reference by “Title” because CSV uses product titles in `product_id`.

- HTTP fetcher error / invalid scheme
  - Ensure the feed source URL uses a valid scheme and the site can read local files or the configured URL.


## Maintenance tips
- When making configuration changes:
  - `ddev drush cex -y` to export.
  - Commit to Git.
  - On a fresh DB: `ddev drush cim -y` then `ddev drush cr`.
- Keep CSVs consistent (SKUs stable and unique). Attribute names must match exactly or be creatable via autocreate.
- Stores should be pipe-delimited store IDs: `1|2|3`.
- If you restore an older database, remember to re-import configuration and reset feeds before testing.


## Quick checklist
- [ ] Product feed does NOT map `variations`.
- [ ] Product feed maps Title (unique) and Stores (by ID), with `skip_validation: true`.
- [ ] Variation feed maps SKU (unique), Price, Size attribute (by name, autocreate on), and Product base field `product_id` (reference by Title, no autocreate).
- [ ] Import order: Products → Variations.
- [ ] If re-running: reset feeds and clear caches.


## Paths & files
- CSV: `web/sites/default/files/fresh_submarines.csv`
- Config (exported): `web/sites/default/files/sync/`
- Admin pages:
  - Product feed: `/feed/3`
  - Variation feed: `/feed/1`
  - Feed type mappings: `/admin/structure/feeds/manage/*/mapping`
  - Commerce products: `/admin/commerce/products`
