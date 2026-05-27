# BosskuAI 3.0

BosskuAI is a local AI workspace that makes your AI coding assistant smarter and safer. It adds a structured workflow on top of any AI tool — Claude Code, Cursor, or Codex — so the AI **plans before it edits**, **audits after it edits**, and **remembers what it learns** about your project.

<img width="1895" height="921" alt="image" src="https://github.com/user-attachments/assets/10eb8908-f05e-426a-af68-e83162703f4a" />

<img width="1878" height="911" alt="image" src="https://github.com/user-attachments/assets/f5176bb3-a611-4a51-a7e7-560e65b995f6" />

<img width="1906" height="919" alt="image" src="https://github.com/user-attachments/assets/da80b202-7ab5-4ce5-a504-89cb6bce146c" />

<img width="1905" height="916" alt="image" src="https://github.com/user-attachments/assets/fb1a22c8-2d4c-41d3-829c-e43aa6b2b41f" />

<img width="1898" height="914" alt="image" src="https://github.com/user-attachments/assets/6b09ffcc-d3b1-47a5-b793-38b79e476a45" />

<img width="1895" height="886" alt="image" src="https://github.com/user-attachments/assets/70c11e7d-a35c-418c-a54e-dcf2220151bc" />



## What you get

- **Orchestrated runs**: every task goes through planner → executor → auditor → memory, not just a single prompt
- **Run history**: see every step, file change, and audit result in the web dashboard
- **Approval gates**: risky operations (payments, auth, migrations) pause and ask you first
- **Persistent memory**: project decisions, learnings, and context survive across sessions
- **Skills**: reusable task templates for common workflows (project understanding, security review, etc.)
- **Local-first**: your code stays on your machine; you choose which AI provider to use

---

## Choose your setup

| I want to… | Use this path |
|---|---|
| See the full dashboard with run history, memory, and skills | [Path 1 — Docker web app](#path-1-run-the-web-app-docker) |
| Add BosskuAI rules and skills to an existing project | [Path 2 — Repo toolkit](#path-2-add-bosskuai-to-an-existing-repo) |

Start with Path 1 if you're new. The dashboard shows everything clearly.

---

## Path 1 — Run the web app (Docker)

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- Git
- At least one of: a local [Ollama](https://ollama.com) instance, an Anthropic API key, or a ChatGPT/Codex connection

### 1 — Clone and configure

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env
```

On Windows PowerShell:
```powershell
Copy-Item app\.env.example app\.env
```

Open `app/.env` and add your AI provider credentials. Only one is required to get started:

```env
# Option A: Local Ollama (most private, free)
OLLAMA_BASE_URL=http://host.docker.internal:11434
OLLAMA_API_KEY=

# Option B: Ollama Cloud
OLLAMA_BASE_URL=https://ollama.com
OLLAMA_API_KEY=your-ollama-cloud-key

# Option C: Anthropic Claude
ANTHROPIC_API_KEY=your-anthropic-key

# Option D: Codex / ChatGPT — connect from Settings after launch
```

### 2 — Start the stack

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

This takes 2–4 minutes on first run while Docker pulls images and installs dependencies.

### 3 — Open the app

| Service | URL |
|---|---|
| Web dashboard | http://localhost:28470 |
| API (direct) | http://localhost:28480 |

### 4 — Run your first task

1. Open http://localhost:28470
2. Type a task in the prompt box — for example: `bossku, understand this repo and summarize the main architecture`
3. Press **Run task** and watch the planner, executor, and auditor steps appear in the Agent Process tab
4. After it finishes, check the **Plan**, **Changes**, and **Audit** tabs

Tip: type `/` in the prompt box to see available slash commands. `/project-understanding` is the best first command for any unfamiliar repo.

### Working with your repos

Docker mounts the parent folder as `/workspace`, so sibling repos appear automatically.

If your repos are outside the default parent folder:

```env
# app/.env
BOSSKU_WORKSPACE_HOST_PREFIX="C:\path\to\your\workspace"
```

Then open **Project** in the sidebar, add your repo path, and activate it before running tasks.

### Rebuilding the frontend after changes

The Docker container serves a pre-built Nuxt app. After editing files in `web/`:

```bash
docker compose exec -e NUXT_API_PROXY_TARGET=http://nginx/api/** -e NUXT_PUBLIC_API_BASE= frontend npm run build
docker compose restart frontend
```

For live hot-reload outside Docker:

```bash
cd web
npm install
npm run dev   # runs at http://localhost:3000
```

---

## Path 2 — Add BosskuAI to an existing repo

Use this to add BosskuAI rules, skills, and memory files directly into any project without running the dashboard.

**Linux / macOS:**
```bash
./scripts/install.sh /path/to/your/project --profile core
```

**Windows PowerShell:**
```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

This copies into your project:
- `AGENTS.md` — multi-tool AI contract (Claude Code, Cursor, Codex)
- `skill-index.json` — available task skills
- `ai-assistant/` — memory, orchestrator, and model router scripts
- Rule files for supported editors

After installing, open the project in your AI tool and start a message with `bossku,` to activate BosskuAI mode.

---

## Useful starting prompts

```text
bossku, understand this repo and summarize the main architecture.
```
```text
bossku, plan a safe fix for this bug before editing any files.
```
```text
bossku, review the current diff for correctness, security, and missing tests.
```
```text
bossku, run a project understanding pass and tell me the risky areas.
```

---

## Common commands

**Backend (via Docker):**
```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
docker compose exec backend php artisan route:list
docker compose exec backend php artisan test
```

**Frontend:**
```bash
cd web
npm run test      # unit tests
npm run build     # production build
npm run e2e       # end-to-end tests
```

**Python memory tools (optional, repo toolkit only):**
```bash
# Check current memory state
python3 ai-assistant/scripts/auto_memory.py query "your question" --limit 5

# Save a note
python3 ai-assistant/scripts/auto_memory.py remember --tool cursor --kind learning "what you learned"

# Dashboard
python3 scripts/dashboard.py
```

---

## Ports

Ports are chosen to avoid conflicts with common local dev tools.

| Service | Default URL / port |
|---|---|
| Web app | http://localhost:28470 |
| API | http://localhost:28480 |
| Postgres | 28432 |
| Redis | 28379 |

Override in the root `.env` with `BOSSKU_PORT_WEB`, `BOSSKU_PORT_API`, `BOSSKU_PORT_POSTGRES`, `BOSSKU_PORT_REDIS`.

---

## Security

Local development has no authentication by default — fine for single-machine use.

Before exposing BosskuAI outside your machine:

```env
# app/.env
BOSSKU_API_AUTH_ENABLED=true
BOSSKU_API_TOKEN=a-long-random-string
```

```env
# web/.env or set at build time
NUXT_PUBLIC_API_TOKEN=same-long-random-string
```

Start with the production overlay:
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

See [`docs/production-deploy.md`](docs/production-deploy.md) for TLS, CORS, and hardening.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| **Bootstrap 500 on first load** | `docker compose build backend && docker compose up -d backend` |
| **"Run task" gets no events** | Check `docker compose logs -f backend`, then test http://localhost:28480/api/runs |
| **No skills in the slash menu** | `docker compose exec backend php artisan bosskuai:import-knowledge --fresh` |
| **Local Ollama unreachable** | Confirm Ollama is running; check `OLLAMA_BASE_URL=http://host.docker.internal:11434` |
| **Cloud model fails** | Check API key or connection in `app/.env` or the Settings page |
| **Planner JSON error** | Check selected model, key, and base URL in Settings → Model Routing |
| **Port already in use** | Set `BOSSKU_PORT_WEB`, `BOSSKU_PORT_API`, etc. in the root `.env` |

---

## Documentation

| Document | What it covers |
|---|---|
| [`docs/quickstart.md`](docs/quickstart.md) | Shortest path to a first run |
| [`docs/what-is-bossku-ai.md`](docs/what-is-bossku-ai.md) | Plain explanation and boundaries |
| [`docs/architecture.md`](docs/architecture.md) | System map |
| [`docs/orchestration.md`](docs/orchestration.md) | Planner, executor, auditor, final reviewer |
| [`docs/skills.md`](docs/skills.md) | Skill system |
| [`docs/memory.md`](docs/memory.md) | Project memory |
| [`docs/providers.md`](docs/providers.md) | AI provider setup |
| [`docs/model-routing.md`](docs/model-routing.md) | Role-based model routing |
| [`docs/faq.md`](docs/faq.md) | Common questions |

---

## License

See [`LICENSE`](LICENSE).
