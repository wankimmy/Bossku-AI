# Architecture

```text
Bossku-AI repo (canonical skills + contract)
        │
        ├─ bossku install ──► ~/.agents/skills + ~/.claude/skills
        │
        └─ bossku init ──► project AGENTS.md + .bossku/memory/
                                    │
                                    └─ bossku sync ──► Obsidian/BosskuAI/<project>/
```

## Principles

- **One source of truth** — skills and contract in this repo
- **Thin adapters** — projects keep small instruction blocks, not full copies
- **On-demand depth** — ~100 specialist skills; small always-on contract
- **No runtime orchestrator** — agents follow markdown contracts in your editor

## Agents

Five workflow roles in [`agents/`](../agents/): orchestrator → planner → executor → auditor → final reviewer.

Specialist behavior lives in skills, not separate agent services.
