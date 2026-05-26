# Quickstart

This guide gets you to one useful BosskuAI run.

Choose one path:

- **Local web app** - best if you want the dashboard, run history, memory, and visual workflow.
- **Repo toolkit** - best if you want BosskuAI guidance inside another project.

## Path 1: Local Web App

From the BosskuAI repo root:

```bash
cp app/.env.example app/.env
```

On Windows PowerShell:

```powershell
Copy-Item app\.env.example app\.env
```

Set at least one model connection in `app/.env`.

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

Codex/OpenAI can be connected from Settings after the app starts.

Start and bootstrap:

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

Open the web app:

```text
http://localhost:28470
```

The direct API URL is:

```text
http://localhost:28480
```

Try this first prompt:

```text
Use project understanding mode. Inspect this repo and summarize what I should know before editing it.
```

## Path 2: Repo Toolkit

Install BosskuAI files into another project.

Linux or macOS:

```bash
./scripts/install.sh /path/to/your/project --profile core
```

Windows PowerShell:

```powershell
.\scripts\install.ps1 C:\path\to\your\project -Profile core
```

Open that project in your AI coding tool and try:

```text
bossku, understand this repo and identify the safest next step.
```

## After the First Run

Useful next prompts:

```text
bossku, plan this change before editing files: <describe the change>
```

```text
bossku, review the current diff and tell me what is risky.
```

```text
bossku, write a test plan for this feature.
```

## Common Fixes

- Web app does not open: run `docker compose ps` and check that `frontend`, `nginx`, `backend`, `postgres`, and `redis` are running.
- API returns `500`: run `docker compose logs -f backend`.
- No skills appear: run `docker compose exec backend php artisan bosskuai:import-knowledge --fresh`.
- Local Ollama fails: check `OLLAMA_BASE_URL` and confirm Ollama is running.
- Cloud model fails: check the relevant API key or Settings connection.

For more setup detail, see [`installation.md`](installation.md).
