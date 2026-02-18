# 🚀 Brave Mode Quick Start

## What is Brave Mode?

**Brave Mode** = Copilot agents work autonomously without asking for permission.

Like Junie's brave option, it frees you from constant confirmations.

## How to Activate

### Option 1: Simple Command
```
@workspace Use brave mode
```

### Option 2: Explicit Instructions
```
@workspace Use brave mode - execute commands, make changes, and run tests without asking
```

### Option 3: In Your Prompt
```
In brave mode: install the admin toolbar module and configure it
```

## What Changes?

### ❌ Without Brave Mode
```
User: "Run the tests"
Agent: "Should I run PHPUnit?"
User: "Yes"
Agent: "Should I run PHPStan?"
User: "Yes"
Agent: "2 tests failed. Should I investigate?"
User: "YES JUST DO IT!"
```

### ✅ With Brave Mode
```
User: "Run the tests"
Agent: ✓ PHPUnit: 43/45 passed (2 failures)
       ✓ PHPStan: Level 9 - no errors
       ✓ Fixed: Missing use statement
       ✓ Re-ran tests: 45/45 passed
       "All tests passing"
```

## What Agents Will Do Automatically

✅ **Execute terminal commands** - No asking
✅ **Make code changes** - Direct edits
✅ **Run tests** - Automatic validation
✅ **Fix issues** - Proactive fixes
✅ **Create files** - As needed
✅ **Install packages** - When required
✅ **Clear caches** - Standard operations
✅ **Report results** - Show what happened

## What Agents Will STILL Ask About

⚠️ **Destructive operations** - File deletion
⚠️ **Production changes** - Deployments
⚠️ **Database drops** - Data loss risk
⚠️ **Security changes** - Permission modifications
⚠️ **Large refactoring** - Major restructuring

## Safety Mechanisms

All brave mode changes are safe because:

1. **Git Version Control** - All changes tracked and reversible
2. **Terminal Reliability** - Commands use verified patterns
3. **Explicit Warnings** - Agents warn about risks
4. **Validation** - Agents verify their changes worked

## Examples

### Install a Package
```
User: "Install admin toolbar"
Agent: ✓ composer require drupal/admin_toolbar
       ✓ drush en admin_toolbar
       ✓ drush cr
       "Installed and enabled at /admin/toolbar"
```

### Debug and Fix
```
User: "Tests are failing"
Agent: ✓ Ran tests - 2 failures identified
       ✓ Fixed missing imports
       ✓ Re-ran tests - all passing
       "Fixed. Ready to commit."
```

### Full Feature
```
User: "Add a content type for articles"
Agent: ✓ Created article content type
       ✓ Added fields: title, body, image
       ✓ Configured form display
       ✓ Configured view display
       ✓ Exported configuration
       ✓ Cleared cache
       "Article content type ready at /node/add/article"
```

## Deactivate Brave Mode

```
Exit brave mode - ask before making changes
```

Or for specific caution:
```
Carefully check this - don't change anything without asking
```

## Tips

💡 **Use brave mode for development** - Fast iteration
💡 **Review changes in git** - `git diff` shows everything
💡 **Create branches** - Work safely in feature branches
💡 **Trust but verify** - Agents report what they did
💡 **Set boundaries** - Specify "read-only" if needed

## Undo Changes

If an agent makes unwanted changes:

```bash
# See what changed
git diff

# Undo specific file
git checkout HEAD -- path/to/file

# Undo all changes
git reset --hard HEAD

# Undo last commit
git reset --hard HEAD~1
```

## Full Documentation

📖 **Complete Guide:** `.github/BRAVE_MODE.md`
📖 **Terminal Patterns:** `.github/copilot-terminal-guide.md`
📖 **Quick Reference:** `.github/TERMINAL_QUICK_REF.md`

---

## TL;DR

**Activate:** `@workspace Use brave mode`

**Result:** Agents do stuff without asking

**Safety:** Git makes everything reversible

**Like:** Junie's brave option for Copilot

---

**Just say "Use brave mode" and let Copilot work! 🚀**

