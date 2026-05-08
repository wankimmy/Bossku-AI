# Quickstart

## Workspace-only (skills + rules)

1. Install Bossku into a project repo (see [`installation.md`](installation.md)).
2. Open your editor; type `bossku` to activate BosskuAI mode per [`AGENTS.md`](../AGENTS.md).
3. Try a bounded task; confirm the **`[BOSSKUAI]` header** precedes answers.
4. Optional: query memory  

   ```bash
   python3 ai-assistant/scripts/auto_memory.py query "what did we decide about auth?"
   ```

## Docker MVP (Laravel API + Nuxt UI)

Rough flow (details + troubleshooting in [`README.md`](../README.md)):

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

- UI: **http://localhost:3000**
- API: **http://localhost:8000**

Ensure **`app/.env`** exists and set **`OLLAMA_BASE_URL`** / **`OLLAMA_API_KEY`** (Ollama Cloud) or **`http://host.docker.internal:11434`** (Ollama on your machine). See [`README.md`](../README.md). Optional: **`OLLAMA_EMBEDDING_MODEL`** for vector memory search via Ollama **`/api/embed`**.

**If Stream run fails immediately or `/api/runs` returns 500:** check `docker compose logs -f backend` for `bootstrap/cache` / `storage` not writable. Rebuild the backend image (`docker compose build backend && docker compose up -d backend`); the Dockerfile entrypoint fixes permissions on container start. Validate with `curl -i http://localhost:8000/api/runs`.

## Next

- Example prompts: [`examples.md`](examples.md)
- Architecture tour: [`architecture.md`](architecture.md)
