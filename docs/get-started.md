# Get Started

Full setup guide for BosskuAI. For the shortest first run, see [`quickstart.md`](quickstart.md). For desktop and advanced install paths, see [`installation.md`](installation.md).

## Two ways to use BosskuAI

| You want... | Use this |
|---|---|
| Dashboard, run history, memory, skills, and approval gates | **Path 1 — Docker web app** (below) |
| Rules and skills in an existing project, no dashboard | **Path 2 — Repo toolkit** (below) |

**New here?** Start with Path 1. The dashboard shows Planner, Executor, and Auditor steps as they run.

---

## Path 1 — Docker web app (5 minutes)

### What you need

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- Git
- One AI provider: local [Ollama](https://ollama.com), an Anthropic API key, or ChatGPT/Codex (connect from Settings after launch)

### Step 1 — Clone and configure

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env
```

Open `app/.env` and add **one** provider:

```env
# A — Local Ollama (most private, free)
OLLAMA_BASE_URL=http://host.docker.internal:11434

# B — Ollama Cloud
OLLAMA_BASE_URL=https://ollama.com
OLLAMA_API_KEY=your-ollama-cloud-key

# C — Anthropic Claude
ANTHROPIC_API_KEY=your-anthropic-key

# D — Codex / ChatGPT (connect from Settings after launch)
```

### Step 2 — Start it

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

First run takes 2–4 minutes while Docker pulls images and installs dependencies.

### Step 3 — Open it

| Service | URL |
|---|---|
| Web dashboard | http://localhost:28470 |
| API (direct) | http://localhost:28480 |

### Step 4 — Run your first task

1. Open http://localhost:28470
2. Type a task, for example: `bossku, understand this repo and summarize the architecture`
3. Press **Run task** and watch the Planner, Executor, and Auditor steps appear live
4. When it finishes, open the **Plan**, **Changes**, and **Audit** tabs

> Tip: type `/` in the prompt box to see slash commands. `/project-understanding` is the best first command for any unfamiliar repo.

### Working with your own repos

Docker mounts the parent folder as `/workspace`, so sibling repos appear automatically.

If your repos live elsewhere, point BosskuAI at them:

```env
# app/.env
BOSSKU_WORKSPACE_HOST_PREFIX="C:\path\to\your\workspace"
```

Then open **Project** in the sidebar, add the repo path, and activate it before running tasks.

### Editing the frontend

The container serves a pre-built Nuxt app. After changing files in `web/`, rebuild:

```bash
docker compose exec -e NUXT_API_PROXY_TARGET=http://nginx/api/** -e NUXT_PUBLIC_API_BASE= frontend npm run build
docker compose restart frontend
```

For live hot-reload during development:

```bash
cd web
npm install
npm run dev   # http://localhost:3000
```

---

## Path 2 — Add to an existing repo

Drop BosskuAI's rules, skills, and memory into any project. No dashboard required.

**Linux / macOS:**

```bash
./scripts/install.sh /path/to/your/project --profile core
```

**Windows PowerShell:**

```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

This copies in:

- `AGENTS.md` — the shared contract for Claude Code, Cursor, and Codex
- `skill-index.json` — available task skills
- `ai-assistant/` — memory, orchestrator, and model-router scripts
- Rule files for supported editors

Then open the project in your AI tool and start any message with `bossku,` to activate BosskuAI mode.

---

## Prompts to try

```text
bossku, understand this repo and summarize the main architecture.
bossku, plan a safe fix for this bug before editing any files.
bossku, review the current diff for correctness, security, and missing tests.
bossku, run a project understanding pass and tell me the risky areas.
```

More examples: [`examples.md`](examples.md).

---

## Common commands

**Backend (via Docker):**

```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
docker compose exec backend php artisan route:list
docker compose exec backend php artisan test
```

> `import-knowledge --fresh` refreshes only knowledge (skills, rules, playbooks). Run history and memory are preserved.

**Frontend:**

```bash
cd web
npm run test    # unit tests
npm run build   # production build
npm run e2e     # end-to-end tests
```

**Memory tools (repo toolkit only):**

```bash
python3 ai-assistant/scripts/auto_memory.py query "your question" --limit 5
python3 ai-assistant/scripts/auto_memory.py remember --tool cursor --kind learning "what you learned"
python3 scripts/dashboard.py
```

---

## Ports

Chosen to avoid clashing with common local dev tools.

| Service | Default |
|---|---|
| Web app | http://localhost:28470 |
| API | http://localhost:28480 |
| Postgres | 28432 |
| Redis | 28379 |

Override in the root `.env`: `BOSSKU_PORT_WEB`, `BOSSKU_PORT_API`, `BOSSKU_PORT_POSTGRES`, `BOSSKU_PORT_REDIS`.

---

## Security

Local development has **no authentication** by default — fine for a single machine.

Before exposing BosskuAI to a network, turn auth on:

```env
# app/.env
BOSSKU_API_AUTH_ENABLED=true
BOSSKU_API_TOKEN=a-long-random-string
```

```env
# web/.env (or set at build time)
NUXT_PUBLIC_API_TOKEN=same-long-random-string
```

Then start with the production overlay:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

See [`production-deploy.md`](production-deploy.md) for TLS, CORS, and hardening.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| **Bootstrap 500 on first load** | `docker compose build backend && docker compose up -d backend` |
| **502 on `/api/runs/stream`** | Wait until `docker compose logs backend` shows `Bootstrap complete. Starting php-fpm`, then retry. Still stuck: `docker compose build backend frontend && docker compose up -d` |
| **"Run task" produces no events** | Check `docker compose logs -f backend`, then test http://localhost:28480/api/runs |
| **No skills in the slash menu** | `docker compose exec backend php artisan bosskuai:import-knowledge --fresh` |
| **Local Ollama unreachable** | Confirm Ollama is running and `OLLAMA_BASE_URL=http://host.docker.internal:11434` |
| **Cloud model fails** | Check the API key in `app/.env` or the Settings page |
| **Planner JSON error** | Check the model, key, and base URL in Settings, then Model Routing |
| **Port already in use** | Set `BOSSKU_PORT_WEB`, `BOSSKU_PORT_API`, etc. in the root `.env` |

More fixes: [`quickstart.md#common-fixes`](quickstart.md#common-fixes) and [`faq.md`](faq.md).
