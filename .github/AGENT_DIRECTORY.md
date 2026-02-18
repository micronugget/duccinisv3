# Specialized Agent Directory for GitHub Copilot

## Quick Reference: When to Use Which Agent

This document provides a quick reference for GitHub Copilot to identify which specialized agent to delegate tasks to based on the work required.

## 🎯 Core Development Agents

### Architect (Mission Control)
**File**: `architect.md`  
**Use For**:
- Task decomposition and workflow orchestration
- System architecture decisions
- Feature planning and roadmap
- Cross-team coordination
- Design pattern recommendations

**Keywords**: architecture, planning, coordination, workflow, system design

---

### Drupal Developer
**File**: `developer_drupal.md`  
**Use For**:
- PHP backend development
- Custom module development
- Drupal hooks implementation
- Configuration management
- Entity and field management
- Content type creation
- Workflow and moderation systems
- Drush commands and automation

**Keywords**: drupal, php, module, hook, entity, content type, drush, configuration

**Example Tasks**:
- Creating content moderation workflows
- Building custom modules
- Implementing entity hooks
- Configuration export/import

---

### Media Developer
**File**: `media-dev.agent.md`  
**Use For**:
- VideoJS integration and configuration
- YouTube API integration
- GPS metadata extraction from media
- FFprobe integration for video processing
- Media field handling
- Video player customization

**Keywords**: video, media, youtube, gps, metadata, ffprobe, videojs

**Example Tasks**:
- Extracting GPS data from uploaded videos
- Configuring VideoJS players
- YouTube upload automation

---

### Themer (Frontend Specialist)
**File**: `themer.agent.md`  
**Use For**:
- Radix 6 theme customization
- Bootstrap 5 implementation
- Responsive design and breakpoints
- Masonry.js layouts
- Swiper.js mobile interactions
- CSS/SCSS development
- Template (.html.twig) creation
- Image optimization and WebP

**Keywords**: theme, css, scss, bootstrap, radix, masonry, swiper, twig, responsive

**Example Tasks**:
- Creating responsive image galleries
- Implementing mobile-friendly navigation
- Custom Twig templates

---

## 🎨 Design & Frontend Agents

### UX/UI Designer
**File**: `ux-ui-designer.md`  
**Use For**:
- User experience design
- Interface prototypes
- Accessibility (WCAG) compliance
- Design specifications
- User flow diagrams
- Wireframes and mockups

**Keywords**: ux, ui, design, accessibility, wireframe, prototype, user flow

---

## ✅ Quality & Documentation Agents

### Tester (QA/QC)
**File**: `tester.md`  
**Use For**:
- PHPUnit test writing
- PHPStan static analysis
- Nightwatch.js browser testing
- Manual testing procedures
- Test automation
- Quality assurance
- Bug validation

**Keywords**: test, phpunit, phpstan, qa, quality, validation, nightwatch

**Example Tasks**:
- Writing unit tests for modules
- Creating kernel tests for workflows
- Browser testing with Nightwatch

---

### Technical Writer
**File**: `technical-writer.md`  
**Use For**:
- README documentation
- User guides and tutorials
- API documentation
- Changelog maintenance
- Installation instructions
- Configuration guides
- Developer documentation

**Keywords**: documentation, readme, guide, tutorial, api docs, changelog

---

## 🔧 Infrastructure & Operations Agents

### Environment Manager
**File**: `environment-manager.md`  
**Use For**:
- DDEV configuration
- Docker setup
- CI/CD pipeline configuration
- Environment parity (dev/staging/prod)
- Development environment troubleshooting

**Keywords**: ddev, docker, environment, ci/cd, pipeline

---

### Provisioner/Deployer
**File**: `provisioner-deployer.md`  
**Use For**:
- Production server deployment
- OpenLiteSpeed configuration
- SSL certificate management
- Server provisioning
- Deployment automation
- Release management

**Keywords**: deployment, production, server, openlitespeed, ssl, release

---

### Ansible Developer
**File**: `developer_ansible.md`  
**Use For**:
- Infrastructure as code
- Server automation with Ansible
- Playbook development
- Configuration management
- Automated provisioning

**Keywords**: ansible, automation, infrastructure, playbook, provisioning

---

## 🛡️ Specialist Agents

### Database Administrator
**File**: `database-administrator.md`  
**Use For**:
- MySQL 8.0 optimization
- Database schema design
- Query optimization
- Backup strategies
- Database migrations
- Index management

**Keywords**: database, mysql, optimization, schema, query, backup

---

### Performance Engineer
**File**: `performance-engineer.md`  
**Use For**:
- Core Web Vitals optimization
- Caching strategies
- Performance profiling
- Load time optimization
- Image optimization
- CDN configuration

**Keywords**: performance, optimization, caching, speed, web vitals

---

### Security Specialist
**File**: `security-specialist.md`  
**Use For**:
- Security audits
- Vulnerability assessments
- File upload security
- User permission auditing
- XSS/CSRF protection
- Security best practices
- Privacy compliance

**Keywords**: security, vulnerability, audit, permissions, xss, csrf, privacy

---

## 📚 Skills & Resources

### Frontend Design Skill
**File**: `skills/frontend-design/SKILL.md`  
**Use For**:
- Design aesthetics guidelines
- Van Gogh "Starry Night" inspiration
- Visual design principles
- Color palette guidance

---

## 🔄 Standard Workflows

### Feature Development
```
Architect → Drupal Developer → Tester → Technical Writer → Architect (Review)
```

### Media Feature (GPS/Video)
```
Architect → Media Developer → Drupal Developer → Themer → Tester → Architect (Review)
```

### Frontend/Theme Work
```
Architect → UX/UI Designer → Themer → Drupal Developer → Tester → Architect (Review)
```

### Infrastructure Changes
```
Architect → Environment Manager → Provisioner/Deployer → Security Specialist → Tester → Architect (Review)
```

### Security Review
```
Security Specialist → Drupal Developer → Tester → Security Specialist (Validation)
```

---

## 🎯 Task Matching Guide

### Use This Decision Tree:

1. **Is it about planning/architecture?** → **Architect**
2. **Is it Drupal backend code?** → **Drupal Developer**
3. **Is it about video/media processing?** → **Media Developer**
4. **Is it about CSS/theme/frontend?** → **Themer**
5. **Is it about user interface design?** → **UX/UI Designer**
6. **Is it about testing?** → **Tester**
7. **Is it about documentation?** → **Technical Writer**
8. **Is it about DDEV/environments?** → **Environment Manager**
9. **Is it about deployment?** → **Provisioner/Deployer**
10. **Is it about automation scripts?** → **Ansible Developer**
11. **Is it about database?** → **Database Administrator**
12. **Is it about performance?** → **Performance Engineer**
13. **Is it about security?** → **Security Specialist**

---

## 💡 Usage Examples

### Example 1: Content Moderation Feature
**Task**: Implement content moderation workflow for archive submissions

**Agents to Use**:
1. **Architect** - Plan the feature architecture
2. **Drupal Developer** - Implement workflow, roles, permissions
3. **Tester** - Write PHPUnit tests for workflow transitions
4. **Technical Writer** - Document the moderation process
5. **Security Specialist** - Review permission model

### Example 2: Video Upload with GPS
**Task**: Extract GPS metadata from uploaded videos

**Agents to Use**:
1. **Architect** - Design metadata extraction pipeline
2. **Media Developer** - Implement FFprobe integration
3. **Drupal Developer** - Create custom fields and storage
4. **Security Specialist** - Review file upload security
5. **Tester** - Test metadata extraction

### Example 3: Responsive Gallery
**Task**: Create mobile-friendly image gallery with Masonry

**Agents to Use**:
1. **UX/UI Designer** - Design gallery layout and interactions
2. **Themer** - Implement Masonry.js and Swiper.js
3. **Performance Engineer** - Optimize image loading
4. **Tester** - Test on multiple devices

### Example 4: Production Deployment
**Task**: Deploy new features to production server

**Agents to Use**:
1. **Provisioner/Deployer** - Handle deployment process
2. **Environment Manager** - Ensure config sync
3. **Database Administrator** - Handle database updates
4. **Security Specialist** - Security checklist
5. **Tester** - Smoke testing after deployment

---

## 🚨 Important Notes

### All Agents Must:
- Use `ddev` prefix for CLI commands (Drush, Composer, PHPUnit)
- Follow Drupal Coding Standards and PSR-12
- Use `declare(strict_types=1);` in PHP files
- Export configuration with `ddev drush cex`
- Run tests before committing
- Clear cache with `ddev drush cr` after changes

### Handoff Protocol:
When one agent completes work, they should:
1. Document what was done
2. Specify what still needs to be done
3. Identify the next agent in the workflow
4. Provide context for the next agent

### Agent Location:
All agent definitions are in: `/home/runner/work/friday-night-skate/friday-night-skate/.github/agents/`

---

## 📖 Additional Resources

- **Agent README**: `.github/agents/README.md` - Full agent directory
- **Guidance**: `.github/agents/guidance.md` - Operational framework
- **Setup Instructions**: `.github/copilot-instructions.md` - Project standards
- **Setup Script**: `.github/copilot-setup.sh` - Environment automation

---

## 🔍 Finding the Right Agent

**Can't decide which agent?**

1. Look at the **keywords** in each agent section
2. Check the **example tasks** provided
3. Follow the **decision tree** above
4. When in doubt, start with **Architect** for planning

**Multiple agents needed?**

Follow the **standard workflows** section to see typical agent sequences for common tasks.

---

*Last Updated: 2026-01-29*
