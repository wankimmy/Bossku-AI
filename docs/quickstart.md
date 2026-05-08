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
docker compose exec ollama ollama pull llama3
```

- UI: **http://localhost:3000**
- API: **http://localhost:8000**

Set API keys in `app/.env` as documented in the main README.

## Next

- Example prompts: [`examples.md`](examples.md)
- Architecture tour: [`architecture.md`](architecture.md)
