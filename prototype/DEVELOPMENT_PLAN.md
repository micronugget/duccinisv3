# Development Plan: Menu Complete Development Display with Accordion Grouping

## Project Overview
Transform the 'Menu complete development' Views Display to group products by type in a mobile-friendly Bootstrap 5 accordion interface, matching the pattern shown in `prototype/menu-accordion-prototype.html`.

## Current State Analysis

### Views Configuration
- **File**: `config/sync/views.view.product_variations.yml`
- **Display**: `menu_complete_development` (Menu complete development)
- **Current behavior**: Uses all defaults from base display
- **Base display grouping**: Groups by Product Title (`title_1` field) - line 408-410
- **Fields displayed**:
  - SKU (excluded)
  - Product Title (`title_1` - excluded, used for grouping)
  - Variation Title
  - Price
  - Add to Cart link

### Issue Identified
The current base display groups variations by individual Product Title, which creates one group per product (e.g., "Italian Cheese Steak", "Cheese Steak [Rib-Eye]"). We need to group by Product Type instead (e.g., "Fresh Submarines", "Beverages", "Pizza") to create category-level accordion sections.

### Product Types Available
Based on dependencies in the config:
- beverages
- chicken_wings (not yet in config but implied)
- desserts
- famous_stromboli
- fresh_submarines
- gyro
- homemade_pasta
- menu_extras
- pizza
- salad
- side_orders
- specials

## Proposed Solution

### Phase 1: Views Configuration Changes (Primary Approach)

#### 1.1 Add Product Type Field to Display
Add a new field to expose the Product Type (bundle) for grouping purposes.

**Configuration to add** (in `menu_complete_development` display overrides):
```yaml
display_options:
  defaults:
    fields: false
    style: false
  fields:
    type_1:
      id: type_1
      table: commerce_product_field_data
      field: type
      relationship: product_id
      entity_type: commerce_product
      entity_field: type
      plugin_id: field
      label: ''
      exclude: true
      element_type: ''
      element_class: ''
      element_wrapper_type: ''
    [... copy remaining fields from default ...]
```

#### 1.2 Configure Grouping by Product Type
Update the style configuration to group by Product Type instead of Product Title.

**Configuration change**:
```yaml
style:
  type: default
  options:
    grouping:
      -
        field: type_1
        rendered: true
        rendered_strip: false
    row_class: 'views-row'
    default_row_class: true
```

**Why `rendered: true`**: This will display the human-readable label (e.g., "Fresh Submarines") instead of machine name (e.g., "fresh_submarines").

#### 1.3 Update Sort Order
Configure sorting to organize logically by Product Type, then Product Title, then Variation Title.

**Configuration**:
```yaml
sorts:
  type_1:
    id: type_1
    table: commerce_product_field_data
    field: type
    relationship: product_id
    entity_type: commerce_product
    entity_field: type
    plugin_id: standard
    order: ASC
  title_1:
    id: title_1
    table: commerce_product_field_data
    field: title
    relationship: product_id
    entity_type: commerce_product
    entity_field: title
    plugin_id: standard
    order: ASC
  title:
    id: title
    table: commerce_product_variation_field_data
    field: title
    entity_type: commerce_product_variation
    entity_field: title
    plugin_id: standard
    order: ASC
```

#### 1.4 Configure Element Wrappers for Targeted Styling
Update field element wrappers to add IDs and classes for JavaScript/CSS targeting.

**Product Title field (`title_1`)** - remains excluded but used for sub-grouping header:
```yaml
title_1:
  exclude: false
  element_type: 'h3'
  element_class: 'product-title'
  element_wrapper_type: 'div'
  element_wrapper_class: 'product-group'
  element_default_classes: false
```

**Variation Title field**:
```yaml
title:
  element_type: ''
  element_class: 'variation-title'
  element_wrapper_type: ''
  element_wrapper_class: ''
```

**Price field**:
```yaml
price__number:
  element_type: ''
  element_class: 'variation-price'
  element_wrapper_type: ''
  element_wrapper_class: ''
```

### Phase 2: Theme Integration (Minimal Approach)

#### 2.1 Create Views Template Override
Create a template to wrap the output in Bootstrap 5 accordion structure.

**File**: `web/themes/custom/duccinis_theme/templates/views/views-view--product-variations--page-2.html.twig`

**Purpose**: Transform Views grouping output into Bootstrap accordion HTML.

**Key requirements**:
- Outer wrapper: `<div id="accordion" class="accordion">`
- Each group (Product Type) becomes an accordion card
- Product Type label becomes collapsible button
- Group content becomes collapsible card body
- Maintain product sub-grouping within each type
- Add proper IDs for accordion functionality

#### 2.2 Add Accordion JavaScript
**File**: `web/themes/custom/duccinis_theme/js/menu-accordion.js`

[edit: web/themes/custom/duccinis_theme/js/menu-accordion.js is *probably* wrong and must be corrected, because duccinis_theme is a Single Directory Component theme]

**Purpose**: Initialize Bootstrap 5 accordion behavior if not automatic.

**Minimal implementation**:
```javascript
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.menuAccordion = {
    attach: function (context, settings) {
      once('menu-accordion', '#accordion', context).forEach(function (element) {
        // Bootstrap 5 accordion initializes automatically via data attributes
        // This is a placeholder for any custom behavior
        console.log('Menu accordion initialized');
      });
    }
  };
})(Drupal, once);
```

#### 2.3 Add Accordion Styling
**File**: `web/themes/custom/duccinis_theme/css/menu-accordion.css`

**Purpose**: Style accordion to match `desirable.html` aesthetic.

**Key styles**:
```css
/* Accordion card styling */
.accordion .card {
  margin-bottom: .5rem;
  border: 1px solid #ddd;
}

.accordion .card-header {
  background: #605954;
  padding: 0;
}

.accordion .card-button {
  color: #fff;
  font-weight: bold;
  text-align: left;
  width: 100%;
  padding: 1rem;
  background: #605954;
  border: none;
}

.accordion .card-button:hover {
  background: #4a4440;
  color: #fff;
}

/* Product group (within each accordion) */
.product-group {
  margin-bottom: 1rem;
}

.product-title {
  font-size: 1.2rem;
  font-weight: bold;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

/* Variation row styling */
.views-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #eee;
}

.views-row:last-child {
  border-bottom: none;
}

.variation-title {
  flex: 1;
}

.variation-price {
  font-weight: bold;
  margin-left: 1rem;
}
```

### Phase 3: Testing & Refinement

#### 3.1 Configuration Import
```bash
ddev drush cim -y
ddev drush cr
```

#### 3.2 Test Checklist
- [ ] All product types display as accordion sections
- [ ] Products within each type are grouped with headings
- [ ] Variations display correctly under each product
- [ ] Add to cart links function properly
- [ ] Accordion expand/collapse works on mobile
- [ ] Accordion expand/collapse works on desktop
- [ ] Logical sort order (by type, then product, then variation)
- [ ] Proper IDs on product and variation divs
- [ ] Responsive design tested on multiple screen sizes

#### 3.3 Refinement Areas
- Adjust accordion card styling to match brand
- Fine-tune spacing and typography
- Optimize load performance for large menus
- Add accessibility attributes (ARIA labels)
- Consider lazy-loading for closed accordion sections

## Implementation Steps

### Step 1: Backup Current Configuration
```bash
cd /home/lee/ams_projects/2025/week-43/v1/duccinisV3
git add config/sync/views.view.product_variations.yml
git commit -m "Backup: Current state before accordion implementation"
```

### Step 2: Edit Views Configuration
Edit `config/sync/views.view.product_variations.yml`:

1. Locate `menu_complete_development` display (line ~4709)
2. Change `defaults: fields: true` to `defaults: fields: false`
3. Copy all fields from default display
4. Add `type_1` field (Product Type) with `exclude: false` for debugging first
5. Update `title_1` field configuration for product headings
6. Change `defaults: style: true` to `defaults: style: false`
7. Copy style configuration and change grouping field to `type_1`
8. Change `defaults: sorts: true` to `defaults: sorts: false`
9. Add sort configuration (type → product title → variation title)

### Step 3: Import and Test Base Grouping
```bash
ddev drush cim -y
ddev drush cr
```
Visit `/product-variations` and verify:
- Products are grouped by type (not styled yet, but grouped in plain HTML)
- Sort order is logical
- All products and variations display

### Step 4: Create Theme Template
1. Create template file: `views-view--product-variations--page-2.html.twig`
2. Start with base Views template from core
3. Add accordion HTML structure around grouping loops
4. Test incrementally

### Step 5: Add Styling and JavaScript
1. Create CSS file
2. Create JS file (if needed)
3. Register in theme `.libraries.yml`
4. Attach library to template
5. Clear cache and test

### Step 6: Refinement
1. Hide excluded fields properly
2. Add product/variation IDs to wrapper divs
3. Adjust styling to match design
4. Test responsive behavior
5. Performance optimization

## Expected Outcome

### HTML Structure (Simplified)
```html
<div id="accordion" class="accordion">
  <!-- Fresh Submarines Section -->
  <div class="card" id="product-type-fresh-submarines">
    <div class="card-header">
      <h2>
        <button class="card-button" data-bs-toggle="collapse"
                data-bs-target="#collapse-fresh-submarines">
          Fresh Submarines
        </button>
      </h2>
    </div>
    <div id="collapse-fresh-submarines" class="collapse">
      <div class="card-body">
        <!-- Italian Cheese Steak Product Group -->
        <div class="product-group" id="product-123">
          <h3 class="product-title">Italian Cheese Steak</h3>
          <div class="views-row" id="variation-456">
            <div class="variation-title">Medium 8"</div>
            <div class="variation-price">$8.99</div>
            <div class="variation-cart">[Add to cart]</div>
          </div>
          <div class="views-row" id="variation-457">
            <div class="variation-title">X-Large 12"</div>
            <div class="variation-price">$12.50</div>
            <div class="variation-cart">[Add to cart]</div>
          </div>
        </div>
        <!-- Cheese Steak Product Group -->
        <div class="product-group" id="product-124">
          <h3 class="product-title">Cheese Steak [Rib-Eye]</h3>
          <!-- variations... -->
        </div>
      </div>
    </div>
  </div>

  <!-- Beverages Section -->
  <div class="card" id="product-type-beverages">
    <!-- similar structure... -->
  </div>

  <!-- Additional product type sections... -->
</div>
```

## Timeline Estimate

- **Phase 1 (Views Config)**: 2-3 hours
- **Phase 2 (Theme Integration)**: 2-4 hours
- **Phase 3 (Testing & Refinement)**: 1-2 hours
- **Total**: 5-9 hours

## Questions for Review

1. Should all accordion sections start collapsed, or should the first section be expanded by default? [all accordion sections start collapsed]
2. What should the sort order be for Product Types? (Alphabetical vs. custom order) [I don't know yet but the prototype/menu-accordion-prototype.html looks nice for example]
3. Should we add a "View All" option to expand all sections at once? [no]
4. Do you want smooth scroll animation when expanding accordion sections? yes, butonly if it can be discrete and not taxing of the device CPU]
5. Should closed accordion sections lazy-load content or pre-render everything? [yes, in the future I plan to add image fields and use Drupal's Responsive Image Cache so only small files are sent to small displays, while the source files are high resolution.]

## Resources & References

- Current prototype: `static_html/desirable.html`
- Bootstrap 5 Accordion docs: https://getbootstrap.com/docs/5.0/components/accordion/
- Drupal Views grouping: https://www.drupal.org/docs/user_guide/en/views-grouping.html
- Views template suggestions: https://www.drupal.org/docs/theming-drupal/twig-in-drupal/twig-template-naming-conventions
