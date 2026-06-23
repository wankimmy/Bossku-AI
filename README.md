# BosskuAI

**A safety layer for your AI coding assistant. It makes the AI plan before it edits, check its own work after, and remember what it learns.**

You already use an AI tool like Claude Code, Cursor, Codex, or OpenCode. BosskuAI sits on top of it. Instead of the AI taking one shot at your prompt and editing files, every task goes through a small team of agents that plan, build, review, and approve before anything reaches you.

> Free and open source. Runs on your machine. No account, no cloud lock-in. Your code stays on your computer unless you choose a cloud AI provider.

---

## Why use BosskuAI instead of a plain AI assistant?

When you ask an AI to fix a bug or add a feature, you usually get one shot: the AI reads your prompt, edits files, and hands you the result. If it gets something wrong, you find out after the damage is done.

BosskuAI changes that. Every task flows through a pipeline:

1. **Plan first** — one agent figures out what to change, which files, and what could go wrong. You see the plan before any edit happens.
2. **Edit carefully** — another agent makes the actual changes, following the plan.
3. **Review the work** — a second agent checks the diff for bugs, security holes, and missing tests. If it finds problems, it sends the work back.
4. **Approve risky changes** — for anything touching payments, auth, or database migrations, BosskuAI pauses and asks you before continuing.
5. **Remember lessons** — decisions and patterns are saved so the next session does not start from zero.

The result: fewer surprise edits, fewer broken builds, fewer security regressions, and a dashboard that shows you exactly what happened.

---

## How it is different from other AI agent tools

| Feature | BosskuAI | Plain AI (Claude Code, Cursor, etc.) | LangChain / CrewAI | LangGraph | Paperclip |
|---|---|---|---|---|---|
| **Plans before editing** | Yes - always | Sometimes, if you ask | No | No | Yes |
| **Audits its own work** | Yes - second agent reviews the diff | No | No | No | Yes |
| **Approval gates for risky work** | Yes - auth, payments, migrations pause | No | No | No | Yes |
| **Remembers across sessions** | Yes - durable memory with vector search | No (starts fresh each time) | No | Optional | Planned |
| **Runs locally** | Yes - Ollama support, your code never leaves | Partial (depends on provider) | No (needs API keys) | No (Python library) | Yes |
| **Works with your existing AI tool** | Yes - Claude Code, Cursor, Codex, OpenCode | That IS the tool | No (build your own) | No (build your own) | Yes |
| **Dashboard with run history** | Yes - see plans, changes, audits live | No | No | No | Yes |
| **Free and open source** | Yes | Tool itself, but you pay for API | Yes | Yes | Yes |
| **No cloud lock-in** | Yes - bring any provider | No (tied to one vendor) | No (API-dependent) | No (API-dependent) | Yes |
| **Skill system** | 85+ built-in skills, add your own | No | No | No | Yes |
| **Cross-tool consistency** | One contract works across Claude, Cursor, Codex, OpenCode | No (each tool is different) | No | No | Yes |

**In one sentence:** BosskuAI is the only tool that adds planning, auditing, approval gates, and memory on top of the AI coding assistant you already use, without locking you into one vendor or the cloud.

---

## Is this for me?

- You use an AI coding assistant and want it to be **safer and more predictable** - no surprise edits.
- You want a **dashboard** that shows what the AI planned, changed, and checked.
- You care about **privacy** and want the option to run everything locally with Ollama.
- You are okay with a little more structure in exchange for a lot fewer broken builds.

If you just want raw autocomplete with zero guardrails, BosskuAI will feel like more process than you need.

---

## How it works

```
Your prompt
    |
    v
  Router       Reads your prompt, picks the right path,
               decides how much rigor the task needs.
    |
    v
  Planner       Plans the change: which files, what steps,
               what could go wrong, what to test.
    |
    v
  Executor     Makes the actual edits, following the plan.
    |
    v
  Auditor      Reviews the diff for bugs, security, and
               missing tests. Can send work back to fix.
    |
    v
  Final Review  Final gate for risky work: MERGE, REVISE,
               or REJECT before you ever see the result.
    |
    v
  Memory       Saves decisions and lessons for next time.
```

Simple questions take the short path (just answer directly). Risky changes get the full treatment (plan, edit, audit, review, approve).

---

## Two ways to use it

| You want... | Use this |
|---|---|
| The full dashboard with run history, memory, skills, and approval gates | **Path 1 - Docker web app** (below) |
| Just add BosskuAI's rules and skills to an existing project, no dashboard | **Path 2 - Repo toolkit** (see below) |

**New here? Start with Path 1.** The dashboard shows everything as it happens.

---

## Get started in 5 minutes (Path 1 - Docker web app)

### What you need

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- Git
- One AI provider: a local [Ollama](https://ollama.com), an Anthropic API key, or a ChatGPT/Codex connection

### Step 1 - Clone and configure

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env
```

Open `app/.env` and add **one** provider:

```env
# A - Local Ollama (most private, free)
OLLAMA_BASE_URL=http://host.docker.internal:11434

# B - Ollama Cloud
OLLAMA_BASE_URL=https://ollama.com
OLLAMA_API_KEY=your-ollama-cloud-key

# C - Anthropic Claude
ANTHROPIC_API_KEY=your-anthropic-key

# D - Codex / ChatGPT (connect from Settings after launch)
```

### Step 2 - Start it

```bash
docker compose up -d --build
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

First run takes 2-4 minutes while Docker pulls images and installs dependencies.

### Step 3 - Open it

| Service | URL |
|---|---|
| Web dashboard | http://localhost:28470 |
| API (direct) | http://localhost:28480 |

### Step 4 - Run your first task

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

## Path 2 - Add to an existing repo

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

- `AGENTS.md` - the shared contract for Claude Code, Cursor, and Codex
- `skill-index.json` - available task skills
- `ai-assistant/` - memory, orchestrator, and model-router scripts
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

Local development has **no authentication** by default - fine for a single machine.

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

See [`docs/production-deploy.md`](docs/production-deploy.md) for TLS, CORS, and hardening.

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

---

## Documentation

| Doc | Covers |
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

## Contributing

BosskuAI is open source and contributions are welcome - whether it is a bug report, a docs fix, or a new skill.

- Found a bug or have an idea? Open an issue on GitHub.
- Want to contribute code? Read [`CONTRIBUTING.md`](CONTRIBUTING.md) first - it covers setup, conventions, and how to run the tests.
- Found a security issue? Please follow [`SECURITY.md`](SECURITY.md) instead of opening a public issue.
- Working with an AI assistant in this repo? [`AGENTS.md`](AGENTS.md) is the shared contract that keeps Claude Code, Cursor, and Codex aligned.

If BosskuAI is useful to you, a star on GitHub helps other people find it.

---

## License

Released under the license in [`LICENSE`](LICENSE).