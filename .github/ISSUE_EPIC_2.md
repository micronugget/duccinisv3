# Epic #2: Delivery Radius Validation with Interactive Map

<!-- 
GitHub Issue Metadata:
Labels: epic, priority:medium, feature, enhancement
Assignees: @architect
Projects: Friday Night Skate Development
Milestone: Phase 1 - Core Commerce Features
-->

**Epic Type:** Feature Enhancement  
**Priority:** Medium  
**Estimated Effort:** 10 Story Points (6-8 days)  
**Dependencies:** Epic #1 (Time-Based Order Fulfillment), `store_fulfillment`, `commerce_shipping`, `geofield`, `geocoder`

---

## 📋 Epic Overview

Implement delivery radius validation with an interactive map interface that:
- Validates customer addresses against store delivery zones in real-time
- Provides visual feedback showing delivery coverage areas on an interactive map
- Integrates with checkout flow to prevent out-of-range orders
- Supports per-store customizable delivery radiuses

---

## 🎯 Business Rules

### Rule 1: Address Validation
- Customer shipping address MUST be within the selected store's delivery radius
- Validation occurs during checkout before payment processing
- Out-of-range addresses trigger error message with alternative options
- Fallback options:
  - Switch to pickup method
  - Select a different store
  - Use alternate address

### Rule 2: Delivery Radius Configuration
- Each store has a configurable `delivery_radius` field (in miles or kilometers)
- Radius is measured from store's geocoded address
- Default radius: 5 miles (configurable per store)
- Maximum radius: 25 miles (system limit)

### Rule 3: Interactive Map Display
- Show delivery coverage area on an interactive map
- Display selected store location as a marker
- Show delivery radius as a circle overlay
- Allow address verification before checkout starts

### Rule 4: Multi-Store Handling
- If address is outside current store's radius, suggest nearest store within range
- Store switcher updates delivery validation dynamically
- Cart recalculates delivery fees based on distance

---

## 🏗️ Technical Architecture

### Components to Build

1. **Delivery Radius Calculator Service** (Already exists - enhance)
   - `validateAddress(AddressInterface $address, StoreInterface $store)`
   - `calculateDistance(array $origin, array $destination)`
   - `findNearestStore(AddressInterface $address)`
   - `getDeliveryRadiusInMeters(StoreInterface $store)`

2. **Shipping Method Validation** (Extend `StoreDelivery`)
   - Integrate radius calculator into shipping method plugin
   - Block "Delivery" option if address out of range
   - Show inline error messages in checkout

3. **Interactive Map Component**
   - JavaScript component using Leaflet.js or Google Maps API
   - Shows store location and delivery radius circle
   - Address geocoding for customer input
   - Mobile-responsive design

4. **Checkout Pane Enhancement**
   - Address validation AJAX callback
   - Real-time feedback on address entry
   - "Check Delivery Availability" button
   - Map modal integration

5. **Admin Configuration**
   - Per-store delivery radius field (already exists in `commerce_store`)
   - Unit selection (miles vs kilometers)
   - Map API key configuration
   - Geocoding service settings

---

## 📦 Sub-Issues Breakdown

### 🔹 Sub-Issue 2.1: Enhance Delivery Radius Calculator Service
**Assignee:** @drupal-developer
**Story Points:** 2
**Files to Modify:**
- `web/modules/custom/store_fulfillment/src/DeliveryRadiusCalculator.php`
- `web/modules/custom/store_fulfillment/store_fulfillment.services.yml`

**Acceptance Criteria:**
- [ ] `validateAddress()` returns TRUE/FALSE with validation result
- [ ] `calculateDistance()` uses Haversine formula for accurate distance
- [ ] `findNearestStore()` queries all stores and returns closest within range
- [ ] `getDeliveryRadiusInMeters()` handles unit conversion (miles/km)
- [ ] Service properly injected with `geocoder` and `geofield` dependencies
- [ ] Unit tests cover edge cases (polar coordinates, very long distances)
- [ ] PHPStan level max passes

**Technical Notes:**
- Use Geofield module's distance calculation utilities
- Handle timezone and coordinate system edge cases
- Cache geocoding results to avoid API rate limits

---

### 🔹 Sub-Issue 2.2: Shipping Method Integration
**Assignee:** @drupal-developer
**Story Points:** 2
**Files to Modify:**
- `web/modules/custom/store_fulfillment/src/Plugin/Commerce/ShippingMethod/StoreDelivery.php`

**Acceptance Criteria:**
- [ ] Shipping method validates address during `calculateRates()`
- [ ] Out-of-range addresses return empty rates array
- [ ] Error message stored in session: "Delivery not available to this address"
- [ ] Alternative stores suggested if within 25-mile search radius
- [ ] Pickup method remains available as fallback
- [ ] Form rebuilds dynamically when store changes
- [ ] Functional test validates shipping method behavior

**Integration Points:**
- Hook into Commerce Shipping's rate calculation workflow
- Use `store_fulfillment.delivery_radius_calculator` service
- Trigger address revalidation on store selection change

---

### 🔹 Sub-Issue 2.3: Interactive Map Component (Frontend)
**Assignee:** @themer
**Story Points:** 4
**Files to Create:**
- `web/themes/custom/fridaynightskate_radix/js/delivery-map.js` (NEW)
- `web/themes/custom/fridaynightskate_radix/templates/commerce/commerce-checkout-pane--delivery-map.html.twig` (NEW)
- `web/themes/custom/fridaynightskate_radix/fridaynightskate_radix.libraries.yml` (MODIFY)

**Acceptance Criteria:**
- [ ] Leaflet.js integrated via CDN or local library
- [ ] Map displays store location as custom marker icon
- [ ] Delivery radius shown as semi-transparent circle overlay
- [ ] Address geocoding via Nominatim or Google Geocoding API
- [ ] "Check My Address" button triggers validation
- [ ] Success: green checkmark + "Within delivery area"
- [ ] Failure: red X + "Outside delivery area - Try pickup or select another store"
- [ ] Mobile-responsive (works on touch devices)
- [ ] Accessible (keyboard navigation, ARIA labels)

**UI/UX Requirements:**
- Bootstrap 5 modal for map display
- Match existing theme aesthetics (Radix 6)
- Loading spinner during geocoding
- Clear visual distinction between in-range and out-of-range states

---

### 🔹 Sub-Issue 2.4: Checkout Pane - Address Validation UI
**Assignee:** @drupal-developer
**Story Points:** 3
**Files to Create/Modify:**
- `web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/DeliveryAddressValidator.php` (NEW)
- `web/modules/custom/store_fulfillment/store_fulfillment.routing.yml` (MODIFY - add AJAX route)

**Acceptance Criteria:**
- [ ] Checkout pane appears after "Shipping information" step
- [ ] "Verify Delivery Availability" button triggers AJAX validation
- [ ] AJAX callback:
  - Geocodes entered address
  - Validates against current store radius
  - Returns JSON response with result + nearest stores if out-of-range
- [ ] Inline status messages:
  - Success: "✓ Delivery available to your address"
  - Failure: "⚠ Address outside delivery range. Nearest store: [Store Name] (3.2 miles away)"
- [ ] Option to open map modal for visual verification
- [ ] Form prevents advancement if delivery selected but validation failed
- [ ] Pickup method bypasses this validation

**Technical Implementation:**
- Use Drupal AJAX API
- Cache validation results in user session
- Re-validate if address or store changes

---

### 🔹 Sub-Issue 2.5: Admin Configuration & Store Settings
**Assignee:** @drupal-developer
**Story Points:** 1
**Files to Modify:**
- `web/modules/custom/store_fulfillment/config/schema/store_fulfillment.schema.yml`
- `web/modules/custom/store_fulfillment/src/Form/StoreFulfillmentSettingsForm.php`

**Acceptance Criteria:**
- [ ] Settings form includes:
  - Default delivery radius (number field, default: 5 miles)
  - Radius unit selector (miles/kilometers)
  - Map provider (Leaflet/Google Maps)
  - Google Maps API key field (conditional on provider)
  - Geocoding service (Nominatim/Google/Mapbox)
- [ ] Store edit form shows `delivery_radius` field (already exists)
- [ ] Field validates: minimum 1 mile, maximum 25 miles
- [ ] Configuration schema properly defined
- [ ] Permissions: `administer commerce_store` required

---

### 🔹 Sub-Issue 2.6: Automated Testing
**Assignee:** @tester
**Story Points:** 2
**Files to Create:**
- `web/modules/custom/store_fulfillment/tests/src/Kernel/DeliveryRadiusCalculatorTest.php` (ENHANCE)
- `web/modules/custom/store_fulfillment/tests/src/Functional/DeliveryRadiusCheckoutTest.php` (NEW)
- `web/modules/custom/store_fulfillment/tests/src/FunctionalJavascript/DeliveryMapTest.php` (NEW)

**Acceptance Criteria:**
- [ ] Kernel tests for `DeliveryRadiusCalculator`:
  - Address within radius → TRUE
  - Address outside radius → FALSE
  - Distance calculation accuracy (compare to known values)
  - Unit conversion (miles to meters)
  - findNearestStore() returns correct result
- [ ] Functional tests for checkout:
  - User enters valid address → can complete checkout
  - User enters invalid address → delivery option disabled
  - User switches to pickup → checkout completes
  - Nearest store suggestion appears for out-of-range address
- [ ] JavaScript tests for map:
  - Map initializes correctly
  - Store marker displays
  - Radius circle renders
  - Address validation updates UI
- [ ] All tests pass: `ddev phpunit web/modules/custom/store_fulfillment`
- [ ] Nightwatch.js test for end-to-end flow

---

### 🔹 Sub-Issue 2.7: Documentation & User Guide
**Assignee:** @technical-writer
**Story Points:** 1
**Files to Modify:**
- `web/modules/custom/store_fulfillment/README.md`
- Create: `DELIVERY_RADIUS_SETUP.md` (NEW)

**Acceptance Criteria:**
- [ ] Document delivery radius configuration
- [ ] Explain map provider setup (Leaflet vs Google Maps)
- [ ] Provide API key configuration guide
- [ ] Include screenshots of:
  - Admin settings form
  - Checkout delivery validation
  - Interactive map modal
- [ ] Troubleshooting section:
  - Geocoding failures
  - Map not displaying
  - Incorrect distance calculations
- [ ] Video walkthrough of customer experience (optional)

---

## 🧪 Testing Strategy

### Manual Testing Checklist
- [ ] **Scenario 1:** Address within radius → Delivery available, map shows green area
- [ ] **Scenario 2:** Address outside radius → Delivery disabled, pickup suggested
- [ ] **Scenario 3:** Address exactly on radius boundary → Validation handles edge case
- [ ] **Scenario 4:** Switch stores mid-checkout → Validation re-runs correctly
- [ ] **Scenario 5:** Very distant address (>100 miles) → Nearest store suggestion accurate
- [ ] **Scenario 6:** Invalid address (non-geocodable) → Error message clear
- [ ] **Scenario 7:** Map interaction on mobile → Touch gestures work correctly

### Automated Tests
- Run `ddev phpunit web/modules/custom/store_fulfillment`
- Run `ddev phpstan analyse web/modules/custom/store_fulfillment`
- Run `ddev yarn test:nightwatch --tag delivery-radius`

### Performance Testing
- [ ] Geocoding requests cached appropriately
- [ ] Map loads in <2 seconds
- [ ] Distance calculations don't slow checkout (<500ms)

---

## 📊 Definition of Done

- [ ] All sub-issues completed and merged
- [ ] All automated tests passing
- [ ] Manual testing checklist completed
- [ ] Configuration exported: `ddev drush cex -y`
- [ ] Documentation updated
- [ ] Code review completed by @architect
- [ ] Deployed to staging environment
- [ ] UAT (User Acceptance Testing) passed
- [ ] No PHPStan errors
- [ ] No Drupal coding standards violations
- [ ] Map accessible (WCAG 2.1 AA compliant)
- [ ] Performance benchmarks met

---

## 🔗 Related Issues

- Depends on: Epic #1 (Time-Based Order Fulfillment)
- Related to: Store configuration and multi-store setup
- Integrates with: Commerce Shipping, Geofield, Geocoder modules

---

## 📝 Notes

- **Existing Infrastructure:** `DeliveryRadiusCalculator` service already exists but needs enhancement
- **Field Already Configured:** `delivery_radius` field exists on `commerce_store.online` entity
- **Map Provider:** Default to Leaflet.js (free, no API key required) with Google Maps as optional upgrade
- **Geocoding:** Use Nominatim (free) by default, allow Google Geocoding API as premium option
- **Timeline:** Target completion by February 28, 2026

---

## 🚀 Implementation Order

1. Sub-Issue 2.1 (Enhance Delivery Radius Calculator) - **START HERE**
2. Sub-Issue 2.5 (Admin Configuration)
3. Sub-Issue 2.2 (Shipping Method Integration)
4. Sub-Issue 2.3 (Interactive Map Component)
5. Sub-Issue 2.4 (Checkout Address Validation UI)
6. Sub-Issue 2.6 (Automated Testing)
7. Sub-Issue 2.7 (Documentation)

---

**Copy this content to create GitHub Issue with labels: `epic`, `priority:medium`, `feature`, `enhancement`**
