# Usage and Cost Tracking

BosskuAI tracks every LLM call's token usage and estimates cost in real time. This gives you visibility into where your AI spend is going, which agents and skills are most expensive, and when you are approaching configured budget limits.

## UsageEvent Ledger

Every model call produces a `UsageEvent` record persisted to the `usage_events` table. Each event captures:

| Field | Description |
|---|---|
| `run_id` | The run this call belongs to |
| `agent_role` | Which agent made the call (orchestrator, planner, executor, etc.) |
| `provider_id` | Which LLM provider handled the call |
| `model_name` | The specific model (e.g. `claude-sonnet-4-6`) |
| `prompt_tokens` | Tokens in the input/prompt |
| `completion_tokens` | Tokens in the output/completion |
| `total_tokens` | Sum of prompt + completion |
| `estimated_cost_usd` | Calculated from `ModelRegistry` pricing at call time |
| `latency_ms` | Time from request send to response complete |
| `called_at` | Timestamp |

The ledger is append-only — usage events are never updated or deleted. This makes the total cost of any run always auditable by summing its usage events.

## Per-Call Token Tracking

Token counts are collected from the provider's API response. All supported providers return token counts in their response metadata:

- **Anthropic**: `usage.input_tokens`, `usage.output_tokens`
- **OpenAI**: `usage.prompt_tokens`, `usage.completion_tokens`
- **Ollama**: `prompt_eval_count`, `eval_count`
- **openai_compatible**: same as OpenAI format

For streaming responses, the final chunk contains the usage summary. `UsageEventService` reads this from the stream's last event and persists the `UsageEvent` after the stream closes.

## ModelRegistry Pricing Table

`ModelRegistry` maintains a pricing table mapping `(provider, model_name)` to `(input_price_per_1k_tokens, output_price_per_1k_tokens)` in USD.

```
ModelRegistry::getPrice('anthropic', 'claude-sonnet-4-6')
// => { input: 0.003, output: 0.015 }
```

The registry is seeded with known prices at deployment time and can be updated via `/settings/model-routing` → **Update Pricing**. This fetches current pricing from each provider's pricing API where available, or allows manual entry.

When a model is not found in the registry (e.g. a newly added Ollama model), cost is recorded as `$0.00` with a `pricing_unknown` flag. These events are highlighted in the `/usage` page so you can add pricing data.

## Cost Estimation Before Execution

`PlannerService` estimates the cost of the full execution plan before any executor steps run. It counts approximate tokens for each planned step (based on step description length and historical averages for that step type) and surfaces the estimate in the run plan view.

This estimate is shown to the user as: "Estimated cost: $0.042 — $0.089". Runs with estimated cost above the `high_cost_threshold_usd` config value are flagged and require approval gate confirmation.

## The /usage Page

The `/usage` page at `http://localhost:3000/usage` provides:

- **Period selector** — view usage for today, this week, this month, or a custom date range
- **Total cost** — sum of all `estimated_cost_usd` for the period
- **Cost by provider** — breakdown across Anthropic, OpenAI, Ollama, etc.
- **Cost by agent role** — which pipeline stage costs the most (executors tend to be the most expensive)
- **Cost by skill** — which skills generate the highest token usage (useful for identifying skills with bloated prompts)
- **Top runs by cost** — the most expensive individual runs in the period
- **Token volume chart** — daily prompt and completion token volumes over time

All charts are exportable as CSV.

## Budget Configuration in Model Routes

Each model route can have a `budget_limit_usd` that caps total spend through that route in the current billing period. Configure this on the `/settings/model-routing` page.

When cumulative spend on a route reaches the limit:
- The route is automatically deactivated
- Routing falls back to the next available route (typically Ollama at zero cost)
- Admin users receive a notification: "Route [name] has reached its budget limit of $X.XX"
- The route reactivates at the start of the next billing period (or manually on the settings page)

This is the primary cost control mechanism. For tighter control, combine per-route budgets with the `high_cost_threshold_usd` approval gate — expensive runs are flagged before they run, and routes have hard monthly caps.

## Zero-Cost Ollama Runs

Runs executed entirely through Ollama have `estimated_cost_usd = 0.00` by default (since you are running inference locally). If you are using Ollama Cloud (a paid hosted inference service), add its pricing to the `ModelRegistry` so costs are tracked accurately.
