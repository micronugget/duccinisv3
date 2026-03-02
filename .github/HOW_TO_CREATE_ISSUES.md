# GitHub Copilot Enterprise Workbench Planning Issues - User Guide

## 🎯 What This Does

This repository contains properly formatted **GitHub Copilot Enterprise Workbench Planning Issues** for two major epics:

1. **Epic #1**: Time-Based Order Fulfillment with Store Hours Validation
2. **Epic #2**: Delivery Radius Validation with Interactive Map

Each epic is broken down into sub-issues (child tasks) that can be tracked hierarchically in GitHub Issues.

---

## 📁 File Structure

```
.github/
├── ISSUE_EPIC_1.md              # Epic #1 specification (parent issue)
├── ISSUE_EPIC_2.md              # Epic #2 specification (parent issue)
├── create-epic-issues.sh        # Automation script to create all issues
└── HOW_TO_CREATE_ISSUES.md      # This file
```

---

## 🚀 Quick Start: Automated Creation

### Prerequisites

1. **Install GitHub CLI** (if not already installed):
   ```bash
   # macOS
   brew install gh
   
   # Ubuntu/Debian
   sudo apt install gh
   
   # Windows
   winget install GitHub.cli
   
   # Or download from: https://cli.github.com/
   ```

2. **Authenticate with GitHub**:
   ```bash
   gh auth login
   ```
   Follow the prompts to authenticate.

### Run the Script

From your repository root:

```bash
./.github/create-epic-issues.sh
```

This will:
- ✅ Create Epic #1 parent issue
- ✅ Create 6 sub-issues for Epic #1
- ✅ Create Epic #2 parent issue
- ✅ Create 7 sub-issues for Epic #2
- ✅ Attempt to link sub-issues to their parent epics
- ✅ Apply proper labels (epic, sub-issue, priority, etc.)

**Total**: 2 parent epics + 13 sub-issues = **15 issues created automatically**

---

## 📋 What Gets Created

### Epic #1: Time-Based Order Fulfillment
**Parent Issue** with 6 sub-issues:
- 🔹 Sub-Issue 1.1: Order Validator Service (3 SP)
- 🔹 Sub-Issue 1.2: Checkout Pane - Dynamic Form Alteration (5 SP)
- 🔹 Sub-Issue 1.3: Order Placement Validation Event Subscriber (2 SP)
- 🔹 Sub-Issue 1.4: Admin Configuration Form (2 SP)
- 🔹 Sub-Issue 1.5: Automated Testing (3 SP)
- 🔹 Sub-Issue 1.6: Documentation & User Guide (1 SP)

**Total**: 16 Story Points (8-10 days)

### Epic #2: Delivery Radius Validation
**Parent Issue** with 7 sub-issues:
- 🔹 Sub-Issue 2.1: Enhance Delivery Radius Calculator Service (2 SP)
- 🔹 Sub-Issue 2.2: Shipping Method Integration (2 SP)
- 🔹 Sub-Issue 2.3: Interactive Map Component (4 SP)
- 🔹 Sub-Issue 2.4: Checkout Pane - Address Validation UI (3 SP)
- 🔹 Sub-Issue 2.5: Admin Configuration & Store Settings (1 SP)
- 🔹 Sub-Issue 2.6: Automated Testing (2 SP)
- 🔹 Sub-Issue 2.7: Documentation & User Guide (1 SP)

**Total**: 15 Story Points (6-8 days)

---

## 🔗 Understanding Sub-Issues in GitHub

### What are Sub-Issues?

Sub-issues are **child tasks** that belong to a parent issue (epic). GitHub displays them hierarchically:

```
📦 Epic #1: Time-Based Order Fulfillment (#123)
   ├─ 🔹 Sub-Issue 1.1: Order Validator Service (#124)
   ├─ 🔹 Sub-Issue 1.2: Checkout Pane Enhancement (#125)
   └─ 🔹 Sub-Issue 1.3: Event Subscriber (#126)
```

### Benefits:
- ✅ Track progress: Parent issue shows completion percentage
- ✅ Organization: Group related tasks together
- ✅ Planning: Use GitHub Projects with hierarchical views
- ✅ Copilot Integration: Copilot can understand task context

---

## 🛠️ Manual Creation (Alternative Method)

If you prefer to create issues manually or the script doesn't work:

### Step 1: Create Parent Epic Issue

1. Go to your repository's **Issues** tab
2. Click **New Issue**
3. Copy content from `.github/ISSUE_EPIC_1.md`
4. Set:
   - **Title**: `Epic #1: Time-Based Order Fulfillment with Store Hours Validation`
   - **Labels**: `epic`, `priority:high`, `feature`, `enhancement`
   - **Body**: Paste the content
5. Click **Submit new issue**
6. Note the issue number (e.g., #123)

### Step 2: Create Sub-Issues

For each sub-issue (e.g., Sub-Issue 1.1):

1. Click **New Issue**
2. Set:
   - **Title**: `[Epic #1] Sub-Issue 1.1: Order Validator Service`
   - **Labels**: `epic`, `sub-issue`, `backend`, `php`
   - **Body**: Copy acceptance criteria from `.github/ISSUE_EPIC_1.md`
3. Submit the issue
4. In the new issue, scroll to the bottom
5. Click **"Add parent issue"** → Select the parent epic (#123)
6. The sub-issue is now linked!

### Step 3: Repeat for All Sub-Issues

Repeat Step 2 for:
- Sub-Issues 1.2 through 1.6 (Epic #1)
- Sub-Issues 2.1 through 2.7 (Epic #2)

---

## 🤖 Using with GitHub Copilot

### In GitHub Copilot Workbench

1. **Open Copilot Chat** in your IDE or on GitHub.com
2. Reference the epic:
   ```
   @workspace Based on issue #123 (Epic #1), help me implement Sub-Issue 1.1
   ```
3. Copilot will:
   - Understand the full context of the epic
   - Know the acceptance criteria
   - Generate code that aligns with the specification

### In Pull Requests

When creating PRs, reference the sub-issue:
```
Closes #124

This PR implements Sub-Issue 1.1 from Epic #1:
- Created OrderValidator service
- Added validateFulfillmentTime() method
- Implemented unit tests
```

GitHub will automatically:
- Link the PR to the sub-issue
- Update the parent epic's checklist when merged
- Show progress on the epic

---

## 📊 Tracking Progress

### In GitHub Issues

The parent epic shows a checklist:
```
- [x] Sub-Issue 1.1: Order Validator Service (#124) ✅
- [ ] Sub-Issue 1.2: Checkout Pane Enhancement (#125)
- [ ] Sub-Issue 1.3: Event Subscriber (#126)
```

When you close a sub-issue, the parent epic's checklist updates automatically!

### In GitHub Projects

1. Create a **Project** (Projects tab)
2. Add both epics and all sub-issues
3. Use **Hierarchy View** to see the tree structure
4. Filter by:
   - Epic (show all tasks for Epic #1)
   - Assignee (show all tasks assigned to @drupal-developer)
   - Status (show all "In Progress" tasks)

### Progress Tracking

The script output will show you all issue numbers:
```
Epic #1: #123
  Sub-Issue 1.1: #124
  Sub-Issue 1.2: #125
  ...
Epic #2: #130
  Sub-Issue 2.1: #131
  ...
```

Save this output! You can use it to reference issues in commits and PRs.

---

## 🔍 Verifying the Issues

After running the script, verify everything was created:

```bash
# List all issues with "epic" label
gh issue list --label epic

# View a specific issue (--json avoids exit code 1 from Projects classic deprecation warning)
gh issue view 123 --json title,body,labels,state,number 2>/dev/null

# List all sub-issues for Epic #1
gh issue list --label sub-issue --search "Epic #1"
```

---

## 🎨 Customizing Labels

The script creates these labels automatically:
- `epic` - Parent epic issues
- `sub-issue` - Child task issues
- `priority:high` / `priority:medium` - Priority levels
- `feature` - New feature work
- `enhancement` - Improvements to existing features
- `backend` - Backend/PHP work
- `frontend` - Frontend/JavaScript work
- `testing` - QA/testing work
- `documentation` - Documentation work

**To add more labels**, edit `.github/create-epic-issues.sh` and modify the `--label` flags.

---

## 🐛 Troubleshooting

### Issue: "gh: command not found"
**Solution**: Install GitHub CLI (see Prerequisites section)

### Issue: "authentication required"
**Solution**: Run `gh auth login` and follow prompts

### Issue: "failed to create issue"
**Possible causes**:
1. Not authenticated: `gh auth login`
2. No write permissions to repository
3. Network issues: Check your internet connection

### Issue: Sub-issues not linking to parent
**Solution**: 
- The `gh issue edit --add-parent` feature requires GitHub CLI 2.40.0+
- Update GitHub CLI: `brew upgrade gh` (macOS) or `sudo apt upgrade gh` (Linux)
- If still not working, manually link in the GitHub UI:
  1. Open the sub-issue
  2. Scroll to bottom
  3. Click "Add parent issue"
  4. Select the parent epic

### Issue: Labels not created
**Solution**: 
Create labels manually first, or remove the `--label` flags from the script temporarily.

To create labels via CLI:
```bash
gh label create "epic" --description "Parent epic issues" --color "0052CC"
gh label create "sub-issue" --description "Child task issues" --color "0E8A16"
```

---

## 📚 Additional Resources

### GitHub Documentation
- [GitHub Issues Documentation](https://docs.github.com/en/issues)
- [Sub-issues Feature](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/adding-sub-issues)
- [GitHub CLI Manual](https://cli.github.com/manual/)
- [GitHub Projects](https://docs.github.com/en/issues/planning-and-tracking-with-projects)

### GitHub Copilot
- [Planning a Project with Copilot](https://docs.github.com/en/copilot/tutorials/plan-a-project)
- [Creating Issues with Copilot](https://docs.github.com/en/copilot/how-tos/use-copilot-for-common-tasks/use-copilot-to-create-or-update-issues)
- [Copilot Workbench](https://github.com/copilot)

---

## ✅ Next Steps

After creating the issues:

1. **Organize in a Project**:
   ```bash
   # Create a project
   gh project create --owner micronugget --title "Friday Night Skate - Core Commerce"
   
   # Add all epic issues to the project
   gh project item-add PROJECT_NUMBER --owner micronugget --url https://github.com/micronugget/duccinisv3/issues/123
   ```

2. **Assign Team Members**:
   ```bash
   gh issue edit 124 --add-assignee username
   ```

3. **Set Milestones**:
   ```bash
   gh issue edit 123 --milestone "Phase 1"
   ```

4. **Start Development**:
   - Developers can pick up sub-issues
   - Create feature branches: `feature/epic1-sub1.1-order-validator`
   - Reference issues in commits: `git commit -m "feat: add OrderValidator service (#124)"`
   - Create PRs that close sub-issues: `Closes #124`

---

## 🎉 Success!

You now have:
- ✅ Properly formatted GitHub Planning Issues
- ✅ Hierarchical epic/sub-issue structure
- ✅ Automated creation script
- ✅ Full project breakdown with story points
- ✅ Ready for GitHub Copilot integration

**Happy coding! 🚀**

---

## 💡 Pro Tips

1. **Use Copilot in PRs**: When creating a PR, mention the sub-issue number and Copilot will understand the context.

2. **Task Branches**: Create branches per sub-issue: `feature/epic1-sub1.1`, `feature/epic1-sub1.2`, etc.

3. **Daily Standups**: Use `gh issue list --assignee @me --label epic` to see your assigned tasks.

4. **Progress Reports**: Use GitHub's burndown charts in Projects to track epic completion.

5. **Documentation**: Keep `.github/ISSUE_EPIC_*.md` files as the source of truth. Update them as requirements evolve.

---

**Questions?** Open a discussion in your repository or consult the GitHub Copilot Enterprise documentation.
