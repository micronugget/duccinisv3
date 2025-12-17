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

### Phase 2B: Layout Builder JavaScript Transformation (Alternative Approach)

**Status: IMPLEMENTED** (2025-12-16)

For pages using Layout Builder with multiple Views blocks (one per menu category), a JavaScript-based transformation approach was implemented instead of the grouped Views approach.

#### 2B.1 JavaScript Transformation Logic
**File**: `web/themes/custom/duccinis_theme/src/js/menu-accordion.js`

**Implementation**:
- Targets `.layout` containers with product variation blocks
- Finds all blocks matching `[class*="block-views-blockproduct-variations-"]`
- Creates a Bootstrap 5 accordion container
- Transforms each block's `.block__title` (h2) into accordion button
- Moves each block's `.block__content` into accordion body
- Removes original blocks after transformation

**Key features**:
- Uses Drupal behaviors with `once()` for proper attachment
- Generates unique IDs for each accordion item
- Maintains Bootstrap 5 data attributes for collapse functionality
- Preserves all original content including Views output

#### 2B.2 Library Attachment
**File**: `web/themes/custom/duccinis_theme/templates/block/block--views-block.html.twig`

**Purpose**: Attaches `duccinis_theme/menu_accordion` library to all Views blocks, ensuring the JavaScript is loaded on pages with product variation blocks.

#### 2B.3 CSS Styling
**File**: `web/themes/custom/duccinis_theme/src/css/menu-accordion.css`

**Existing styles support**:
- `.menu-accordion__button` - Bold accordion headers
- `.menu-variation-row` - Flexbox layout for variation items
- `.variation-title`, `.variation-price`, `.variation-cart` - Item styling

### Phase 2C: Pure Twig/Bootstrap 5 Accordion (Recommended Approach)

**Status: IMPLEMENTED** (2025-12-16)

This approach uses a single Views display with grouping, rendered entirely via Twig templates using Bootstrap 5's native accordion structure. **No JavaScript transformation required.**

#### 2C.1 Views Display Configuration
**Display**: `menu_complete_3` (Menu complete 3)
**Path**: `/menu-complete-3`
**File**: `config/sync/views.view.product_variations.yml`

**Configuration**:
- Groups by `type_1` field (Product Type) with `rendered: true`
- Sorts by: type_1 ASC → title_1 (product) ASC → title (variation) ASC
- Fields: type_1 (excluded), sku (excluded), title_1 (product title), title (variation), price, add-to-cart
- Row class: `menu-variation-row`

#### 2C.2 Views-View Template (Accordion Container)
**File**: `web/themes/custom/duccinis_theme/templates/views/views-view--product-variations--menu-complete-3.html.twig`

**Purpose**: Wraps the grouped Views output in a Bootstrap 5 accordion container.

**Key structure**:
```twig
<div id="menu-complete-3-accordion" class="accordion menu-accordion">
  {{ rows }}
</div>
```

#### 2C.3 Views-View-Unformatted Template (Accordion Items)
**File**: `web/themes/custom/duccinis_theme/templates/views/views-view-unformatted--product-variations--menu-complete-3.html.twig`

**Purpose**: Renders each group as a Bootstrap 5 accordion-item. Called once per group by Views.

**Key structure**:
```twig
<div class="accordion-item">
  <h2 class="accordion-header">
    <button class="accordion-button collapsed"
            data-bs-toggle="collapse"
            data-bs-target="#collapse-id">
      {{ title }}
    </button>
  </h2>
  <div class="accordion-collapse collapse" data-bs-parent="#accordion-id">
    <div class="accordion-body">
      {% for row in rows %}...{% endfor %}
    </div>
  </div>
</div>
```

#### 2C.4 Preprocess Hooks
**File**: `web/themes/custom/duccinis_theme/includes/view.theme`

**Functions**:
- `duccinis_theme_preprocess_views_view()` - Sets accordion ID and flags
- `duccinis_theme_preprocess_views_view_unformatted()` - Passes group title

#### 2C.5 Advantages Over JavaScript Approach
1. **Server-side rendering** - No DOM manipulation after page load
2. **Better SEO** - Accordion content is in initial HTML
3. **Faster perceived load** - No flash of unstyled content
4. **Simpler debugging** - Standard Twig/Views workflow
5. **Native Bootstrap 5** - Uses data attributes, no custom JS needed

### Phase 2D: Basic Page Styling Enhancements (Bootstrap Buttons & Bold Prices)

**Status: IMPLEMENTED** (2025-12-17)

This phase enhances the Basic Page (Layout Builder with multiple Views blocks) to display "Add to cart" links as Bootstrap buttons and prices in bold, matching the styling in the Menu Complete View.

#### 2D.1 CSS Enhancements
**File**: `web/themes/custom/duccinis_theme/src/css/menu-accordion.css`

**Added styles for**:
- `.menu-accordion__body .views-row` - Flexbox layout with proper spacing and border separators
- `.menu-accordion__body .views-row a[href^="/add-to-cart"]` - Bootstrap 5 primary button styling (btn btn-sm btn-primary)
- `.menu-accordion__body h3` - Product group heading styling

#### 2D.2 JavaScript Row Styling
**File**: `web/themes/custom/duccinis_theme/src/js/menu-accordion.js`

**Added `styleAccordionRows()` function** that:
1. Adds Bootstrap button classes (`btn`, `btn-sm`, `btn-primary`) to all "Add to cart" links
2. Parses text nodes to find price patterns (`$XX.XX`)
3. Wraps prices in `<span class="variation-price">` for bold styling
4. Wraps variation titles in `<span class="variation-title">` for flex layout

**Key implementation details**:
- Uses `querySelectorAll('a[href^="/add-to-cart"]')` to target cart links
- Regex pattern `/(\$\d+\.\d{2})/` matches standard price format
- Creates document fragments for efficient DOM manipulation
- Called after accordion transformation completes

#### 2D.3 Resulting HTML Structure
After JavaScript transformation, each `.views-row` contains:
```html
<div class="views-row">
  <span class="variation-title">Bucket [50 wings]</span>
  <span class="variation-price">$59.95</span>
  <a href="/add-to-cart/69/165" class="btn btn-sm btn-primary">Add to cart</a>
</div>
```

#### 2D.4 Visual Improvements
- **Buttons**: Blue Bootstrap primary buttons instead of plain text links
- **Prices**: Bold font weight (700) for easy scanning
- **Layout**: Flexbox row with proper spacing between elements
- **Hover states**: Button color changes on hover for better UX

### Phase 3: Testing & Refinement

#### 3.1 Configuration Import
```bash
ddev drush cim -y
ddev drush cr
```

#### 3.2 Test Checklist

**For Pure Twig/Bootstrap 5 (Phase 2C - Menu complete 3) - RECOMMENDED:**
- [ ] Visit `/menu-complete-3` and verify accordion renders
- [ ] All product types display as accordion sections (Beverages, Chicken Wings, Desserts, etc.)
- [ ] Accordion buttons show human-readable product type names
- [ ] Accordion expand/collapse works (Bootstrap 5 native behavior)
- [ ] Products within each type display with title, price, add-to-cart
- [ ] Add to cart links function properly
- [ ] No JavaScript errors in browser console
- [ ] Responsive design works on mobile and desktop
- [ ] Page source shows accordion HTML (server-side rendered)

**For Layout Builder Pages (Phase 2B/2D - JavaScript Transformation + Styling):**
- [ ] Menu blocks transform into accordion on page load
- [ ] Each menu category becomes a collapsible accordion item
- [ ] Block titles become accordion buttons
- [ ] Block content moves into accordion body
- [ ] Accordion expand/collapse works on mobile
- [ ] Accordion expand/collapse works on desktop
- [ ] Add to cart links display as Bootstrap primary buttons (blue, btn-sm)
- [ ] Add to cart buttons have hover state (darker blue)
- [ ] Prices display in bold (font-weight: 700)
- [ ] Variation titles and prices are properly separated in flexbox layout
- [ ] Add to cart links function properly after transformation
- [ ] No JavaScript errors in browser console
- [ ] Responsive design tested on multiple screen sizes

**For Grouped Views Display (Phase 2 - Template Approach):**
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
