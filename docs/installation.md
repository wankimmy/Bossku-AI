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

> `--fresh` refreshes the knowledge base only (skills, rules, playbooks, checklists, references). Your run history and memory are preserved, so it is safe to re-run anytime.

Open:

- Web app: `http://localhost:28470`
- API: `http://localhost:28480`

## Desktop App (Windows)

BosskuAI ships a desktop launcher (Electron) that wraps the same Docker stack in a native window. The app still runs everything in Docker — it just automates start-up, first-run database setup, and gives you a tray to start/stop the stack.

### Requirements

- Docker Desktop (installed and running)
- Internet access on first launch (to build images and initialize the database)

### Install and run

1. Run `BosskuAI Setup <version>.exe` and follow the installer.
2. Launch BosskuAI. On first run it will:
   - check that Docker Desktop is available,
   - copy the app files to your user-data folder,
   - run `docker compose up -d --build`,
   - run the first-time database bootstrap (`key:generate`, `migrate`, `db:seed`, `import-knowledge`),
   - open the dashboard once it is ready.
3. First launch can take several minutes (image build + Nuxt build). Subsequent launches are fast.

Use the tray icon to Open, Restart stack, Stop stack (free resources), or Quit. Closing the window keeps the app in the tray.

Add at least one model connection in Settings (Ollama, Anthropic, or Codex/OpenAI) after the app opens, or edit `app/.env` in the user-data stack folder.

### Build the installer from source

```powershell
cd desktop
npm install
npm run dist
```

The installer is written to `desktop/dist/BosskuAI Setup <version>.exe`. Notes:

- The build bundles the stack (`app`, `web`, `docker`, `docker-compose.yml`) as `resources/stack`, excluding `node_modules`, `vendor`, build output, and `app/.env`.
- The installer is unsigned by default, so Windows SmartScreen may warn on first run. Provide a code-signing certificate in `desktop/electron-builder.yml` to sign it.
- To brand the app, drop a 256x256 `desktop/assets/icon.ico`; otherwise the default Electron icon is used.

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
