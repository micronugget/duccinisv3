---
description: "Close a GitHub issue on duccinisv3 — fetches issue details, explores the codebase, implements the fix, runs tests, exports config, commits, and reports commands for brave-mode approval."
name: "Close Issue"
argument-hint: "GitHub issue URL or number (e.g. 30 or https://github.com/micronugget/duccinisv3/issues/30)"
agent: "agent"
---

Close the GitHub issue provided by the user.

Follow all rules in [copilot-instructions.md](../copilot-instructions.md) and [copilot-terminal-guide.md](../copilot-terminal-guide.md).

## Brave Mode

**Proceed without asking for confirmation** on the following command categories — they are local, reversible, and safe:

| Category | Commands |
|---|---|
| DDEV environment | `ddev status`, `ddev describe`, `ddev exec …` |
| Cache / config | `ddev drush cr`, `ddev drush cex` |
| Code quality | `ddev exec vendor/bin/phpcs …`, `ddev exec vendor/bin/phpstan …` |
| Tests | `ddev exec vendor/bin/phpunit …` (all read-only test runs) |
| Composer | `ddev composer install`, `ddev composer require …` (no destructive flags) |
| Build | `ddev npm run dev`, `ddev exec "cd … && npm run dev"` |
| Git (read) | `git status`, `git log`, `git diff`, `git branch` |
| Git (write, local) | `git add`, `git commit` |
| File reads | `cat`, `grep`, `find`, `head`, `tail`, `wc` |
| Drupal entity ops | `ddev drush entity:delete` (cleanup only) |

**Always ask before running:**
- `git push` or `git push --force` — visible to collaborators
- `ddev drush cim -y` — could overwrite local config
- `gh issue close` — publicly closes the issue
- Any `DROP TABLE`, `DELETE FROM`, or destructive DB operations
- Any command that modifies `web/sites/default/settings.php`

---

## Step 1 — Fetch Issue Details

Run the following, replacing `$ISSUE` with the number extracted from the user's input:

```bash
echo "=== Fetching issue ===" && \
gh issue view $ISSUE --repo micronugget/duccinisv3 2>&1
```

If `gh` is not authenticated, halt and ask the user to run `gh auth login`.

Parse from the output:
- **Title** and **body** (acceptance criteria / description)
- **Labels** (signals which agent/area is relevant)
- **Linked PRs or branches** (check if work already started)

---

## Step 2 — Verify DDEV Environment

```bash
echo "=== DDEV Status ===" && ddev status 2>&1 | head -20
```

If DDEV is not running (status ≠ `running`):
```bash
ddev start 2>&1 | tail -10
```

---

## Step 3 — Understand the Codebase

Based on the issue title and labels, search for relevant files. Use `search_subagent` or direct searches — do **not** guess file locations. Pay special attention to:

- `web/modules/custom/store_fulfillment/` — core business logic
- `web/themes/custom/duccinis_1984_olympics/` — theme / Twig / SCSS
- `config/sync/` — configuration
- `web/modules/custom/` — other custom modules

Load any relevant `.instructions.md` files from `.github/instructions/` before writing code.

---

## Step 4 — Plan the Work

Before writing any code:
1. List the files that need to change.
2. Identify whether config export (`ddev drush cex`) will be needed.
3. Identify whether new tests are needed (any new functionality → yes).
4. State the acceptance criteria from the issue and how you will verify each one.

---

## Step 5 — Implement

Write code following all rules in `copilot-instructions.md`:
- `declare(strict_types=1);` on every new PHP file
- No `\Drupal::service()` inside service classes
- Never modify `web/core/` or `vendor/`
- Checkout pane visibility, AJAX, and billing ownership patterns per `store-fulfillment.instructions.md`

---

## Step 6 — Quality Gates

Run these in sequence. **Do not skip.** Each must pass before proceeding to the next.

### 6a. Coding Standards
```bash
echo "=== PHPCS ===" && \
ddev exec vendor/bin/phpcs --standard=Drupal \
  web/modules/custom/store_fulfillment/src \
  2>&1 | head -50
```

Fix any errors before continuing.

### 6b. Cache Rebuild
```bash
echo "=== Cache Rebuild ===" && ddev drush cr 2>&1 | tail -5
```

### 6c. Tests
```bash
echo "=== PHPUnit ===" && \
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --colors=never 2>&1 | tail -20
```

All tests must pass. If any fail, fix them before proceeding.

### 6d. Config Export (if config changed)

If any UI configuration was changed, or any `.yml` files in `config/sync/` were modified:
```bash
echo "=== Config Export ===" && ddev drush cex -y 2>&1 | tail -10
```

---

## Step 7 — Commit

```bash
git add -A && \
git commit -m "fix: close issue #$ISSUE — <short description>"
```

Use a conventional commit message. Reference the issue number.

---

## Step 8 — Push and Close (requires confirmation)

**Ask the user before running these:**

```bash
git push origin HEAD
```

```bash
gh issue close $ISSUE --repo micronugget/duccinisv3 \
  --comment "Implemented in commit $(git rev-parse --short HEAD). All tests pass."
```

---

## Step 9 — Command Audit

At the end of every session, output this table filled with the actual commands run:

```
## Commands Run — Brave Mode Audit

| # | Command | Category | Auto-approvable? |
|---|---------|----------|-----------------|
| 1 | ddev status | environment | ✅ yes |
| 2 | gh issue view 30 … | read/fetch | ✅ yes |
| … | … | … | … |
```

Include a short **recommendation** section:
- Which new commands (if any) should be added to the brave-mode allow list
- Any commands that *required* confirmation but could safely be pre-approved in future
- Any commands that surfaced unexpected prompts or access issues, with suggested fixes
