# BosskuAI

A cofounder-style AI layer for **Claude Code, Cursor, and Codex** — auto-loads skills, reads shared memory, and enforces plan-first on every prompt.

## Quick setup

```bash
# 1. Clone
git clone <repo-url> bosskuAI

# 2. Install into your project
./bosskuAI/scripts/install.sh /path/to/your/project

# 3. Open your project in Claude / Cursor / Codex and run the onboarding prompt
```

> **Windows:** use `.\bosskuAI\scripts\install.ps1 C:\path\to\your\project`

After install, paste the onboarding prompt from [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) once to initialize memory for your project.

## How it works

Every prompt, across every tool, automatically:

1. **Reads shared memory** — `ai-assistant/memory/` (continuation → profile → understanding → log)
2. **Routes to the right skill** — picks from 39 skills across product, engineering, design, security, and GTM
3. **Emits `[TASK START]`** — visible audit header so you can see if protocol was followed
4. **Plans first** — Opus 4.6 / gpt-5.4 for planning, Sonnet 4.6 / gpt-5.2 for execution
5. **Emits `[TASK END]`** — confirms memory updated and what was learned

If `[TASK START]` is missing from a response, the protocol was skipped.

## Activation

Say `bossku` anywhere in a prompt to explicitly activate BosskuAI mode:

```
bossku review this PR for security and business-logic risks
bossku plan the smallest safe implementation for this feature
bossku run launch readiness across engineering, SEO, and GTM
```

You don't need to name a skill — the assistant routes automatically.

## Skills (39 total)

| Cluster | Examples |
|---------|---------|
| Product | product-strategy, planning-execution, analytics-metrics |
| Engineering | engineering-delivery, devops-iac, coding-best-practices |
| Design | ui-ux-design-to-code, i18n-l10n, 3d-web-development |
| Security | cybersecurity-risk, agent-security-hardening, legal-compliance |
| Quality | rigorous-code-review, bug-finding, business-logic-review |
| Architecture | software-architecture, api-design, data-architecture |
| Growth | marketing-growth, seo-geo, sales-strategy, paid-acquisition |
| Orchestration | project-understanding, continuous-learning, subagent-delegation |

Full roster + quick reference: [AGENTS.md](AGENTS.md)

## Shared memory

`ai-assistant/memory/` is shared across all tools — Claude, Cursor, and Codex read and write the same files.

| File | Purpose |
|------|---------|
| `agent-profile.md` | Your company, product, stack |
| `project-understanding.md` | What the repo is and how it works |
| `learning-log.md` | Dated handoffs and durable lessons |
| `active-continuation.md` | In-flight work across sessions (ephemeral) |

Read/write protocol: [memory-first-handoff-protocol.md](ai-assistant/references/memory-first-handoff-protocol.md)

## Repo layout

```
bosskuAI/
├── AGENTS.md               ← skill roster, model split, memory rules
├── CLAUDE.md               ← Claude Code entry point
├── WORKSPACE-ONBOARDING.md ← onboarding prompt (run once)
├── scripts/
│   ├── install.sh
│   └── install.ps1
├── .claude/                ← hooks, rules, commands for Claude Code
├── .cursor/                ← always-on rules for Cursor
├── .codex/                 ← agent roles and rules for Codex
└── ai-assistant/
    ├── skills/             ← 39 skill SKILL.md files
    ├── memory/             ← shared durable memory
    ├── hooks/              ← fires on every prompt (UserPromptSubmit)
    └── references/         ← checklists, playbooks, protocols
```

## Customize

- **Your project context** — edit `ai-assistant/memory/agent-profile.md`
- **Add/remove skills** — drop folders under `ai-assistant/skills/`
- **Skill routing** — edit `AGENTS.md` Quick reference table
- **Rules** — `.claude/rules/bosskuai.md`, `.cursor/rules/bosskuai.mdc`

## License

MIT — see [LICENSE](LICENSE).
