# Installation

Two common paths:

## 1. Command line (recommended for teams)

From a machine with Git + bash or PowerShell:

```bash
git clone https://github.com/YOUR_ORG/Bossku-AI.git bosskuAI
./bosskuAI/scripts/install.sh /your/project --profile core
```

Windows (PowerShell):

```powershell
.\bosskuAI\scripts\install.ps1 C:\your\project -Profile core
```

This copies `AGENTS.md`, `skill-index.json`, `ai-assistant/`, rules stubs, and helper scripts.

## 2. Python dashboard (optional)

```bash
python3 scripts/dashboard.py
# open http://127.0.0.1:8765 — Actions → Sync skills
```

## After install

```bash
bash scripts/check-workspace.sh . --profile full
python3 -S scripts/eval_workspace.py
```

## Integration guides

| Tool | Doc |
|---|---|
| Cursor | [`../integrations/cursor/install.md`](../integrations/cursor/install.md) |
| Claude Code | [`../integrations/claude-code/install.md`](../integrations/claude-code/install.md) |
| Codex | [`../integrations/codex/install.md`](../integrations/codex/install.md) |
| OpenCode | [`../integrations/opencode/install.md`](../integrations/opencode/install.md) |

## Observable stack (Docker MVP)

See **Quickstart** in [`quickstart.md`](quickstart.md) and root [`README.md`](../README.md).
