# Installation

BosskuAI has two installation paths.

| Path | Best for |
|---|---|
| Local web app | Running the dashboard and API on your machine |
| Repo toolkit | Adding BosskuAI skills, rules, and helper files to another repo |

## Local Web App

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
- supported editor and assistant rules
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
