# Installation

BosskuAI has three installation paths.

| Path | Best for |
|---|---|
| **Desktop app (Windows .exe)** | End users — one installer, no Docker (Hermes-style) |
| **Docker stack** | Developers and power users who want Postgres/pgvector + Redis |
| **Repo toolkit** | Adding BosskuAI skills, rules, and helper files to another repo |

## Desktop App (Windows) — recommended

Download and run **BosskuAI Setup** (same experience as [Hermes Desktop](https://hermes-agent.nousresearch.com/docs/user-guide/desktop)): a small installer that opens BosskuAI in a native window. **Docker is not required.**

### Requirements

- Windows 10 or 11
- Internet on first launch (downloads portable PHP 8.3, Node 22, and Git into `%LOCALAPPDATA%\BosskuAI\`)
- At least one model connection (Ollama Cloud, Anthropic, or Codex) after setup

### Install and run

1. Run `BosskuAI Setup <version>.exe` from `desktop/dist/` (or your release channel).
2. Launch BosskuAI. First run will:
   - copy the app to `%LOCALAPPDATA%\BosskuAI\stack`,
   - download and install portable PHP, Node, and Git,
   - run `composer install`, build the Nuxt dashboard, migrate SQLite, seed, and import knowledge,
   - start the API (`localhost:28480`) and web UI (`localhost:28470`).
3. First launch typically takes **5–15 minutes**. Later launches are much faster.

Semantic memory works on desktop via **SQLite + stored embeddings** (no Postgres). Configure Ollama (or another embedding provider) in Settings so memories get vectorized.

Use the tray icon to open, restart servers, stop servers, or quit.

### Docker mode from the desktop app (optional)

If you prefer the full Docker stack while developing the Electron shell:

```powershell
$env:BOSSKU_DESKTOP_RUNTIME = "docker"
cd desktop
npm start
```

Requires Docker Desktop running and the same ports (`28470` / `28480`) as below.

### Build the installer from source

```powershell
cd desktop
npm install
npm run dist
```

Output: `desktop/dist/BosskuAI Setup <version>.exe`. The installer is unsigned by default (SmartScreen may warn). Add `desktop/assets/icon.ico` to brand the app.

Logs: `%LOCALAPPDATA%\BosskuAI\logs\desktop.log`

## Local Web App (Docker)

### Requirements

- Docker Desktop
- Git
- At least one model connection: local Ollama, Ollama Cloud, Anthropic, or Codex/OpenAI

### Setup

From the BosskuAI repo root:

```bash
cp app/.env.example app/.env
```

On Windows PowerShell:

```powershell
Copy-Item app\.env.example app\.env
```

Edit `app/.env` and set at least one model connection.

Local Ollama from Docker:

```env
OLLAMA_BASE_URL=http://host.docker.internal:11434
OLLAMA_API_KEY=
```

Ollama Cloud:

```env
OLLAMA_BASE_URL=https://ollama.com
OLLAMA_API_KEY=your-ollama-cloud-key
```

Anthropic Claude:

```env
ANTHROPIC_API_KEY=your-anthropic-key
```

Codex/OpenAI can be connected from Settings after the app starts. The default callback URL is `http://localhost:28480/api/oauth/codex/callback`.

Start the stack:

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

> `--fresh` refreshes the knowledge base only (skills, rules, playbooks, checklists, references). Your run history and memory are preserved, so it is safe to re-run anytime.

Open:

- Web app: `http://localhost:28470`
- API: `http://localhost:28480`

## Repo Toolkit

Use this path to copy BosskuAI guidance files into another project.

Linux or macOS:

```bash
./scripts/install.sh /path/to/your/project --profile core
```

Windows PowerShell:

```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

If PowerShell blocks scripts, run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\install.ps1 C:\path\to\your\project -Profile core
```

The installer copies:

- `AGENTS.md`
- `skill-index.json`
- `ai-assistant/`
- supported editor and assistant rules for Claude Code, Codex, Cursor, and OpenCode
- helper scripts

## Optional Dashboard Helper

The lightweight Python helper is useful for local checks and skill sync work:

```bash
python scripts/dashboard.py
```

Open:

```text
http://127.0.0.1:8765
```

## Tool Integration Guides

| Tool | Guide |
|---|---|
| Cursor | [`../integrations/cursor/install.md`](../integrations/cursor/install.md) |
| Claude Code | [`../integrations/claude-code/install.md`](../integrations/claude-code/install.md) |
| Codex | [`../integrations/codex/install.md`](../integrations/codex/install.md) |
| OpenCode | [`../integrations/opencode/install.md`](../integrations/opencode/install.md) |

## Verify

After Docker setup:

```bash
docker compose ps
docker compose exec backend php artisan route:list
```

After repo toolkit install, open the target project and ask your AI assistant:

```text
bossku, confirm what BosskuAI files are available in this repo.
```

For a shorter first-run guide, see [`quickstart.md`](quickstart.md).
