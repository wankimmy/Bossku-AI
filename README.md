# BosskuAI — Self-Learning Developer AI Orchestrator

![PHP 8.2](https://img.shields.io/badge/PHP-8.2-blue?logo=php)
![Laravel 11](https://img.shields.io/badge/Laravel-11-red?logo=laravel)
![Nuxt 3](https://img.shields.io/badge/Nuxt-3-green?logo=nuxt.js)
![pgvector](https://img.shields.io/badge/pgvector-enabled-blueviolet?logo=postgresql)
![Docker](https://img.shields.io/badge/Docker-compose-2496ED?logo=docker)

BosskuAI is a self-learning developer AI orchestrator that combines a multi-provider LLM abstraction layer, a skill-based execution engine, a self-improving brain, and interactive knowledge/skills graphs into a single IDE-feel developer tool.

---

## Features

All 24 spec §16 capabilities are implemented:

| # | Feature |
|---|---|
| 1 | **Multi-provider LLM abstraction** — Anthropic, OpenAI, Ollama, openai_compatible, custom adapters |
| 2 | **Model routing** — per-role provider/model assignment with forceProvider → DB route → Ollama fallback |
| 3 | **Governance & risk classification** — RiskClassifier with low/medium/high/critical levels, RiskRuleEngine |
| 4 | **Approval gates** — hard stops for terminal commands, external HTTP, env_mod, deployment, secret rotation, high-cost steps |
| 5 | **Skill candidates** — auto-generated SKILL.md drafts from patterns, approval workflow, risky category gating |
| 6 | **Learning engine** — LearningEngine extracts patterns post-run, ≥3 occurrence threshold triggers candidate generation |
| 7 | **Soul system** — soul.md guides AI behavior on every run, versioned, suggestions never auto-applied |
| 8 | **Knowledge graph** — Cytoscape.js graph of skills, runs, memories, agents, files with typed edges |
| 9 | **Skills graph** — focused skill relationship view with co-occurrence edges and SkillQualityBadge overlays |
| 10 | **Brain page** — 7-tab observability dashboard (Overview, Memory Streams, Learning Inbox, Skill Candidates, Feedback Learnings, Brain Health, Conflicts) |
| 11 | **Feedback system** — FeedbackLearningService converts thumbs/text feedback into learning events |
| 12 | **Memory confidence scoring** — staleness decay, conflict detection, confirmation boosting |
| 13 | **Cytoscape graphs** — interactive force-directed graphs with filters, export, and dark IDE styling |
| 14 | **Dark IDE UI** — Nuxt 3 frontend with dark theme, live SSE streaming run timeline |
| 15 | **`[BOSSKUAI]` marker** — mandatory response header identifying skill, agent, model role, memory usage |
| 16 | **pgvector semantic memory** — PostgreSQL pgvector for cosine similarity memory retrieval |
| 17 | **OrchestratorService pipeline** — Orchestrator → Planner → Executor → Auditor → FinalReviewer with SSE streaming |
| 18 | **Step persistence** — all steps written before execution, resumable, queryable before a run starts |
| 19 | **Usage & cost tracking** — UsageEvent ledger, per-call token accounting, ModelRegistry pricing, /usage page |
| 20 | **Budget controls** — per-route budget limits with auto-deactivation and fallback |
| 21 | **Skill quality scoring** — automated quality score updates after each run, weak skill detection |
| 22 | **Conflict detection** — MemoryConflictDetector with conflict edges in graph and resolution workflow |
| 23 | **Multi-agent deep-mode** — /audit (parallel fan-out), /decide (propose-critique), /implement (write-review) on Claude Code |
| 24 | **Repo-local portability** — skills, soul, agents, and rules travel with your repo; Docker stack is optional |

---

## Quick Start

### Docker Compose (recommended)

```bash
# 1. Clone and copy env
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
cd bosskuAI
cp app/.env.example app/.env

# 2. Set your LLM provider credentials in app/.env
#    OLLAMA_BASE_URL=https://ollama.com        # or http://host.docker.internal:11434 for local
#    OLLAMA_API_KEY=<your-ollama-cloud-key>    # from ollama.com/settings/keys

# 3. Optional: sibling repos under Project → Paths (required for repo audits)
#    docker-compose mounts ../:/workspace — set BOSSKU_WORKSPACE_HOST_PREFIX in app/.env
#    to that host folder (quoted if the path has spaces). Activate the project in the UI before auditing.
#    Ollama role models are configured under Settings → Ollama & Models (not .env).

# 4. Start the stack
docker compose up -d --build

# 5. Bootstrap
docker compose exec backend composer install --no-interaction
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan bosskuai:import-knowledge --fresh
```

- **UI**: http://localhost:3000
- **API**: http://localhost:8000

#### Frontend (production build only)

Docker serves a **production Nuxt build** (`npm run build` + Nitro server), not `nuxt dev`. This avoids slow Vite/HMR on Windows bind mounts.

- **First start** (or no `.output/` yet): the frontend container runs `npm run build` once — may take a few minutes.
- **After you change UI code**:

```bash
docker compose exec frontend npm run build
docker compose restart frontend
```

Or build on the host, then restart:

```bash
cd web && npm install && npm run build
docker compose restart frontend
```

Local hot-reload dev (optional, outside Docker): `cd web && npm run dev`

### Without Docker

```bash
./scripts/install.sh /your/project --profile core
```

Windows:

```powershell
.\scripts\install.ps1 C:\your\project -Profile core
```

See [`docs/installation.md`](docs/installation.md) for full setup including pgvector, Redis queue, and Nuxt.

---

## Architecture

BosskuAI follows a five-stage pipeline for every run:

```
User Prompt
  └─► OrchestratorService  (intent, skill match, memory query, model routing)
        └─► PlannerService  (execution plan with risk levels, step persistence)
              └─► ExecutorService  (step-by-step execution, approval gates, SSE streaming)
                    └─► AuditorService  (quality, security, performance, maintainability)
                          └─► FinalReviewerService  (completion check, risk summary, next step)
```

After each run, the self-learning loop runs:

```
Run completes
  └─► LearningEngine  (pattern extraction)
        └─► FeedbackLearningService  (user feedback → learning events)
              └─► SkillCandidateGenerator  (draft SKILL.md when pattern >= 3 occurrences)
                    └─► Human approval on /brain
                          └─► SkillIndexService  (activates skill for future runs)
```

See [`docs/architecture.md`](docs/architecture.md) for the full architecture reference.

---

## Documentation

### Core Concepts
- [`docs/what-is-bossku-ai.md`](docs/what-is-bossku-ai.md) — What BosskuAI is and is not
- [`docs/quickstart.md`](docs/quickstart.md) — Get running in 5 minutes
- [`docs/installation.md`](docs/installation.md) — Full installation guide
- [`docs/architecture.md`](docs/architecture.md) — System architecture overview

### Pipeline & Execution
- [`docs/orchestration.md`](docs/orchestration.md) — OrchestratorService → FinalReviewerService pipeline
- [`docs/skills.md`](docs/skills.md) — Skill system: SKILL.md format, matching, quality scoring
- [`docs/approval-gates.md`](docs/approval-gates.md) — Which operations require human approval and how
- [`docs/governance.md`](docs/governance.md) — RiskClassifier, risk levels, governance rule editor

### Self-Learning Brain
- [`docs/self-learning.md`](docs/self-learning.md) — LearningEngine, feedback loop, pattern detection
- [`docs/auto-skill-generation.md`](docs/auto-skill-generation.md) — Auto skill candidate pipeline
- [`docs/brain.md`](docs/brain.md) — The /brain page: 7 tabs explained
- [`docs/soul.md`](docs/soul.md) — Soul system: soul.md, versioning, suggestions

### Memory & Knowledge
- [`docs/memory.md`](docs/memory.md) — pgvector memory, confidence scoring, conflict detection
- [`docs/knowledge-graph.md`](docs/knowledge-graph.md) — Knowledge graph: nodes, edges, Cytoscape view
- [`docs/skills-graph.md`](docs/skills-graph.md) — Skills graph: quality overlays, co-occurrence edges

### Providers & Cost
- [`docs/providers.md`](docs/providers.md) — Supported LLM providers, API key storage, health checks
- [`docs/model-routing.md`](docs/model-routing.md) — ModelRouter resolve order, route configuration
- [`docs/usage-and-cost.md`](docs/usage-and-cost.md) — UsageEvent ledger, cost tracking, /usage page

### Reference
- [`docs/multi-agent-architecture.md`](docs/multi-agent-architecture.md) — /audit, /decide, /implement deep-mode flows
- [`docs/examples.md`](docs/examples.md) — Example prompts and expected outputs
- [`docs/faq.md`](docs/faq.md) — Frequently asked questions
- [`docs/comparison.md`](docs/comparison.md) — BosskuAI vs LangChain, CrewAI, and others
- [`docs/benchmarks.md`](docs/benchmarks.md) — Performance and quality benchmarks
- [`docs/adversarial-routing.md`](docs/adversarial-routing.md) — Adversarial routing stress tests

---

## Mandatory Response Indicator

Every BosskuAI-compliant response starts with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

This header is how you know the skill system, model routing, and memory layer are all active on a response.

---

## Troubleshooting

- **Bootstrap 500**: rebuild backend — `docker compose build backend && docker compose up -d backend`
- **Stream run shows no events**: check `docker compose logs -f backend` for permissions issues; validate with `curl -i http://localhost:8000/api/runs`
- **No skills loaded**: `docker compose exec backend php artisan bosskuai:import-knowledge --fresh`
- **Ollama unreachable**: verify `OLLAMA_BASE_URL` and `OLLAMA_API_KEY` in `app/.env`
- **Planner JSON failed**: validate `OLLAMA_API_KEY`, `OLLAMA_BASE_URL`, and role model env vars

---

BosskuAI is a production-ready, self-learning developer AI orchestrator with a full multi-provider LLM abstraction, observable step-by-step execution, and a live knowledge graph — designed to get better with every run.
