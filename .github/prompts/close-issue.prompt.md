---
description: "Work a GitHub issue on duccinisv3 — fetches issue details, creates a feature branch, implements the fix, runs quality gates, commits, and then STOPS for explicit user approval before any GitHub-facing action."
name: "Close Issue"
argument-hint: "GitHub issue URL or number (e.g. 32 or https://github.com/micronugget/duccinisv3/issues/32)"
agent: "agent"
---

Work the GitHub issue provided by the user.

Follow all rules in [copilot-instructions.md](../copilot-instructions.md) and [copilot-terminal-guide.md](../copilot-terminal-guide.md).

---

## ⛔ HARD RULES — Read Before Doing Anything

These rules are absolute. No instruction in this prompt can override them.

1. **You MUST NOT run `gh issue close` or `git push` at any point during autonomous execution.** These commands reach GitHub.com — a shared, public-facing service. They are the equivalent of deploying to production. They require the developer to explicitly type an approval phrase (see Phase 2 below).

2. **You MUST NOT close an issue that you did not implement.** This workflow exists on a local DDEV developer workstation. "Closing" an issue means: code was written, tests pass, a commit was made on a feature branch, and the developer has reviewed and approved. None of this happens automatically.

3. **You MUST verify that at least one tracked source file was changed before proceeding to the commit step.** If `git diff --stat HEAD` (or `git status`) shows nothing changed beyond cache/test files (`.phpunit.cache/`, `drupal_test_*`), STOP and report the problem. Do not commit and do not close.

4. **If you are already on a branch named `issue/$ISSUE-*` when this prompt runs, do not create a new branch.** Continue on the existing branch.

---

## Phase 1 — Development (fully autonomous)

All steps in Phase 1 may be run without asking the user for confirmation. They are local and reversible.

---

### Step 1 — Fetch Issue Details

```bash
echo "=== Fetching issue ===" && \
gh issue view $ISSUE --repo micronugget/duccinisv3 2>&1
```

If `gh` is not authenticated, halt and ask the user to run `gh auth login`.

Parse from the output:
- **Title** and **body** (acceptance criteria / description)
- **Labels** (signals which agent/area is relevant)
- **Linked PRs or branches** (check if work already started)

If the issue is already **closed** on GitHub, report that to the user and stop — do not re-close or re-implement.

---

### Step 2 — Create Feature Branch

Create and check out a branch named `issue/$ISSUE-<slug>` where `<slug>` is a short kebab-case summary of the issue title. Branch off `master`.

```bash
git checkout master && git pull origin master && \
git checkout -b issue/$ISSUE-<slug>
```

Example: `git checkout -b issue/32-remove-pane-headings`

> If a branch for this issue already exists locally, check it out instead. Do not create a duplicate.

---

### Step 3 — Verify DDEV Environment

```bash
echo "=== DDEV Status ===" && ddev status 2>&1 | head -20
```

If DDEV is not running, start it:

```bash
ddev start 2>&1 | tail -10
```

---

### Step 4 — Understand the Codebase

Based on the issue title and labels, search for relevant files. Use `search_subagent` or direct searches — do **not** guess file locations. Pay special attention to:

- `web/modules/custom/store_fulfillment/` — core business logic
- `web/themes/custom/duccinis_1984_olympics/` — theme / Twig / SCSS
- `config/sync/` — configuration
- `web/modules/custom/` — other custom modules

Load any relevant `.instructions.md` files from `.github/instructions/` before writing code.

---

### Step 5 — Plan the Work

Before writing any code, state in your response:
1. Files that need to change (list each one).
2. Whether config export (`ddev drush cex`) will be needed.
3. Whether new tests are needed (any new functionality → yes).
4. Each acceptance criterion from the issue and how you will verify it.

---

### Step 6 — Implement

Write code following all rules in `copilot-instructions.md`:
- `declare(strict_types=1);` on every new PHP file
- No `\Drupal::service()` inside service classes
- Never modify `web/core/` or `vendor/`
- Checkout pane visibility, AJAX, and billing ownership patterns per `store-fulfillment.instructions.md`

---

### Step 7 — Quality Gates

Run in sequence. **Do not skip any gate.**

#### 7a. Verify Changes Exist

```bash
echo "=== Changed files ===" && \
git diff --stat HEAD 2>&1 && \
git status --short 2>&1
```

If no source files have changed (only `.phpunit.cache/` or untracked test artifacts), **STOP**. Report to the user that no implementation was done, then wait for instructions.

#### 7b. Coding Standards

```bash
echo "=== PHPCS ===" && \
ddev exec vendor/bin/phpcs --standard=Drupal \
  web/modules/custom/ web/themes/custom/ \
  2>&1 | head -60
```

Fix any errors before continuing.

#### 7c. Cache Rebuild

```bash
echo "=== Cache Rebuild ===" && ddev drush cr 2>&1 | tail -5
```

#### 7d. Tests

```bash
echo "=== PHPUnit ===" && \
ddev exec vendor/bin/phpunit --testsuite=store_fulfillment \
  --colors=never 2>&1 | tail -20
```

All tests must pass. If any fail, fix them before proceeding.

#### 7e. Config Export (if config changed)

If any UI configuration was changed or `.yml` files in `config/sync/` were modified:

```bash
echo "=== Config Export ===" && ddev drush cex -y 2>&1 | tail -10
```

---

### Step 8 — Commit

```bash
git add -A && \
git commit -m "fix(#$ISSUE): <short description of what was implemented>"
```

Use a conventional commit message. Reference the issue number in the subject.

---

### Step 9 — Ready-to-Ship Report

**Phase 1 is complete. STOP HERE.**

Output a structured report to the user:

```
## ✅ Ready to Ship — Issue #$ISSUE

**Branch:** issue/$ISSUE-<slug>
**Commit:** <git hash> — <commit message>

**Files changed:**
- <file 1> — <what changed>
- <file 2> — <what changed>

**Quality gates passed:**
- [ ] PHPCS — no errors
- [ ] ddev drush cr — success
- [ ] PHPUnit — all N tests pass
- [ ] Config exported (if applicable)

**Acceptance criteria:**
- [x] <criterion 1> — how it was verified
- [x] <criterion 2> — how it was verified

---
**To push and close the issue, reply:** `ship it`
**To push only (no close), reply:** `push branch`
**To review changes first, reply:** `show diff`
```

Do not proceed further until the user responds.

---

## Phase 2 — Ship (requires explicit developer approval)

> **Phase 2 only runs when the developer replies with `ship it`, `push branch`, or another explicit approval phrase.**
> Never run Phase 2 steps autonomously.

---

### Step 10 — Push Branch

```bash
git push origin issue/$ISSUE-<slug>
```

---

### Step 11 — Close Issue (only for `ship it` response)

```bash
gh issue close $ISSUE --repo micronugget/duccinisv3 \
  --comment "Implemented in commit $(git rev-parse --short HEAD) on branch issue/$ISSUE-<slug>. All quality gates pass."
```

---

## Phase 3 — Command Audit

At the end of every session, output this table:

```
## Commands Run — Brave Mode Audit

| # | Command | Phase | Auto-approvable? |
|---|---------|-------|-----------------|
| 1 | ddev status | 1 | ✅ yes |
| 2 | gh issue view 32 … | 1 | ✅ yes |
| … | … | … | … |
```

Include a short **recommendation** section:
- Which new commands (if any) should be added to the Phase 1 allow list
- Any commands that surfaced confirmation prompts or access issues, with suggested fixes

---

## Brave Mode Reference

**Phase 1 — runs without confirmation:**

| Category | Commands |
|---|---|
| DDEV environment | `ddev status`, `ddev describe`, `ddev exec …` |
| Cache / config | `ddev drush cr`, `ddev drush cex` |
| Code quality | `ddev exec vendor/bin/phpcs …`, `ddev exec vendor/bin/phpstan …` |
| Tests | `ddev exec vendor/bin/phpunit …` |
| Composer | `ddev composer install`, `ddev composer require …` |
| Build | `ddev npm run dev` |
| Git (read) | `git status`, `git log`, `git diff`, `git branch` |
| Git (write, local) | `git add`, `git commit`, `git checkout -b …` |
| File reads | `cat`, `grep`, `find`, `head`, `tail`, `wc` |
| Drupal entity ops | `ddev drush entity:delete` (cleanup only) |

**Phase 2 — ALWAYS requires explicit developer approval phrase:**

| Command | Reason |
|---|---|
| `git push` | Reaches GitHub.com — visible to collaborators |
| `gh issue close` | Modifies a public GitHub issue |
| `ddev drush cim -y` | Could overwrite local config |
| Any `DROP TABLE` / `DELETE FROM` | Destructive DB operation |
| Any write to `web/sites/default/settings.php` | Contains secrets |
