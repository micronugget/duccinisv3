# Quick Agent Reference Card

## 📋 All Available Specialized Agents

### 🔧 Core Development (4 agents)
```
┌─────────────────────────────────────────────────────────────┐
│ Architect              │ Planning, architecture, coordination│
│ developer_drupal.md    │ Backend, modules, hooks, workflows  │
│ media-dev.agent.md     │ Video, YouTube, GPS metadata        │
│ themer.agent.md        │ CSS, Bootstrap, Masonry, Swiper     │
└─────────────────────────────────────────────────────────────┘
```

### ✅ Quality & Documentation (2 agents)
```
┌─────────────────────────────────────────────────────────────┐
│ tester.md              │ PHPUnit, PHPStan, Nightwatch        │
│ technical-writer.md    │ Docs, README, guides, tutorials     │
└─────────────────────────────────────────────────────────────┘
```

### 🚀 Infrastructure & Ops (3 agents)
```
┌─────────────────────────────────────────────────────────────┐
│ environment-manager.md │ DDEV, Docker, CI/CD                 │
│ provisioner-deployer.md│ Production deployment, OLS          │
│ developer_ansible.md   │ Infrastructure automation           │
└─────────────────────────────────────────────────────────────┘
```

### 🛡️ Specialists (4 agents)
```
┌─────────────────────────────────────────────────────────────┐
│ security-specialist.md │ Security audits, vulnerabilities    │
│ performance-engineer.md│ Optimization, caching, Web Vitals   │
│ database-administrator.md│ MySQL optimization, schema        │
│ ux-ui-designer.md      │ Design, UX, accessibility           │
└─────────────────────────────────────────────────────────────┘
```

## 🎯 Quick Task → Agent Matcher

| I need to... | Use this agent |
|--------------|----------------|
| Build a Drupal module | **developer_drupal.md** |
| Create content workflow | **developer_drupal.md** |
| Handle video uploads | **media-dev.agent.md** |
| Extract GPS from media | **media-dev.agent.md** |
| Style components with CSS | **themer.agent.md** |
| Create responsive layouts | **themer.agent.md** |
| Write unit tests | **tester.md** |
| Run PHPUnit/PHPStan | **tester.md** |
| Write documentation | **technical-writer.md** |
| Configure DDEV | **environment-manager.md** |
| Deploy to production | **provisioner-deployer.md** |
| Automate with Ansible | **developer_ansible.md** |
| Review security | **security-specialist.md** |
| Optimize performance | **performance-engineer.md** |
| Tune MySQL queries | **database-administrator.md** |
| Design user interface | **ux-ui-designer.md** |
| Plan architecture | **architect.md** |

## 📂 File Locations

All agents are in: `.github/agents/[agent-name].md`

Example paths:
- `.github/agents/developer_drupal.md`
- `.github/agents/security-specialist.md`
- `.github/agents/themer.agent.md`

## 📖 More Info

- **Full directory**: `.github/AGENT_DIRECTORY.md`
- **Agent README**: `.github/agents/README.md`
- **Guidance doc**: `.github/agents/guidance.md`

## 🔄 Common Workflows

```
Feature Development:
Architect → Drupal-Dev → Tester → Tech-Writer

Media Feature:
Architect → Media-Dev → Drupal-Dev → Themer → Tester

Theme Work:
Architect → UX-Designer → Themer → Tester

Infrastructure:
Architect → Env-Manager → Provisioner → Security → Tester

Security Review:
Security-Specialist → Drupal-Dev → Tester → Security-Specialist
```

## ⚡ Quick Commands

```bash
# View all agents
ls .github/agents/

# View full agent directory
cat .github/AGENT_DIRECTORY.md

# View specific agent
cat .github/agents/developer_drupal.agent.md

# View agents README
cat .github/agents/README.md
```

---

*For detailed descriptions, use cases, and decision tree, see: `.github/AGENT_DIRECTORY.md`*
