# Friday Night Skate - AI Agent Team

This directory contains the definitions and protocols for the AI agent team working on the Friday Night Skate Drupal CMS 2 project.

## Quick Start

1. **Read the guidance document first:** `guidance.md` contains the operational framework
2. **Identify your task type** and follow the appropriate workflow
3. **Use handoff protocols** for all agent transitions
4. **Always use DDEV** for CLI commands

## Agent Directory

### Core Development Agents

| File | Agent | Quick Description |
|------|-------|-------------------|
| `architect.md` | **Architect (Mission Control)** | Task decomposition, workflow orchestration, architecture |
| `developer_drupal.md` | **Drupal Developer** | PHP, modules, hooks, configuration |
| `media-dev.agent.md` | **Media Developer** | VideoJS, YouTube API, GPS metadata extraction |
| `themer.agent.md` | **Themer** | Radix 6, Bootstrap 5, Masonry.js, Swiper.js |

### Design & Frontend

| File | Agent | Quick Description |
|------|-------|-------------------|
| `ux-ui-designer.md` | **UX/UI Designer** | Design specs, prototypes, accessibility |
| `skills/frontend-design/SKILL.md` | Design Skill | Frontend aesthetics guidelines |

### Quality & Documentation

| File | Agent | Quick Description |
|------|-------|-------------------|
| `tester.md` | **Tester (QA/QC)** | PHPUnit, PHPStan, Nightwatch, manual testing |
| `technical-writer.md` | **Technical Writer** | README, user guides, API docs, changelog |

### Infrastructure & Operations

| File | Agent | Quick Description |
|------|-------|-------------------|
| `environment-manager.md` | **Environment Manager** | DDEV config, CI/CD, environment parity |
| `provisioner-deployer.md` | **Provisioner/Deployer** | Production deployment, OpenLiteSpeed, SSL |
| `developer_ansible.md` | **Ansible Developer** | Infrastructure automation |

### Specialists

| File | Agent | Quick Description |
|------|-------|-------------------|
| `database-administrator.md` | **DBA** | MySQL optimization, backups, schema |
| `performance-engineer.md` | **Performance Engineer** | Core Web Vitals, caching, optimization |
| `security-specialist.md` | **Security Specialist** | Security audits, file uploads, privacy |

## Standard Workflows

### Feature Development
```
Architect → Drupal-Developer → Tester → Technical-Writer → Architect (Review)
```

### Media Feature (GPS/Video)
```
Architect → Media-Dev → Drupal-Developer → Themer → Tester → Architect (Review)
```

### Frontend/Theme
```
Architect → UX-UI-Designer → Themer → Drupal-Developer → Tester → Architect (Review)
```

### Infrastructure
```
Architect → Environment-Manager → Provisioner-Deployer → Security-Specialist → Tester → Architect (Review)
```

## DDEV Requirement

**All CLI commands must use DDEV prefix:**

```bash
# ✅ Correct
ddev drush cr
ddev composer require drupal/module
ddev phpunit

# ❌ Wrong
drush cr
composer require drupal/module
phpunit
```

## Validation Before Merge

- [ ] `ddev phpunit` passes
- [ ] `ddev phpstan` passes
- [ ] `ddev drush cex` executed (config committed)
- [ ] Handoff documentation provided
- [ ] Security review (if user-facing)

## Contributing to Agent Definitions

1. Agent files are version-controlled like code
2. Changes should be reviewed via PR
3. Update `guidance.md` if protocols change
4. Keep agents aligned with project complexity

## Related Files

- `../copilot-instructions.md` - Project-wide coding standards
- `../copilot-setup-steps.sh` - Environment setup script
