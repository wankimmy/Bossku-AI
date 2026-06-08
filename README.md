# BosskuAI 3.0

**A safety layer that makes your AI coding assistant plan before it edits, check its own work after, and remember what it learns.**

BosskuAI runs locally and works with the AI tool you already use — Claude Code, Cursor, or Codex. Instead of one AI taking a single shot at your prompt, every task flows through a small team of specialized agents.

> 🟢 **Free and open source.** Self-host it on your own machine in a few minutes — no account, no cloud lock-in. Your code never leaves your computer unless you choose a cloud AI provider.

**New here?** Read [How it works](#how-it-works) for the 30-second picture, then jump to [Get started in 5 minutes](#get-started-in-5-minutes).

### Is this for me?

- ✅ You use an AI coding assistant and want it to be **safer and more predictable** — no surprise edits.
- ✅ You want a **dashboard** that shows what the AI planned, changed, and checked.
- ✅ You care about **privacy** and want the option to run everything locally with Ollama.
- ❓ You just want raw autocomplete with zero structure — BosskuAI adds guardrails, so it may feel like more process than you need.

<img width="1895" height="921" alt="image" src="https://github.com/user-attachments/assets/10eb8908-f05e-426a-af68-e83162703f4a" />

<img width="1878" height="911" alt="image" src="https://github.com/user-attachments/assets/f5176bb3-a611-4a51-a7e7-560e65b995f6" />

<img width="1906" height="919" alt="image" src="https://github.com/user-attachments/assets/da80b202-7ab5-4ce5-a504-89cb6bce146c" />

<img width="1905" height="916" alt="image" src="https://github.com/user-attachments/assets/fb1a22c8-2d4c-41d3-829c-e43aa6b2b41f" />

<img width="1898" height="914" alt="image" src="https://github.com/user-attachments/assets/6b09ffcc-d3b1-47a5-b793-38b79e476a45" />

<img width="1883" height="917" alt="image" src="https://github.com/user-attachments/assets/ecd3433a-bb86-4e2a-b87c-9ea69a57007b" />

<img width="1892" height="911" alt="image" src="https://github.com/user-attachments/assets/ee209dc8-11ae-4f71-bb7b-f18303a64ff8" />

<img width="1885" height="894" alt="image" src="https://github.com/user-attachments/assets/38f1d9c6-9a6d-4432-af85-4edd14e48701" />

---

## How it works

Every task is routed through a pipeline instead of a single prompt. Simple questions take the short path; risky changes get the full treatment.

```
Your prompt
    │
    ▼
┌─────────────┐   Reads your prompt, picks the right path,
│   Router    │   and decides how much rigor the task needs.
└─────────────┘
    │
    ▼
┌─────────────┐   Plans the change: which files, what steps,
│  Planner    │   what could go wrong, what to test.
└─────────────┘
    │
    ▼
┌─────────────┐   Makes the actual edits, following the plan.
│  Executor   │
└─────────────┘
    │
    ▼
┌─────────────┐   Reviews the diff for bugs, security, and
│  Auditor    │   missing tests. Can send work back to fix.
└─────────────┘
    │
    ▼
┌─────────────┐   Final gate for risky work: MERGE, REVISE,
│ Final Review│   or REJECT before you ever see the result.
└─────────────┘
    │
    ▼
   Memory       Saves decisions and lessons for next time.
```

- **Plans before editing** — no blind edits; you see the plan first
- **Audits after editing** — a second agent checks the work before it reaches you
- **Approval gates** — payments, auth, and migrations pause and ask you first
- **Remembers** — project decisions and lessons survive across sessions
- **Local-first** — your code stays on your machine; you pick the AI provider

---

## Two ways to use it

| You want to… | Use |
|---|---|
| The full dashboard — run history, memory, skills, approval gates | **Path 1 — Docker web app** (below) |
| Just add BosskuAI's rules and skills to an existing project | **[Path 2 — Repo toolkit](#path-2--add-to-an-existing-repo)** |

**New here? Start with Path 1.** The dashboard shows everything as it happens.

---

## Get started in 5 minutes

> This is **Path 1 — the Docker web app**, the recommended way to try BosskuAI. Prefer to skip the dashboard? See [Path 2 — Repo toolkit](#path-2--add-to-an-existing-repo).

### What you need

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running
- Git
- One AI provider: a local [Ollama](https://ollama.com), an Anthropic API key, or a ChatGPT/Codex connection

### Step 1 — Clone and configure

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env
```

> On Windows PowerShell, use `Copy-Item app\.env.example app\.env`.

Open `app/.env` and add **one** provider:

```env
# A — Local Ollama (most private, free)
OLLAMA_BASE_URL=http://host.docker.internal:11434

# B — Ollama Cloud
OLLAMA_BASE_URL=https://ollama.com
OLLAMA_API_KEY=your-ollama-cloud-key

# C — Anthropic Claude
ANTHROPIC_API_KEY=your-anthropic-key

# D — Codex / ChatGPT — connect from Settings after launch
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
2. Type a task — e.g. `bossku, understand this repo and summarize the architecture`
3. Press **Run task** and watch the Planner → Executor → Auditor steps appear live
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

Drop BosskuAI's rules, skills, and memory into any project — no dashboard required.

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

---

## Common commands

**Backend (via Docker):**
```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
docker compose exec backend php artisan route:list
docker compose exec backend php artisan test
```

> `import-knowledge --fresh` refreshes only knowledge (skills, rules, playbooks, checklists). Run history and memory are preserved.

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
| **Planner JSON error** | Check the model, key, and base URL in Settings → Model Routing |
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

## Contributing & community

BosskuAI is open source and contributions are welcome — whether it's a bug report, a docs fix, or a new skill.

- 🐛 **Found a bug or have an idea?** Open an issue on GitHub.
- 🔧 **Want to contribute code?** Read [`CONTRIBUTING.md`](CONTRIBUTING.md) first — it covers setup, conventions, and how to run the tests.
- 🔒 **Found a security issue?** Please follow [`SECURITY.md`](SECURITY.md) instead of opening a public issue.
- 📐 **Working with an AI assistant in this repo?** [`AGENTS.md`](AGENTS.md) is the shared contract that keeps Claude Code, Cursor, and Codex aligned.

If BosskuAI is useful to you, a ⭐ on GitHub helps other people find it.

---

## License

Released under the license in [`LICENSE`](LICENSE).
