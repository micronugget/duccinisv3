#!/bin/bash
# Script to create GitHub Copilot Enterprise Workbench Planning Issues from EPIC files
# This script uses GitHub CLI (gh) to create parent epics and sub-issues
# 
# Prerequisites:
# - GitHub CLI installed: https://cli.github.com/
# - Authenticated: gh auth login
# - Run from repository root
#
# Usage: ./.github/create-epic-issues.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if gh is installed
if ! command -v gh &> /dev/null; then
    echo -e "${RED}Error: GitHub CLI (gh) is not installed.${NC}"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Check if authenticated
if ! gh auth status &> /dev/null; then
    echo -e "${YELLOW}Not authenticated with GitHub. Running authentication...${NC}"
    gh auth login
fi

# Get repository info
REPO=$(gh repo view --json nameWithOwner -q .nameWithOwner)
echo -e "${BLUE}Creating issues in repository: ${REPO}${NC}\n"

# Function to create an issue and return its number
create_issue() {
    local title="$1"
    local body="$2"
    local labels="$3"
    local assignees="$4"
    
    echo -e "${YELLOW}Creating issue: ${title}${NC}"
    
    local issue_url
    if [ -n "$assignees" ]; then
        issue_url=$(gh issue create \
            --title "$title" \
            --body "$body" \
            --label "$labels" \
            --assignee "$assignees" 2>&1)
    else
        issue_url=$(gh issue create \
            --title "$title" \
            --body "$body" \
            --label "$labels" 2>&1)
    fi
    
    if [ $? -eq 0 ]; then
        # Extract issue number from URL
        local issue_number=$(echo "$issue_url" | grep -oP '/issues/\K\d+' | tail -1)
        echo -e "${GREEN}✓ Created issue #${issue_number}: ${title}${NC}"
        echo "$issue_number"
    else
        echo -e "${RED}✗ Failed to create issue: ${title}${NC}"
        echo "$issue_url"
        echo "0"
    fi
}

# Function to add a sub-issue to a parent
add_sub_issue() {
    local parent_number="$1"
    local sub_issue_number="$2"
    
    echo -e "${YELLOW}  Adding #${sub_issue_number} as sub-issue of #${parent_number}${NC}"
    
    # Add sub-issue using GitHub CLI (requires GitHub CLI 2.40.0+)
    # Note: Sub-issue feature might require GitHub Enterprise or specific repo settings
    gh issue edit "$sub_issue_number" --add-parent "$parent_number" 2>&1 || {
        echo -e "${YELLOW}  Note: Sub-issue linking not available. You may need to manually link in the GitHub UI.${NC}"
    }
}

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Creating Epic #1: Time-Based Order Fulfillment${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Create Epic #1 Parent Issue
epic1_body="## 📋 Epic Overview

Implement a complete order fulfillment system that enforces store operating hours:
- **Immediate orders** can ONLY be placed when the store is currently open
- **Scheduled orders** can be placed anytime but must be scheduled within store operating hours
- Users are guided through appropriate fulfillment time selection based on current store status

## 🎯 Business Rules

See full details in: \`.github/ISSUE_EPIC_1.md\`

### Rule 1: Immediate Pickup/Delivery
- Order can be marked \"ASAP\" or \"Immediate\" ONLY if current time is within store's operating hours
- If store is currently CLOSED, \"Immediate\" option is DISABLED

### Rule 2: Scheduled Orders
- Can be placed 24/7 (even when store is closed)
- Selected fulfillment time MUST be within store operating hours
- At least 30 minutes in the future (configurable)

### Rule 3: Multi-Store Support
- Validation applies to currently selected store
- Switching stores re-validates fulfillment time

## 📦 Sub-Issues

This epic consists of 6 sub-issues:
- [ ] Sub-Issue 1.1: Order Validator Service
- [ ] Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration
- [ ] Sub-Issue 1.3: Order Placement Validation Event Subscriber
- [ ] Sub-Issue 1.4: Admin Configuration Form
- [ ] Sub-Issue 1.5: Automated Testing
- [ ] Sub-Issue 1.6: Documentation & User Guide

## 📊 Definition of Done
- [ ] All sub-issues completed and merged
- [ ] All automated tests passing
- [ ] Configuration exported: \`ddev drush cex -y\`
- [ ] Code review completed
- [ ] No PHPStan errors
- [ ] No Drupal coding standards violations

**Full specification:** See \`.github/ISSUE_EPIC_1.md\` in the repository.
"

epic1_number=$(create_issue \
    "Epic #1: Time-Based Order Fulfillment with Store Hours Validation" \
    "$epic1_body" \
    "epic,priority:high,feature,enhancement" \
    "")

if [ "$epic1_number" = "0" ]; then
    echo -e "${RED}Failed to create Epic #1. Aborting.${NC}"
    exit 1
fi

echo ""

# Create Epic #1 Sub-Issues
echo -e "${BLUE}Creating Sub-Issues for Epic #1...${NC}\n"

# Sub-Issue 1.1
sub11_body="**Parent Epic:** #${epic1_number}
**Story Points:** 3
**Assignee:** @drupal-developer

## Files to Create/Modify
- \`web/modules/custom/store_fulfillment/src/OrderValidator.php\` (NEW)
- \`web/modules/custom/store_fulfillment/store_fulfillment.services.yml\` (MODIFY)

## Acceptance Criteria
- [ ] Service \`store_fulfillment.order_validator\` is registered
- [ ] \`validateFulfillmentTime()\` returns TRUE/FALSE with validation messages
- [ ] \`getNextAvailableSlot()\` returns next valid timestamp
- [ ] \`isImmediateOrderAllowed()\` checks current time against store hours
- [ ] Unit tests cover edge cases (overnight hours, timezone differences)
- [ ] PHPStan level max passes

## Technical Notes
- Inject \`store_resolver.store_hours_validator\` service
- Use \`TimeInterface\` for timezone-aware calculations
- Handle overnight hours (e.g., \"23:00-02:00\")

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.1"

sub11_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.1: Order Validator Service" \
    "$sub11_body" \
    "epic,sub-issue,backend,php" \
    "")

# Sub-Issue 1.2
sub12_body="**Parent Epic:** #${epic1_number}
**Story Points:** 5
**Assignee:** @drupal-developer

## Files to Modify
- \`web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/FulfillmentTime.php\`

## Acceptance Criteria
- [ ] Form includes radio buttons: \"ASAP\" vs \"Schedule for later\"
- [ ] \"ASAP\" option is disabled (greyed out) when store is closed
- [ ] \"Schedule for later\" shows datetime picker
- [ ] AJAX validation on datetime selection
- [ ] Form state rebuilds if user changes store selection
- [ ] Proper error messages display inline

## UI/UX Requirements
- Use Bootstrap 5 form components (Radix theme)
- Mobile-friendly datetime picker
- Accessible (WCAG 2.1 AA compliant)

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.2"

sub12_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration" \
    "$sub12_body" \
    "epic,sub-issue,backend,frontend,ux" \
    "")

# Sub-Issue 1.3
sub13_body="**Parent Epic:** #${epic1_number}
**Story Points:** 2
**Assignee:** @drupal-developer

## Files to Create
- \`web/modules/custom/store_fulfillment/src/EventSubscriber/OrderPlacementValidator.php\` (NEW)

## Acceptance Criteria
- [ ] Subscribes to \`commerce_order.place.pre_transition\`
- [ ] Calls \`OrderValidator::validateFulfillmentTime()\` before order placement
- [ ] Throws \`\\InvalidArgumentException\` if validation fails
- [ ] Logs validation failures to \`commerce_order\` log channel
- [ ] Test coverage for valid and invalid scenarios

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.3"

sub13_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.3: Order Placement Validation Event Subscriber" \
    "$sub13_body" \
    "epic,sub-issue,backend,php" \
    "")

# Sub-Issue 1.4
sub14_body="**Parent Epic:** #${epic1_number}
**Story Points:** 2
**Assignee:** @drupal-developer

## Files to Create
- \`web/modules/custom/store_fulfillment/src/Form/StoreFulfillmentSettingsForm.php\` (NEW)
- \`web/modules/custom/store_fulfillment/store_fulfillment.routing.yml\` (MODIFY)
- \`web/modules/custom/store_fulfillment/config/install/store_fulfillment.settings.yml\` (NEW)

## Acceptance Criteria
- [ ] Form accessible at \`/admin/commerce/config/store-fulfillment\`
- [ ] Minimum advance notice (default: 30 minutes)
- [ ] Maximum scheduling window (default: 14 days)
- [ ] ASAP cutoff before closing (default: 15 minutes)
- [ ] Configuration stored in \`store_fulfillment.settings\`
- [ ] Proper permissions: \`administer commerce_store\`

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.4"

sub14_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.4: Admin Configuration Form" \
    "$sub14_body" \
    "epic,sub-issue,backend,admin-ui" \
    "")

# Sub-Issue 1.5
sub15_body="**Parent Epic:** #${epic1_number}
**Story Points:** 3
**Assignee:** @tester

## Files to Create
- \`web/modules/custom/store_fulfillment/tests/src/Kernel/OrderValidatorTest.php\` (NEW)
- \`web/modules/custom/store_fulfillment/tests/src/Functional/FulfillmentTimeCheckoutTest.php\` (NEW)

## Acceptance Criteria
- [ ] Kernel test for \`OrderValidator\` service
- [ ] Functional test for checkout flow
- [ ] All tests pass: \`ddev phpunit web/modules/custom/store_fulfillment\`

## Test Scenarios
- Test immediate order when store open → PASS
- Test immediate order when store closed → FAIL
- Test scheduled order during business hours → PASS
- Test scheduled order outside hours → FAIL

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.5"

sub15_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.5: Automated Testing" \
    "$sub15_body" \
    "epic,sub-issue,testing,quality-assurance" \
    "")

# Sub-Issue 1.6
sub16_body="**Parent Epic:** #${epic1_number}
**Story Points:** 1
**Assignee:** @technical-writer

## Files to Modify
- \`web/modules/custom/store_fulfillment/README.md\`

## Acceptance Criteria
- [ ] Document new configuration options
- [ ] Provide examples of configuring store hours
- [ ] Explain immediate vs scheduled order logic
- [ ] Include screenshots of checkout pane
- [ ] Troubleshooting section for common issues

**Full details:** See \`.github/ISSUE_EPIC_1.md\` - Sub-Issue 1.6"

sub16_number=$(create_issue \
    "[Epic #1] Sub-Issue 1.6: Documentation & User Guide" \
    "$sub16_body" \
    "epic,sub-issue,documentation" \
    "")

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Creating Epic #2: Delivery Radius Validation${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Create Epic #2 Parent Issue
epic2_body="## 📋 Epic Overview

Implement delivery radius validation with an interactive map interface that:
- Validates customer addresses against store delivery zones in real-time
- Provides visual feedback showing delivery coverage areas on an interactive map
- Integrates with checkout flow to prevent out-of-range orders
- Supports per-store customizable delivery radiuses

## 🎯 Business Rules

See full details in: \`.github/ISSUE_EPIC_2.md\`

### Rule 1: Address Validation
- Customer shipping address MUST be within the selected store's delivery radius
- Out-of-range addresses trigger error message with alternative options

### Rule 2: Delivery Radius Configuration
- Each store has a configurable \`delivery_radius\` field
- Default radius: 5 miles (configurable per store)
- Maximum radius: 25 miles (system limit)

### Rule 3: Interactive Map Display
- Show delivery coverage area on an interactive map
- Display selected store location as a marker
- Show delivery radius as a circle overlay

## 📦 Sub-Issues

This epic consists of 7 sub-issues:
- [ ] Sub-Issue 2.1: Enhance Delivery Radius Calculator Service
- [ ] Sub-Issue 2.2: Shipping Method Integration
- [ ] Sub-Issue 2.3: Interactive Map Component (Frontend)
- [ ] Sub-Issue 2.4: Checkout Pane - Address Validation UI
- [ ] Sub-Issue 2.5: Admin Configuration & Store Settings
- [ ] Sub-Issue 2.6: Automated Testing
- [ ] Sub-Issue 2.7: Documentation & User Guide

## 📊 Definition of Done
- [ ] All sub-issues completed and merged
- [ ] All automated tests passing
- [ ] Map accessible (WCAG 2.1 AA compliant)
- [ ] Performance benchmarks met

**Dependencies:** Epic #1 (Time-Based Order Fulfillment)

**Full specification:** See \`.github/ISSUE_EPIC_2.md\` in the repository.
"

epic2_number=$(create_issue \
    "Epic #2: Delivery Radius Validation with Interactive Map" \
    "$epic2_body" \
    "epic,priority:medium,feature,enhancement" \
    "")

if [ "$epic2_number" = "0" ]; then
    echo -e "${RED}Failed to create Epic #2. Aborting.${NC}"
    exit 1
fi

echo ""

# Create Epic #2 Sub-Issues
echo -e "${BLUE}Creating Sub-Issues for Epic #2...${NC}\n"

# Sub-Issue 2.1
sub21_body="**Parent Epic:** #${epic2_number}
**Story Points:** 2
**Assignee:** @drupal-developer

## Files to Modify
- \`web/modules/custom/store_fulfillment/src/DeliveryRadiusCalculator.php\`
- \`web/modules/custom/store_fulfillment/store_fulfillment.services.yml\`

## Acceptance Criteria
- [ ] \`validateAddress()\` returns TRUE/FALSE with validation result
- [ ] \`calculateDistance()\` uses Haversine formula
- [ ] \`findNearestStore()\` queries all stores and returns closest within range
- [ ] \`getDeliveryRadiusInMeters()\` handles unit conversion (miles/km)
- [ ] Unit tests cover edge cases
- [ ] PHPStan level max passes

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.1"

sub21_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.1: Enhance Delivery Radius Calculator Service" \
    "$sub21_body" \
    "epic,sub-issue,backend,php" \
    "")

# Sub-Issue 2.2
sub22_body="**Parent Epic:** #${epic2_number}
**Story Points:** 2
**Assignee:** @drupal-developer

## Files to Modify
- \`web/modules/custom/store_fulfillment/src/Plugin/Commerce/ShippingMethod/StoreDelivery.php\`

## Acceptance Criteria
- [ ] Shipping method validates address during \`calculateRates()\`
- [ ] Out-of-range addresses return empty rates array
- [ ] Error message: \"Delivery not available to this address\"
- [ ] Alternative stores suggested if within 25-mile search radius
- [ ] Pickup method remains available as fallback
- [ ] Functional test validates shipping method behavior

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.2"

sub22_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.2: Shipping Method Integration" \
    "$sub22_body" \
    "epic,sub-issue,backend,php,commerce" \
    "")

# Sub-Issue 2.3
sub23_body="**Parent Epic:** #${epic2_number}
**Story Points:** 4
**Assignee:** @themer

## Files to Create
- \`web/themes/custom/fridaynightskate_radix/js/delivery-map.js\` (NEW)
- \`web/themes/custom/fridaynightskate_radix/templates/commerce/commerce-checkout-pane--delivery-map.html.twig\` (NEW)
- \`web/themes/custom/fridaynightskate_radix/fridaynightskate_radix.libraries.yml\` (MODIFY)

## Acceptance Criteria
- [ ] Leaflet.js integrated via CDN or local library
- [ ] Map displays store location as custom marker icon
- [ ] Delivery radius shown as semi-transparent circle overlay
- [ ] Address geocoding via Nominatim or Google Geocoding API
- [ ] Mobile-responsive (works on touch devices)
- [ ] Accessible (keyboard navigation, ARIA labels)

## UI/UX Requirements
- Bootstrap 5 modal for map display
- Match existing theme aesthetics (Radix 6)

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.3"

sub23_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.3: Interactive Map Component (Frontend)" \
    "$sub23_body" \
    "epic,sub-issue,frontend,javascript,ux" \
    "")

# Sub-Issue 2.4
sub24_body="**Parent Epic:** #${epic2_number}
**Story Points:** 3
**Assignee:** @drupal-developer

## Files to Create/Modify
- \`web/modules/custom/store_fulfillment/src/Plugin/Commerce/CheckoutPane/DeliveryAddressValidator.php\` (NEW)
- \`web/modules/custom/store_fulfillment/store_fulfillment.routing.yml\` (MODIFY)

## Acceptance Criteria
- [ ] Checkout pane appears after \"Shipping information\" step
- [ ] \"Verify Delivery Availability\" button triggers AJAX validation
- [ ] AJAX callback geocodes and validates address
- [ ] Inline status messages for success/failure
- [ ] Option to open map modal for visual verification
- [ ] Form prevents advancement if delivery selected but validation failed

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.4"

sub24_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.4: Checkout Pane - Address Validation UI" \
    "$sub24_body" \
    "epic,sub-issue,backend,frontend,ux" \
    "")

# Sub-Issue 2.5
sub25_body="**Parent Epic:** #${epic2_number}
**Story Points:** 1
**Assignee:** @drupal-developer

## Files to Modify
- \`web/modules/custom/store_fulfillment/config/schema/store_fulfillment.schema.yml\`
- \`web/modules/custom/store_fulfillment/src/Form/StoreFulfillmentSettingsForm.php\`

## Acceptance Criteria
- [ ] Settings form includes delivery radius configuration
- [ ] Radius unit selector (miles/kilometers)
- [ ] Map provider (Leaflet/Google Maps)
- [ ] Google Maps API key field
- [ ] Geocoding service selection
- [ ] Configuration schema properly defined
- [ ] Permissions: \`administer commerce_store\` required

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.5"

sub25_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.5: Admin Configuration & Store Settings" \
    "$sub25_body" \
    "epic,sub-issue,backend,admin-ui" \
    "")

# Sub-Issue 2.6
sub26_body="**Parent Epic:** #${epic2_number}
**Story Points:** 2
**Assignee:** @tester

## Files to Create
- \`web/modules/custom/store_fulfillment/tests/src/Kernel/DeliveryRadiusCalculatorTest.php\` (ENHANCE)
- \`web/modules/custom/store_fulfillment/tests/src/Functional/DeliveryRadiusCheckoutTest.php\` (NEW)
- \`web/modules/custom/store_fulfillment/tests/src/FunctionalJavascript/DeliveryMapTest.php\` (NEW)

## Acceptance Criteria
- [ ] Kernel tests for \`DeliveryRadiusCalculator\`
- [ ] Functional tests for checkout flow
- [ ] JavaScript tests for map functionality
- [ ] All tests pass: \`ddev phpunit web/modules/custom/store_fulfillment\`
- [ ] Nightwatch.js test for end-to-end flow

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.6"

sub26_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.6: Automated Testing" \
    "$sub26_body" \
    "epic,sub-issue,testing,quality-assurance" \
    "")

# Sub-Issue 2.7
sub27_body="**Parent Epic:** #${epic2_number}
**Story Points:** 1
**Assignee:** @technical-writer

## Files to Modify/Create
- \`web/modules/custom/store_fulfillment/README.md\`
- \`DELIVERY_RADIUS_SETUP.md\` (NEW)

## Acceptance Criteria
- [ ] Document delivery radius configuration
- [ ] Explain map provider setup (Leaflet vs Google Maps)
- [ ] Provide API key configuration guide
- [ ] Include screenshots
- [ ] Troubleshooting section

**Full details:** See \`.github/ISSUE_EPIC_2.md\` - Sub-Issue 2.7"

sub27_number=$(create_issue \
    "[Epic #2] Sub-Issue 2.7: Documentation & User Guide" \
    "$sub27_body" \
    "epic,sub-issue,documentation" \
    "")

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ All Issues Created Successfully!${NC}"
echo -e "${GREEN}========================================${NC}\n"

echo -e "${BLUE}Summary:${NC}"
echo -e "Epic #1: #${epic1_number} - Time-Based Order Fulfillment with Store Hours Validation"
echo -e "  Sub-Issue 1.1: #${sub11_number}"
echo -e "  Sub-Issue 1.2: #${sub12_number}"
echo -e "  Sub-Issue 1.3: #${sub13_number}"
echo -e "  Sub-Issue 1.4: #${sub14_number}"
echo -e "  Sub-Issue 1.5: #${sub15_number}"
echo -e "  Sub-Issue 1.6: #${sub16_number}"
echo ""
echo -e "Epic #2: #${epic2_number} - Delivery Radius Validation with Interactive Map"
echo -e "  Sub-Issue 2.1: #${sub21_number}"
echo -e "  Sub-Issue 2.2: #${sub22_number}"
echo -e "  Sub-Issue 2.3: #${sub23_number}"
echo -e "  Sub-Issue 2.4: #${sub24_number}"
echo -e "  Sub-Issue 2.5: #${sub25_number}"
echo -e "  Sub-Issue 2.6: #${sub26_number}"
echo -e "  Sub-Issue 2.7: #${sub27_number}"
echo ""
echo -e "${YELLOW}Note: Sub-issue parent-child linking may need to be done manually in the GitHub UI${NC}"
echo -e "${YELLOW}if your GitHub CLI version doesn't support the --add-parent flag.${NC}"
echo ""
echo -e "${BLUE}View all issues: https://github.com/${REPO}/issues${NC}"
