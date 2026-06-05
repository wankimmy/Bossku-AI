# BosskuAI Orchestrator Upgrade

BosskuAI now supports AO/Emdash-style fleet orchestration while keeping the planner → executor → auditor → memory pipeline.

## Features

- **Worktree isolation** — per-run git worktrees under `.bossku/worktrees/` (`WorktreeManager`, `RunWorkspace`)
- **Parallel supervisor** — `POST /api/runs/supervisor/spawn` creates a parent run and queued child runs
- **Provider CLI runtime** — `GET /api/providers/cli`, `POST /api/runs/{id}/cli-session`
- **Agent hooks** — `POST /api/hooks/agent` (optional `BOSSKU_AGENT_HOOK_TOKEN`)
- **SCM reactions** — GitHub CI/review polling via `PollScmReactionsJob` and `bossku.reactions` config
- **Verified learning** — `FeedbackReport` + verification gate before auto-promote
- **Remote/BYOI** — SSH command preview, BYOI workspace attach endpoints

## Configuration (`app/.env`)

```env
BOSSKU_WORKTREE_ENABLED=true
BOSSKU_WORKTREE_AUTO_PROVISION=false
BOSSKU_GITHUB_TOKEN=
BOSSKU_AGENT_HOOK_TOKEN=
BOSSKU_LEARNING_REQUIRE_VERIFICATION=true
BOSSKU_SSH_EXECUTION_ENABLED=false
BOSSKU_BYOI_ENABLED=false
```

## API quick reference

| Endpoint | Purpose |
|----------|---------|
| `POST /api/runs/supervisor/spawn` | Spawn parallel child runs |
| `GET /api/runs/{id}/supervisor` | Supervisor fleet status |
| `GET /api/providers/cli` | Detect installed agent CLIs |
| `POST /api/runs/{id}/cli-session` | Run provider CLI in worktree |
| `POST /api/hooks/agent` | CLI lifecycle hooks |
| `GET /api/feedback-reports` | Structured self-improvement reports |
| `POST /api/runs/{id}/scm` | Attach GitHub PR for SCM reactions |
| `GET /api/runs/{id}/scm` | SCM link + reaction state |

## Desktop (BosskuAI Setup 3.0.0.exe)

Native desktop starts **API + queue worker + scheduler** automatically (`desktop/src/nativeRuntime.js`).

- `QUEUE_CONNECTION=database` (SQLite) from `app/.env.desktop.example`
- Git + worktrees use portable Git from `%LOCALAPPDATA%\BosskuAI\runtime\git`
- Provider CLIs detected from Windows PATH (Claude, Codex, Cursor, etc.)
- UI: Settings → Orchestrator (spawn fleet), Run detail (CLI + SCM panels)

**Upgrading an existing install:** copy orchestrator vars from `.env.desktop.example` into `%LOCALAPPDATA%\BosskuAI\stack\app\.env`, set `QUEUE_CONNECTION=database`, restart the app.

## Docker

`docker compose up -d` starts `queue` and `scheduler` services alongside `backend`.
