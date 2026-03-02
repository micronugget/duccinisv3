---
description: "Use when writing or modifying files in the duccinis_1984_olympics theme — Twig templates, SCSS, SDC components, JS behaviors, preprocess functions, and library definitions. Covers the build pipeline, the #after_build ↔ Twig variable chain for saved-card display, and the CSS-only :checked selection state pattern."
applyTo: "web/themes/custom/duccinis_1984_olympics/**"
---

# duccinis_1984_olympics Theme — Coding Patterns

## Build Pipeline

The theme uses **Laravel Mix (webpack.mix.js)** with Sass. **Never edit files
in `build/` directly** — they are generated output.

> **Known issue — NVM:** `ddev npm run dev` (from project root) fails because the
> root `package.json` has no `dev` script. Always build from inside the theme
> directory. The `.nvmrc` is pinned to `22`; a DDEV `post-start` hook installs
> that version via nvm so it resolves correctly after container restarts.

```bash
# Compile once (cd into theme inside DDEV)
ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev" 2>&1 | tail -20

# Watch mode
ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run watch"
```

After editing any `.scss` or component `.js` file, run the compile command above and
**also run `ddev drush cr`** — Drupal caches the SDC asset manifest and library
definitions. Without a cache clear the old compiled CSS is served.

### What compiles what

| Source | Output |
|---|---|
| `src/scss/main.style.scss` | `build/css/main.style.css` |
| `src/js/main.script.js` | `build/js/main.script.js` |
| `components/**/*.scss` | `components/**/*.css` (same path, `.css` extension) |
| `components/**/_*.js` (underscore-prefixed) | `components/**/*.js` (underscore stripped) |

SDC component CSS is compiled from `components/<name>/<name>.scss` to
`components/<name>/<name>.css` by the glob loop in `webpack.mix.js`. The `.css`
file is what Drupal reads from the SDC manifest — never import component SCSS
from `main.style.scss`.

---

## Library Definitions (`duccinis_1984_olympics.libraries.yml`)

When adding a new JS file, define a library entry before attaching it:

```yaml
my-feature:
  js:
    src/js/my-feature.js: {}
  dependencies:
    - core/drupal
    - core/drupalSettings   # only if you read drupalSettings in JS
    - core/jquery           # only if you use $()
    - core/once             # required when using once()
```

Attach from PHP:

```php
// From hook_form_alter or a preprocess:
$form['#attached']['library'][] = 'duccinis_1984_olympics/my-feature';

// SDC component CSS is loaded via the SDC library name, NOT libraries.yml:
$form['#attached']['library'][] = 'core/components.duccinis_1984_olympics--saved-card';
```

---

## `includes/` PHP Preprocess Files

Preprocess functions live in `includes/*.theme` files (loaded automatically by
`duccinis_1984_olympics.theme`). Keep one file per Drupal hook family — e.g.,
`form.theme` for all form-related preprocesses.

Do **not** add preprocess logic directly to `duccinis_1984_olympics.theme`.

---

## The `#after_build` → Preprocess → Twig Variable Chain

The saved-card display requires three cooperating pieces. Understand this
chain before modifying any part of it.

### Step 1 — `store_fulfillment_payment_radios_after_build()` (PHP, module)

Runs after `Radios::processRadios()` has created individual `<input>` children.
Stamps metadata onto each child element:

```php
// For stored payment method radios (numeric option_id):
$element[$opt_id]['#card_data'] = [
  'brand'    => 'visa',
  'last4'    => '4242',
  'expMonth' => '03',
  'expYear'  => '2033',
];
$element[$opt_id]['#attributes']['class'][] = 'saved-card__radio';
$element[$opt_id]['#attributes']['class'][] = 'visually-hidden';

// For the gateway "Use a different card" option (non-numeric option_id):
$element[$opt_id]['#is_new_card_option'] = TRUE;
$element[$opt_id]['#attributes']['class'][] = 'saved-card__radio';
$element[$opt_id]['#attributes']['class'][] = 'visually-hidden';
```

### Step 2 — `duccinis_1984_olympics_preprocess_form_element()` (PHP, `includes/form.theme`)

Reads the stamped properties and promotes them to top-level Twig variables:

```php
function duccinis_1984_olympics_preprocess_form_element(array &$variables): void {
  $element = $variables['element'];

  if (!empty($element['#card_data'])) {
    $variables['card_data']   = $element['#card_data'];
    $variables['element_id']  = $element['#id'] ?? NULL;
  }
  elseif (!empty($element['#is_new_card_option'])) {
    $variables['is_new_card'] = TRUE;
    $variables['element_id']  = $element['#id'] ?? NULL;
  }
}
```

`element_id` is the `id` attribute of the hidden `<input>` — it is used in the
Twig template as `for="{{ element_id }}"` on the `<label>` to link them.

### Step 3 — `templates/form/form-element--radio.html.twig`

Renders three distinct layouts based on which variables are defined:

```twig
{% if card_data is defined %}
  {# Saved card row — brand badge, masked number, expiry #}
  <div class="saved-card-item">
    {{ children }}  {# <-- the visually-hidden <input type="radio"> #}
    <label for="{{ element_id }}" class="saved-card"> … </label>
  </div>

{% elseif is_new_card is defined %}
  {# "+ Use a different card" row #}
  <div class="saved-card-item saved-card-item--new">
    {{ children }}
    <label for="{{ element_id }}" class="saved-card__new-link"> … </label>
  </div>

{% else %}
  {# All other radios — fall through to Radix component #}
  {% include 'radix:form-element--radiocheckbox' … %}
{% endif %}
```

**Critical constraint:** `{{ children }}` renders the `<input type="radio">`.
It **must come before** the `<label>` in the DOM. The entire selection state
is driven by the CSS adjacent-sibling selector:

```scss
.saved-card__radio:checked + .saved-card { … }
```

If you reorder `{{ children }}` after the `<label>`, the `:checked` CSS rule
stops working and the selected state disappears with no JS error to debug.

---

## CSS-Only `:checked` Selection State — Rules

The saved-card selected state uses **no JavaScript**. These CSS patterns make
it work:

```scss
// Correct: radio immediately precedes the label (.saved-card or .saved-card__new-link).
.saved-card__radio:checked + .saved-card {
  border-color: $olympics-blue;
  background: lighten($olympics-blue, 48%);

  .saved-card__check {
    opacity: 1;
  }
}

// Correct: new-card link active state.
.saved-card__radio:checked + .saved-card__new-link {
  color: $olympics-magenta;
  font-weight: 600;
}
```

**Rules to preserve this pattern:**

1. Never insert any element between `{{ children }}` and `<label>` in the Twig template.
2. `visually-hidden` (not `display:none` or `visibility:hidden`) must be used on `<input>` — the element must be focusable and occupy layout space for the adjacent-sibling selector to work in all browsers.
3. Do not add a wrapper `<div>` between the `<input>` and `<label>`.

---

## `saved-card-fix.js` — When to Modify

`saved-card-fix.js` (library `duccinis_1984_olympics/saved-card-fix`) handles
one specific edge case: when the Stripe gateway radio is **already checked** and
the user clicks the "+ Use a different card" label, no native `change` event
fires (nothing changed), so Drupal's AJAX handler never runs.

The behavior uses `once()` to avoid double-attaching:

```js
Drupal.behaviors.savedCardNewCardLink = {
  attach(context) {
    once('saved-card-new-link', '.saved-card-item--new label', context).forEach(
      function (label) {
        label.addEventListener('click', function () {
          const radio = document.getElementById(label.getAttribute('for'));
          if (radio && radio.checked) {
            $(radio).trigger('change'); // force jQuery change for Drupal AJAX
          }
        });
      },
    );
  },
};
```

This file is attached from `store_fulfillment_form_alter()` — it is **not**
auto-loaded. Do not move this responsibility to a Twig template or a global
library attachment.

### `drupalSettings` dependency

`saved-card-fix.js` lists `core/drupalSettings` as a dependency in
`libraries.yml`. This is required — removing it changes JS asset load order and
may cause the behavior to attach before Drupal AJAX bindings are registered.

---

## SDC Component Conventions (`components/`)

Each SDC lives in `components/<name>/` and requires:

| File | Purpose |
|---|---|
| `<name>.component.yml` | Schema definition (props, slots) |
| `<name>.twig` | Component template |
| `<name>.scss` | Component styles (compiled to `<name>.css`) |

The `$schema` key in `.component.yml` must point to the SDC metadata schema:

```yaml
$schema: https://git.drupalcode.org/project/sdc/-/raw/1.x/src/metadata.schema.json
name: My Component
status: experimental
```

To load a component's compiled CSS from a form alter or preprocess:

```php
$form['#attached']['library'][] = 'core/components.duccinis_1984_olympics--saved-card';
// Pattern: core/components.<theme_name>--<component-name>
```

After creating or renaming a component, run `ddev drush cr` to regenerate the
SDC discovery cache.

---

## Layout: Bootstrap 5 Grid — Not Custom CSS Grid

**Use Bootstrap 5 `row`/`col-*` utilities in Twig templates for all page-level layout.** Do not write custom `display: grid` rules in SCSS as a substitute.

### Why
Commerce ships `commerce_checkout.form.css` that applies `float: left; width: 65%; padding-right: 2em` to `.layout-region-checkout-main` at 780px+. Our theme also applies `.commerce-checkout-flow .layout-region { padding: 2rem }`. A custom CSS Grid rule stacks on top of both — it does not replace them. The result is compounding dead whitespace (gap + region padding + float padding ≈ 6rem) that requires ever-more overrides to fix.

### The right approach
Override the Twig template to emit Bootstrap markup directly:

```twig
{# commerce-checkout-form--with-sidebar.html.twig #}
<div class="layout-checkout-form">
  <div class="row g-4 align-items-start">
    <div class="col-12 col-lg-8 layout-region layout-region-checkout-main">
      {{ form|without('sidebar', 'actions') }}
    </div>
    <div class="col-12 col-lg-4 layout-region layout-region-checkout-secondary">
      {{ form.sidebar }}
    </div>
  </div>
  <div class="layout-region layout-region-checkout-footer">
    {{ form.actions }}
  </div>
</div>
```

**Rules:**
1. Use `col-lg-*` (992px breakpoint) for the sidebar layout — `col-md-*` at 720px inner width is too narrow for a sidebar.
2. Use `g-3` or `g-4` for gutters — never add compensating `padding`/`margin` to SCSS.
3. Retain `layout-region` and `layout-region-checkout-*` classes so Commerce JS and contrib modules that target them continue to work.
4. If overriding Commerce layout templates, keep `layout-checkout-form` on the outer wrapper for the same reason.

---

## Overriding Drupal Core/contrib Libraries

The theme overrides several core libraries (e.g., `drupal.ajax`, `drupal.dialog`)
by declaring library entries in `libraries.yml` that use the **same machine names**
as the core libraries. These overrides live in `src/js/overrides/`.

When editing an override file, test that the original Drupal behavior still
works — these files completely replace the corresponding core JS.

---

## Cache Clearing Requirements

| Change | Required commands |
|---|---|
| Any `.twig` template edit | `ddev drush cr` |
| New or renamed preprocess function | `ddev drush cr` |
| New `*.libraries.yml` entry | `ddev drush cr` |
| New SDC component or `.component.yml` change | `ddev drush cr` |
| `.scss` / `.js` source change | `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` then `ddev drush cr` |
| `#attached['library']` added in PHP | `ddev drush cr` |
