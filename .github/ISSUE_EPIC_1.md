# Epic #1: Time-Based Order Fulfillment with Store Hours Validation

**Epic Type:** Feature Enhancement
**Priority:** High
**Estimated Effort:** 13 Story Points (8-10 days)
**Dependencies:** `store_resolver`, `store_fulfillment`, `commerce_order`, `commerce_checkout`

---

## 📋 Epic Overview

Implement a complete order fulfillment system that enforces store operating hours:
- **Immediate orders** can ONLY be placed when the store is currently open
- **Scheduled orders** can be placed anytime but must be scheduled within store operating hours
- Users are guided through appropriate fulfillment time selection based on current store status

---

## 🎯 Business Rules

### Rule 1: Immediate Pickup/Delivery
- Order can be marked "ASAP" or "Immediate" ONLY if:
  - Current time is within store's operating hours (`store_hours` field)
  - Validation uses `StoreHoursValidator::isStoreOpen()` service
- If store is currently CLOSED:
  - "Immediate" option is DISABLED
  - User MUST select a scheduled time

### Rule 2: Scheduled Orders
- Can be placed 24/7 (even when store is closed)
- Selected fulfillment time MUST be:
  - Within store operating hours
  - At least 30 minutes in the future (configurable)
  - Not on days when store is closed

### Rule 3: Multi-Store Support
- Validation applies to the currently selected store (via `store_resolver`)
- Switching stores re-validates fulfillment time
- Each store has independent operating hours

---

## 🏗️ Technical Architecture

### Components to Build

1. **Order Validator Service** (`store_fulfillment.order_validator`)
   - `validateFulfillmentTime(OrderInterface $order, $requested_time)`
   - `getNextAvailableSlot(StoreInterface $store)`
   - `isImmediateOrderAllowed(StoreInterface $store)`

2. **Checkout Pane Enhancement** (Extend existing `FulfillmentTime`)
   - Dynamic form alteration based on store status
   - AJAX validation on time selection
   - User-friendly error messages

3. **Event Subscriber** (`OrderPlacementValidator`)
   - Subscribe to `commerce_order.place.pre_transition`
   - Block order placement if time validation fails
   - Log validation failures for debugging

4. **Admin Configuration Form** (`/admin/commerce/config/store-fulfillment`)
   - Minimum advance notice (default: 30 minutes)
   - Maximum scheduling window (default: 14 days)
   - Immediate order cutoff time (e.g., stop ASAP orders 15 min before closing)

---

## 📦 Sub-Issues Breakdown

### Sub-Issue 1.1: Order Validator Service
**Assignee:** @drupal-developer
**Story Points:** 3
**Files to Create/Modify:**
- `web/modules/custom/store_fulfillment/src/OrderValidator.php` (NEW)
- `web/modules/custom/store_fulfillment/store_fulfillment.services.yml` (MODIFY)

**Acceptance Criteria:**
- [ ] Service `store_fulfillment.order_validator` is registered
- [ ] `validateFulfillmentTime()` returns TRUE/FALSE with validation messages
- [ ] `getNextAvailableSlot()` returns next valid timestamp
- [ ] `isImmediateOrderAllowed()` checks current time against store hours
- [ ] Unit tests cover edge cases (overnight hours, timezone differences)
- [ ] PHPStan level max passes

**Technical Notes:**
- Inject `store_resolver.store_hours_validator` service
- Use `TimeInterface` for timezone-aware calculations
- Handle overnight hours (e.g., "23:00-02:00")

---

### Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration
**Assignee:** @drupal-developer
**Story Points:** 5
**Files to Modify:**
- `web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/FulfillmentTime.php`

**Acceptance Criteria:**
- [ ] Form includes radio buttons: "ASAP" vs "Schedule for later"
- [ ] "ASAP" option is disabled (greyed out) when store is closed
- [ ] Disabled option shows message: "Store is currently closed. Please schedule your order."
- [ ] "Schedule for later" shows datetime picker with:
  - Minimum time: current time + 30 minutes
  - Maximum time: current time + 14 days
  - Disabled dates/times when store is closed
- [ ] AJAX validation on datetime selection
- [ ] Form state rebuilds if user changes store selection
- [ ] Proper error messages display inline

**UI/UX Requirements:**
- Use Bootstrap 5 form components (Radix theme)
- Mobile-friendly datetime picker
- Clear visual distinction between enabled/disabled options
- Accessible (WCAG 2.1 AA compliant)

---

### Sub-Issue 1.3: Order Placement Validation Event Subscriber
**Assignee:** @drupal-developer
**Story Points:** 2
**Files to Create:**
- `web/modules/custom/store_fulfillment/src/EventSubscriber/OrderPlacementValidator.php` (NEW)

**Acceptance Criteria:**
- [ ] Subscribes to `commerce_order.place.pre_transition`
- [ ] Calls `OrderValidator::validateFulfillmentTime()` before order placement
- [ ] Throws `\InvalidArgumentException` if validation fails
- [ ] Logs validation failures to `commerce_order` log channel
- [ ] Test coverage for:
  - Valid immediate order (store open)
  - Invalid immediate order (store closed) → BLOCKED
  - Valid scheduled order
  - Invalid scheduled order (outside hours) → BLOCKED

---

### Sub-Issue 1.4: Admin Configuration Form
**Assignee:** @drupal-developer
**Story Points:** 2
**Files to Create:**
- `web/modules/custom/store_fulfillment/src/Form/StoreFulfillmentSettingsForm.php` (NEW)
- `web/modules/custom/store_fulfillment/store_fulfillment.routing.yml` (MODIFY)
- `web/modules/custom/store_fulfillment/config/install/store_fulfillment.settings.yml` (NEW)

**Acceptance Criteria:**
- [ ] Form accessible at `/admin/commerce/config/store-fulfillment`
- [ ] Settings:
  - Minimum advance notice (number field, default: 30 minutes)
  - Maximum scheduling window (number field, default: 14 days)
  - ASAP cutoff before closing (number field, default: 15 minutes)
- [ ] Configuration stored in `store_fulfillment.settings`
- [ ] Form uses ConfigFormBase pattern
- [ ] Proper permissions: `administer commerce_store`

---

### Sub-Issue 1.5: Automated Testing
**Assignee:** @tester
**Story Points:** 3
**Files to Create:**
- `web/modules/custom/store_fulfillment/tests/src/Kernel/OrderValidatorTest.php` (NEW)
- `web/modules/custom/store_fulfillment/tests/src/Functional/FulfillmentTimeCheckoutTest.php` (NEW)

**Acceptance Criteria:**
- [ ] Kernel test for `OrderValidator` service
  - Test immediate order when store open → PASS
  - Test immediate order when store closed → FAIL
  - Test scheduled order during business hours → PASS
  - Test scheduled order outside hours → FAIL
  - Test timezone edge cases
- [ ] Functional test for checkout flow
  - User sees disabled "ASAP" when store closed
  - User can select valid scheduled time
  - Order placement blocked with invalid time
- [ ] All tests pass: `ddev phpunit web/modules/custom/store_fulfillment`

---

### Sub-Issue 1.6: Documentation & User Guide
**Assignee:** @technical-writer
**Story Points:** 1
**Files to Modify:**
- `web/modules/custom/store_fulfillment/README.md`

**Acceptance Criteria:**
- [ ] Document new configuration options
- [ ] Provide examples of configuring store hours
- [ ] Explain immediate vs scheduled order logic
- [ ] Include screenshots of checkout pane
- [ ] Troubleshooting section for common issues

---

## 🧪 Testing Strategy

### Manual Testing Checklist
- [ ] **Scenario 1:** Store open, select "ASAP" → Order placed successfully
- [ ] **Scenario 2:** Store closed, "ASAP" disabled → User forced to schedule
- [ ] **Scenario 3:** Schedule order during closed hours → Validation error
- [ ] **Scenario 4:** Schedule order during open hours → Order placed successfully
- [ ] **Scenario 5:** Change store during checkout → Form updates correctly
- [ ] **Scenario 6:** Overnight hours (e.g., 22:00-02:00) → Validation correct

### Automated Tests
- Run `ddev phpunit web/modules/custom/store_fulfillment`
- Run `ddev phpstan analyse web/modules/custom/store_fulfillment`
- Run `ddev drush test-run store_fulfillment`

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

---

## 🔗 Related Issues

- Depends on: Existing `store_resolver` and `store_fulfillment` modules
- Blocks: Epic #2 (Delivery Radius Validation)
- Related: Store configuration documentation

---

## 📝 Notes

- **Configuration Already Exists:** `store_hours` field is already on `commerce_store.online`
- **Service Already Exists:** `StoreHoursValidator::isStoreOpen()` service is ready to use
- **Integration Point:** Checkout pane exists but needs enhancement
- **Timeline:** Target completion by February 15, 2026

---

## 🚀 Implementation Order

1. Sub-Issue 1.1 (Order Validator Service) - **START HERE**
2. Sub-Issue 1.4 (Admin Config Form)
3. Sub-Issue 1.2 (Checkout Pane Enhancement)
4. Sub-Issue 1.3 (Event Subscriber Validation)
5. Sub-Issue 1.5 (Automated Testing)
6. Sub-Issue 1.6 (Documentation)

---

**Copy this content to create GitHub Issue with label: `epic`, `priority:high`, `feature`**
