# BosskuAI

BosskuAI is a local AI workspace for software teams. It helps an AI assistant understand a project, plan changes, make focused edits, review the result, and remember useful project knowledge.

BosskuAI can run with local AI or cloud models. Common setups are local Ollama, Ollama Cloud, Anthropic Claude, and Codex/OpenAI models.

## Choose Your Path

BosskuAI can be used in two ways.

| Path | Use this when | What you run |
|---|---|---|
| Local web app | You want the BosskuAI dashboard, run history, memory, skills, and visual workflow | Docker Compose |
| Repo toolkit | You want BosskuAI rules, skills, and helper files inside another project | `scripts/install.sh` or `scripts/install.ps1` |

You can start with either path. The Docker app is easier to see. The repo toolkit is easier to add to an existing coding workflow.

## Path 1: Run the Local Web App

### Requirements

- Docker Desktop
- At least one model connection: local Ollama, Ollama Cloud, Anthropic, or Codex/OpenAI
- Git

### Start

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env
```

On Windows PowerShell, copy the env file with:

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

Codex/OpenAI is connected from Settings after the app is running. The default OAuth callback is `http://localhost:28480/api/oauth/codex/callback`.

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
- API direct access: `http://localhost:28480`

### Connect Other Repos

Docker mounts the parent folder as `/workspace`, so sibling projects can be audited from the UI.

If your repos are outside the default parent folder, set this in `app/.env`:

```env
BOSSKU_WORKSPACE_HOST_PREFIX="C:\path\to\your\workspace"
```

Then open the web app, go to project paths, add the repo, and activate it before running audits or implementation tasks.

### Frontend Notes

The Docker frontend serves a production Nuxt build. After changing files in `web/`, rebuild it:

```bash
docker compose exec -e NUXT_API_PROXY_TARGET=http://nginx/api/** -e NUXT_PUBLIC_API_BASE= frontend npm run build
docker compose restart frontend
```

For hot reload outside Docker:

```bash
cd web
npm install
npm run dev
```

## Path 2: Install BosskuAI Into Another Repo

Use this when you want BosskuAI guidance inside an existing project without running the dashboard.

Linux or macOS:

```bash
./scripts/install.sh /path/to/your/project --profile core
```

Windows PowerShell:

```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

This copies the repo-local AI layer into your project:

- `AGENTS.md`
- `skill-index.json`
- `ai-assistant/`
- editor and assistant rule files where supported
- helper scripts for checks and evals

After installing, open the target repo in your AI coding tool and ask for BosskuAI mode.

## First Useful Prompts

Try these after setup:

```text
bossku, understand this repo and summarize the main architecture.
```

```text
bossku, plan a safe fix for this bug before editing files.
```

```text
bossku, review the current diff for correctness, security, and missing tests.
```

```text
bossku, run a project understanding pass and tell me the risky areas.
```

In the web app chat, type `/` to see available slash commands. `/project-understanding` is the safest first command for an unfamiliar repo.

## What BosskuAI Gives You

- Project understanding before edits
- Planner, executor, auditor, and final reviewer workflow
- Repo-local skills and rules
- Memory for durable project knowledge
- Approval gates for risky work
- Run history and step-by-step visibility in the web app
- Optional knowledge and skill graphs
- Local-first setup using your repos and your chosen AI models

## Common Commands

Backend through Docker:

```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
docker compose exec backend php artisan route:list
docker compose exec backend php artisan test
```

Frontend:

```bash
cd web
npm run test
npm run build
npm run e2e
```

Dashboard helper:

```bash
python scripts/dashboard.py
```

## Ports

Default ports are chosen to avoid common local development conflicts.

| Service | URL or port |
|---|---|
| Web app | `http://localhost:28470` |
| API | `http://localhost:28480` |
| Postgres | `28432` |
| Redis | `28379` |

Override them in the repo-root `.env` with `BOSSKU_PORT_WEB`, `BOSSKU_PORT_API`, `BOSSKU_PORT_POSTGRES`, and `BOSSKU_PORT_REDIS`.

## Security

Local development has no login by default.

Before exposing BosskuAI outside your machine:

1. Set `BOSSKU_API_AUTH_ENABLED=true` in `app/.env`.
2. Set a long random `BOSSKU_API_TOKEN`.
3. Set the same token for the frontend as `NUXT_PUBLIC_API_TOKEN`.
4. Use the production compose overlay:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

See [`docs/production-deploy.md`](docs/production-deploy.md) for TLS, CORS, and deployment hardening.

## Documentation

Start here:

- [`docs/quickstart.md`](docs/quickstart.md) - shortest path to a first run
- [`docs/installation.md`](docs/installation.md) - Docker and repo toolkit setup
- [`docs/what-is-bossku-ai.md`](docs/what-is-bossku-ai.md) - plain explanation and boundaries
- [`docs/examples.md`](docs/examples.md) - useful prompts
- [`docs/faq.md`](docs/faq.md) - common questions

Go deeper:

- [`docs/architecture.md`](docs/architecture.md) - system map
- [`docs/orchestration.md`](docs/orchestration.md) - planner, executor, auditor, final reviewer
- [`docs/skills.md`](docs/skills.md) - skill system
- [`docs/memory.md`](docs/memory.md) - project memory
- [`docs/providers.md`](docs/providers.md) - local AI, Anthropic, Codex/OpenAI, and provider setup
- [`docs/model-routing.md`](docs/model-routing.md) - role-based model routing

## Troubleshooting

- `Bootstrap 500`: rebuild backend with `docker compose build backend && docker compose up -d backend`.
- Stream run has no events: check `docker compose logs -f backend`, then test `http://localhost:28480/api/runs`.
- No skills loaded: run `docker compose exec backend php artisan bosskuai:import-knowledge --fresh`.
- Local Ollama unreachable: check `OLLAMA_BASE_URL` in `app/.env` and confirm Ollama is running.
- Cloud model fails: check the relevant key or connection in `app/.env` or Settings.
- Planner JSON failed: check the selected model, key, base URL, and role model settings in the app.

## License

See [`LICENSE`](LICENSE).
