# Guidance: Friday Night Skate AI Agent Team

This document provides the operational framework for the AI agent team working on the Friday Night Skate Drupal CMS 2 project.

## 1. Agent Team Overview

| Agent | Primary Output | Success Metric |
| :--- | :--- | :--- |
| **Architect** | Task assignments, workflows, architecture decisions | Project cohesion, feature completion |
| **Drupal Developer** | Custom modules, hooks, services, configuration | Code quality, test coverage |
| **Media Developer** | Media plugins, GPS extraction, YouTube integration | Metadata preservation, upload UX |
| **Themer** | SCSS, JS, Twig templates, responsive images | Performance, mobile UX |
| **UX/UI Designer** | Design specs, prototypes, design system | Visual quality, accessibility |
| **Tester** | Test reports, bug logs, QA approval | Regression rate, test coverage |
| **Technical Writer** | README, user guides, API docs, changelog | Documentation accuracy |
| **Database Administrator** | Schema optimization, backup procedures | Query performance, data integrity |
| **Performance Engineer** | Performance audits, caching config | Core Web Vitals, load times |
| **Security Specialist** | Security audits, vulnerability reports | Security posture, compliance |
| **Environment Manager** | DDEV config, CI/CD pipelines | Environment parity |
| **Provisioner/Deployer** | Deployment procedures, rollback plans | Deployment success rate |

## 2. Handoff Protocol System

### Standard Handoff Document
Every agent-to-agent transition must include:

```markdown
## [Agent] Handoff: [TASK-ID]
**Status:** Complete / Blocked / Needs Work
**Changes Made:**
- [File/Component]: [Description]
**Test Commands:**
- `ddev [command]`
**Validation:**
- [ ] Tests pass
- [ ] Standards met
**Next Steps:** [For receiving agent]
**Blockers:** [If any]
```

### Workflow Patterns

#### Feature Development (Standard)
```
┌──────────┐    ┌────────────────┐    ┌────────┐    ┌──────────────────┐    ┌──────────┐
│ Architect│───▶│Drupal Developer│───▶│ Tester │───▶│ Technical Writer │───▶│ Architect│
└──────────┘    └────────────────┘    └────────┘    └──────────────────┘    └──────────┘
    Task           Implementation       Testing        Documentation         Review
  Assignment                                                                  & Merge
```

#### Media Feature (Friday Night Skate Specific)
```
┌──────────┐    ┌───────────┐    ┌────────────────┐    ┌────────┐    ┌────────┐    ┌──────────┐
│ Architect│───▶│ Media Dev │───▶│Drupal Developer│───▶│ Themer │───▶│ Tester │───▶│ Architect│
└──────────┘    └───────────┘    └────────────────┘    └────────┘    └────────┘    └──────────┘
    Task          GPS/Media          Entity/Field        Display      Testing       Review
  Assignment     Extraction          Integration         Layer
```

#### Frontend/Theme Development
```
┌──────────┐    ┌───────────────┐    ┌────────┐    ┌────────────────┐    ┌────────┐    ┌──────────┐
│ Architect│───▶│ UX/UI Designer│───▶│ Themer │───▶│Drupal Developer│───▶│ Tester │───▶│ Architect│
└──────────┘    └───────────────┘    └────────┘    └────────────────┘    └────────┘    └──────────┘
    Task           Design Spec       Implementation    Twig/Preprocess    Testing       Review
  Assignment                                                                            & Merge
```

## 3. DDEV Command Requirements

**CRITICAL:** All CLI commands MUST use DDEV prefix.

```bash
# ✅ Correct
ddev drush cr
ddev composer require drupal/module
ddev phpunit
ddev phpstan analyze

# ❌ Wrong
drush cr
composer require drupal/module
phpunit
phpstan analyze
```

## 4. Validation Checkpoints

### Before PR/Merge
- [ ] `ddev phpunit` passes
- [ ] `ddev phpstan` passes (level max)
- [ ] `ddev exec phpcs --standard=Drupal` passes
- [ ] `ddev drush cex` executed and config committed
- [ ] Security review (if user-facing)
- [ ] Documentation updated

### Git Hygiene
- One feature per branch
- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `test:`
- Config changes always committed with code

## 5. Project-Specific Considerations

### Media Workflow (GPS Preservation)
1. User uploads file → stored in `private://`
2. **Media Dev** extracts GPS via ffprobe/exif BEFORE external upload
3. GPS data stored in Drupal fields
4. File may be transferred to YouTube (metadata already preserved)
5. Public view shows location data from Drupal, not from media file

### OpenLiteSpeed Compatibility
- `.htaccess` rules need OLS context configuration
- Test rewrite rules specifically for OLS
- Document any Apache-specific features that need adaptation

### Responsive Images
- Bootstrap 5 breakpoints: xs, sm, md, lg, xl, xxl
- WebP as default format
- Lazy loading for below-fold content
- Masonry.js with imagesLoaded for proper layout

## 6. Agent Communication Quick Reference

| Need Help With... | Contact |
|------------------|---------|
| Architecture/Planning | @architect |
| PHP/Drupal Code | @drupal-developer |
| Media/GPS/Video | @media-dev |
| Frontend/SCSS/JS | @themer |
| Design/UX | @ux-ui-designer |
| Testing/QA | @tester |
| Documentation | @technical-writer |
| Database/Queries | @database-administrator |
| Performance | @performance-engineer |
| Security | @security-specialist |
| DDEV/Environment | @environment-manager |
| Deployment | @provisioner-deployer |

## 7. Iterative Improvement

### Feedback Loops
- When Tester finds recurring bug patterns → update Developer's guiding principles
- When Performance issues found → update relevant agent constraints
- When Handoff confusion occurs → clarify protocol in this guidance doc

### Agent File Versioning
- Agent `.md` files are version-controlled
- Changes require PR review
- Keep aligned with project complexity
