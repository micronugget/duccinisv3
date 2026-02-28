# 📸 Visual Example: How Issues Will Look in GitHub

This document shows you exactly what the issues will look like once created in GitHub.

---

## 🎯 Parent Epic Example

### Epic #1: Time-Based Order Fulfillment with Store Hours Validation

**Issue #123** (example number)

**Labels:** `epic` `priority:high` `feature` `enhancement`  
**Assignees:** @architect  
**Milestone:** Phase 1 - Core Commerce Features

---

### Issue Description:

## 📋 Epic Overview

Implement a complete order fulfillment system that enforces store operating hours:
- **Immediate orders** can ONLY be placed when the store is currently open
- **Scheduled orders** can be placed anytime but must be scheduled within store operating hours
- Users are guided through appropriate fulfillment time selection based on current store status

## 🎯 Business Rules

See full details in: `.github/ISSUE_EPIC_1.md`

### Rule 1: Immediate Pickup/Delivery
- Order can be marked "ASAP" or "Immediate" ONLY if current time is within store's operating hours
- If store is currently CLOSED, "Immediate" option is DISABLED

### Rule 2: Scheduled Orders
- Can be placed 24/7 (even when store is closed)
- Selected fulfillment time MUST be within store operating hours
- At least 30 minutes in the future (configurable)

### Rule 3: Multi-Store Support
- Validation applies to currently selected store
- Switching stores re-validates fulfillment time

## 📦 Sub-Issues

This epic consists of 6 sub-issues:
- [ ] #124 Sub-Issue 1.1: Order Validator Service
- [ ] #125 Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration
- [ ] #126 Sub-Issue 1.3: Order Placement Validation Event Subscriber
- [ ] #127 Sub-Issue 1.4: Admin Configuration Form
- [ ] #128 Sub-Issue 1.5: Automated Testing
- [ ] #129 Sub-Issue 1.6: Documentation & User Guide

---

**In GitHub, you'll see:**
- ✅ A visual hierarchy tree showing all sub-issues
- 📊 Progress bar: "0 of 6 completed"
- 🔗 Clickable links to each sub-issue

---

## 🔹 Sub-Issue Example

### [Epic #1] Sub-Issue 1.1: Order Validator Service

**Issue #124** (example number)

**Labels:** `epic` `sub-issue` `backend` `php`  
**Assignees:** @drupal-developer  
**Parent Issue:** #123  
**Story Points:** 3

---

### Issue Description:

**Parent Epic:** #123

## Files to Create/Modify
- `web/modules/custom/store_fulfillment/src/OrderValidator.php` (NEW)
- `web/modules/custom/store_fulfillment/store_fulfillment.services.yml` (MODIFY)

## Acceptance Criteria
- [ ] Service `store_fulfillment.order_validator` is registered
- [ ] `validateFulfillmentTime()` returns TRUE/FALSE with validation messages
- [ ] `getNextAvailableSlot()` returns next valid timestamp
- [ ] `isImmediateOrderAllowed()` checks current time against store hours
- [ ] Unit tests cover edge cases (overnight hours, timezone differences)
- [ ] PHPStan level max passes

## Technical Notes
- Inject `store_resolver.store_hours_validator` service
- Use `TimeInterface` for timezone-aware calculations
- Handle overnight hours (e.g., "23:00-02:00")

**Full details:** See `.github/ISSUE_EPIC_1.md` - Sub-Issue 1.1

---

**In GitHub, you'll see:**
- 🔗 "Parent issue: #123" link at the top
- ✅ Checkboxes for acceptance criteria
- 🏷️ All relevant labels for easy filtering
- 👤 Assigned developer

---

## 📊 How Progress Tracking Works

### Scenario: Developer Completes Sub-Issue 1.1

1. **Developer creates PR:**
   ```
   Title: feat: Add OrderValidator service
   Body: Closes #124
   
   Implements Sub-Issue 1.1 with all acceptance criteria:
   - ✅ Service registered
   - ✅ validateFulfillmentTime() implemented
   - ✅ All tests passing
   ```

2. **PR gets merged → Issue #124 automatically closes**

3. **Parent Epic #123 updates automatically:**
   ```
   ## 📦 Sub-Issues
   This epic consists of 6 sub-issues:
   - [x] #124 Sub-Issue 1.1: Order Validator Service ✅
   - [ ] #125 Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration
   - [ ] #126 Sub-Issue 1.3: Order Placement Validation Event Subscriber
   - [ ] #127 Sub-Issue 1.4: Admin Configuration Form
   - [ ] #128 Sub-Issue 1.5: Automated Testing
   - [ ] #129 Sub-Issue 1.6: Documentation & User Guide
   
   Progress: ████░░░░░░ 1 of 6 completed (17%)
   ```

---

## 🎨 GitHub Issues UI Features

### Parent Epic View
```
┌─────────────────────────────────────────────────────────┐
│ Epic #1: Time-Based Order Fulfillment                   │
│ Labels: [epic] [priority:high] [feature]                │
│                                                          │
│ 📦 Sub-Issues (6)                                        │
│   ┌─── #124 Order Validator Service                     │
│   │    Labels: [sub-issue] [backend]                    │
│   │    Assignee: @drupal-developer                      │
│   │    Status: Open                                     │
│   │                                                      │
│   ┌─── #125 Checkout Pane Enhancement                   │
│   │    Labels: [sub-issue] [frontend]                   │
│   │    Assignee: @drupal-developer                      │
│   │    Status: Open                                     │
│   │                                                      │
│   └─── (4 more sub-issues...)                           │
│                                                          │
│ Progress: ██░░░░░░░░ 0 of 6 completed                   │
└─────────────────────────────────────────────────────────┘
```

### Sub-Issue View
```
┌─────────────────────────────────────────────────────────┐
│ 🔙 Parent: Epic #1: Time-Based Order Fulfillment        │
│                                                          │
│ [Epic #1] Sub-Issue 1.1: Order Validator Service        │
│ Labels: [epic] [sub-issue] [backend] [php]              │
│ Assignee: @drupal-developer                             │
│ Story Points: 3                                          │
│                                                          │
│ Acceptance Criteria:                                     │
│ ☐ Service registered                                    │
│ ☐ validateFulfillmentTime() implemented                 │
│ ☐ Tests passing                                         │
│                                                          │
│ Comments (0)                                             │
│ Linked PRs (0)                                           │
└─────────────────────────────────────────────────────────┘
```

---

## 📱 Mobile View

Issues are fully responsive and look great on mobile devices:

```
┌─────────────┐
│ Epic #1     │
│ ─────────── │
│             │
│ 📦 6 tasks  │
│ ██░░░░ 17%  │
│             │
│ ✅ #124     │
│ Order       │
│ Validator   │
│             │
│ ⬜ #125     │
│ Checkout    │
│ Pane        │
│             │
│ [View all]  │
└─────────────┘
```

---

## 🤖 GitHub Copilot Integration

### In Your IDE

When you reference the issue:
```javascript
// @copilot: Based on issue #124, create the OrderValidator service
```

Copilot understands:
- ✅ The full context from the epic
- ✅ All acceptance criteria
- ✅ Technical requirements
- ✅ Related sub-issues

### In GitHub Copilot Chat

```
You: @workspace Help me implement issue #124

Copilot: I can help you implement Sub-Issue 1.1: Order Validator Service 
from Epic #1. Based on the issue description, we need to:

1. Create OrderValidator.php in web/modules/custom/store_fulfillment/src/
2. Register the service in store_fulfillment.services.yml
3. Implement validateFulfillmentTime() method
4. Add unit tests

Let me start by creating the service class...

[Code generation follows with full context awareness]
```

---

## 🎯 Project Board View

In GitHub Projects, you can create a board view:

```
┌──────────┬──────────┬──────────┬──────────┐
│ Backlog  │ To Do    │ In Prog  │ Done     │
├──────────┼──────────┼──────────┼──────────┤
│          │ Epic #1  │          │          │
│          │ 📦 6 sub │          │          │
│          │ ██░░░░   │          │          │
│          │          │          │          │
│          │ #124     │          │          │
│          │ Service  │          │          │
│          │ @dev     │          │          │
│          │          │          │          │
│          │ #125     │          │          │
│          │ UI Form  │          │          │
│          │ @dev     │          │          │
└──────────┴──────────┴──────────┴──────────┘
```

### Custom Views

You can filter by:
- **Epic**: Show only Epic #1 tasks
- **Assignee**: Show only @drupal-developer tasks
- **Label**: Show only `backend` tasks
- **Story Points**: Show high-effort tasks (5 SP)
- **Status**: Show `In Progress` tasks

---

## 🔍 Search & Filter Examples

### Find All Epic #1 Sub-Issues
```
is:issue label:sub-issue "Epic #1"
```

### Find Open Backend Tasks
```
is:open label:backend label:sub-issue
```

### Find Your Assigned Tasks
```
is:open assignee:@me label:sub-issue
```

### Find High Priority Epics
```
is:open label:epic label:priority:high
```

---

## 📈 Burndown Chart (GitHub Projects)

Projects can show burndown charts:

```
Story Points Remaining
30 │     
   │ ⬤    
25 │ │⬤   
   │ │ ⬤  
20 │ │  ⬤ 
   │ │   │⬤
15 │ │   │ ⬤
   │ │   │  ⬤
10 │ │   │   ⬤
   │ │   │    ⬤
 5 │ │   │     ⬤
   │ │   │      ⬤
 0 └─┴───┴───────┴─
   W1 W2  W3   W4
   
Epic #1: 16 SP remaining → 0 SP
Target: 4 weeks
Current pace: On track ✅
```

---

## ✅ Complete Workflow Example

### Week 1: Setup
```
✅ Epic #1 created (#123)
✅ 6 sub-issues created (#124-#129)
✅ Team assigned
✅ Project board configured
```

### Week 2: Development Begins
```
🔄 #124 (Order Validator) → In Progress
👤 @drupal-developer working
📝 PR #234 opened: "feat: add OrderValidator service"
```

### Week 3: First Sub-Issue Complete
```
✅ PR #234 merged
✅ #124 automatically closed
📊 Epic #1 progress: 17% complete (1/6)
🔄 #125 (Checkout Pane) → In Progress
```

### Week 4-5: Steady Progress
```
✅ #125 closed (33% complete)
✅ #126 closed (50% complete)
✅ #127 closed (67% complete)
```

### Week 6: Final Sprint
```
✅ #128 (Testing) closed (83% complete)
✅ #129 (Docs) closed (100% complete)
🎉 Epic #1 COMPLETE!
📋 Epic #2 begins
```

---

## 🎉 Final Result

After running the script, you'll have:

✅ **2 Parent Epics** with full specifications  
✅ **13 Sub-Issues** all properly linked  
✅ **Hierarchical Structure** for easy navigation  
✅ **Auto-Progress Tracking** as tasks complete  
✅ **Copilot Integration** for context-aware coding  
✅ **Team Organization** with clear assignments  
✅ **Visual Progress** in Projects and burndown charts  

---

**Ready to create your issues?** Run `./.github/create-epic-issues.sh`!
