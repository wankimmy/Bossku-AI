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

- `~/.agents/skills/` — Cursor, Codex, and OpenCode (OpenCode also scans `~/.claude/skills/`)
- `~/.claude/skills/` — Claude Code (and OpenCode)

Bossku does **not** duplicate skills into `~/.cursor/skills/`, `~/.codex/skills/`, or `~/.config/opencode/skills/`; those tools discover the paths above per their upstream docs.

No symlinks. Unrelated skills in those folders are left untouched.

After `bossku install` or `bossku update`, the JSON includes `tools` (per-tool skill paths) and `agents_count` / `claude_count` (must match). Run `bossku doctor` for a human-readable coverage summary; use `bossku doctor --project .` to verify instruction adapters in a repo.

Coding agents pick skills from installed folders using each skill's `description` (especially **Use when…**) plus your project `AGENTS.md`. After pulling Bossku-AI changes, run `bossku update`. For CLI hints: `bossku skills find "<task>"`.

## Tool compatibility

| Tool | Project instructions | Skills (`bossku install`) |
|---|---|---|
| Cursor | `AGENTS.md` | `~/.agents/skills/` |
| Codex | `AGENTS.md` | `~/.agents/skills/` |
| OpenCode | `AGENTS.md` (uses `CLAUDE.md` only if `AGENTS.md` is absent) | `~/.agents/skills/` and `~/.claude/skills/` |
| Claude Code | `CLAUDE.md` with a bare `@AGENTS.md` line (imports `AGENTS.md` at session start) | `~/.claude/skills/` |

Keep `AGENTS.md` as the canonical contract. `bossku init` adds `CLAUDE.md` containing only `@AGENTS.md` so Claude Code shares the same rules without duplicating them. On Windows, prefer this import over symlinking `CLAUDE.md` to `AGENTS.md`.

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
bossku doctor
bossku doctor --project /path/to/project
bossku uninstall          # removes managed skills only
bossku uninstall --purge  # also removes ~/.bosskuai/config.json
```

Project memory is kept unless you delete `.bossku/` manually.
