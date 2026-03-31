# Terminal Reliability Guide

## Core Rules — Always Follow

1. **Always read output** — use `isBackground: false` equivalent (interactive mode)
2. **Add markers** around operations so you know what succeeded
3. **Capture both stdout and stderr** with `2>&1`
4. **Verify success explicitly** — do not assume a command worked
5. **Limit verbose output** with `| head -50` or `| tail -50`

---

## Standard Pattern: Announce → Execute → Verify

```bash
echo "=== Starting Operation ===" && \
ddev command 2>&1 && \
echo "=== Operation Complete: Exit Code $? ==="
```

---

## DDEV Command Patterns

### Starting / Checking the Environment

```bash
echo "=== Starting DDEV ===" && \
ddev start 2>&1 && \
echo "=== Verifying Status ===" && \
ddev describe | grep -E "NAME|STATUS|PHP"
```

### Drush Commands

```bash
echo "=== Importing Config ===" && \
ddev drush cim --yes 2>&1 && \
EXIT_CODE=$? && \
echo "=== Config Import Exit Code: $EXIT_CODE ===" && \
ddev drush status | grep -E "Drupal|Database"
```

```bash
echo "=== Clearing Cache ===" && \
ddev drush cr 2>&1 && \
echo "=== Cache cleared ==="
```

### Config Export (safe pattern)

```bash
# Check for surprises before exporting:
echo "=== Config Status ===" && \
ddev drush config:status 2>&1 | grep -v 'Only in sync dir' | grep -v 'No differences' | head -20

# Then export:
echo "=== Config Export ===" && \
ddev drush cex -y 2>&1 | tail -10
```

### Composer

```bash
echo "=== Installing Package ===" && \
ddev composer require drupal/module_name 2>&1 | tee /tmp/composer.log && \
echo "=== Installation Complete: Exit Code $? ==="
```

### Tests

```bash
echo "=== Running PHPUnit Tests ===" && \
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --colors=never 2>&1 | tee /tmp/test-results.log && \
echo "=== Test Suite Exit Code: $? ===" && \
tail -20 /tmp/test-results.log
```

### Targeted tests (frontend-only issues)

```bash
echo "=== PHPUnit (targeted) ===" && \
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --filter=OrderPlacementDeliveryRadiusValidatorTest \
  --colors=never 2>&1 | tail -10
```

### Code Quality

```bash
echo "=== PHPCS ===" && \
ddev exec vendor/bin/phpcs --standard=Drupal \
  web/modules/custom/store_fulfillment/src \
  2>&1 | head -50
```

### Theme Build

```bash
# Always cd into the theme directory — root package.json has no 'dev' script.
echo "=== Compiling theme assets ===" && \
ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev" 2>&1 | tail -20 && \
echo "=== Build done ===" && \
ddev drush cr 2>&1 | tail -3
```

---

## Output Capture Strategies

### Capture to file AND display

```bash
ddev phpunit 2>&1 | tee /tmp/test-output.log
echo "Exit code: $?"
cat /tmp/test-output.log | tail -20
```

### Explicit exit code check

```bash
ddev drush cim -y 2>&1
EXIT_CODE=$?
if [ $EXIT_CODE -eq 0 ]; then
  echo "✓ Config import succeeded"
else
  echo "✗ Config import failed with exit code $EXIT_CODE"
fi
```

---

## Verification Commands

Run these after major operations:

```bash
# After DDEV changes
ddev describe | head -10

# After config changes
ddev drush status && ddev drush config:status | head -5

# After code changes
ls -la web/modules/custom/store_fulfillment/src/

# After theme build
ls -lh web/themes/custom/duccinis_1984_olympics/build/css/ | head -5
```

---

## Common Pitfalls

| Mistake | Fix |
|---|---|
| Running `npm run dev` from project root | `cd` into theme first: `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` |
| Forgetting `ddev drush cr` after template change | Cache must be cleared after any `.twig`, preprocess, library, or SDC change |
| `ddev drush cim -y` overwriting local config | Always run `ddev drush config:status` first to check for local-only changes |
| Piping huge output without limiting | Add `| head -50` or `| tail -20` to all verbose commands |
| Not capturing stderr in CI | Always append `2>&1` to DDEV commands |

---

## Quick Reference

```bash
ddev status                                          # check environment state
ddev drush cr                                        # clear all caches
ddev drush cex -y                                    # export config
ddev drush cim -y                                    # import config
ddev drush config:status                             # compare DB vs sync dir
ddev exec vendor/bin/phpunit --colors=never          # run all tests
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/store_fulfillment/src
ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"
ddev drush uli --uid=3 --uri=https://duccinisv4.ddev.site   # login link for Geena (saved cards)
```
