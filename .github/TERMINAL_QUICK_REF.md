# Copilot Terminal Quick Reference Card

## 🚨 CRITICAL: Read This When Running Commands

### The Golden Rules

| Rule | Pattern | Why |
|------|---------|-----|
| **1. Never Background Output Commands** | `isBackground: false` | You need to read the result |
| **2. Always Add Markers** | `echo "=== Step ===" && cmd && echo "=== Done ==="` | Makes parsing reliable |
| **3. Capture All Output** | `command 2>&1` | Get both stdout and stderr |
| **4. Verify Explicitly** | `cmd && echo "Exit: $?"` | Don't assume success |
| **5. Limit Verbose Output** | `cmd \| head -50` | Prevent buffer overflow |

## 📋 Copy-Paste Patterns

**Note:** Adapt these patterns to your project's specific tools (Docker, DDEV, npm, etc.)

### Start Development Environment
```bash
echo "=== Starting Environment ===" && \
dev-start-command 2>&1 && \
echo "=== Status ===" && \
dev-status-command | grep -E "STATUS|RUNNING"
```

### Run Application Command
```bash
echo "=== Running: COMMAND ===" && \
app-command --option 2>&1 && \
echo "=== Exit Code: $? ==="
```

### Install Package
```bash
echo "=== Installing: PACKAGE ===" && \
package-manager install PACKAGE 2>&1 | tee /tmp/install.log && \
echo "=== Done: Exit Code $? ==="
```

### Run Tests
```bash
echo "=== Running Tests ===" && \
test-command 2>&1 | tee /tmp/test.log && \
EXIT_CODE=$? && \
echo "=== Tests Exit Code: $EXIT_CODE ==="
```

### Health Check
```bash
echo "=== Health Check ===" && \
status-command | head -10 && \
app-verify-command | grep -E "VERSION|STATUS"
```

## 🔍 Debugging Failed Commands

If Copilot says "nothing happened":

```bash
# Check what actually ran
history | tail -5

# Check DDEV status
ddev list && ddev describe

# Check for errors
ddev logs | tail -50

# Verify Drupal status
ddev drush status
```

## ✅ Verification Checklist

Before reporting "complete":

- [ ] Command ran with `isBackground: false`
- [ ] Output was captured with `2>&1`
- [ ] Exit code was checked
- [ ] Result was verified (file exists, service running, etc.)
- [ ] You actually READ the output and can confirm success

## 🎯 Common Mistakes

| ❌ Don't Do This | ✅ Do This Instead |
|------------------|-------------------|
| `ddev start` | `echo "Starting..." && ddev start 2>&1 && echo "Done"` |
| `isBackground: true` for commands needing output | `isBackground: false` |
| `ddev cmd` without verification | `ddev cmd 2>&1 && ddev describe \| head -5` |
| Long chains without markers | Add `echo` statements between steps |
| Assuming success | Check `$?` or grep for success messages |

## � GitHub CLI (`gh`) Gotcha

`gh issue view --repo owner/repo` returns **exit code 1** when the org has
Projects (classic) enabled, due to a GraphQL deprecation warning printed to
stderr. This kills the command even though the data was fetched successfully.

**Fix: always use `--json` + `2>/dev/null`:**

```bash
# ❌ Fails with exit code 1 (GraphQL deprecation warning)
gh issue view 77 --repo micronugget/duccinisv3 2>&1

# ✅ Correct — suppress stderr, request JSON
gh issue view 77 --repo micronugget/duccinisv3 \
  --json title,body,labels,state,number 2>/dev/null
```

The `--json` flag bypasses the text renderer that triggers the warning, and
`2>/dev/null` drops the deprecation stderr so the exit code stays 0.

## �📖 Full Documentation

- **Comprehensive Guide:** `.github/copilot-terminal-guide.md`
- **Command Patterns Script:** `.github/ddev-command-patterns.sh`
- **Environment Manager Agent:** `.github/agents/environment-manager.agent.md`

---

**Print this and keep it next to your keyboard! 🖨️**

