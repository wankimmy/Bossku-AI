# Installation

## Requirements

- Python 3.11+
- Git

## Plugin / marketplace install

BosskuAI ships native plugin manifests for Claude Code, Cursor, and Codex. OpenCode uses skill discovery plus the `.opencode` harness.

### Claude Code

```text
/plugin marketplace add wankimmy/Bossku-AI
/plugin install bossku-ai@bosskuai-marketplace
```

Skills and agents load from this repository via [`.claude-plugin/plugin.json`](../.claude-plugin/plugin.json). Refresh after updates with `/plugin marketplace update` and reinstall if needed.

### Cursor

**Windows (PowerShell):**

```powershell
git clone https://github.com/wankimmy/Bossku-AI $env:USERPROFILE\.cursor\plugins\local\bossku-ai
```

Or link an existing clone:

```powershell
$pluginDir = "$env:USERPROFILE\.cursor\plugins\local\bossku-ai"
New-Item -ItemType Directory -Force -Path (Split-Path $pluginDir) | Out-Null
if (Test-Path $pluginDir) { Remove-Item $pluginDir -Recurse -Force }
New-Item -ItemType Junction -Path $pluginDir -Target "C:\path\to\Bossku-AI"
```

**macOS / Linux:**

```bash
git clone https://github.com/wankimmy/Bossku-AI ~/.cursor/plugins/local/bossku-ai
```

Restart Cursor after install. Cursor reads [`.cursor-plugin/plugin.json`](../.cursor-plugin/plugin.json) and the thin rule in [`.cursor/rules/bosskuai.mdc`](../.cursor/rules/bosskuai.mdc).

For team distribution, point a Cursor marketplace at this repository using [`.cursor-plugin/marketplace.json`](../.cursor-plugin/marketplace.json).

### Codex

```bash
codex plugin marketplace add wankimmy/Bossku-AI
```

Restart the ChatGPT desktop app, open the Plugins Directory, choose the **BosskuAI** marketplace, and install **bossku-ai**. Catalog: [`.agents/plugins/marketplace.json`](../.agents/plugins/marketplace.json).

### OpenCode

OpenCode does not use a Claude-style marketplace plugin. Install skills with the CLI, then open this repo as the workspace:

```bash
pip install -e /path/to/Bossku-AI
bossku install --profile full
```

[`.opencode/opencode.jsonc`](../.opencode/opencode.jsonc) references `AGENTS.md`, `skills/`, and `agents/`.

## Install the CLI

```bash
pip install -e /path/to/Bossku-AI
```

## User-level skills (once per machine)

```bash
bossku install --profile full --vault "/path/to/Obsidian/Vault"
```

`--profile core` installs co-founder essentials plus the **loop-engineering** pack (12 skills). Loop discipline is always on in [`AGENTS.md`](../AGENTS.md#loop-engineering-always-on). After pulling Bossku-AI changes, run `bossku update` so installed skills match the repo.

Skills are copied to:

- `~/.agents/skills/` — Cursor, Codex, and OpenCode (OpenCode also scans `~/.claude/skills/`)
- `~/.claude/skills/` — Claude Code (and OpenCode)

Bossku does **not** duplicate skills into `~/.cursor/skills/`, `~/.codex/skills/`, or `~/.config/opencode/skills/`; those tools discover the paths above per their upstream docs.

No symlinks. Unrelated skills in those folders are left untouched.

After `bossku install` or `bossku update`, the JSON includes `tools` (per-tool skill paths) and `agents_count` / `claude_count` (must match). Run `bossku doctor` for a human-readable coverage summary; use `bossku doctor --project .` to verify instruction adapters in a repo.

Coding agents pick skills from installed folders using each skill's `description` (especially **Use when…**) plus your project `AGENTS.md`. After pulling Bossku-AI changes, run `bossku update`. For CLI hints: `bossku skills find "<task>"`.

## Tool compatibility

| Tool | Plugin / marketplace | Project instructions | Skills |
|---|---|---|---|
| Cursor | [`.cursor-plugin/`](../.cursor-plugin/) | `AGENTS.md` | `~/.agents/skills/` or bundled plugin skills |
| Codex | [`.codex-plugin/`](../.codex-plugin/) + [`.agents/plugins/`](../.agents/plugins/) | `AGENTS.md` | `~/.agents/skills/` or bundled plugin skills |
| OpenCode | [`.opencode/`](../.opencode/) references only | `AGENTS.md` (uses `CLAUDE.md` only if `AGENTS.md` is absent) | `~/.agents/skills/` and `~/.claude/skills/` |
| Claude Code | [`.claude-plugin/`](../.claude-plugin/) | `CLAUDE.md` with a bare `@AGENTS.md` line | `~/.claude/skills/` or bundled plugin skills |

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
