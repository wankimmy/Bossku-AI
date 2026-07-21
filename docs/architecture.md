# Architecture

```text
Bossku-AI repo (canonical skills + contract)
        │
        ├─ bossku install ──► ~/.agents/skills + ~/.claude/skills
        │
        └─ bossku init ──► project AGENTS.md + CLAUDE.md (@AGENTS.md) + .bossku/memory/
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

## Cross-tool adapters

- **Cursor, Codex, OpenCode** load project `AGENTS.md` (OpenCode falls back to `CLAUDE.md` only when `AGENTS.md` is missing).
- **Claude Code** loads `CLAUDE.md`; Bossku-AI repos and `bossku init` projects use `@AGENTS.md` so Claude imports the same contract without a second copy.
- **Skills** install to `~/.agents/skills/` (Cursor, Codex, OpenCode) and `~/.claude/skills/` (Claude Code). See [installation.md](installation.md#tool-compatibility).
