# Quickstart

This is the **shortest** path to one useful BosskuAI run. For the full guide (workspace setup, frontend dev, ports, security, troubleshooting), see [`get-started.md`](get-started.md).

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

> `--fresh` refreshes the knowledge base only (skills, rules, playbooks, checklists, references). Your run history and memory are preserved, so it is safe to re-run anytime.

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

## Pixel Office furniture (full desks, couches, etc.)

The landing page **Pixel office** sidebar uses a zep-style layout plus exported furniture sprites. Positions come from `realistic-office-layout.json`; PNGs come from the zep tileset export pipeline.

**Docker (`docker compose up`):** The `frontend` service runs `fetch:zep-furniture` → `export:zep-furniture` → `sync:zep-assets` → `build:pixel-office:bundle` when furniture PNGs are missing (see `web/scripts/docker-web-start.sh`). First start may download the Pixel Agents VSIX from Open VSX once (`BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE=1`). Ensure `zep-pixel-agents` is a sibling of `Bossku-AI` at `/workspace/zep-pixel-agents`, or set `ZEP_PIXEL_AGENTS_ROOT` in repo-root `.env`.

**On your host (manual, same steps as Docker):**

```bash
cd web
export ZEP_PIXEL_AGENTS_ROOT=../../zep-pixel-agents   # adjust path
npm run export:zep-furniture   # once per tileset / zep metadata change
npm run sync:zep-assets
npm run build:pixel-office
```

Windows PowerShell:

```powershell
cd web
$env:ZEP_PIXEL_AGENTS_ROOT = "..\..\zep-pixel-agents"
npm run export:zep-furniture
npm run sync:zep-assets
npm run build:pixel-office
```

From the **frontend Docker container** (`WORKDIR` is `/app` = `web/`), use the same npm scripts (not only `pixel-office/`):

```bash
docker compose exec frontend sh -c 'ZEP_PIXEL_AGENTS_ROOT=/workspace/zep-pixel-agents npm run sync:zep-assets'
```

You need the [Office Interior Tileset (16x16)](https://donarg.itch.io/officetileset) in your zep clone (`npm run import-tileset` in `zep-pixel-agents`). Without it, furniture sprites are omitted but Docker still starts.

**Crash loop fix:** `BOSSKU_PIXEL_OFFICE_GRACEFUL=1` (default) keeps the container running even when the tileset is not imported. Furniture fetch is still attempted via VSIX/vendor/zep. For a strict bake requiring full furniture, set `BOSSKU_PIXEL_OFFICE_STRICT=1` after importing the tileset.

**Optional:** copy a pre-exported `furniture/` tree to `web/pixel-office/vendor/zep-furniture/` so Docker builds do not need zep.

**Full furniture export:** After tileset import, set `BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=0`, `BOSSKU_PIXEL_OFFICE_GRACEFUL=0`, recreate the frontend container.

If furniture still missing after a good build, hard-refresh and clear `localStorage` key `bossku-pixel-layout-v3`.

**Auto-fetch on Docker:** First startup may download the furniture bundle from Open VSX (`BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE=1`). Cached under `web/pixel-office/vendor/zep-furniture/` when `BOSSKU_ZEP_FURNITURE_CACHE=1`. After the first successful bundle, clear `localStorage` key `bossku-pixel-layout-v3` once. Disable fetch with `BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE=0`.

### Layout editing (in the pixel office iframe)

1. Expand **Pixel office** on the landing page.
2. Click **Layout** in the bottom toolbar.
3. Select furniture, then **R** (or the rotate button) to rotate any item.
4. **Delete** or **Backspace** removes the selected item; **Erase** removes furniture under the cursor.
5. Open **Tech** and place **Laptop** on a desk tile (snaps to the desk).
6. Changes persist when you save in the editor (`localStorage` key `bossku-pixel-layout-v3`). Clear that key if an old sparse layout is cached.

## Commands Bossku cannot run in Docker

The Bossku **backend** container often cannot run `docker compose` on your host (no `/var/run/docker.sock`). When a run needs those commands, Bossku pauses and shows **Run a command on your machine**:

1. Copy the command from the modal.
2. Run it in PowerShell or Terminal on your PC (project root).
3. Paste the **full stdout/stderr** into the text box.
4. Click **Submit output & continue** — the agent merges your output and resumes (auditor, etc.).

Example: `npm run build:pixel-office` inside `web/` when the frontend container cannot build the pixel office bundle (especially when zep furniture must be exported on the host first).

## Common Fixes

- Web app does not open: run `docker compose ps` and check that `frontend`, `nginx`, `backend`, `postgres`, and `redis` are running.
- Database or seed errors mentioning `postgres` / **Name does not resolve**: run `docker compose ps` and ensure `postgres` is healthy, then `docker compose exec backend getent hosts postgres` and re-run `docker compose exec backend php artisan migrate --force` and `db:seed --class=BosskuAiSpecSeeder --force`.
- **502 on `/api/runs/stream` (Bad Gateway)**: the API may still be bootstrapping. Run `docker compose logs backend` and wait for `Bootstrap complete. Starting php-fpm`. Confirm http://localhost:28480/api/health/ollama returns 200, then retry the run. Rebuild after code changes: `docker compose build backend frontend && docker compose up -d`.
- API returns `500`: run `docker compose logs -f backend`.
- No skills appear: run `docker compose exec backend php artisan bosskuai:import-knowledge --fresh`.
- Local Ollama fails: check `OLLAMA_BASE_URL` and confirm Ollama is running.
- Cloud model fails: check the relevant API key or Settings connection.

For more setup detail, see [`installation.md`](installation.md).
