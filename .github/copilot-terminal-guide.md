# GitHub Copilot Terminal Output Reliability Guide

## Problem Statement
GitHub Copilot agents (especially in JetBrains IDEs) sometimes fail to properly read terminal output after executing commands, leading to confusion about whether commands succeeded or failed.

## Root Causes
1. **Asynchronous Output:** Terminal output may not be immediately available when the agent checks
2. **Buffering:** Output can be buffered and not flushed immediately
3. **Missing Delimiters:** Without clear start/end markers, parsing can fail
4. **Background vs Foreground:** Misuse of background flag for commands that need output

## Solutions Implemented

### 1. Terminal Command Best Practices

#### ✅ DO: Use Explicit Output Capture
```bash
# Good: Explicit output redirection with clear markers
echo "=== Starting Operation ===" && \
command 2>&1 && \
echo "=== Operation Completed Successfully ==="
```

#### ✅ DO: Add Verification Steps
```bash
# Good: Command + immediate verification
command --action && \
verify-command | grep -E "expected|pattern"
```

#### ✅ DO: Use Short, Focused Commands
```bash
# Good: One clear operation per command
tool subcommand --option value
```

#### ❌ DON'T: Use Background Flag for Commands Needing Output
```bash
# Bad: This will hide the output you need
isBackground: true  # Only for servers/watch processes
```

#### ❌ DON'T: Chain Too Many Commands Without Markers
```bash
# Bad: Hard to parse which step failed
command1 && command2 && command3 && command4 && command5
```

### 2. Development Environment Command Patterns

**Note:** Examples below use generic command patterns. Adapt to your project's specific tools (Docker, DDEV, Vagrant, npm, etc.)

**Note:** Examples below use generic command patterns. Adapt to your project's specific tools (Docker, DDEV, Vagrant, npm, etc.)

#### Standard Command Pattern
```bash
# Pattern: Announce → Execute → Verify
echo "=== [OPERATION] ===" && \
your-dev-command 2>&1 && \
verify-command | head -5
```

#### Examples

**Starting Development Environment:**
```bash
echo "=== Starting Development Environment ===" && \
dev-start-command 2>&1 && \
echo "=== Checking Status ===" && \
dev-status-command | grep -E "KEY|STATUS"
```

**Running Application Commands:**
```bash
echo "=== Running Application Command ===" && \
app-command --option 2>&1 && \
echo "=== Command Complete ==="
```

**Installing Dependencies:**
```bash
echo "=== Installing Package ===" && \
package-manager install package-name 2>&1 | tee /tmp/install-output.log && \
echo "=== Installation Status: $? ==="
```

### 3. Output Capture Strategies

#### Strategy A: Redirect to File + Display
```bash
# Capture to file AND show on terminal
ddev phpunit 2>&1 | tee /tmp/test-output.log
echo "Exit code: $?"
cat /tmp/test-output.log | tail -20
```

#### Strategy B: Explicit Status Checks
```bash
# Run command and explicitly check result
ddev drush cim -y 2>&1
EXIT_CODE=$?
echo "=== Exit Code: $EXIT_CODE ==="
if [ $EXIT_CODE -eq 0 ]; then
  echo "✓ Config import successful"
else
  echo "✗ Config import failed"
fi
```

#### Strategy C: Progressive Output
```bash
# Show progress at each step
echo "Step 1/3: Starting DDEV" && ddev start 2>&1 && \
echo "Step 2/3: Running Composer" && ddev composer install 2>&1 && \
echo "Step 3/3: Clearing Cache" && ddev drush cr 2>&1 && \
echo "=== ALL STEPS COMPLETE ==="
```

### 4. Copilot Agent Instructions

When you (the AI agent) run terminal commands:

1. **ALWAYS set `isBackground: false` for commands where you need to read output**
2. **ADD echo statements before and after critical operations**
3. **USE `2>&1` to capture both stdout and stderr**
4. **VERIFY success with explicit checks** (exit codes, grep patterns, file existence)
5. **WAIT for command completion** - don't assume it worked, check the output
6. **ADD `| head -n 50` or `| tail -n 50`** to limit output for very verbose commands

### 5. PHPStorm-Specific Configuration

#### Terminal Settings (Optional)
Create `.idea/terminal.xml` if it doesn't exist:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<project version="4">
  <component name="TerminalProjectOptionsProvider">
    <option name="shellPath" value="/bin/bash" />
    <option name="tabName" value="Local" />
    <option name="closeSessionOnLogout" value="true" />
    <option name="reportMouse" value="true" />
  </component>
</project>
```

### 6. Quick Reference Table

| Scenario | Command Pattern | isBackground |
|----------|----------------|--------------|
| Start DDEV | `echo "Starting..." && ddev start 2>&1 && echo "Done"` | `false` |
| Run Tests | `ddev phpunit 2>&1 \| tee test.log` | `false` |
| Install Package | `ddev composer require pkg 2>&1 && echo "Exit: $?"` | `false` |
| Start Server | `ddev start` | Only if truly backgrounding |
| Check Status | `ddev describe \| head -10` | `false` |
| Drush Commands | `ddev drush cmd --yes 2>&1 && echo "Complete"` | `false` |
| Config status | `ddev drush cst --format=json 2>&1` | `false` |

### 6a. Known DDEV / Drush ANSI Quirks

#### `ddev drush config:status` / `ddev drush cst` — ANSI bold error

`ddev drush cst` (and `ddev drush config:status`) fails in this environment with:

```
Invalid option specified: "bold". Expected one of (bold, underscore, blink, reverse, conceal).
```

**Root cause:** The terminal's `$TERM` / colour-capability flags cause Drush's table formatter to request an unsupported "bold" attribute, causing a fatal error even before output is produced.

**Fix — always append `--format=json`:**

```bash
# ❌ Fails with ANSI bold error:
ddev drush cst 2>&1

# ✅ Works reliably:
ddev drush cst --format=json 2>&1
```

To inspect the output, pipe through Python for a readable summary:

```bash
ddev drush cst --format=json 2>&1 | python3 -c \
  "import json,sys; d=json.load(sys.stdin);
   states={}
   [states.update({v['state']: states.get(v['state'], []) + [k]}) for k,v in d.items()]
   [print(f'{s}: {len(items)} item(s)') for s,items in states.items()]"
```

To check whether `ddev drush cim -y` is safe to run (no "Only in DB" or "Different" entries):

```bash
ddev drush cst --format=json 2>&1 | python3 -c \
  "import json,sys; d=json.load(sys.stdin)
   unsafe=[k for k,v in d.items() if v['state'] in ('Only in DB','Different')]
   print('SAFE' if not unsafe else 'UNSAFE — review: ' + ', '.join(unsafe))"
```

### 7. Debugging Terminal Issues

If Copilot says "nothing happened" but the command ran:

**Diagnostic Command:**
```bash
# Run this to see recent command history and output
history | tail -5
echo "=== Last Exit Code: $? ==="
ddev describe | head -10
```

**Check for Zombie Processes:**
```bash
ps aux | grep -E "ddev|docker" | grep -v grep
```

**Verify DDEV State:**
```bash
ddev list && \
ddev describe && \
ddev exec pwd
```

### 8. Environment Manager Agent Template

When performing environment management tasks:

```bash
#!/bin/bash
# Environment Management Operation Template

set -e  # Exit on error
set -u  # Exit on undefined variable

# Clear operation marker
echo "=================================================="
echo "OPERATION: [Description]"
echo "TIMESTAMP: $(date)"
echo "=================================================="

# Step 1: Pre-check
echo "=== Pre-flight Checks ==="
ddev describe | grep -E "NAME|STATUS" || true

# Step 2: Execute main operation
echo "=== Executing Main Operation ==="
ddev [command] 2>&1

# Step 3: Verify result
EXIT_CODE=$?
echo "=== Exit Code: $EXIT_CODE ==="

# Step 4: Post-check
echo "=== Post-operation Status ==="
ddev describe | grep -E "NAME|STATUS" || true

# Step 5: Summary
if [ $EXIT_CODE -eq 0 ]; then
  echo "✓ Operation completed successfully"
else
  echo "✗ Operation failed with code $EXIT_CODE"
  exit $EXIT_CODE
fi

echo "=================================================="
echo "OPERATION COMPLETE: $(date)"
echo "=================================================="
```

## Validation Checklist

Before marking any terminal-based task complete:

- [ ] Command executed with `isBackground: false`
- [ ] Output captured with `2>&1`
- [ ] Success/failure explicitly verified
- [ ] Exit codes checked where applicable
- [ ] Clear markers (echo statements) for parsing
- [ ] Output confirmed readable by agent

## Future Improvements

Consider these enhancements:

1. **Structured Output:** Use JSON output where possible (`ddev describe --json-output`)
2. **Logging:** All critical commands log to `/tmp/ddev-operations.log`
3. **Notifications:** Success/failure notifications via desktop alerts
4. **Metrics:** Track command execution times and failure rates

---

**Last Updated:** March 15, 2026
**Maintained By:** Environment Manager Agent
**Related:** `.github/copilot-instructions.md`, `.github/agents/environment-manager.md`

