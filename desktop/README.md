# BosskuAI Desktop

Hermes-style Windows desktop app: thin installer, full stack on first launch.

## Orchestrator (v3.1+)

The desktop app uses the same Laravel orchestrator API as the web stack:

- Parallel supervisor runs (`POST /api/runs/supervisor/spawn`)
- Per-run git worktrees (`.bossku/worktrees`)
- Provider CLI detection (`GET /api/providers/cli`)
- Optional SSH/BYOI remote workspaces

See [docs/orchestrator-upgrade.md](../docs/orchestrator-upgrade.md).

## Modes

| Mode | How |
|---|---|
| **Native (default)** | Packaged `.exe` or `npm start` — portable PHP + Node + Git, SQLite, database queue worker + scheduler (no Docker) |
| **Docker** | `BOSSKU_DESKTOP_RUNTIME=docker npm start` — requires Docker Desktop + compose stack |

## Data locations (native)

- `%LOCALAPPDATA%\BosskuAI\stack` — app copy
- `%LOCALAPPDATA%\BosskuAI\runtime` — PHP, Node, Git
- `%LOCALAPPDATA%\BosskuAI\data\bossku.sqlite` — database
- `%LOCALAPPDATA%\BosskuAI\logs\desktop.log` — logs

## Build installer

```powershell
cd desktop
npm install
npm run dist
```

Output: `dist/BosskuAI Setup 3.0.3.exe`
