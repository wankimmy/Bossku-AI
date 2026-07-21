# BosskuAI

Lightweight AI co-founder toolkit for **Cursor**, **Claude Code**, **Codex**, and **OpenCode**.

One canonical skill library, a small Python CLI, thin project adapters, and optional one-way Obsidian export for durable memory.

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
- [`bossku/`](bossku/) — CLI package (stdlib only)
- [`docs/third-party.md`](docs/third-party.md) — MIT attribution for vendored packs

## Vendored skill packs

BosskuAI ships skills from **marketingskills**, **superpowers**, **hallmark**, **taste-skill**, **loop-engineering**, **scroll-world**, **browser-use**, **graphify**, and a thin **markitdown** wrapper. Use `bossku install --profile full` to install all of them.

Optional CLIs for some packs: see [`requirements-optional.txt`](requirements-optional.txt).

## Archive

The Docker/Laravel/Nuxt product MVP is preserved on branch `archive/product-mvp-2026-07`.

## License

MIT — see [`LICENSE`](LICENSE).
