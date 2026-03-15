---
description: "Close a GitHub issue on duccinisv4 — fetches issue details, explores the codebase, implements the fix, runs tests, exports config, commits, and reports commands for brave-mode approval."
name: "Close Issue"
argument-hint: "GitHub issue URL or number (e.g. 30 or https://github.com/micronugget/duccinisv4/issues/30)"
agent: "agent"
---

Close the GitHub issue provided by the user.

Follow all rules in [copilot-instructions.md](../copilot-instructions.md) and [copilot-terminal-guide.md](../copilot-terminal-guide.md).

## Brave Mode

**Proceed without asking for confirmation** on the following command categories — they are local, reversible, and safe:

| Category | Commands |
|---|---|
| DDEV environment | `ddev status`, `ddev describe`, `ddev exec …`, `ddev drush pm-list`, `ddev drush pm:list` |
| Cache / config | `ddev drush cr`, `ddev drush cex` |
| Code quality | `ddev exec vendor/bin/phpcs …`, `ddev exec vendor/bin/phpstan …`, `ddev exec vendor/bin/phpcbf …` |
| Tests | `ddev exec vendor/bin/phpunit …` (all read-only test runs) |
| Composer | `ddev composer install`, `ddev composer require …` (no destructive flags) |
| Build | `ddev npm run dev`, `ddev exec "cd … && npm run dev"`, `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"` |
| Git (read) | `git status`, `git log`, `git diff`, `git branch`, `git show` |
| Git (write, local) | `git add`, `git commit`, `git checkout`, `git checkout -b`, `git stash`, `git merge`, `git rebase`, `git cherry-pick`, `git push origin <feature-branch>` (non-force, feature/issue branches only) |
| File reads | `cat`, `grep`, `find`, `head`, `tail`, `wc`, `ls`, `sort`, `sed -n '…p'` |
| Drupal entity ops | `ddev drush entity:delete` (cleanup only) |
| Drupal module ops | `ddev drush en <module> -y` (reversible with `ddev drush pm:uninstall`) |
| Drupal module ops (post-enable sync) | `ddev drush cim -y` immediately after `ddev drush en … -y` + `ddev drush cex -y` — safe when used only to apply the newly-exported module list back; not to overwrite in-progress local config |
| Drupal recipes | `ddev drush recipe <path>` — applies a recipe; all actions are logged and reversible via `ddev drush cim` |
| Config import (additive only) | `ddev drush cim -y` **when** a prior `ddev drush config:status` confirms zero "Only in DB" and zero "Different" rows — i.e. the sync dir contains only "Only in sync dir" new entries; no existing config will be deleted or overwritten |
| GitHub CLI (read) | `gh issue view … --json … 2>/dev/null`, `gh issue list … 2>/dev/null` |
| GitHub CLI (issue tracking) | `gh issue create --repo … --title … --body …` — creates a tracking issue; no code affected |
| GitHub CLI (auth) | `gh auth login --hostname github.com --web` — re-authenticate when PAT scope errors occur |
| GitHub CLI (API read) | `gh api repos/…/secret-scanning/alerts …`, `gh api repos/…/secret-scanning/alerts/$N/locations …` — read-only security state queries |
| Drupal PHP eval | `ddev drush php:eval "…"` (read-only operations: UUID generation, entity queries, service calls with no side effects) |
| PHPStan baseline | `ddev exec vendor/bin/phpstan analyze … --generate-baseline=phpstan-baseline.php` — writes a local baseline file; analysis only, no side effects |

**Always ask before running:**
- `git push origin master` or `git push origin main` — pushes to the default branch, visible to all collaborators
- `git push --force` — destructive remote history rewrite
- `git-filter-repo …` — **Security remediation**: irreversibly rewrites local git history; confirm before running
- `gh api --method PATCH repos/…/secret-scanning/alerts/$N` — **Security remediation**: resolves/dismisses a public secret scanning alert; confirm before running
- `ddev drush cim -y` — could overwrite local config (**exception:** auto-approvable when `ddev drush config:status` shows zero "Only in DB" and zero "Different" rows — only "Only in sync dir" additive entries; see Brave Mode table)
- `gh issue close` — publicly closes the issue
- `gh pr create` — opens a public pull request
- Any `DROP TABLE`, `DELETE FROM`, or destructive DB operations
- Any command that modifies `web/sites/default/settings.php`

---

## Step 1 — Fetch Issue Details

Run the following, replacing `$ISSUE` with the number extracted from the user's input:

```bash
gh issue view $ISSUE --repo micronugget/duccinisv3 \
  --json title,body,labels,state,number 2>/dev/null
```

If `gh` is not authenticated, halt and ask the user to run:
```bash
gh auth login --hostname github.com --web
```
This opens a browser window to approve access. No token copying required.

> **Note:** Do **not** use the plain `gh issue view … 2>&1` form. Repos with
> Projects (classic) enabled return exit code 1 due to a GraphQL deprecation
> warning even when data is fetched successfully. The `--json` + `2>/dev/null`
> pattern is the only reliable approach.

Parse from the output:
- **Title** and **body** (acceptance criteria / description)
- **Labels** (signals which agent/area is relevant)
- **Linked PRs or branches** (check if work already started)

---

## Step 2 — Create a Feature Branch

> **⚠️ Branching rule for issues #94–141:** All migration epic branches **must base off `migration_branch`**, not `master`. PRs and merges target `migration_branch`; `migration_branch` → `master` only when the full epic is done.

Before writing any code, create and check out a branch named `issue/$ISSUE-<slug>` where `<slug>` is a short kebab-case summary of the issue title:

```bash
git checkout migration_branch && git checkout -b issue/$ISSUE-<slug>
```

Example: `git checkout migration_branch && git checkout -b issue/94-scrub-api-keys`

This keeps `master` clean. For issues #94–141 the PR base is `migration_branch`, not `master`.

---

## Step 3 — Verify DDEV Environment

> Already created your branch in Step 2? Good. Continue.

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
> **Frontend-only issues** (theme SCSS, Twig, libraries, JS — no PHP logic changed):  
> The full suite can hang in this environment. Running `--filter` on the nearest
> relevant test class is sufficient. Skip to step 6d if no PHP was modified.
>
> ```bash
> echo "=== PHPUnit (targeted) ==" && \
> ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
>   --filter=OrderPlacementDeliveryRadiusValidatorTest \
>   --colors=never 2>&1 | tail -10
> ```
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
# Push the feature branch
git push origin issue/$ISSUE-<slug>
```

```bash
# Open a PR targeting migration_branch (issues #94–141) or master (pre-#94)
gh pr create --repo micronugget/duccinisv3 \
  --base migration_branch \
  --head issue/$ISSUE-<slug> \
  --title "fix: close issue #$ISSUE — <short description>" \
  --body "Closes #$ISSUE"
```

```bash
# Close the issue
gh issue close $ISSUE --repo micronugget/duccinisv3 \
  --comment "Implemented in commit $(git rev-parse --short HEAD). All tests pass."
```

> **If any `gh` command fails with `Resource not accessible by personal access token`:**
> Run `gh auth login --hostname github.com --web` to re-authenticate with full repo scope.

---

## Step 9 — Command Audit

At the end of every session, output this table filled with the actual commands run:

```
## Commands Run — Brave Mode Audit

| # | Command | Category | Auto-approvable? |
|---|---------|----------|-----------------|
| 1 | ddev status | environment | ✅ yes |
| 2 | gh issue view 30 --json title,body,labels,state,number 2>/dev/null | GitHub CLI (read) | ✅ yes |
| … | … | … | … |
```

Include a short **recommendation** section:
- Which new commands (if any) should be added to the brave-mode allow list
- Any commands that *required* confirmation but could safely be pre-approved in future
- Any commands that surfaced unexpected prompts or access issues, with suggested fixes
