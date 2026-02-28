# 🎨 GitHub Copilot Workbench: Create Issues from Natural Language

## 🎯 What This Guide Covers

This guide shows you how to use **GitHub Copilot Enterprise Workbench** to create GitHub issues directly from natural language descriptions - **no sandbox, no scripts, no barriers.**

---

## 🚀 Quick Start: Natural Language → Issues in 3 Steps

### Step 1: Access GitHub Copilot Workbench

**Option A: Via GitHub.com**
1. Go to your repository: https://github.com/micronugget/duccinisv3
2. Click the **Copilot** icon in the top right corner
3. Or visit directly: https://github.com/copilot

**Option B: Via Issues Tab**
1. Go to your repository's **Issues** tab
2. Look for the Copilot integration
3. Click "Create with Copilot"

**Option C: Via GitHub CLI**
```bash
gh copilot
```

---

### Step 2: Describe Your Epic in Natural Language

**Example 1: Simple Feature**

```
@copilot Create an epic for a user authentication system with:
- User registration with email verification
- Login with username/email and password
- Password reset functionality
- Two-factor authentication
- Session management
- OAuth integration (Google, GitHub)

Break this into sub-issues with acceptance criteria and story points.
```

**Example 2: Detailed Epic**

```
@copilot I need a complete shopping cart feature for my e-commerce site. 

Requirements:
1. Users can add products to cart from product pages
2. Cart persists across sessions (logged in users)
3. Guest cart stored in session/local storage
4. Users can update quantities or remove items
5. Real-time price calculations
6. Discount code support
7. Tax calculation based on shipping address
8. Integration with inventory system
9. Mobile-responsive cart UI
10. Checkout button leads to checkout flow

Technical context:
- Drupal 11 / Drupal CMS 2
- Commerce module already installed
- Using Radix theme (Bootstrap 5)
- Need both backend and frontend work

Please create:
- One parent epic issue
- Break down into sub-issues (backend, frontend, testing, docs)
- Include acceptance criteria
- Assign story points
- Suggest assignees based on domain (@drupal-developer, @themer, @tester)
```

---

### Step 3: Review and Create

Copilot will generate:
1. **Parent Epic Issue** with overview
2. **Sub-Issues** with detailed acceptance criteria
3. **Labels** (epic, sub-issue, backend, frontend, etc.)
4. **Story Point Estimates**
5. **Hierarchical Links** (parent-child relationships)

**Review the preview and click:**
- "Create all" - Creates parent + all sub-issues
- "Edit" - Modify before creating
- "Create" - Create individual issues

**Done!** ✨ Issues are now in your GitHub repository.

---

## 📝 Best Practices for Natural Language Descriptions

### ✅ DO: Be Specific

**Good:**
```
Create an epic for implementing GPS metadata extraction from uploaded videos.
The system should:
- Use ffprobe to read GPS coordinates from video files
- Store location data in Drupal fields
- Display videos on an interactive map
- Filter videos by location/date
- Support MP4, MOV, and AVI formats
```

**Bad:**
```
Make video thing work with maps
```

---

### ✅ DO: Provide Context

**Good:**
```
Context: This is a Drupal 11 project using the Media module.
Current state: Videos are uploaded but GPS data is ignored.
Goal: Extract GPS metadata during upload and display videos on a Leaflet.js map.
```

**Bad:**
```
Add maps
```

---

### ✅ DO: Mention Technical Stack

**Good:**
```
Tech stack:
- Backend: Drupal 11, PHP 8.3
- Frontend: Radix theme, Bootstrap 5, Leaflet.js
- Database: MySQL 8.0
- Dev environment: DDEV
```

**Bad:**
```
[No technical context provided]
```

---

### ✅ DO: Request Sub-Issue Breakdown

**Good:**
```
Break this epic into sub-issues covering:
- Backend service development
- Frontend component implementation
- Database schema updates
- Testing (unit + functional)
- Documentation
- Deployment considerations
```

**Bad:**
```
Just make some issues
```

---

### ✅ DO: Specify Assignee Preferences

**Good:**
```
Suggested assignees:
- Backend work → @drupal-developer
- Frontend/theme work → @themer
- Testing → @tester
- Documentation → @technical-writer
```

**Bad:**
```
[No assignee guidance]
```

---

## 🎨 Example Copilot Prompts

### Example 1: E-Commerce Feature

```
@copilot Create a comprehensive epic for implementing a "Product Comparison" feature.

User Stories:
- As a customer, I can select up to 4 products to compare side-by-side
- As a customer, I can see product attributes in a comparison table
- As a customer, I can add/remove products from comparison
- As a customer, I can share comparison with a unique URL

Technical Requirements:
- Use Drupal Commerce Product module
- Responsive design (mobile, tablet, desktop)
- AJAX-based for smooth UX
- Persist selections in session
- Generate shareable comparison URLs

Break down into:
1. Backend API endpoints (1 sub-issue)
2. Database schema for comparison storage (1 sub-issue)
3. Frontend React/JS component (1 sub-issue)
4. Theme integration (1 sub-issue)
5. Unit + functional tests (1 sub-issue)
6. Documentation (1 sub-issue)

Assign story points and include acceptance criteria for each.
```

---

### Example 2: Performance Optimization

```
@copilot Create an epic for optimizing the homepage load time.

Current Performance:
- LCP: 4.2s (target: <2.5s)
- FID: 180ms (target: <100ms)
- CLS: 0.15 (target: <0.1)

Optimization Areas:
1. Image lazy loading + WebP conversion
2. CSS/JS minification and bundling
3. Implement HTTP/2 push
4. Add Redis caching layer
5. Database query optimization
6. Implement service worker for offline support
7. CDN integration for static assets

Tech stack: Drupal 11, OpenLiteSpeed, MySQL 8.0

Create parent epic + sub-issues for each optimization area.
Include:
- Performance benchmarks as acceptance criteria
- Testing methodology
- Rollback plan if performance degrades
```

---

### Example 3: Security Audit Epic

```
@copilot Create an epic for a comprehensive security audit and hardening.

Scope:
1. Authentication & Authorization
   - Review user permissions
   - Implement 2FA
   - Session management audit
   
2. Input Validation
   - Form input sanitization
   - SQL injection prevention
   - XSS protection
   
3. File Upload Security
   - Validate file types
   - Scan for malware
   - Restrict upload directories
   
4. API Security
   - Rate limiting
   - OAuth token validation
   - CORS configuration
   
5. Infrastructure
   - SSL/TLS configuration
   - Firewall rules
   - Backup encryption

Create sub-issues for each area with:
- Security testing procedures
- Vulnerability assessment criteria
- Remediation steps
- Compliance requirements (OWASP Top 10)
```

---

## 🔧 Advanced: Using Copilot with Existing EPICs

If you already have epic specifications (like ISSUE_EPIC_1.md), you can:

### Option 1: Ask Copilot to Refine Them

```
@copilot I have an epic specification in .github/ISSUE_EPIC_1.md

Please:
1. Review the specification
2. Suggest improvements
3. Create the parent epic issue
4. Create all sub-issues
5. Link them hierarchically
```

### Option 2: Use Copilot to Split Large Epics

```
@copilot The epic in ISSUE_EPIC_1.md is too large (16 story points).

Please split it into two epics:
- Epic 1A: Core validation logic (5-8 SP)
- Epic 1B: UI components and admin config (5-8 SP)

Redistribute sub-issues accordingly.
```

---

## 📊 Comparing Methods

| Method | Natural Language | Speed | Batch Creation | Flexibility |
|--------|------------------|-------|----------------|-------------|
| **Copilot Workbench** | ✅ Yes | ⚡ Fast | ❌ One-by-one | 🎨 High |
| **Automation Script** | ❌ No | ⚡⚡ Very fast | ✅ 15 at once | 📋 Low (template-based) |
| **Manual UI** | ❌ No | 🐌 Slow | ❌ One-by-one | 🎨 High |

**Recommendation:** Use **Copilot Workbench** for ad-hoc epic creation from natural language. Use **automation script** for batch creating pre-defined epics.

---

## 🎓 Learning Resources

### Official Documentation
- [Planning a Project with Copilot](https://docs.github.com/en/copilot/tutorials/plan-a-project)
- [Creating Issues with Copilot](https://docs.github.com/en/copilot/how-tos/use-copilot-for-common-tasks/use-copilot-to-create-or-update-issues)
- [Sub-Issues Documentation](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/adding-sub-issues)

### Video Tutorials
- [GitHub Copilot for Project Planning](https://www.youtube.com/results?search_query=github+copilot+project+planning)
- [Creating Hierarchical Issues](https://www.youtube.com/results?search_query=github+sub-issues)

---

## ❓ Troubleshooting

### Issue: "Copilot doesn't understand my request"

**Solution:** Be more specific. Provide:
- Technical context
- Current state vs desired state
- Acceptance criteria
- Technical stack

---

### Issue: "Created issues don't have sub-issues linked"

**Solution:** 
1. Ask Copilot explicitly: "Create parent epic with linked sub-issues"
2. Or manually link: Open sub-issue → Click "Add parent issue"

---

### Issue: "I want to modify issues before creating"

**Solution:**
1. Ask Copilot to generate the content
2. Click "Edit" before creating
3. Modify as needed
4. Then click "Create"

---

### Issue: "Can Copilot estimate story points accurately?"

**Solution:** Copilot provides estimates based on complexity, but you should:
1. Review story points
2. Adjust based on your team's velocity
3. Consider your specific context

---

## ✅ Summary

**GitHub Copilot Enterprise Workbench is the best tool for:**
- ✅ Creating issues from natural language
- ✅ Ad-hoc epic planning
- ✅ Iterative refinement
- ✅ Quick issue generation

**No sandbox interference. No scripts needed. Direct creation.** 🎉

---

## 🔗 Quick Reference

**Access Copilot:**
- https://github.com/copilot
- Repository Issues tab → "Create with Copilot"
- `gh copilot` (GitHub CLI)

**Basic Prompt Template:**
```
@copilot Create an epic for [FEATURE NAME].

Requirements:
- [Requirement 1]
- [Requirement 2]
- [Requirement 3]

Technical Context:
- [Stack info]
- [Current state]

Break into sub-issues with acceptance criteria and story points.
```

**Done!** 🚀 Start creating issues from natural language today!
