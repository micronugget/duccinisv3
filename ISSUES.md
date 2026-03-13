# Duccinis V3 → V4 Migration Issues

> **Goal:** Bring `/home/lee/ams_projects/2025/week-43/v1/duccinisV3` up to parity with this DrupalCMS 2–based V4 site, incorporating all security fixes, architectural improvements, custom-module additions, and theme cleanups landed in V4.
>
> **Baseline comparison (March 10, 2026)**
> - V3: Drupal 11.3.3 · Commerce site · `master` branch
> - V4: Drupal 11.3.5 · DrupalCMS 2 scaffold (`drupalcms2migration`) + full Commerce site (`master`)

---

## Epic 1 — Security Hardening

> Eliminate all credentials from the V3 git history and harden the repository against future leaks. This mirrors the git-filter-repo remediation already completed in V4 (Feb 26, 2026).

### Issues

- [ ] **#1.1 — Rotate exposed Stripe test keys**
  Remove and rotate any `sk_test_*` / `pk_test_*` keys that were committed in
  `config/sync/commerce_payment.commerce_payment_gateway.stripe.yml` across V3's history.
  Even after history rewrite, GitHub caches may retain the values.
  _Done when: Stripe Dashboard shows old keys revoked; new keys issued._

- [x] **#1.2 — Rewrite V3 git history to scrub API keys** ✅ *Closed 2026-03-12 (#94)*
  Run `git-filter-repo` (two-pass) on all V3 branches to replace key strings with `REDACTED`.
  Match the procedure documented in `.github/copilot-instructions.md` security section.
  Branches to rewrite: `master` + all `issue/*`, `fix/*`, `copilot/*` branches.
  _Done when: `git log -p | grep sk_test_51` returns no matches on any branch._
  **Result:** `git-filter-repo --replace-text` rewrote 163 commits across all 38 local branches.
  Only occurrence was a truncated doc reference (`sk_test_51Sfs1Z…`) in `DRUPALCMS2_MIGRATION_RUNBOOK.md` — no full key was ever committed. Replaced with `REDACTED`. Verification: `git log --all --text -p | grep sk_test_51` → 0 matches.

- [ ] **#1.3 — Move Stripe keys to `settings.php` `$config` overrides**
  Replicate the V4 pattern: leave empty strings in the YAML; populate at runtime via:
  ```php
  $config['commerce_payment.commerce_payment_gateway.stripe']['configuration']['secret_key'] = '...';
  ```
  Ensure `web/sites/default/settings.php` is gitignored.
  _Done when: `config/sync/commerce_payment.commerce_payment_gateway.stripe.yml` has `''` for all key fields._

- [ ] **#1.4 — Remove `databasebackup.sql` from repo and history**
  `databasebackup.sql` is committed at V3 root — contains full DB dump including PII.
  Remove from HEAD and rewrite history with `git-filter-repo --path databasebackup.sql --invert-paths`.
  Add `*.sql` and `*.sql.gz` to `.gitignore`.
  _Done when: `git log --all --full-history -- databasebackup.sql` returns empty._

- [ ] **#1.5 — Harden `.gitignore` to match V4**
  Add the following entries matching V4's hardened gitignore:
  ```
  web/sites/default/settings.php
  web/sites/default/settings.ddev.php
  *.sql
  *.sql.gz
  *.pem
  *.key
  *.cert
  ```
  _Done when: `git status` no longer tracks any of the above file types._

- [ ] **#1.6 — Enable GitHub Secret Scanning on V3 repository**
  Navigate to repo Settings → Security → Secret scanning and enable.
  _Done when: Secret scanning is active and shows no open alerts._

---

## Epic 2 — Drupal Core & Dependency Parity

> Align V3's Drupal core version and composer dependencies with V4's current state, and integrate the DrupalCMS 2 recipe layer.

### Issues

- [ ] **#2.1 — Update Drupal core from 11.3.3 to 11.3.5**
  Run `ddev composer update drupal/core-recommended drupal/core-composer-scaffold drupal/core-project-message --with-all-dependencies`.
  Verify no update hooks are needed (`ddev drush updb`).
  _Done when: `ddev drush status` shows Drupal 11.3.5._

- [ ] **#2.2 — Add `drupal/drupal_cms_helper ^2.0` to V3**
  V4 depends on `drupal/drupal_cms_helper` as the DrupalCMS 2 base layer.
  Add it to V3's `composer.json` and enable the module.
  _Done when: Module is listed in `core.extension.yml`._

- [x] **#2.3 — Reconcile contrib module version drift** _(verified 2026-03-13, issue #101)_
  Compare V3 and V4 `composer.lock` for divergent contrib package versions.
  Key packages to check: `drupal/radix`, `drupal/geocoder`, `drupal/commerce_stripe`, `drupal/eca`, `drupal/gin`, `drupal/easy_email`, `drupal/trash`.
  **Verification result:** V4 is at parity or ahead of V3 on all 7 key packages and all 109 drupal/* packages in V3 are present in V4 (V4 has 116 total). `composer outdated | grep drupal/` returns empty — no drupal packages outdated in V4. No changes required.
  _Done when: `composer outdated | grep drupal/` shows no version gaps vs V4._

- [ ] **#2.4 — Evaluate and integrate DrupalCMS 2 recipe bundles**
  V4's `drupalcms2migration` branch includes recipes for: `drupal_cms_accessibility_tools`, `drupal_cms_ai`, `drupal_cms_forms`, `drupal_cms_google_analytics`, `drupal_cms_seo_tools`, `drupal_cms_starter`.
  Review each recipe against V3's already-installed modules to avoid conflicts.
  Apply relevant recipes to V3 and export config.
  _Done when: Applicable recipes are applied; `ddev drush cr` shows no errors._

---

## Epic 3 — Custom Module Parity

> Sync all custom module improvements from V4 → V3, and port V3-only modules (`duccinis_archive`) into V4.

### Issues

#### `store_fulfillment` — V4 improvements to backport into V3

- [ ] **#3.1 — Port `DeliveryAddress` checkout pane to V3**
  V4 introduced a standalone `DeliveryAddress` checkout pane (weight 5) that owns billing for delivery orders.
  Copy `web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/DeliveryAddress.php` and update V3's checkout flow config.
  Update `FulfillmentTime::ajaxRefreshPane()` to return an `AjaxResponse` with two `ReplaceCommand`s (fulfillment-time-wrapper AND delivery-address-wrapper).
  _Done when: Delivery address pane renders/hides via AJAX on V3 checkout._

- [ ] **#3.2 — Port `OrderPlacementDeliveryRadiusValidator::resolveDeliveryAddress()` to V3**
  V4 fixed the validator to fall back: `delivery_address_profile` → shipments → billing profile → customer default profile, preventing `\InvalidArgumentException` after payment on delivery orders.
  Apply the same fallback chain to V3's `OrderPlacementDeliveryRadiusValidator`.
  _Done when: Delivery order placement no longer throws exception when no shipping profile exists._

- [ ] **#3.3 — Port `FulfillmentTime::resolveCustomerAddress()` improvements to V3**
  V4 added `resolveCustomerAddress()` that checks `delivery_address_profile` first (instead of only shipments).
  Also: `isVisible()` now falls back to `$this->order->getStore()` when store resolver has no cookie context.
  Apply both changes to V3.
  _Done when: `ddev phpunit --filter FulfillmentTime` passes._

- [ ] **#3.4 — Port `#after_build` fix for payment radios to V3**
  V3's `store_fulfillment.module` likely still uses `$radios['#process'][]` which causes zero child elements.
  V4 changed this to `$radios['#after_build'][]` and renamed the callback to `store_fulfillment_payment_radios_after_build()`.
  Apply the same fix to V3's `store_fulfillment.module`.
  _Done when: Saved-card radio list renders child elements on V3 checkout._

- [ ] **#3.5 — Port `hook_form_alter` billing-removal logic to V3**
  V4 adds `store_fulfillment_form_alter()` to remove `billing_information` from the `PaymentInformation` pane when delivery is selected (the `DeliveryAddress` pane owns billing for delivery orders).
  Port this to V3's `store_fulfillment.module`.
  _Done when: No duplicate billing form appears for delivery orders._

- [ ] **#3.6 — Verify V3 `store_fulfillment.services.yml` parity**
  V4 defines three services: `order_validator`, `delivery_radius_calculator`, `delivery_radius_validator`.
  Ensure V3 registers all three with identical constructor injection patterns.
  _Done when: `ddev drush php-eval "print_r(\Drupal::service('store_fulfillment.delivery_radius_validator'));"` works._

#### `duccinis_archive` — Port V3 → V4

- [ ] **#3.7 — Copy `duccinis_archive` module from V3 → V4**
  This module exists only in V3. It provides:
  - "Archived" flag on Commerce products
  - Auto-unpublish of archived products from menu views
  - Audit logging (`ArchiveAuditLogger`)
  - `archive-controls` SDC component
  - Event subscriber, controller, routing
  Copy the full module directory and verify dependencies are met.
  _Done when: Module enables cleanly on V4 (`ddev drush en duccinis_archive`)._

- [ ] **#3.8 — Port `archive-controls` SDC component from V3 theme → V4 theme**
  The V3 `duccinis_1984_olympics` theme includes an `archive-controls` SDC component; V4 does not.
  Copy `components/archive-controls/` to the V4 theme and rebuild assets.
  _Done when: `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` compiles without errors._

- [ ] **#3.9 — Export `duccinis_archive` config and add to `config/sync/`**
  After enabling the module, run `ddev drush cex` to capture any install config.
  _Done when: All `duccinis_archive.*` YMLs committed to `config/sync/`._

#### Minor module cleanups (V3)

- [ ] **#3.10 — Remove `payment-ajax-diagnostic` library from V3 `libraries.yml`**
  The diagnostic library is still registered in V3's theme `libraries.yml` and has a JS file in `src/js/diagnostics/`.
  Remove the library definition and delete the diagnostic JS file.
  _Done when: `grep -r "payment-ajax-diagnostic" web/themes/custom/duccinis_1984_olympics/` returns nothing._

---

## Epic 4 — Theme Migration

> Copy `duccinis_1984_olympics` from V3 into V4 wholesale. The goal is **identical theme output** — same Twig templates, same SCSS/JS, same SDC components — running on the DrupalCMS 2 codebase. No theme replacement is planned.
>
> Note: V3 also has a legacy unused `duccinis_theme` directory (predecessor to `duccinis_1984_olympics`). That predecessor is already absent from V4 and does not need to be copied.

### Issues

- [ ] **#4.1 — Copy the full `duccinis_1984_olympics` theme from V3 → V4**
  Copy the entire `web/themes/custom/duccinis_1984_olympics/` directory from V3 into V4.
  This includes all Twig templates, SCSS source, JS behaviors, SDC components (`saved-card`, `checkout-progress`, `archive-controls`), theme includes (`form.theme`, `commerce.theme`, etc.), and `webpack.mix.js`.
  Do NOT copy the `build/` output directory — it will be regenerated.
  _Done when: `diff -rq --exclude="build" web/themes/custom/duccinis_1984_olympics` between V3 and V4 returns no differences._

- [ ] **#4.2 — Install npm dependencies and rebuild theme assets in V4**
  After copying the theme, install npm packages and compile CSS/JS:
  ```bash
  ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm ci && npm run dev"
  ```
  Confirm `build/css/main.style.css` and `build/js/main.script.js` match V3's compiled output.
  _Done when: Build completes with zero errors and the V4 checkout looks identical to V3._

- [ ] **#4.3 — Remove `payment-ajax-diagnostic` library from theme**
  The diagnostic library (`payment-ajax-diagnostic`) is still registered in V3's `libraries.yml` and has a JS file at `src/js/diagnostics/payment-ajax-diagnostic.js`. It should not be carried forward into V4.
  After copying the theme: delete the diagnostic JS file and remove its library definition.
  _Done when: `grep -r "payment-ajax-diagnostic" web/themes/custom/duccinis_1984_olympics/` returns nothing._

- [ ] **#4.4 — Remove unused legacy `duccinis_theme` from V3 (V3-only cleanup)**
  Strictly a V3 housekeeping task. `duccinis_theme` is the old predecessor to `duccinis_1984_olympics` still sitting on disk in V3 but not active.
  Uninstall it (`ddev drush theme:uninstall duccinis_theme`) and delete its directory.
  _Done when: `ls web/themes/custom/` in V3 returns only `duccinis_1984_olympics`._

- [ ] **#4.5 — Verify theme config in `core.extension.yml` and block placements**
  Ensure V4's `config/sync/system.theme.yml` names `duccinis_1984_olympics` as the default theme.
  Copy V3's `config/optional/block.block.*.yml` placements into V4 `config/sync/` (or `config/optional/`).
  Run `ddev drush cim -y && ddev drush cr`.
  _Done when: V4 frontend renders with `duccinis_1984_olympics` active and all blocks in place._

---

## Epic 5 — Checkout Flow & Configuration Parity

> Ensure the V3 checkout flow config exactly matches V4's improved structure.

### Issues

- [ ] **#5.1 — Update V3 checkout flow YAML to add `delivery_address` pane**
  V4's `config/sync/commerce_checkout.commerce_checkout_flow.default.yml` includes both `fulfillment_time` (weight 2) and `delivery_address` (weight 5) panes.
  Copy the updated YAML to V3 and run `ddev drush cim -y && ddev drush cr`.
  _Done when: Both panes appear in the V3 checkout `order_information` step._

- [ ] **#5.2 — Verify `stripe_review` pane on V3 `review` step**
  Stripe PE is an offsite gateway — card entry lives on the `review` step via the `stripe_review` pane, not on `order_information`.
  Confirm V3's checkout flow YAML includes this pane and the Stripe PE module is correctly configured.
  _Done when: V3 review step shows the Stripe Payment Element iframe._

- [ ] **#5.3 — Reconcile `store_fulfillment.settings.yml` values**
  V3 has: `minimum_advance_notice: 30`, `maximum_scheduling_window: 14`, `asap_cutoff_before_closing: 15`, `time_slot_interval: 15`.
  Verify these match V4 or document any intentional differences.
  _Done when: Both sites have identical settings or a documented rationale for divergence._

- [ ] **#5.4 — Export all V3 config after changes and commit to `config/sync/`**
  Run `ddev drush cex` after every change in this epic.
  _Done when: Git shows no unstaged config changes._

---

## Epic 6 — AI Module Stack Evaluation

> V3 has a full AI module stack (`drupal/ai`, `ai_agents`, `ai_provider_anthropic`, `ai_provider_openai`, `ai_image_alt_text`) not present in V4. Decide whether to retain, expand, or remove.

### Issues

- [ ] **#6.1 — Audit current AI module usage in V3**
  Identify which AI features are actually in use:
  - Are any ECA automations using AI services?
  - Is `ai_image_alt_text` generating alt text for media?
  - Are `ai_agents` configured?
  Check `eca.eca.*` YMLs and `/admin/config/ai` in V3.
  _Done when: Usage audit documented._

- [ ] **#6.2 — Decide: retain AI stack in V4 or remove from V3**
  If AI features are valuable, port the configuration and module dependencies to V4.
  If unused, remove the AI modules from V3's `composer.json` and config.
  _Done when: A decision is documented and implemented._

- [ ] **#6.3 — If AI retained: secure AI provider API keys**
  Anthropic and OpenAI API keys must NOT be committed to config.
  Move to `settings.php` `$config` overrides following the same pattern as Stripe keys.
  _Done when: No API keys appear in `config/sync/` YMLs._

---

## Epic 7 — Testing

> Bring the test suite to full parity between V3 and V4, add missing tests for ported modules.

### Issues

- [ ] **#7.1 — Run V4 test suite against V3 codebase**
  After porting `store_fulfillment` improvements: `ddev phpunit`
  All existing tests must pass: `OrderValidatorTest`, `DeliveryRadiusValidatorTest`, `OrderPlacementDeliveryRadiusValidatorTest`, `CheckoutHeaderBlockTest`, `CheckoutProgressBarBlockTest`, `DeliveryRadiusCheckoutTest`, `PaymentPaneFormAlterTest`.
  _Done when: `ddev phpunit` exits 0 with all tests green on V3._

- [ ] **#7.2 — Write Kernel tests for `duccinis_archive` module**
  The V3 `duccinis_archive` module has no PHPUnit tests.
  Write at minimum:
  - `ArchiveProductTest` — test that archiving unpublishes product and hides from views
  - `ArchiveAuditLogTest` — test that audit log entries are created
  Place in `web/modules/custom/duccinis_archive/tests/src/Kernel/`.
  _Done when: Tests pass with `ddev phpunit --filter Archive`._

- [ ] **#7.3 — Add functional test for `DeliveryAddress` pane**
  V4 has `DeliveryRadiusCheckoutTest` for radius validation; add a complementary test covering:
  - Delivery address pane hides when pickup is selected
  - Delivery address pane shows when delivery is selected via AJAX
  - "Billing same as delivery" checkbox syncs addresses
  _Done when: Test added and passes._

- [ ] **#7.4 — Run PHPStan and PHPCS on all custom modules**
  ```bash
  ddev exec phpstan analyze web/modules/custom/
  ddev exec phpcs --standard=Drupal web/modules/custom/
  ```
  Fix any reported errors.
  _Done when: Both commands exit with zero errors._

---

## Epic 8 — Feeds & Product Data Migration

> Ensure the Feeds configuration and product catalog can be cleanly imported into V4.

### Issues

- [ ] **#8.1 — Compare V3 and V4 Feeds feed type configs**
  Both sites have 21 `feeds.feed_type.*` YMLs for per-category product/variation imports.
  Diff V3 vs V4 feed configs and reconcile any divergence.
  _Done when: All 21 feed types are identical between sites._

- [ ] **#8.2 — Export and validate V3 product catalog as CSV**
  Run the V3 feeds export (or use Views data export) to produce CSVs for all product categories.
  Validate column headers match the V4 feed importer expectations.
  _Done when: CSVs produced and validated against V4 feed type field mappings._

- [ ] **#8.3 — Test product import into V4 via Feeds**
  Import the V3 CSVs into V4's fresh database using `ddev drush feeds:import`.
  Verify all product types, variation attributes, and pricing import correctly.
  _Done when: V4 `/menu` page shows all V3 products with correct variations._

- [ ] **#8.4 — Validate `duccinis_feeds_fix` module parity**
  Both V3 and V4 have `duccinis_feeds_fix` but with potentially different service registrations.
  Compare `duccinis_feeds_fix.services.yml` between sites and sync.
  _Done when: Multi-variation CSV imports work end-to-end on V4._

---

## Epic 9 — Cleanup & Repository Hygiene

> Remove cruft, debug artifacts, and dead code that accumulated in V3, and establish clean conventions for the migrated V4 site.

### Issues

- [ ] **#9.1 — Remove all `output`, `output2`, `output3`, `logs/` directories from V3 repo**
  These appear to be debug artifact directories at the V3 root.
  Delete and add to `.gitignore`.
  _Done when: `ls` at V3 root shows none of these directories._

- [ ] **#9.2 — Remove migration documentation Markdown files from V3 repo**
  Files like `DRUPALCMS2_MIGRATION_RUNBOOK.md`, `EPIC_1_IMPLEMENTATION_SUMMARY.md`, `PR4_STORE_HOURS_SUMMARY.md`, `STORE_HOURS_FIELD_VERIFICATION.md`, `STORE_MODULES_STATUS.md`, `ISSUES_AJAX.md`, `ISSUES_PROGRESS_BAR.md` are in-progress notes that shouldn't live in the project root.
  Move useful content to a `docs/` directory or this `ISSUES.md`; delete the rest.
  _Done when: Project root contains only standard Drupal project files._

- [ ] **#9.3 — Remove `payment-ajax-diagnostic` library and JS from V3 theme**
  The diagnostic JS is confirmed still registered in `libraries.yml` in V3.
  Remove the library entry and delete `src/js/diagnostics/payment-ajax-diagnostic.js`.
  _Done when: `grep -r "diagnostic" web/themes/custom/duccinis_1984_olympics/` returns nothing._

- [ ] **#9.4 — Prune stale git branches**
  Both V3 and V4 have accumulated many `issue/*`, `fix/*`, `copilot/*` branches.
  Review and delete merged/abandoned branches.
  _Done when: `git branch -r | wc -l` is below 10 on both repos._

- [ ] **#9.5 — Consolidate V3 onto a single `main` branch aligned with V4 `master`**
  After all Epic changes are applied, merge V3's work into a clean `main` branch.
  Tag the release (e.g. `v3.0.0-pre-migration` before changes; `v4.0.0` after).
  _Done when: `git log --oneline -5` on `main` shows the migration completion commit._

---

## Epic 10 — Store Data & Content Migration

> Migrate live store configurations, hours, and any static content pages from V3 into V4's database.

### Issues

- [ ] **#10.1 — Export V3 store entity data**
  Export the three store entities (Adams Morgan DC, Arlington VA, 7th Street NW) with their `delivery_radius`, `store_location` (lat/lon), and store hours fields.
  Options: `ddev drush dce` (default content), or manual `drush sql-query` export.
  _Done when: Store data is in a format importable to V4._

- [ ] **#10.2 — Verify V4 store field config matches V3**
  Both sites should have `delivery_radius` (decimal) and `store_location` (geofield) on the store entity.
  Run field config diff; add missing fields to V4 if any.
  _Done when: `ddev drush field:info commerce_store` shows identical fields on V4._

- [ ] **#10.3 — Import store data into V4**
  Import the three store entities into V4 via default content or manual DB insert.
  Verify store coordinates match (Adams Morgan: ~38.9216° N, 77.0428° W, etc.).
  _Done when: V4 `/admin/commerce/stores` shows all 3 stores with correct radii and coordinates._

- [ ] **#10.4 — Migrate static content nodes (Menu page, etc.) from V3 → V4**
  V3 has a `/menu` content node that product variation Views attach to.
  Create matching node(s) in V4 with the same paths.
  _Done when: `/menu` returns 200 on V4 with product variation blocks rendering._

---

## Issue Priorities & Suggested Implementation Order

| Priority | Epic | Rationale |
|---|---|---|
| **P0 — Do First** | Epic 1 (Security) | Credentials and PII must be secured before any other work |
| **P0 — Do First** | Epic 2 (Dependencies) | Infrastructure must be stable before code changes |
| **P1 — Core** | Epic 3 (Custom Modules) | Business logic is the bulk of migration value |
| **P1 — Core** | Epic 5 (Checkout Config) | Checkout flow drives revenue; must work before go-live |
| **P2 — Important** | Epic 4 (Theme) | UX polish and diagnostic cleanup |
| **P2 — Important** | Epic 10 (Store Data) | Required for live site |
| **P3 — Standard** | Epic 7 (Testing) | Quality gate before go-live |
| **P3 — Standard** | Epic 8 (Feeds/Products) | Import pipeline validation |
| **P4 — Low** | Epic 6 (AI) | Optional enhancement |
| **P4 — Low** | Epic 9 (Cleanup) | Post-migration housekeeping |

---

_Last updated: March 10, 2026_
