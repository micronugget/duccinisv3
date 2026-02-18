---
name: Project Instructions
description: Project-specific foundation standards, coding guidelines, and technical requirements for AI agents
tags: [instructions, standards, setup, guidelines]
version: 1.0.0
---

# Project Foundation & Standards

## 0. CRITICAL: Environment Setup (First Step)

**⚠️ BEFORE STARTING ANY WORK: Check if this project has environment setup requirements.**

Projects may require specific development environments (e.g., Docker, DDEV, local servers). Check the project README or setup scripts before beginning work.

### Automated Setup (if available)
Check if a setup script exists and run it first:

```bash
bash .github/copilot-setup.sh  # If this file exists
```

### Verification
Verify your environment is working according to project-specific requirements (check project README).

**❌ DO NOT proceed without completing required setup steps!**

---

## 0.1. Brave Mode (Autonomous Operation)

**⚠️ OPTIONAL: Enable brave mode for autonomous agent operation without confirmation prompts.**

By default, you can enable "brave mode" to have agents work autonomously:

### Quick Activation
Tell agents to use brave mode:
```
@workspace Use brave mode - execute commands and make changes without asking
```

### What Brave Mode Does
- ✅ Agents execute terminal commands immediately
- ✅ Agents make code changes without requesting approval
- ✅ Agents run tests automatically
- ✅ Agents investigate and fix issues proactively
- ✅ Agents still warn about destructive operations

### Full Documentation
See `.github/BRAVE_MODE.md` for:
- Complete brave mode guide
- Safety mechanisms and boundaries
- Agent-specific behaviors
- Decision matrix for when to ask vs. act
- Examples and best practices

### Safety
Brave mode maintains safety through:
- Version control (all changes are in Git)
- Warnings for destructive operations
- Terminal command reliability patterns
- Explicit verification of results

---

## 0.25. Terminal Command Best Practices (Critical for AI Agents)

**⚠️ IF YOU ARE AN AI AGENT: Read `.github/copilot-terminal-guide.md` for critical terminal command patterns.**

### Quick Rules for Reliable Terminal Output

When running commands:

1. **ALWAYS use `isBackground: false`** for commands where you need output
2. **ADD echo markers** around important operations:
   ```bash
   echo "=== Starting Operation ===" && \
   command 2>&1 && \
   echo "=== Operation Complete ==="
   ```
3. **CAPTURE stderr with stdout** using `2>&1`
4. **VERIFY results explicitly** - check exit codes, grep for status, confirm expected output
5. **USE output limiters** for verbose commands: `| head -50` or `| tail -50`

**Example Pattern:**
```bash
echo "=== Running Command ===" && \
command --with-options 2>&1 && \
echo "Exit Code: $?" && \
verify-command | grep "expected output"
```

**Full Guide:** See `.github/copilot-terminal-guide.md` for comprehensive patterns and debugging.

---

## 0.5. Specialized Agent Team (Task Delegation)

**⭐ This project has a team of specialized AI agents for different tasks.**

Before implementing any feature yourself, check if there's a specialized agent available:

### Quick Agent Reference

**View full agent directory**: `.github/AGENT_DIRECTORY.md`

See `.github/AGENT_DIRECTORY.md` for the complete list of available specialized agents for your project type.

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

---

## 1. Project Context

**⚠️ CUSTOMIZE THIS SECTION FOR YOUR PROJECT**

Update this section with your project-specific information:
- System architecture and technology stack
- Development environment requirements
- Production environment details
- Key features and workflows
- Deployment processes

## 2. Technical Standards

**⚠️ CUSTOMIZE THIS SECTION FOR YOUR PROJECT**

Define your project's coding standards and requirements:
- Coding style guides
- Testing requirements
- Git workflow and commit conventions
- Quality gates for pull requests

## 3. Environment-Specific Logic

**⚠️ CUSTOMIZE THIS SECTION FOR YOUR PROJECT**

Document any environment-specific considerations:
- Development vs production differences
- Special configuration requirements
- Third-party integrations

## 4. Validation Rules

**⚠️ CUSTOMIZE THIS SECTION FOR YOUR PROJECT**

Define validation steps for changes:
- Required test commands
- Code quality checks
- Pre-commit requirements
