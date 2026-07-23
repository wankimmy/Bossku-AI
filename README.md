# BosskuAI

Open-source AI co-founder toolkit for **Cursor**, **Claude Code**, **Codex**, and **OpenCode**.

One canonical skill library, plan → execute → audit agents, project memory, and the `bossku` CLI — optional one-way Obsidian export for durable memory.

## Quick start

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
pip install -e .
bossku install --vault "C:/path/to/your/Obsidian/Vault"
cd /path/to/your/project
bossku init .
```

Open the project in any supported coding agent. Say `bossku` or ask for cofounder mode.

## Plugin / marketplace install

Install BosskuAI as a native plugin on Claude Code, Cursor, and Codex. OpenCode uses skill discovery plus the `.opencode` harness (no marketplace plugin).

### Claude Code

Run inside Claude Code:

```text
/plugin marketplace add wankimmy/Bossku-AI
/plugin install bossku-ai@bosskuai-marketplace
```

Manifests: [`.claude-plugin/`](.claude-plugin/).

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

Restart Cursor after install. Manifests: [`.cursor-plugin/`](.cursor-plugin/).

### Codex

```bash
codex plugin marketplace add wankimmy/Bossku-AI
```

Then restart the ChatGPT desktop app, open **Plugins**, choose the **BosskuAI** marketplace, and install **bossku-ai**. Manifests: [`.codex-plugin/`](.codex-plugin/) and [`.agents/plugins/marketplace.json`](.agents/plugins/marketplace.json).

### OpenCode

No marketplace plugin. Install skills once per machine, then open this repo (or any `bossku init` project) as the workspace:

```bash
pip install -e /path/to/Bossku-AI
bossku install --profile full
```

OpenCode reads [`.opencode/opencode.jsonc`](.opencode/opencode.jsonc) for `AGENTS.md` and agent references.

See [`docs/installation.md`](docs/installation.md) for the CLI path and per-project setup.

## Commands

| Command | Purpose |
|---|---|
| `bossku install` | Copy skills to `~/.agents/skills` and `~/.claude/skills` |
| `bossku init <project>` | Add project adapter + `.bossku/memory/` |
| `bossku init <project> --portable` | Vendor skills into the project |
| `bossku update` | Refresh user-level skills from this repo |
| `bossku remember --project . --kind decision "..."` | Save curated memory |
| `bossku sync --project .` | Export memory to Obsidian |
| `bossku skills find "laravel security"` | Suggest a skill |
| `bossku doctor` | Install health check |
| `bossku validate --root .` | Repository validation |

## Layout

- [`AGENTS.md`](AGENTS.md) — cross-tool contract
- [`skills/`](skills/) — canonical skill library (~196 skills, including vendored packs)
- [`skills/vendored.json`](skills/vendored.json) — third-party skill provenance
- [`agents/`](agents/) — orchestrator, planner, executor, auditor, final reviewer
- [`.claude-plugin/`](.claude-plugin/) — Claude Code plugin + marketplace manifests
- [`.cursor-plugin/`](.cursor-plugin/) — Cursor plugin + marketplace manifests
- [`.codex-plugin/`](.codex-plugin/) — Codex plugin manifest
- [`.agents/plugins/`](.agents/plugins/) — Codex marketplace catalog
- [`.opencode/`](.opencode/) — OpenCode references harness
- [`bossku/`](bossku/) — CLI package (stdlib only)
- [`docs/third-party.md`](docs/third-party.md) — MIT attribution for vendored packs

## Vendored skill packs

BosskuAI ships skills from **marketingskills**, **superpowers**, **hallmark**, **taste-skill**, **loop-engineering**, **scroll-world**, **browser-use**, **graphify**, and a thin **markitdown** wrapper. Use `bossku install --profile full` to install all of them.

Optional CLIs for some packs: see [`requirements-optional.txt`](requirements-optional.txt).

## Archive

The Docker/Laravel/Nuxt product MVP is preserved on branch `archive/product-mvp-2026-07`.

## License

MIT — see [`LICENSE`](LICENSE).
