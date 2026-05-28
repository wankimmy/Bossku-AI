# Model Routing

> **Primary path today:** per-role models in **Settings → Models** (`RuntimeSettings` + `ModelFallbackService`). The **DB model routes** UI at `/settings/model-routing` is **experimental**: it applies when an active route exists for the agent role and the provider slug is registered at runtime (`ollama`, `anthropic`, `codex`). Slugs like `ollama-cloud` are normalized to `ollama`.

BosskuAI supports multiple LLM providers and can route different agent roles to different models. Model routing lets you use a powerful (expensive) model for planning and a faster (cheaper) model for auditing, or route everything through a local Ollama instance for cost control.

## Role-to-Provider Mapping

Every agent role in the pipeline has its own model assignment:

| Role | Default model | Purpose |
|---|---|---|
| `orchestrator` | configured or Ollama default | Intent parsing, skill selection, context assembly |
| `planner` | configured or Ollama default | Step-by-step execution plan generation |
| `executor` | configured or Ollama default | Code generation, command composition |
| `auditor` | configured or Ollama default | Quality, security, performance review |
| `final_reviewer` | configured or Ollama default | Completion assessment, risk summary |
| `embedder` | Ollama embedding model | Memory and skill embedding |
| `skill_generator` | configured or Ollama default | Auto skill candidate generation |

Roles are configured in the model routes table, accessible at `/settings/model-routing`.

## ModelRouter Resolve Order

`ModelRouter::resolve($role)` follows this precedence:

### 1. forceProvider

If the run was submitted with an explicit `provider` override in the request payload:

```json
{ "prompt": "...", "provider": "anthropic" }
```

That provider is used for all roles in this run, bypassing all configured routes. This is useful for testing a specific provider on a single run without changing global configuration.

### 2. DB Route

The `model_routes` table is checked for a route matching the current role. Each route specifies:

- `role` — the agent role
- `provider_id` — foreign key to the `llm_providers` table
- `model_name` — the specific model to use (e.g. `claude-sonnet-4-6`, `gpt-4o`, `llama3.2`)
- `budget_limit_usd` — optional per-role cost cap; if a run would exceed this, the route is skipped and fallback applies
- `active` — bool; inactive routes are skipped

The first active route matching the role is used.

### 3. Ollama Fallback

If no DB route matches (or the matched route's provider is unhealthy), `ModelRouter` falls back to Ollama using the model configured in `OLLAMA_DEFAULT_MODEL` (default: `llama3.2`). The Ollama base URL is read from `OLLAMA_BASE_URL`.

If Ollama is also unavailable, the run fails immediately with a `no_provider_available` error rather than silently returning a degraded response.

## Configuring Model Routes via /settings/model-routing

The model routing UI at `/settings/model-routing` shows:

- The current route for each role
- Provider health indicators (green/yellow/red)
- Budget remaining for budget-capped routes
- A form to add, edit, or deactivate routes

To add a new route:

1. Select the role
2. Select a configured provider (see [`providers.md`](providers.md))
3. Enter the model name (or pick from the provider's synced model list)
4. Optionally set a budget limit in USD
5. Save — the route is active immediately for new runs

Routes are evaluated in the order they appear in the table. Drag to reorder if you have multiple routes for the same role.

## Fallback Rules

BosskuAI applies fallback in this order when a route fails:

1. **Provider error (5xx, timeout)**: retry once after 2 seconds, then try the next active route for the same role
2. **Budget exceeded**: skip to next active route (no retry)
3. **Model not found**: skip to next active route immediately
4. **All routes exhausted**: fall back to Ollama if available, else fail the run

Fallback attempts are recorded in `UsageEvent` metadata when a call succeeds after model-level retries. A dedicated `model_routing_events` table is not implemented yet.

## Budget Configuration

Budget limits are set per route in USD. They apply to the cumulative cost of tokens processed through that route in the current billing period (configurable, default: calendar month).

Set a budget limit to prevent runaway costs when using paid providers. When the limit is reached:
- The route is automatically deactivated for the rest of the billing period
- A notification is sent to configured admin users
- Routing falls back to the next available route (which may be Ollama at zero cost)

Budget tracking requires accurate pricing data in `ModelRegistry`. See [`usage-and-cost.md`](usage-and-cost.md) for how pricing is maintained.
