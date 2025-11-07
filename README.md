# Drupal CMS

Drupal CMS is a fast-moving open source product that enables site builders to easily create new Drupal sites and extend them with smart defaults, all using their browser.

## Getting started

If you want to use [DDEV](https://ddev.com) to run Drupal CMS locally, follow these instructions:

1. Install DDEV following the [documentation](https://ddev.com/get-started/)
2. Open the command line and `cd` to the root directory of this project
3. Run the following commands:
```shell
ddev config --project-type=drupal11 --docroot=web
ddev start
ddev composer install
ddev composer drupal:recipe-unpack
ddev launch
```

Note: When running PHP commands inside DDEV, prefix them with "ddev". For example:

```bash
ddev php -v
# Run Drupal core scripts
ddev php web/core/scripts/drupal list
# Drush examples
ddev drush status
# Or explicitly via PHP binary
ddev php vendor/bin/drush status
```

Drupal CMS has the same system requirements as Drupal core, so you can use your preferred setup to run it locally. [See the Drupal User Guide for more information](https://www.drupal.org/docs/user_guide/en/installation-chapter.html) on how to set up Drupal.

### Installation options

The Drupal CMS installer offers a list of features preconfigured with smart defaults. You will be able to customize whatever you choose, and add additional features, once you are logged in.

After the installer is complete, you will land on the dashboard.

## Documentation

Coming soon ... [We're working on Drupal CMS specific documentation](https://www.drupal.org/project/drupal_cms/issues/3454527).

In the meantime, learn more about managing a Drupal-based application in the [Drupal User Guide](https://www.drupal.org/docs/user_guide/en/index.html).

### Feeds Importers (Products and Variations)

This project ships with Feeds importer configurations exported into `web/sites/default/files/sync`:
- `feeds.feed_type.product_variation_importer.yml` — imports Commerce product variations and automatically creates parent products when missing.
- `feeds.feed_type.product_importer.yml` — helper/legacy, not required when using the variation importer below.

Known fixes applied:
- Variation importer now autocreates Products when a referenced title does not exist (bundle: `default`).
- The sample CSV at `html_references/product_variation.csv` has been normalized so the `price__number` column contains numeric values (no leading `$`), which satisfies Drupal Commerce price field validation.

Before you import via Feeds (one-time per environment):
1. Import the configuration so Product Attributes and their Values are available in the active config.
   - Using DDEV: `ddev drush cim -y && ddev drush cr`
   - Or inside the web container: `ddev ssh -s web` then run `scripts/import-config.sh`
2. Verify attributes and values are present: Admin → Commerce → Product attributes, then open any attribute (e.g., Wing quantity) and confirm values are listed.

How to run the import:
1. Ensure a Commerce Store exists (Commerce > Configuration > Stores). The default install provides one “Online” store.
2. Go to Manage > Structure > Feeds > Product Variation Importer.
3. Upload `html_references/product_variation.csv` and import.
   - Mappings in the importer expect the following CSV headers: `sku,title,field_food_menu_item,product_id,body,price__number`.
   - `product_id` column contains the Product title (e.g., "Coke"). If a Product with that title doesn’t exist, it will be created automatically in the `default` bundle.
   - Price is imported into the Commerce price field using currency USD.

Notes:
- Importing Products via the separate “Product Importer” is not necessary and will fail unless you also map required fields (Stores and Variations). The recommended workflow is to import Variations only; Products will be created automatically.
- The Product Variation importer now automatically strips currency symbols and other non-numeric characters from `price__number` via a Feeds Tamper (Regex) configuration included in this repo. You can keep "$" or commas in your CSV; they’ll be removed during import.

### Troubleshooting
- Error: "Missing bundle for entity type commerce_product"
  - What it means: Drupal is trying to create/load a Commerce Product without a valid bundle (Product type). This usually happens when the Product type wasn’t actually created in the active configuration yet, or when the variation’s Product reference field doesn’t declare which Product bundles can be referenced/created.
  - Fix (safe sequence):
    1. Import the configuration and rebuild caches so Commerce picks up bundles and plugins.
       - Drush: `drush cim -y && drush cr`
       - Admin UI: Configuration → Configuration synchronization → Import all. Then clear caches.
    2. Verify modules are enabled via configuration: this repo includes `core.extension.yml` under `web/sites/default/files/sync` which enables Feeds, Commerce, Commerce Product, Commerce Store, and Commerce Price. After import, check Extend to confirm these are enabled.
    3. Verify bundles exist:
       - Commerce → Configuration → Product types: you should see a Product type named "Default" (machine name `default`) and its variation type set to "Default".
       - Commerce → Configuration → Product variation types: ensure a variation type with machine name `default` exists.
    4. Verify the variation → product reference allows the `default` bundle:
       - This repo now includes a base field override at `core.base_field_override.commerce_product_variation.default.product_id.yml` that sets `target_bundles: { default }` for the Product reference. This allows Feeds to auto‑create the parent Product in the correct bundle when it doesn’t exist.
    5. Clear caches once more, then run the Product Variation importer again.
  - Why this repo change helps: We added explicit configuration dependencies and a base field override so that the Product Type and Variation Type are registered, and the variation’s Product reference knows which bundle to use for autocreation. This prevents partial/incorrect imports where the bundle wouldn’t be registered yet.

## Contributing

Drupal CMS is developed in the open on [Drupal.org](https://www.drupal.org). We are grateful to the community for reporting bugs and contributing fixes and improvements.

[Report issues in the queue](https://drupal.org/node/add/project-issue/drupal_cms), providing as much detail as you can. You can also join the #drupal-cms-support channel in the [Drupal Slack community](https://www.drupal.org/slack).

Drupal CMS has adopted a [code of conduct](https://www.drupal.org/dcoc) that we expect all participants to adhere to.

To contribute to Drupal CMS development, see the [drupal_cms project](https://www.drupal.org/project/drupal_cms).

## License

Drupal CMS and all derivative works are licensed under the [GNU General Public License, version 2 or later](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

Learn about the [Drupal trademark and logo policy here](https://www.drupal.com/trademark).


### Additional Troubleshooting: Price field validation during import
- Symptom: Feeds importer reports "price.0.number: This value is not valid." or your DB logs show errors like "Incorrect decimal value: '$1.29' for column commerce_product_variation_field_data.price__number".
- What we changed in this repo to unblock imports:
  - Configured the Product Variation importer to parse all CSV rows by setting `parser_configuration.line_limit: -1`.
  - Temporarily disabled strict entity validation during import by setting `processor_configuration.skip_validation: true` for the variation importer.
  - Added and preconfigured a Feeds Tamper (Regex) on source `price__number` to strip all non-numeric characters (pattern `/[^0-9\.]/`), so currency symbols and commas are removed automatically.
- Why: Some source files include currency symbols (e.g., "$1.29"). Commerce requires a plain decimal (e.g., `1.29`) in `price__number`. Feeds Tamper removes non-numeric characters during import so both formats work.
- How to proceed on your site:
  1. Install config and clear caches: `ddev drush cim -y && ddev drush cr`.
  2. No manual Tamper setup is required — it is included in the configuration exported in this repository.
  3. Run the Product Variation importer with `html_references/product_variation.csv` (or your own CSV).
  4. Spot-check several variations to confirm their Price is saved correctly in USD.
  5. Optional: Re-enable validation by editing `web/sites/default/files/sync/feeds.feed_type.product_variation_importer.yml` and setting `skip_validation: false`, then re-import config.
- If you still see issues:
  - Ensure prices are plain decimals (no currency symbols). If overriding this repo’s config, keep the Tamper in place to normalize them automatically.


### Composer note: Feeds Tamper version and minimum-stability
- Symptom: Running `ddev composer require 'drupal/feeds_tamper:^2.0@beta' -W` or `ddev composer require 'drupal/feeds_tamper:dev-2.x' -W` fails with a message like:
  - drupal/feeds_tamper 2.x requires drupal/tamper ^1.0-alpha3, which does not match your minimum-stability.
- Reason: This project uses `"minimum-stability": "stable"` (see composer.json). Feeds Tamper 2.x currently depends on `drupal/tamper` at alpha stability, which Composer will not install automatically under a stable-only policy.
- What to do (recommended): Use the latest stable Feeds Tamper 1.x release, which works with this project and Commerce/Feeds:
  - `ddev composer require drupal/feeds_tamper:^1.0`
  - Note: This repository already requires `drupal/feeds_tamper:^1.0` and enables the module via configuration, so you typically do not need to run the command above.
- Advanced (not recommended unless you know why you need Feeds Tamper 2.x): You can explicitly allow the alpha dependency by adding it at the root, which bypasses the global stability restriction for that package only:
  - `ddev composer require drupal/feeds_tamper:2.0.0-beta4 drupal/tamper:^1.0@alpha -W`
  - Be aware that introducing alpha/beta packages may increase maintenance risk. Keep `prefer-stable: true` and test thoroughly.
