# Menu Complete Development - Accordion Implementation

## Files in This Directory

### 1. DEVELOPMENT_PLAN.md
**Purpose**: Comprehensive development plan with implementation strategy.

**Contents**:
- Current state analysis
- Proposed solution (3 phases)
- Detailed Views configuration changes
- Theme integration approach
- Testing checklist
- Step-by-step implementation guide
- Timeline estimates
- Questions for review

**Use this file to**: Understand the overall approach and review the plan before implementation.

---

### 2. menu-accordion-prototype.html
**Purpose**: Working HTML prototype demonstrating the target accordion interface.

**Contents**:
- Complete Bootstrap 5 accordion implementation
- Sample products organized by type (Fresh Submarines, Pizza, Side Orders, Beverages, Desserts)
- Mobile-responsive design
- Proper HTML structure with IDs for products and variations
- Inline CSS matching the desired aesthetic

**How to view**:
```bash
cd /home/lee/ams_projects/2025/week-43/v1/duccinisV3/prototype
# Open in browser - works standalone without server
```

**Use this file to**:
- Visualize the end goal
- Test mobile responsiveness
- Share with stakeholders for approval
- Reference HTML structure for theme template development

---

### 3. views-config-snippet.yml
**Purpose**: Exact YAML configuration snippet for the Views changes.

**Contents**:
- Complete `page_2` display configuration
- Product Type field addition (`type_1`)
- Grouping configuration
- Sort configuration
- Field configuration with element wrappers and classes
- Heavily commented for clarity

**Use this file to**:
- Copy-paste YAML into `config/sync/views.view.product_variations.yml`
- Reference exact syntax for field configuration
- Understand what needs to change from current state

---

## Quick Start Implementation

### Step 1: Review and Approve
1. Open `menu-accordion-prototype.html` in a browser
2. Test on mobile devices
3. Review `DEVELOPMENT_PLAN.md`
4. Make any requested modifications

### Step 2: Configure Views
1. Backup current config: `git commit -am "Backup before accordion implementation"`
2. Edit `config/sync/views.view.product_variations.yml`
3. Find the `page_2` display (around line 4709)
4. Replace with configuration from `views-config-snippet.yml`
5. Test: `ddev drush config:validate`

### Step 3: Import and Test Base
```bash
ddev drush cim -y
ddev drush cr
```
Visit `/product-variations` to verify grouping works (won't look pretty yet, but should be grouped by type).

### Step 4: Add Theme Templates (if needed)
Follow Phase 2 in `DEVELOPMENT_PLAN.md` to:
- Create template override for accordion HTML
- Add CSS styling
- Add JavaScript (minimal, mostly Bootstrap handles it)

### Step 5: Refine and Deploy
- Adjust styling to match brand
- Test thoroughly on all devices
- Import to production when ready

---

## Key Technical Decisions

### Views-First Approach
**Decision**: Rely primarily on Views configuration rather than theme overrides.

**Rationale**:
- More maintainable
- Upgradable with Drupal core updates
- Less custom code to maintain
- Easier for non-developers to modify

**Implementation**:
- Views grouping feature groups by Product Type
- Views field element configuration adds classes/wrappers
- Theme only provides accordion structure and styling

---

### Product Type as Grouping Field
**Decision**: Group by Product Type (bundle) rather than taxonomy.

**Rationale**:
- Product Type already exists in data model
- No need to add taxonomy field
- Simpler configuration
- Matches existing separate block displays

**Implementation**:
- Add `type_1` field (Product Type via relationship)
- Configure style grouping on `type_1`
- Set `rendered: true` to display human-readable labels

---

### Two-Level Grouping Structure
**Decision**: Group variations by Product Type (accordion sections), then by Product Title (headings within sections).

**Rationale**:
- Product Type creates accordion sections
- Product Title groups variations logically
- Matches prototype in `desirable.html`
- User-friendly navigation

**Implementation**:
- Style grouping: Product Type
- Product Title field: Visible as H3 heading
- Variations: Listed under product headings

---

## File Structure Reference

```
prototype/
├── README.md (this file)
├── DEVELOPMENT_PLAN.md
├── menu-accordion-prototype.html
└── views-config-snippet.yml

config/sync/
└── views.view.product_variations.yml (to be edited)

web/themes/custom/duccinis_theme/
├── templates/views/
│   └── views-view--product-variations--page-2.html.twig (to be created)
├── css/
│   └── menu-accordion.css (to be created)
├── js/
│   └── menu-accordion.js (to be created)
└── duccinis_theme.libraries.yml (to be updated)
```

---

## Questions or Issues?

- Review `DEVELOPMENT_PLAN.md` Section 3.3 for refinement areas
- Check "Questions for Review" section at end of development plan
- Test prototype in multiple browsers before proceeding

---

## Implementation Checklist

- [ ] Review prototype HTML and approve design
- [ ] Review development plan and approve approach
- [ ] Backup current configuration (`git commit`)
- [ ] Edit Views configuration file
- [ ] Validate configuration (`ddev drush config:validate`)
- [ ] Import configuration (`ddev drush cim -y`)
- [ ] Clear cache (`ddev drush cr`)
- [ ] Test basic grouping at `/product-variations`
- [ ] Create theme template override (if needed)
- [ ] Add CSS styling
- [ ] Add JavaScript (if needed)
- [ ] Test on desktop browsers
- [ ] Test on mobile devices
- [ ] Test add-to-cart functionality
- [ ] Verify all products display correctly
- [ ] Check sort order is logical
- [ ] Performance test with full menu
- [ ] Deploy to production

---

**Last Updated**: 2025-12-10
**Status**: Ready for review and approval
