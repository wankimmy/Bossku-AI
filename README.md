# BosskuAI

A cofounder-style AI layer for **Claude Code, Cursor, and Codex** — auto-loads skills, reads shared memory, and enforces plan-first on meaningful work.

## Quick setup

```bash
# 1. Clone
git clone https://github.com/wankimmy/Bossku-AI bosskuAI

# 2. Install into your project
./bosskuAI/scripts/install.sh /path/to/your/project

# 3. Open your project in Claude / Cursor / Codex and run the onboarding prompt
```

> **Windows:** use `.\bosskuAI\scripts\install.ps1 C:\path\to\your\project`

After install, paste the onboarding prompt from [WORKSPACE-ONBOARDING.md](WORKSPACE-ONBOARDING.md) once to initialize memory for your project.

## How it works

Every meaningful turn follows **`AGENTS.md`** (tool-neutral source of truth):

1. **Reads shared memory** — `ai-assistant/memory/` using the [memory-first protocol](ai-assistant/references/memory-first-handoff-protocol.md) (retrieve the minimum relevant files, not full dumps every time).
2. **Classifies and routes** — picks from **60** skills across orchestration, product, engineering, design, security, quality, architecture, growth, and continuation.
3. **Plans first** — two-phase model split (e.g. Claude: `claude-opus-4-6` plan → `claude-sonnet-4-6` execute; Codex/Cursor mappings in [AGENTS.md](AGENTS.md)). Quick/trivial tasks may skip the split.
4. **Promotes learnings** — writes durable memory only when the work meets the threshold in `AGENTS.md` (batch at task end; avoid ceremony for no-delta work).
5. **Reports sparsely** — in normal Execution mode, prefer short notes when memory or learning changed; full `[TASK START]` / `[TASK END]`-style blocks are for Debug/Handoff or tool-specific rules (e.g. some Claude Code presets surface headers for audit).

If you need routing, phased pipelines, or “what to ask for,” use **`AGENTS.md`** → Quick reference.

## Activation

Say `bossku` anywhere in a prompt **or run `/bossku` as a slash command** in Claude Code to explicitly activate BosskuAI mode:

```
bossku review this PR for security and business-logic risks
bossku plan the smallest safe implementation for this feature
bossku run launch readiness across engineering, SEO, and GTM
```

```
/bossku design the integration test layer for our API
```

You don't need to name a skill — the assistant routes automatically.

## 60 Skills

The canonical **when to use** table lives in [AGENTS.md](AGENTS.md). A few folders are legacy aliases (e.g. routed to `bosskuai-bug-finding`, `bosskuai-planning-execution`, `bosskuai-marketing-growth`).

| Cluster | Examples |
|---------|----------|
| Orchestration | workspace-assistant, project-understanding, search-first, documentation-lookup, deep-research, skill-creator, claude-code-setup, cross-model-escalation |
| Product | product-strategy, customer-discovery, planning-execution, financial-modeling, operations, launch-commercialization |
| Engineering | engineering-delivery, rapid-prototype, github-workflow, nuxt-development, mongodb, browser-automation, devops-iac, integration-testing |
| Design | ui-ux-design-to-code, i18n-l10n, 3d-web-development |
| Security | cybersecurity-risk, agent-security-hardening, legal-compliance |
| Quality | rigorous-code-review, bug-finding, business-logic-review |
| Architecture | software-architecture, api-design, data-architecture |
| Growth | market-analysis, competitor-intelligence, marketing-growth, growth-experiment, seo-geo, investor-prep, lead-intelligence, sales-strategy |
| Continuation | context-limit-continuation, ai-model-selection |

Full roster + quick reference: [AGENTS.md](AGENTS.md). Machine-readable index: [skill-index.json](skill-index.json).

## Shared memory

`ai-assistant/memory/` is shared across all tools — Claude, Cursor, and Codex read and write the same files.

| File | Purpose |
|------|---------|
| `agent-profile.md` | Your company, product, stack |
| `project-understanding.md` | What the repo is and how it works |
| `learning-log.md` | Dated handoffs and durable lessons |
| `bug-patterns.md` | Recurring defect patterns |
| `market-notes.md` | Market/competitive notes |
| `active-continuation.md` | In-flight work across sessions (ephemeral) |

Read/write protocol: [memory-first-handoff-protocol.md](ai-assistant/references/memory-first-handoff-protocol.md)

## Repo layout

```
bosskuAI/
├── AGENTS.md               ← skill roster, model split, memory rules, quick reference
├── CLAUDE.md               ← Claude Code entry point
├── WORKSPACE-ONBOARDING.md ← onboarding prompt (run once)
├── skill-index.json        ← machine-readable skill metadata / routing hints
├── scripts/
│   ├── install.sh
│   └── install.ps1
├── agents/                 ← subagent briefs (parallel/delegated work; see agents/README.md)
├── mcp-configs/            ← example MCP configs per tool (see mcp-configs/README.md)
├── .claude/                ← hooks, rules, commands for Claude Code
├── .cursor/                ← always-on rules for Cursor
├── .codex/                 ← agent roles and rules for Codex
└── ai-assistant/
    ├── skills/             ← skill SKILL.md folders
    ├── memory/             ← shared durable memory
    ├── hooks/              ← prompt/session hooks
    ├── references/         ← checklists, playbooks, protocols, ADRs
    └── scripts/            ← e.g. learning-doctor.sh
```

> **`install.sh`** copies `AGENTS.md`, `CLAUDE.md`, `WORKSPACE-ONBOARDING.md`, `.claude/`, `.cursor/`, `.codex/`, and `ai-assistant/` into the target project. Optional: browse `agents/` and `mcp-configs/` in this repo for delegation patterns and MCP setup, including the optional `code-review-graph` MCP for graph-aware PR review and blast-radius analysis.

## Customize

- **Your project context** — edit `ai-assistant/memory/agent-profile.md` and `project-understanding.md`
- **Add/remove skills** — folders under `ai-assistant/skills/`
- **Skill routing** — edit [AGENTS.md](AGENTS.md) (Quick reference + Skill roster); [skill-index.json](skill-index.json) for structured consumers
- **Rules** — `.claude/rules/bosskuai.md`, `.cursor/rules/bosskuai.mdc`, `.codex/AGENTS.md`

## License

MIT — see [LICENSE](LICENSE).
