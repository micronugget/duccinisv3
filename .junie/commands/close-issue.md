---
description: Full close-issue flow — fetch issue, branch, implement, test, commit, push, PR, close
---

Close GitHub issue $issue on the duccinisv4 repo.

Follow all rules in `.junie/AGENTS.md` and `.junie/terminal-guide.md`.

## Brave Mode

Enable Brave mode (Ctrl+B) for this workflow — the following categories are safe to auto-execute:

- DDEV environment: `ddev status`, `ddev describe`, `ddev exec …`
- Cache / config: `ddev drush cr`, `ddev drush cex`
- Code quality: `ddev exec vendor/bin/phpcs …`, `ddev exec vendor/bin/phpstan …`, `ddev exec vendor/bin/phpcbf …`
- Tests: `ddev exec vendor/bin/phpunit …`
- Composer: `ddev composer install`, `ddev composer require …`
- Build: `ddev exec "cd web/themes/custom/duccinis_1984_olympics && npm run dev"`
- Git (read): `git status`, `git log`, `git diff`, `git branch`, `git show`
- Git (write, local): `git add`, `git commit`, `git checkout`, `git checkout -b`, `git stash`, `git merge`
- Git push (issue branches only): `git push origin issue/<branch>` — non-force only
- File reads: `cat`, `grep`, `find`, `head`, `tail`, `wc`, `ls`
- GitHub CLI (read): `gh issue view … --json … 2>/dev/null`
- GitHub CLI (PR): `gh pr create --base master …`
- GitHub CLI (close): `gh issue close $N --repo … --comment "…"`
- Drupal module ops: `ddev drush en <module> -y`

**Always ask before:**
- `git push origin master` or `git push origin main`
- `git push --force`
- `ddev drush cim -y` (unless `config:status` shows zero local-only changes)
- Any `DROP TABLE`, `DELETE FROM`, or destructive DB operations

---

## Step 1 — Fetch Issue Details

```bash
gh issue view $issue --repo micronugget/duccinisv4 \
  --json title,body,labels,state,number 2>/dev/null
```

If `gh` is not authenticated:
```bash
gh auth login --hostname github.com --web
```

Parse: title, body (acceptance criteria), labels, linked PRs.

## Step 1b — Blocking Pre-flight Check

If the issue has a `blocked` label **halt immediately** and tell the user. Do not branch or write code.

Check the issue body for "Blocked by" references. If a blocking issue is still open, halt and report it.

---

## Step 2 — Create a Feature Branch

Branching rule — base branch depends on issue number:

| Issue range | Base branch | PR target |
|-------------|-------------|-----------|
| **#94–141** (migration epic) | `migration_branch` | `migration_branch` (local only) |
| **All others** | `master` | `master` |

`migration_branch` does **not** exist on GitHub. Do not use `--base migration_branch` with `gh pr create`.

```bash
# Issues #94–141:
git checkout migration_branch && git checkout -b issue/$issue-<slug>

# All other issues:
git checkout master && git pull origin master && git checkout -b issue/$issue-<slug>
```

---

## Step 3 — Verify DDEV Environment

```bash
echo "=== DDEV Status ===" && ddev status 2>&1 | head -20
```

If not running: `ddev start 2>&1 | tail -10`

---

## Step 4 — Understand the Codebase

Search for relevant files based on the issue title and labels. Pay special attention to:

- `web/modules/custom/store_fulfillment/` — core business logic
- `web/themes/custom/duccinis_1984_olympics/` — theme / Twig / SCSS
- `config/sync/` — configuration
- `web/modules/custom/` — other custom modules

Load the relevant `.junie/instructions/` file before writing code.

---

## Step 5 — Plan the Work

Before writing any code:
1. List the files that need to change.
2. Identify whether config export (`ddev drush cex`) will be needed.
3. Identify whether new tests are needed (any new functionality → yes).
4. State the acceptance criteria and how you will verify each one.

---

## Step 6 — Implement

Follow all rules in `.junie/AGENTS.md`:
- `declare(strict_types=1);` on every new PHP file
- No `\Drupal::service()` inside service classes
- Never modify `web/core/` or `vendor/`
- Checkout pane visibility, AJAX, and billing ownership patterns per `.junie/instructions/store-fulfillment.md`

---

## Step 7 — Quality Gates

Run in sequence. Do not skip. Each must pass before proceeding.

### 7a. Coding Standards
```bash
echo "=== PHPCS ===" && \
ddev exec vendor/bin/phpcs --standard=Drupal \
  web/modules/custom/store_fulfillment/src \
  2>&1 | head -50
```

Fix any errors before continuing.

### 7b. Cache Rebuild
```bash
echo "=== Cache Rebuild ===" && ddev drush cr 2>&1 | tail -5
```

### 7c. Tests

> Frontend-only issues (theme SCSS, Twig, libraries, JS — no PHP logic changed):
> Running `--filter` on the nearest relevant test class is sufficient.

```bash
echo "=== PHPUnit ===" && \
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --colors=never 2>&1 | tail -20
```

All tests must pass. Fix failures before proceeding.

### 7d. Config Export (if config changed)

Check for pre-existing DB/sync divergence first:
```bash
echo "=== Config Status ===" && \
ddev drush config:status 2>&1 | grep -v 'Only in sync dir' | grep -v 'No differences' | head -20
```

Then export:
```bash
echo "=== Config Export ===" && ddev drush cex -y 2>&1 | tail -10
```

---

## Step 8 — Commit

**Stage only files relevant to this issue** — do NOT use `git add -A` blindly.

```bash
git add <specific files> && \
git commit -m "fix: close issue #$issue — <short description>"
```

Use a conventional commit message. Reference the issue number.

---

## Step 9 — Push and Close

```bash
# Push the feature branch
git push origin issue/$issue-<slug>
```

```bash
# Open a PR (non-migration issues only — all others target master):
gh pr create --repo micronugget/duccinisv4 \
  --base master \
  --head issue/$issue-<slug> \
  --title "fix: close issue #$issue — <short description>" \
  --body "Closes #$issue"
```

```bash
# Close the issue
gh issue close $issue --repo micronugget/duccinisv4 \
  --comment "Implemented in commit $(git rev-parse --short HEAD). All tests pass."
```

If any `gh` command fails with `Resource not accessible by personal access token`:
```bash
gh auth login --hostname github.com --web
```

---

## Step 10 — Command Audit

Output this table filled with the actual commands run:

```
## Commands Run — Audit

| # | Command | Auto-executed? |
|---|---------|----------------|
| 1 | ddev status | yes |
| 2 | gh issue view $issue --json … 2>/dev/null | yes |
| … | … | … |
```

Include recommendations for any new commands that could be added to the Action Allowlist.
