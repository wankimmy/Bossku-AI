# BosskuAI

**BosskuAI is a lightweight agentic AI orchestration layer for software builders.**

It helps AI coding assistants behave more like a small engineering team:

- **Orchestrator** — understand the task, detect the right skill, decide if memory helps, draft a compact plan  
- **Executor** — implement with narrow diffs  
- **Auditor** — correctness, security, performance, maintainability  
- **Final reviewer** — confirm completion and remaining risks  
- **Memory** — durable project facts (markdown + retrieval + optional vector DB)

It plugs into **Cursor**, **Claude Code**, **Codex**, **OpenCode**, and ships an optional **Docker MVP** (Laravel + Nuxt) for an observable workflow.

BosskuAI is **not** here to replace LangChain or CrewAI — see [`docs/comparison.md`](docs/comparison.md).

---

## What is BosskuAI?

Read [`docs/what-is-bossku-ai.md`](docs/what-is-bossku-ai.md).

## Why use BosskuAI?

- You switch between editors / agents / models and want **one consistent contract**.  
- You want **auditable discipline** without adopting a heavyweight agent framework.  
- You want **repo-local memory** you control, not SaaS-only state.

## How it works

1. **`AGENTS.md`** is the canonical cross-tool agreement (memory, routing mentality, verification).  
2. **`agents/*.md`** define orchestrator / executor / auditor / final reviewer behavior and the **`[BOSSKUAI]` header**.  
3. **`skill-index.json` + `ai-assistant/skills/`** carry deep playbooks.  
4. **Optional Docker MVP** enforces routing in `app/config/bossku_models.php` + streams runs in the Nuxt UI.

Depth: [`docs/architecture.md`](docs/architecture.md) · honesty about “multi-agent” per IDE: [`docs/multi-agent-architecture.md`](docs/multi-agent-architecture.md).

---

## Mandatory response indicator

Every Bossku-compliant answer starts with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

Details: [`AGENTS.md`](AGENTS.md) · keyword → skill mapping: [`agents/skill-detector.md`](agents/skill-detector.md).

---

## Features

| Area | Highlights |
|---|---|
| Routing | Kimi-class orchestrator/writer/direct-answer, GLM executor, DeepSeek auditor/reviewer (`app/config/bossku_models.php`, Ollama Cloud) |
| Skills | Laravel, Nuxt, Docker, DB, security, UX, SEO/GEO, testing, product strategy — see [`skills/`](skills/) |
| Memory | Policy + schema in [`memory/`](memory/) · CLI `auto_memory.py` |
| Tokens | [`playbooks/token-saving.md`](playbooks/token-saving.md) |
| Auditing | [`agents/auditor.md`](agents/auditor.md) |
| UI specs | [`ui/`](ui/) (dashboards for memory, activity, routing transparency) |

---

## Observable stack (Docker MVP)

Local Laravel (`app/`) + Nuxt (`web/`) + `docker-compose.yml`. Imports SKILL.md/playbooks into **Postgres + pgvector**, streams execution stages, persists run steps.

**Quick compose:**

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

Ensure **`app/.env`** exists (`cp app/.env.example app/.env`) so the backend receives **`OLLAMA_BASE_URL`** (e.g. `https://ollama.com`), **`OLLAMA_API_KEY`** from [ollama.com/settings/keys](https://ollama.com/settings/keys), and optional **`OLLAMA_EMBEDDING_MODEL`** for pgvector-backed memory embeddings via Ollama **`/api/embed`**.

Docker Compose **`backend`** binds **`OLLAMA_*`** exclusively for LLMs at runtime. There is no separate non-Ollama provider configuration in Laravel.

- UI: **http://localhost:3000** · API/SSE base: **http://localhost:8000**

**Role defaults (logical → `:cloud` tags in `bossku_models.php`):**

- Orchestrator / router / writer / direct-answer: **`kimi-k2.6`**
- Executor (default/frontend/backend/devops): **`glm-5.1`**
- High-risk executor + auditor + security auditor + final reviewer: **`deepseek-v4-pro`**

Models whose names match **`BOSSKU_OLLAMA_MODEL_PATTERNS`** are routed through **Ollama** at **`POST {OLLAMA_BASE_URL}/api/chat`**. **`OLLAMA_API_KEY`** is required for Cloud and is sent as a Bearer token from [`OllamaClient`](app/app/Services/Llm/OllamaClient.php).

Docker Compose’s **`backend`** service uses **`env_file: ./app/.env`** (create **`app/.env`** from **`app/.env.example`** before `docker compose up`).

Docker sets `BOSSKU_REPO_PATH=/repo` so the importer reads markdown from this repo.

**Troubleshooting**

- **Bootstrap 500 / Stream run shows no events:** If logs show `The /var/www/html/bootstrap/cache directory must be present and writable`, the **backend image** runs an entrypoint that `chmod`s `bootstrap/cache` and `storage` on start — rebuild: `docker compose build backend && docker compose up -d backend`. Then verify: `curl -i http://localhost:8000/api/runs`. Clear caches: `docker compose exec backend php artisan optimize:clear` and `docker compose restart backend`.
- **Ollama / executor unreachable:** Verify **`OLLAMA_BASE_URL`** and **`OLLAMA_API_KEY`** for Cloud ([keys](https://ollama.com/settings/keys)); for local host Ollama use **`OLLAMA_BASE_URL=http://host.docker.internal:11434`** and align **`OLLAMA_EXECUTOR_MODEL`** with a pulled model tag.  
- **No skills:** `docker compose exec backend php artisan bosskuai:import-knowledge --fresh`  
- **Planner JSON failed:** validate **`OLLAMA_API_KEY`**, **`OLLAMA_BASE_URL`**, and role model envs (`BOSSKU_ORCHESTRATOR_MODEL`, `PLANNER_MODEL`, etc.)

UI smoke tests (Nuxt vs a mock API — no Laravel): from **`web/`** run **`npm run e2e:install`** then **`npm run e2e`**. Details: [`web/e2e/README.md`](web/e2e/README.md).

---

## Install (without Docker)

Follow [`docs/installation.md`](docs/installation.md). Short version:

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /your/project --profile core
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\your\project -Profile core
```

Optional dashboard:

```bash
python3 scripts/dashboard.py
# http://127.0.0.1:8765 — Actions tab → Sync skills
```

---

## Cursor setup

[`integrations/cursor/install.md`](integrations/cursor/install.md) · Rules template [`integrations/cursor/rules.md`](integrations/cursor/rules.md) · upstream rule `.cursor/rules/bosskuai.mdc`.

---

## Claude Code setup

[`integrations/claude-code/install.md`](integrations/claude-code/install.md) · template [`integrations/claude-code/CLAUDE.md`](integrations/claude-code/CLAUDE.md) · root `CLAUDE.md` stays the working copy after install.

---

## Codex setup

[`integrations/codex/install.md`](integrations/codex/install.md) · template [`integrations/codex/AGENTS.md`](integrations/codex/AGENTS.md) · slim Codex SKILL `packages/bossku-ai/skills/bossku-ai/SKILL.md`.

---

## OpenCode setup

[`integrations/opencode/install.md`](integrations/opencode/install.md) · rules template [`integrations/opencode/rules.md`](integrations/opencode/rules.md).

---

## Memory setup

Start with [`memory/memory-policy.md`](memory/memory-policy.md) (what never to store) then [`memory/schema.md`](memory/schema.md).  
Operational commands inside [`AGENTS.md`](AGENTS.md) (`auto_memory.py`, optional `scripts/bosskuai memory …`).

---

## Model routing

Human-readable roles: [`agents/model-router.md`](agents/model-router.md)  
Config truth: [`app/config/bossku_models.php`](app/config/bossku_models.php)  
Workspace YAML hints: [`ai-assistant/config/model-router.yaml`](ai-assistant/config/model-router.yaml)

---

## Example prompts

[`docs/examples.md`](docs/examples.md)

Smoke test inside any connected editor:

```text
bossku what should i build for my SaaS MVP next?
```

---

## Documentation map

[`docs/README.md`](docs/README.md) — installation, quickstart, FAQ, comparison, architecture.

---

## FAQ

[`docs/faq.md`](docs/faq.md)

---

## Roadmap (honest)

- Tighter UI for memory dashboards per [`ui/memory-viewer-spec.md`](ui/memory-viewer-spec.md)  
- Richer routing transparency in-run per [`ui/model-routing-spec.md`](ui/model-routing-spec.md)  
- Continue shrinking duplicate narratives between README and deep docs  

---

## What BosskuAI is NOT

- Not a SaaS platform  
- Not magic autonomy  
- Not guaranteed cheaper responses every time  

---

## Version

**v1.9.5** — see [`CHANGELOG.md`](CHANGELOG.md).
