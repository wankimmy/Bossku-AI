# BosskuAI Backend

This folder contains the Laravel API used by the BosskuAI web app.

Most users do not need to work in this folder directly. Start from the repo root README unless you are changing backend code, running Laravel tests, or debugging API behavior.

## What Lives Here

- API routes for runs, settings, memory, skills, rules, and dashboard data
- Orchestrator services for planning, execution, audit, and final review
- Database migrations and seeders
- Laravel tests
- Configuration loaded by the Docker backend service

## Common Commands

Run these from the repo root:

```bash
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

Useful backend checks:

```bash
docker compose exec backend php artisan route:list
docker compose exec backend php artisan test
```

Run a single Laravel command:

```bash
docker compose exec backend php artisan <command>
```

## Environment

The backend reads `app/.env` through Docker Compose.

Important local settings:

- `OLLAMA_BASE_URL` - local Ollama or Ollama Cloud URL
- `OLLAMA_API_KEY` - Ollama Cloud key, blank for many local setups
- `ANTHROPIC_API_KEY` - optional Anthropic Claude key
- `CODEX_OAUTH_CLIENT_ID` and `CODEX_OAUTH_REDIRECT_URI` - optional Codex/OpenAI connection settings
- `BOSSKU_WORKSPACE_HOST_PREFIX` - optional host path for sibling repos

Do not commit real API keys or local secrets.

## Direct API URL

When Docker is running, the API is available at:

```text
http://localhost:28480
```

The Nuxt web app normally calls the API through its same-origin proxy at `http://localhost:28470/api/...`.
