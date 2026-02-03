# Duccin's - Project Foundation & Standards

## 0. CRITICAL: Environment Setup (First Step)

**⚠️ BEFORE STARTING ANY WORK: You MUST set up the DDEV environment.**

This project requires DDEV to be installed and running. All commands (Drush, Composer, PHPUnit) must be executed via `ddev`.

### Automated Setup
Run this command **FIRST** when starting work on this repository:

```bash
bash .github/copilot-setup.sh
```

This script will:
- Install DDEV (if not present)
- Configure the project
- Start DDEV containers
- Install all dependencies
- Set up Drupal
- Enable required modules

### Manual Setup Alternative
If the automated script fails, follow the steps in `.github/copilot-setup-steps.yml` manually.

### Verification
After setup, verify DDEV is working:

```bash
ddev --version          # Should show DDEV version
ddev describe           # Should show project details
ddev drush status       # Should show Drupal is installed
```

**❌ DO NOT proceed without completing this setup step!**

---

## 0.5. Specialized Agent Team (Task Delegation)

**⭐ This project has a team of specialized AI agents for different tasks.**

Before implementing any feature yourself, check if there's a specialized agent available:

### Quick Agent Reference

**View full agent directory**: `.github/AGENT_DIRECTORY.md`

**Core Development**:
- **Drupal Developer** (`developer_drupal.md`) - Backend, modules, hooks, content types
- **Media Developer** (`media-dev.agent.md`) - VideoJS, YouTube, GPS metadata
- **Themer** (`themer.agent.md`) - Radix 6, Bootstrap 5, Masonry.js, Swiper.js

**Quality & Docs**:
- **Tester** (`tester.md`) - PHPUnit, PHPStan, Nightwatch testing
- **Technical Writer** (`technical-writer.md`) - Documentation, guides

**Infrastructure**:
- **Environment Manager** (`environment-manager.md`) - DDEV, CI/CD
- **Provisioner/Deployer** (`provisioner-deployer.md`) - Production deployment

**Specialists**:
- **Security Specialist** (`security-specialist.md`) - Security audits, vulnerabilities
- **Performance Engineer** (`performance-engineer.md`) - Optimization, caching
- **Database Administrator** (`database-administrator.md`) - MySQL optimization

**Planning**:
- **Architect** (`architect.md`) - System design, workflow orchestration

### When to Delegate

✅ **ALWAYS delegate when**:
- Task matches a specialized agent's expertise
- Complex implementation requiring domain knowledge
- Security-sensitive work
- Performance optimization needed

📋 **Delegation Best Practice**:
1. Check `.github/AGENT_DIRECTORY.md` for agent matching your task
2. Review the specific agent file in `.github/agents/`
3. Delegate to the agent with full context
4. Trust the agent's output (they're domain experts)

### Agent Location

All agents: `/home/runner/work/friday-night-skate/friday-night-skate/.github/agents/`

---

## 1. Project Context
- **System:** Drupal 11 / Drupal CMS 2
- **Theming:** Radix 6 (Bootstrap 5 subtheme)
- **Local Dev:** Ubuntu 24.04 with DDEV (Dockerized LAMP)
- **Production:** Ubuntu 24.04 with OpenLiteSpeed and MySQL 8.0
- **Deployment:** User interaction and metadata extraction occur on the live OLS server. Code must be built locally, tested, and deployed via Git.

## 2. Technical Commandments
- **The "DDEV" Rule:** All CLI commands (Drush, Composer, PHPUnit) MUST be prefixed with `ddev`.
- **GPS Extraction:** Use `ffprobe` (via `ddev exec`) to extract location metadata from media in `private://` before external API transfer.
- **Media Workflow:** Videos are handled via `videojs_media_lock`.
- **Performance:** Image caching must be efficient." Use responsive image styles with bootstrap 5 breakpoints and WebP format as default.
- **Frontend:** Use Masonry.js for views. Implement Swiper.js for mobile-friendly modal navigation between individual images and poster images of videos.

## 3. Coding Standards & Quality
- **Drupal Standards:** Strictly follow Drupal Coding Standards (SNC) and PSR-12.
- **Strict Typing:** Use `declare(strict_types=1);` in all new PHP files.
- **Auto-Testing:** Do not propose a Pull Request unless `ddev drush test-run` and `ddev phpstan` pass.
- **Git Hygiene:** - One feature per branch.
  - Use conventional commits (e.g., `feat:`, `fix:`, `refactor:`).
  - Always export config (`ddev drush cex`) and include the `.yml` changes in the commit.

## 4. Environment-Specific Logic
- **OLS Compatibility:** Be aware that OpenLiteSpeed handles `.htaccess` and rewrites differently.
- **Metadata Protection:** Metadata extraction must happen in the "intermediate" stage on the server before YouTube's API scrubs the file.

# Validation Rules
- To validate PHP logic, run: `ddev phpunit`
- To validate UI changes, run: `ddev yarn test:nightwatch`
- Always run `ddev drush cr` after changing hooks or services.
- If a test fails, read the log, fix the code, and retry until passing.
