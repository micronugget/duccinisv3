# GitHub Copilot Usage Guide for This Project

## For Human Developers: How to Use Copilot Effectively

This project is configured with specialized instructions and patterns to help GitHub Copilot work reliably in PHPStorm. Here's how to get the best results.

## Quick Start

### 0. Brave Mode (Optional but Recommended)

**⭐ NEW: Enable autonomous operation without confirmation prompts**

Like the JetBrains Junie plugin's "brave" option, you can tell Copilot to work autonomously:

```
@workspace Use brave mode
```

**What this does:**
- ✅ Agents execute commands without asking
- ✅ Agents make changes without requesting approval
- ✅ Agents run tests automatically
- ✅ Agents fix issues proactively
- ✅ Agents still warn about destructive operations

**Examples:**

*Without brave mode:*
```
User: "Install admin toolbar"
Agent: "Should I run composer require?"
User: "Yes"
Agent: "Should I enable it?"
User: "Yes..."
```

*With brave mode:*
```
User: "Install admin toolbar"
Agent: ✓ Installed and enabled
       "Ready at /admin/toolbar"
```

**Full guide:** `.github/BRAVE_MODE.md` and `.github/BRAVE_MODE_QUICK_START.md`

### 1. When Starting a Copilot Session

**Always mention the agent role if working on specific tasks:**

- "As the **Drupal Developer**, create a custom module..."
- "As the **Environment Manager**, set up DDEV for..."
- "As the **Tester**, write PHPUnit tests for..."

See `.github/AGENT_DIRECTORY.md` for the complete list of specialized agents.

### 2. When Copilot Needs to Run Terminal Commands

✅ **Good prompts:**
- "Run DDEV status check and show me the output"
- "Install this Composer package and verify it worked"
- "Run the test suite and tell me if it passed"

❌ **Avoid:**
- "Can you check if DDEV is running?" (Just ask it to check!)
- "Would it be possible to..." (Just ask directly!)

### 3. Verifying Copilot's Actions

After Copilot runs a command, it should report back with:
- What command was run
- The output received
- Whether it succeeded or failed
- Exit codes (if applicable)

If Copilot says "nothing happened" or seems confused, ask it to:
1. "Check the last command's output again"
2. "Run a verification command to confirm"
3. "Show me the exit code"

## Understanding the Terminal Fix

### What Was the Problem?

Copilot agents in JetBrains IDEs sometimes couldn't properly read terminal output, leading to:
- Thinking commands failed when they succeeded
- Not recognizing completed operations
- Getting stuck in loops trying to do the same thing

### How Was It Fixed?

We implemented a standard command pattern that:
1. Announces what's happening (`echo "=== Starting... ==="`)
2. Runs the command with full output (`command 2>&1`)
3. Verifies success (`echo "Exit code: $?"`)
4. Confirms completion (`echo "=== Done ==="`)

### Example of the Pattern

Instead of just:
```bash
ddev drush cim
```

Copilot now uses:
```bash
echo "=== Importing Config ===" && \
ddev drush cim --yes 2>&1 && \
echo "=== Done: Exit Code $? ==="
```

This gives Copilot clear markers to parse and understand what happened.

## Available Resources

### For Quick Reference
- **`TERMINAL_QUICK_REF.md`** - One-page cheat sheet
- **`README.md`** - This directory overview

### For Comprehensive Information
- **`copilot-terminal-guide.md`** - Complete terminal patterns guide
- **`copilot-instructions.md`** - Full project standards

### For Working Examples
- **`ddev-command-patterns.sh`** - Executable examples you can run:
  ```bash
  source .github/ddev-command-patterns.sh
  pattern_health_check
  ```

## Common Copilot Tasks

### Task 1: "Set up my development environment"

Copilot should:
1. Run `.github/copilot-setup.sh`
2. Verify DDEV is running
3. Check Drupal installation
4. Report status of all components

### Task 2: "Install a new Drupal module"

Copilot should:
1. Run `ddev composer require drupal/module_name`
2. Enable with `ddev drush en module_name`
3. Export config with `ddev drush cex`
4. Verify installation

### Task 3: "Run the test suite"

Copilot should:
1. Run `ddev phpunit`
2. Show test results
3. Report pass/fail status
4. Show any error details if failed

### Task 4: "Import configuration"

Copilot should:
1. Run `ddev drush cim -y`
2. Clear cache
3. Verify Drupal status
4. Report any config errors

## Troubleshooting Copilot Behavior

### Issue: Copilot says "I can't see the output"

**Solution:** Ask it to:
```
Run the command again using the pattern from .github/TERMINAL_QUICK_REF.md
```

### Issue: Copilot keeps trying the same thing repeatedly

**Solution:** Ask it to:
```
Check if the operation already succeeded by verifying the current state
```

### Issue: Copilot isn't using the specialized agents

**Solution:** Remind it:
```
Check .github/AGENT_DIRECTORY.md for the right agent for this task
```

### Issue: Commands seem to hang or timeout

**Solution:** Ask it to:
```
Add output limiters (| head -50) to the command
```

## Best Practices for Working with Copilot

### ✅ DO:

1. **Be specific about what you want**
   - "Create a content type for skate sessions with these fields..."
   - Not: "Can you help me with content types?"

2. **Reference the agent system**
   - "As the Drupal Developer agent, implement..."
   - This loads the right context

3. **Ask for verification**
   - "After installing, verify it's working"
   - Copilot will run additional checks

4. **Request documentation**
   - "Document this in the README"
   - Copilot can update docs as it works

### ❌ DON'T:

1. **Don't ask permission unnecessarily**
   - Not: "Should I run this command?"
   - Instead: "Run this command"

2. **Don't accept vague responses**
   - If Copilot says "I'll try...", ask for concrete actions

3. **Don't skip verification**
   - Always ask Copilot to verify the changes worked

4. **Don't ignore the agent system**
   - Using specialized agents gives much better results

## Advanced: Custom Agent Mode

You can invoke specific agent modes by:

1. **Setting context in your prompt:**
   ```
   Acting as the Environment Manager agent, diagnose why DDEV won't start
   ```

2. **Referencing agent files directly:**
   ```
   Following the patterns in .github/agents/environment-manager.agent.md,
   set up a new DDEV service
   ```

3. **Using the Architect for coordination:**
   ```
   As the Architect agent, plan the implementation of a new media gallery feature
   and coordinate with the appropriate agents
   ```

## Testing the Terminal Fix

Want to verify the fix is working? Ask Copilot to:

```
Source the command patterns script and run the health check:
source .github/ddev-command-patterns.sh && pattern_health_check
```

You should see:
- Clear section headers
- All status information
- Success/failure indicators
- Exit codes
- Final summary

## Getting Help

### From Copilot
- "Show me the available command patterns"
- "What specialized agents are available?"
- "How should I structure this terminal command?"

### From Documentation
- `.github/AGENT_DIRECTORY.md` - Agent reference
- `.github/copilot-terminal-guide.md` - Terminal patterns
- `.github/copilot-instructions.md` - Project standards

### From Scripts
- `.github/ddev-command-patterns.sh` - Working examples
- `.github/copilot-setup.sh` - Environment setup
- `.github/check-setup.sh` - Quick verification

## Contributing Improvements

If you discover a new terminal pattern that works well:

1. Add it to `.github/ddev-command-patterns.sh`
2. Document it in `.github/copilot-terminal-guide.md`
3. Update `.github/TERMINAL_QUICK_REF.md` if it's commonly used

## Summary

The terminal reliability fix ensures Copilot can:
- ✅ Execute commands reliably
- ✅ Read and parse output correctly
- ✅ Verify success/failure
- ✅ Provide clear feedback
- ✅ Work consistently in PHPStorm

This makes Copilot a much more reliable development assistant for DDEV-based Drupal projects.

---

**Last Updated:** February 11, 2026
**For Questions:** See `.github/README.md` or project maintainers
**Related Files:** `TERMINAL_QUICK_REF.md`, `copilot-terminal-guide.md`

