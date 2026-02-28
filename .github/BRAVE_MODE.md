---
name: Brave Mode Configuration
description: Configuration for autonomous AI agent operation without confirmation prompts. Enables agents to investigate, code, test, and execute commands freely.
tags: [brave-mode, autonomous, configuration, preferences]
version: 1.0.0
---

# Brave Mode Configuration for GitHub Copilot

## Overview

This configuration enables "brave mode" for GitHub Copilot agents, allowing them to work autonomously without requiring confirmation for standard operations like investigating code, making changes, running tests, or executing terminal commands.

## What is Brave Mode?

**Brave Mode** means agents will:
- ✅ Execute terminal commands automatically
- ✅ Make code changes without asking
- ✅ Run tests and report results
- ✅ Investigate issues proactively
- ✅ Apply fixes directly
- ✅ Create/modify files as needed

**Brave Mode does NOT mean:**
- ❌ Deleting files without notice
- ❌ Making destructive changes to production
- ❌ Ignoring errors or warnings
- ❌ Skipping validation steps

## How to Enable Brave Mode

### Method 1: Use Brave Mode Instructions (Recommended)

When starting a conversation with GitHub Copilot, include:

```
@workspace Use brave mode: execute commands, make changes, and run tests
without asking for confirmation. Report results directly.
```

### Method 2: Reference This File

```
@workspace Follow brave mode configuration from .github/BRAVE_MODE.md
```

### Method 3: Set as Default in Custom Instructions

Add to your Copilot custom instructions (if supported):

```markdown
## Brave Mode (Default)

Unless explicitly told otherwise, operate in brave mode:
- Execute commands immediately and report results
- Make code changes without requesting approval
- Run tests automatically after changes
- Investigate issues proactively
- Apply fixes directly when confident
```

## Brave Mode Behaviors by Agent

### Developer Agents (Drupal, Ansible, Media, etc.)

**DO automatically:**
- Install dependencies
- Generate code files
- Modify existing code
- Run build commands
- Execute database migrations
- Clear caches
- Import/export configuration
- Run `ddev drush` commands (cache clear, entity operations, config import/export, status checks, log inspection, user login links, module enable/disable, update hooks)
- Run `ddev drush ev` for read-only evaluation/inspection scripts

**Example behavior:**
```
User: "Add a new field to the content type"
Agent: *Creates field configuration, runs updates, exports config, clears cache*
       "✓ Added field_example to content_type. Changes exported to config/sync/"
```

### Tester Agent

**DO automatically:**
- Run full test suites
- Execute specific tests
- Run linters and code quality checks
- Generate coverage reports
- Fix simple code standard violations

**Example behavior:**
```
User: "Are the tests passing?"
Agent: *Runs test suite*
       "✓ 45/45 tests passed. Coverage: 87%"
```

### Environment Manager Agent

**DO automatically:**
- Start/stop development environment
- Install system dependencies
- Configure services
- Run health checks
- Execute setup scripts

**Example behavior:**
```
User: "Set up the environment"
Agent: *Runs setup script, starts services, verifies status*
       "✓ Environment ready. DDEV running, database connected, cache cleared"
```

### Database Administrator Agent

**DO automatically:**
- Create backups
- Run migrations
- Optimize queries
- Analyze performance
- Generate reports

**Example behavior:**
```
User: "Back up the database"
Agent: *Creates backup, verifies integrity*
       "✓ Backup created: /tmp/backup-20260211.sql.gz (45MB)"
```

### Performance Engineer Agent

**DO automatically:**
- Run benchmarks
- Execute load tests
- Generate performance reports
- Analyze bottlenecks
- Test optimizations

**Example behavior:**
```
User: "How's the performance?"
Agent: *Runs lighthouse audit*
       "✓ Performance score: 92/100. LCP: 1.2s, FID: 45ms"
```

### Security Specialist Agent

**DO automatically:**
- Run security scans
- Check for vulnerabilities
- Analyze dependencies
- Test SSL/TLS configuration
- Generate security reports

**Example behavior:**
```
User: "Scan for security issues"
Agent: *Runs security scanner*
       "✓ No critical vulnerabilities found. 2 medium issues identified (see report)"
```

## Brave Mode Guidelines

### ✅ When to Act Immediately

Execute without confirmation when:
1. **Read-only operations** - Investigating, analyzing, reading files
2. **Standard development tasks** - Installing packages, running builds
3. **Testing** - Running test suites, linters, code quality checks
4. **Reversible changes** - Code changes in version control
5. **Documented operations** - Following established procedures
6. **Low-risk commands** - Status checks, health checks, reports

### ⚠️ When to Ask First

**ALWAYS ask before:**
1. **Destructive operations** - Deleting files, dropping databases
2. **Production changes** - Deploying to production, modifying live data
3. **Major refactoring** - Large-scale code restructuring
4. **Security changes** - Modifying authentication, permissions, encryption
5. **Irreversible operations** - Permanent data deletion, account removal
6. **Cost-incurring actions** - Cloud resource provisioning, API calls with fees

### 🎯 Brave Mode Decision Matrix

| Action Type | Brave Mode | Reason |
|------------|------------|--------|
| Run tests | ✅ Execute | Safe, reversible, expected |
| Install npm package | ✅ Execute | Tracked in package.json, reversible |
| Create new file | ✅ Execute | Version controlled, reversible |
| Modify existing code | ✅ Execute | Version controlled, reversible |
| Delete file | ⚠️ Ask | Could be destructive |
| Deploy to production | ⚠️ Ask | High impact, not reversible |
| Drop database | ⚠️ Ask | Data loss risk |
| Modify user permissions | ⚠️ Ask | Security implications |
| Run load test | ✅ Execute | Standard testing, monitored |
| Clear cache | ✅ Execute | Safe, standard operation |
| Backup database | ✅ Execute | Safe, beneficial |
| Restore database | ⚠️ Ask | Could overwrite data |
| `ddev drush cr` | ✅ Execute | Safe cache rebuild |
| `ddev drush cex` | ✅ Execute | Config export, tracked in git |
| `ddev drush cim` | ✅ Execute | Config import (dev only) |
| `ddev drush updb` | ✅ Execute | Run pending update hooks |
| `ddev drush en` | ✅ Execute | Enable module |
| `ddev drush pmu` | ⚠️ Ask | Uninstall removes schema/data |
| `ddev drush entity:delete` | ⚠️ Ask | Data deletion |
| `ddev drush sql-drop` | ⚠️ Ask | Destructive, data loss |
| `ddev drush ev` (read-only) | ✅ Execute | Safe inspection/diagnosis |
| `ddev drush ev` (write ops) | ✅ Execute | Dev env mutations are reversible via git/db |
| `ddev drush uli` | ✅ Execute | Generate one-time login links |
| `ddev drush ws` / `watchdog:show` | ✅ Execute | Log inspection, read-only |
| **Shell / Filesystem** | | |
| `find` / `ls` / `tree` / `stat` | ✅ Execute | Filesystem inspection, read-only |
| `grep` / `cat` / `head` / `tail` | ✅ Execute | File content inspection |
| `sed` / `awk` / `cut` / `sort` / `wc` | ✅ Execute | Text processing, read-only |
| `diff` / `xargs` / `tee` | ✅ Execute | Content analysis / chaining |
| `cp` | ✅ Execute | Creates copy, non-destructive |
| `mv` (rename/move) | ✅ Execute | Reversible via git |
| `rm` / `rmdir` | ⚠️ Ask | File deletion, hard to reverse |
| `chmod` / `chown` | ⚠️ Ask | Permission/ownership changes |

## Communication Style in Brave Mode

### ✅ DO: Report Actions Taken

```
✓ Installed package drupal/admin_toolbar
✓ Ran composer install - exit code 0
✓ Cleared Drupal cache
✓ Ran 45 tests - all passed
✓ Created module file: web/modules/custom/example/example.module
```

### ✅ DO: Show Key Output

```
=== Test Results ===
PHPUnit: 45/45 passed
Coverage: 87%
PHPStan: Level 9 - no errors
```

### ❌ DON'T: Ask Unnecessary Questions

```
❌ "Should I run the tests?"
❌ "Do you want me to install this package?"
❌ "May I create this file?"
❌ "Is it okay to clear the cache?"
```

### ✅ DO: Warn About Risks

```
⚠️ Warning: This operation will modify 47 files. Recommend creating a git branch first.
Proceeding with changes...
```

## Brave Mode Agent Instructions

Add to each agent's context when in brave mode:

```markdown
## BRAVE MODE ACTIVE

You are operating in brave mode. This means:

1. **Execute commands immediately** - Use the terminal without asking
2. **Make changes directly** - Create/modify files as needed
3. **Run tests automatically** - Validate your changes
4. **Report results** - Show what you did and the outcome
5. **Ask only for destructive operations** - Deletions, production changes, etc.

Follow the terminal command best practices from .github/copilot-terminal-guide.md:
- Use `isBackground: false` for commands needing output
- Add echo markers around operations
- Capture all output with `2>&1`
- Verify success explicitly
- Report exit codes and key output

Example brave mode workflow:
User: "Add a new content type for articles"
You: *Creates content type config, exports configuration, clears cache*
     "✓ Created article content type with title, body, and image fields
      ✓ Configuration exported to config/sync/node.type.article.yml
      ✓ Cache cleared - exit code 0
      Ready to test: Visit /node/add/article"
```

## Quick Reference Commands
### Common `ddev drush` Commands (Auto-Execute in Brave Mode)

```bash
# Cache
ddev drush cr                          # Rebuild all caches

# Configuration
ddev drush cex                         # Export config to config/sync/
ddev drush cim                         # Import config from config/sync/
ddev drush cim --preview               # Dry-run — show what would change

# Database
ddev drush updb                        # Run pending update hooks
ddev drush sql-query 'SELECT ...'      # Run raw SQL (read queries)

# Modules
ddev drush en module_name              # Enable a module
ddev drush pm:list --status=enabled    # List enabled modules

# Entities / Inspection
ddev drush ev "echo ..."               # Evaluate PHP (inspection/diagnosis)
ddev drush entity:delete TYPE IDS      # ⚠️ Ask first — deletes entities

# Users
ddev drush uli --uid=N                 # One-time login link
ddev drush user:info --uid=N           # User details

# Logs
ddev drush watchdog:show               # Recent logs
ddev drush watchdog:show --count=50    # More log entries

# Status
ddev drush status                      # Environment overview
ddev drush core:requirements           # Check system requirements
```
### For Users

**Enable brave mode for current session:**
```
Use brave mode - execute commands and make changes without asking
```

**Disable brave mode:**
```
Exit brave mode - ask before making changes
```

**Brave mode for specific task:**
```
In brave mode: run all tests and fix any failures
```

### For Agents

When you see "brave mode" or "BRAVE MODE ACTIVE", follow these rules:

```bash
# Instead of asking...
"Should I run the tests?"

# Just do it...
echo "=== Running Tests ===" && \
npm test 2>&1 && \
echo "=== Tests Complete: Exit Code $? ==="
```

## Implementation Checklist

To fully enable brave mode in your project:

- [ ] Add this file to `.github/BRAVE_MODE.md`
- [ ] Reference in agent instructions when needed
- [ ] Add to `.github/README.md` for visibility
- [ ] Train team on brave mode usage
- [ ] Document destructive operations that still require approval
- [ ] Set up git hooks for safety (optional)
- [ ] Configure branch protection rules (optional)

## Safety Mechanisms

Even in brave mode, maintain safety through:

### 1. Version Control
All changes are in Git, making them reversible:
```bash
# Undo last change
git reset --hard HEAD~1

# Revert specific file
git checkout HEAD -- path/to/file
```

### 2. Branch Strategy
Work in feature branches:
```bash
# Create branch for brave mode work
git checkout -b feature/brave-mode-changes
```

### 3. Automatic Backups
Before major operations:
```bash
# Environment Manager can auto-backup
ddev export-db --gzip --file=/tmp/pre-brave-backup.sql.gz
```

### 4. Dry Run Options
Use when available:
```bash
# Ansible playbooks
ansible-playbook --check playbook.yml

# Drupal config import
drush config:import --preview
```

## Examples of Brave Mode in Action

### Example 1: Install and Configure Module

**Without Brave Mode:**
```
User: "Install the admin toolbar module"
Agent: "I can install drupal/admin_toolbar. Should I proceed?"
User: "Yes"
Agent: "Should I enable it?"
User: "Yes"
Agent: "Should I clear the cache?"
User: "Yes"
Agent: *Finally does the work*
```

**With Brave Mode:**
```
User: "Install the admin toolbar module"
Agent: *Immediately executes*
       ✓ composer require drupal/admin_toolbar - done
       ✓ drush en admin_toolbar - enabled
       ✓ drush cr - cache cleared
       "Admin toolbar installed and ready at /admin/toolbar"
```

### Example 2: Run Tests and Fix Failures

**Without Brave Mode:**
```
User: "Run the tests"
Agent: "Should I run PHPUnit?"
User: "Yes"
Agent: "2 tests failed. Should I investigate?"
User: "Yes"
Agent: "I found the issue. Should I fix it?"
User: "Yes"
Agent: "Should I run tests again?"
User: "YES!"
```

**With Brave Mode:**
```
User: "Run the tests"
Agent: *Runs tests, finds failures, fixes them, re-runs*
       ✗ Initial run: 43/45 passed (2 failures)
       ✓ Fixed: Missing use statement in UserTest.php
       ✓ Fixed: Incorrect assertion in NodeTest.php
       ✓ Second run: 45/45 passed
       "All tests passing. Fixes committed."
```

### Example 3: Debug Performance Issue

**With Brave Mode:**
```
User: "Why is the homepage slow?"
Agent: *Runs profiler, analyzes results, identifies issue, applies fix, validates*
       "Performance Analysis:
       - Ran XHProf profiler
       - Identified: 47 database queries (N+1 problem in view)
       - Applied: Added entity query caching
       - Before: 3.2s load time
       - After: 0.8s load time
       ✓ Fix applied and verified"
```

## Disabling Brave Mode

If you need to exit brave mode mid-session:

```
Exit brave mode - ask before making changes
```

Or for a specific cautious operation:

```
Carefully check the database - don't make any changes without asking
```

## Best Practices

1. **Use brave mode for development work** - Fast iteration, rapid testing
2. **Exit brave mode near production** - Extra caution for deployments
3. **Trust but verify** - Review agent changes in git diff
4. **Set clear boundaries** - Define destructive operations clearly
5. **Maintain safety nets** - Git, backups, branch protection

## Troubleshooting

### Agent still asking questions?

**Solution:** Be more explicit:
```
BRAVE MODE: Just do it. Run tests, fix issues, don't ask.
```

### Agent making too many changes?

**Solution:** Narrow the scope:
```
Brave mode limited to testing only - don't modify code
```

### Need to revert brave mode changes?

**Solution:** Use git:
```bash
git diff                    # See what changed
git checkout -- file.php    # Revert specific file
git reset --hard HEAD~1     # Undo last commit
```

---

## Summary

Brave mode makes GitHub Copilot work like Junie's brave option:
- ✅ **Autonomous operation** - Agents act without confirmation
- ✅ **Faster workflows** - No approval friction
- ✅ **Proactive fixes** - Agents solve problems directly
- ✅ **Safety preserved** - Git, backups, boundaries maintained

**Default activation:**
```
@workspace Use brave mode
```

**Result:** Agents execute, create, test, and report - without asking permission for standard operations.

---

**Version:** 1.0.0
**Created:** February 11, 2026
**Compatibility:** All GitHub Copilot agents
**Safety Level:** High (with version control)

