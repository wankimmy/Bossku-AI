# Contributing

BosskuAI is a toolkit-only repo: skills, agents, CLI, and docs.

## Changes

1. Keep `AGENTS.md` tool-neutral and concise.
2. Add or edit skills under `skills/<id>/SKILL.md` with YAML frontmatter.
3. Register deprecated names in `skills/aliases.json` instead of duplicating folders.
4. Run before opening a PR:

```bash
pip install -e .
python -m bossku validate --root .
python -m unittest discover -s tests -v
```

## Archive

Product MVP code lives on `archive/product-mvp-2026-07`. Do not reintroduce Laravel/Nuxt/Docker surfaces on `main` without an explicit decision.

## GitHub repository (About)

Use this on **Settings → General → Description** (max 350 characters). It describes toolkit `main`, not the archived Ollama/dashboard product.

**Description (recommended):**

```text
Open-source AI co-founder toolkit for Cursor, Claude Code, Codex & OpenCode. Shared skill library, plan → execute → audit agents, project memory, and bossku CLI — local-first, MIT.
```

**Shorter alternative:**

```text
AI co-founder layer for Cursor, Claude Code, Codex & OpenCode. ~200 skills, multi-agent workflow, persistent memory. Install once with bossku. MIT.
```

**Topics:** `ai-agents`, `cursor`, `claude-code`, `codex`, `opencode`, `agent-skills`, `mit-license`

Do not use “approval gates”, “run dashboard”, or “Local-first (Ollama)” on `main` unless the About text targets `archive/product-mvp-2026-07` instead.
