# Installation

## Requirements

- Python 3.11+
- Git

## Install the CLI

```bash
pip install -e /path/to/Bossku-AI
```

## User-level skills (once per machine)

```bash
bossku install --profile full --vault "/path/to/Obsidian/Vault"
```

Skills are copied to:

- `~/.agents/skills/` — Cursor, Codex, OpenCode
- `~/.claude/skills/` — Claude Code

No symlinks. Unrelated skills in those folders are left untouched.

Coding agents pick skills from installed folders using each skill's `description` (especially **Use when…**) plus your project `AGENTS.md`. After pulling Bossku-AI changes, run `bossku update`. For CLI hints: `bossku skills find "<task>"`.

## Per-project adapter

```bash
bossku init /path/to/project
```

Creates:

- Managed block in `AGENTS.md`
- `CLAUDE.md` with `@AGENTS.md`
- `.bossku/project.json`
- `.bossku/memory/{project,decisions,plans,learnings,handoff}.md`

## Portable mode (cloud/shared repos)

```bash
bossku init /path/to/project --portable --profile core
```

## Update / uninstall

```bash
bossku update
bossku uninstall          # removes managed skills only
bossku uninstall --purge  # also removes ~/.bosskuai/config.json
```

Project memory is kept unless you delete `.bossku/` manually.
