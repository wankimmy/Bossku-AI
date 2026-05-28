# BosskuAI Multi-LLM Agent Architecture Audit

Date: 2026-05-28

Scope: static + evidence audit. No live model calls, no credential checks, and no runtime code changes were made.

## Executive Verdict

Verdict: **hybrid conditional multi-agent**.

BosskuAI should keep the planner -> executor -> auditor -> security/final-reviewer pipeline for high-risk changes, explicit audits, and tasks where author bias or security review matters. It should not present "assign multiple LLMs to every agent" as automatically better than a single LLM yet.

The current runtime has a useful role-based multi-model design, but it is not a complete, cost-controlled, provider-agnostic multi-LLM architecture. Most role calls use Settings-based model IDs and model aliasing through `LlmGateway`, not the DB `model_routes` provider-routing system. The DB route UI/schema exists, but fallback providers, budgets, health-aware routing, provider sync, and per-route observability are only partially implemented or documented ahead of the code.

Practical conclusion:

- **Good today:** conditional multi-stage workflow, per-role model IDs, model fallback lists, independent audit/final-review prompts, run-step telemetry, memory retrieval/writeback, and a benchmark scaffold.
- **Risk today:** cost accounting is incomplete for the default direct Ollama path, DB route roles are not consistently passed into the gateway, DB provider routes can point at providers that are never registered in runtime, and several docs claim production-grade budget/health/model-sync controls that do not exist.
- **Not proven today:** there is no recorded single-LLM vs multi-agent benchmark result proving quality improvement per dollar or per minute.

## Status By Category

### Implemented and Working in Runtime

- Workflow gating exists. `WorkflowRouteHelper` maps workflow strings to actual agents and skips auditor/security/final stages unless both route flags and workflow allow them (`app/app/Services/BosskuAi/WorkflowRouteHelper.php:28`, `app/app/Services/BosskuAi/WorkflowRouteHelper.php:61`).
- The router/planner/executor/auditor/security/final/writer/direct-answer services each pull role-specific model settings through `ModelRoutingConfig` and `RuntimeSettings` (`app/app/Services/BosskuAi/ModelRoutingConfig.php:14`, `app/app/Services/BosskuAi/RuntimeSettings.php:82`, `app/app/Services/BosskuAi/RuntimeSettings.php:87`, `app/app/Services/BosskuAi/RuntimeSettings.php:103`).
- Each main agent service builds an ordered list of primary + fallback models and calls `ModelFallbackService` (`app/app/Services/Orchestrator/PlannerService.php:40`, `app/app/Services/Orchestrator/ExecutorService.php:44`, `app/app/Services/Orchestrator/AuditorService.php:43`, `app/app/Services/Orchestrator/SecurityAuditorService.php:57`, `app/app/Services/Orchestrator/FinalReviewerService.php:38`).
- `ModelFallbackService` retries the selected model and moves to fallback models on errors or invalid JSON (`app/app/Services/BosskuAi/ModelFallbackService.php:37`).
- `LlmGateway` can infer Anthropic or Codex from raw Claude/GPT model IDs, and can resolve logical aliases to Ollama Cloud model tags (`app/app/Services/BosskuAi/LlmGateway.php:34`, `app/app/Services/BosskuAi/LlmGateway.php:80`, `app/app/Services/BosskuAi/LlmGateway.php:125`).
- Run telemetry records workflow, models resolved, agent handoffs, latency estimates, token estimates, and structured artifacts in `run_steps` and SSE metadata (`app/app/Services/Orchestrator/OrchestratorService.php:119`, `app/app/Services/Orchestrator/OrchestratorService.php:134`, `app/app/Services/Orchestrator/OrchestratorService.php:611`, `app/app/Services/Orchestrator/OrchestratorService.php:841`).
- Memory retrieval and storage are real. Memory uses Ollama embeddings when enabled, falls back to text search, and can humanize memory summaries through `LlmGateway` (`app/app/Services/BosskuAi/MemoryService.php:41`, `app/app/Services/BosskuAi/MemoryService.php:79`, `app/app/Services/BosskuAi/MemoryService.php:140`).
- The evaluator exists, but it is deterministic scoring rather than an LLM agent (`app/app/Services/Orchestrator/PostMemoryEvaluationService.php:14`).

### Partially Implemented But Unreliable

- `ModelRouter` exists and tracks usage, but it is only used when the gateway delegates to it. The default direct Ollama path in `LlmGateway` bypasses `UsageTracker` (`app/app/Services/BosskuAi/LlmGateway.php:37`, `app/app/Services/BosskuAi/LlmGateway.php:54`, `app/app/Services/Llm/ModelRouter.php:71`, `app/app/Services/Llm/UsageTracker.php:15`).
- `ModelFallbackService` calls `LlmGateway::chat()` without passing the actual agent role, run ID, or run step ID, so DB route lookup usually sees the default role `coder` instead of `orchestrator`, `executor`, `auditor`, etc. (`app/app/Services/BosskuAi/ModelFallbackService.php:41`, `app/app/Services/BosskuAi/LlmGateway.php:24`, `app/app/Services/BosskuAi/LlmGateway.php:30`).
- `model_routes` has fallback provider/model and monthly budget columns, and the UI can save them, but runtime `ModelRouter::resolve()` only reads the primary provider/model and ignores fallback provider, fallback model, monthly budget, provider health, and route ordering (`app/database/migrations/2026_05_21_100008_create_bossku_ai_model_routes_table.php:16`, `web/pages/settings/model-routing.vue:98`, `app/app/Services/Llm/ModelRouter.php:43`).
- DB provider routing depends on provider slug matching a provider registered in `AppServiceProvider`. Runtime registers `ollama`, `anthropic`, and `codex`; UI/DB providers can use arbitrary slugs such as `ollama-local` or `ollama-cloud`, which will not resolve unless code also registers that exact slug (`app/app/Providers/AppServiceProvider.php:50`, `app/database/seeders/BosskuAiSpecSeeder.php:37`, `app/app/Services/Llm/ModelRouter.php:51`).
- Provider health has a command, but it resolves bindings named `llm.provider.{slug}` and no such provider bindings were found in the app provider search. That makes the health command likely mark DB providers down or fail outside narrow custom wiring (`app/app/Console/Commands/ProviderHealthCheckCommand.php:25`, `app/app/Providers/AppServiceProvider.php:50`).
- `ProviderController::testConnection()` returns static status, and `syncModels()` returns `synced: 0`; the provider UI exposes buttons for these actions, but they do not perform real sync/health behavior (`app/app/Http/Controllers/Api/ProviderController.php:82`, `app/app/Http/Controllers/Api/ProviderController.php:93`, `web/pages/settings/providers.vue:47`).
- Run steps often log provider as `ollama` even after a provider-routed call could have used Anthropic or Codex, because `_provider_used` is not carried back from `ModelFallbackService` into the orchestrator logs (`app/app/Services/Orchestrator/OrchestratorService.php:611`, `app/app/Services/Orchestrator/OrchestratorService.php:841`, `app/app/Services/Orchestrator/OrchestratorService.php:1130`).

### Documented But Not Implemented

- `docs/model-routing.md` says budget limits skip routes and fallback applies, but the code only stores `monthly_budget_usd`; no budget check exists in `ModelRouter` (`docs/model-routing.md:42`, `app/app/Services/Llm/ModelRouter.php:43`).
- `docs/model-routing.md` says fallback events are logged to `model_routing_events`, but no table/model/code reference exists for that event store (`docs/model-routing.md:81`).
- `docs/providers.md` says provider health runs every 5 minutes, model router skips down providers, health logs are stored, and model syncing populates provider models. The current code has a manual command and placeholder controller behavior, with no found `provider_health_logs` or `provider_models` table (`docs/providers.md:69`, `docs/providers.md:77`, `docs/providers.md:81`).
- `docs/usage-and-cost.md` says every model call creates a `UsageEvent` and planner estimates cost before execution. Usage tracking currently happens only through `ModelRouter::complete()`, while default direct Ollama calls return token counts without creating `UsageEvent` rows (`docs/usage-and-cost.md:7`, `docs/usage-and-cost.md:48`, `app/app/Services/BosskuAi/LlmGateway.php:54`).
- `docs/providers.md` describes a custom provider interface that does not match the actual `LlmProviderInterface` method names and DTO signatures (`docs/providers.md:106`, `app/app/Services/Llm/Contracts/LlmProviderInterface.php:10`).

### Not Proven Without Benchmark Data

- The repo has benchmark scaffolding for quality and token-budget comparisons, but no audit evidence found here that multi-agent currently beats single-LLM on the same tasks (`scripts/eval_llm_quality.py:1`, `scripts/eval_token_budget.py:1`, `evals/llm-quality-cases.json:1`, `evals/token-budget-cases.json:1`).
- `docs/multi-agent-architecture.md` is directionally honest: it says default single-call is usually cheaper/faster and deep mode is justified only for audit, critique, or write-then-review workflows (`docs/multi-agent-architecture.md:5`, `docs/multi-agent-architecture.md:118`, `docs/multi-agent-architecture.md:182`).
- The current web runtime has multi-stage orchestration, but the evidence does not prove it improves final quality enough to justify extra calls on routine tasks.

## Findings Ordered By Severity

### Critical: DB Model Routes Do Not Actually Control Agent Roles Reliably

Impact: A user can configure per-agent routes in `/settings/model-routing`, but runtime calls mostly use Settings role models and direct model ID/provider inference. Because `ModelFallbackService` does not pass the role to `LlmGateway`, route lookup defaults to `coder`, not the true stage role. Also, DB providers only work if their slug matches a provider registered in code.

Evidence:

- `ModelFallbackService` calls `gateway->chat($model, $messages, $temperature, $maxTokensAnthropic)` without role/run arguments (`app/app/Services/BosskuAi/ModelFallbackService.php:41`).
- `LlmGateway::chat()` defaults `$role = 'coder'` (`app/app/Services/BosskuAi/LlmGateway.php:30`).
- `ModelRouter` looks up routes by `$request->role` (`app/app/Services/Llm/ModelRouter.php:43`).
- `AppServiceProvider` registers only provider slugs `ollama`, `anthropic`, and `codex` (`app/app/Providers/AppServiceProvider.php:50`).

Recommendation:

- Treat Settings -> Models as the real supported model routing path for now.
- Either remove/de-emphasize DB model routes until complete, or wire stage roles/run IDs/provider slug registration through every agent call.

### Critical: Cost Tracking Is Incomplete For The Default Runtime Path

Impact: BosskuAI cannot currently answer "is multi-LLM cost-efficient?" with runtime data. Most Ollama/Ollama Cloud role calls can bypass `UsageTracker`, so `/usage` can undercount calls and costs. This is especially risky if Ollama Cloud is paid but treated as zero-cost.

Evidence:

- Direct Ollama call path returns tokens but does not call `UsageTracker` (`app/app/Services/BosskuAi/LlmGateway.php:54`).
- `UsageTracker` is only called by `ModelRouter::complete()` (`app/app/Services/Llm/ModelRouter.php:71`, `app/app/Services/Llm/UsageTracker.php:15`).
- `ModelRegistry` only has pricing for older Claude/GPT families and returns zero for unknown models, including current Ollama Cloud aliases (`app/app/Services/Llm/ModelRegistry.php:5`).
- Docs claim every model call produces a `UsageEvent` (`docs/usage-and-cost.md:7`).

Recommendation:

- Track a `UsageEvent` for every `LlmGateway::chat()` call, including direct Ollama/Ollama Cloud.
- Persist logical model, resolved model, provider, stage role, run ID, run step ID, fallback reason, and pricing-known flag.
- Add configurable pricing for the allowed Ollama Cloud models.

### High: Fallback/Budget/Health Controls Are UI/Schema-Visible But Runtime-Ignored

Impact: The product looks more production-ready than it is. Users can configure fallback provider/model and monthly budget, but runtime does not enforce them.

Evidence:

- Schema stores fallback/budget fields (`app/database/migrations/2026_05_21_100008_create_bossku_ai_model_routes_table.php:16`).
- UI saves fallback provider/model (`web/pages/settings/model-routing.vue:98`).
- `ModelRouter::resolve()` returns only the first active primary route and does not inspect fallback, monthly budget, or health status (`app/app/Services/Llm/ModelRouter.php:43`).
- Docs advertise budget skip/fallback and fallback events (`docs/model-routing.md:42`, `docs/model-routing.md:81`).

Recommendation:

- Implement a real route candidate list: primary route, route fallback, configured role fallback models, then default Ollama fallback.
- Add budget check before provider call and record skip reason.
- Hide or label UI fields as "not enforced yet" until implemented.

### High: Provider Health And Model Sync Are Mostly Placeholder

Impact: Scalable multi-provider operation needs current availability and model list truth. Today the docs/UI imply health/model-sync automation that the code does not provide.

Evidence:

- Provider UI exposes sync and health status (`web/pages/settings/providers.vue:47`, `web/pages/settings/providers.vue:202`).
- Controller returns static statuses and `synced: 0` (`app/app/Http/Controllers/Api/ProviderController.php:82`, `app/app/Http/Controllers/Api/ProviderController.php:93`).
- Health command resolves `llm.provider.{slug}` bindings, but provider registration is not slug-bound that way in `AppServiceProvider` (`app/app/Console/Commands/ProviderHealthCheckCommand.php:25`, `app/app/Providers/AppServiceProvider.php:50`).
- Docs mention `provider_health_logs` and `provider_models`, but no matching migrations were found (`docs/providers.md:77`, `docs/providers.md:84`).

Recommendation:

- Centralize provider instance construction from `LlmProvider` DB rows.
- Make health checks and model sync real or remove the claims from public docs.

### Medium: The Multi-Agent Pipeline Is Sequential And Token-Duplicative

Impact: It can improve quality for high-stakes workflows, but it is slower and more expensive by construction. Each stage receives repeated conversation, memory, plan, executor output, and evidence payloads.

Evidence:

- Router runs before planner (`app/app/Services/Orchestrator/OrchestratorService.php:106`).
- Planner, executor, auditor, security auditor, and final reviewer run sequentially (`app/app/Services/Orchestrator/OrchestratorService.php:390`, `app/app/Services/Orchestrator/OrchestratorService.php:551`, `app/app/Services/Orchestrator/OrchestratorService.php:824`, `app/app/Services/Orchestrator/OrchestratorService.php:1101`, `app/app/Services/Orchestrator/OrchestratorService.php:1127`).
- `ContextBudgetGuard` truncates target files but does not enforce a whole-run cost budget (`app/app/Services/BosskuAi/ContextBudgetGuard.php:11`).
- Docs already warn against full multi-agent by default (`docs/multi-agent-architecture.md:118`).

Recommendation:

- Keep `orchestrator_executor` as the default for routine code changes.
- Use auditor/security/final reviewer only for explicit review/audit or high-risk categories.
- Add a pre-run cost/call-count estimate before enabling long chains by default.

### Medium: Evaluator And Memory Agent Are Not Separate LLM Agents

Impact: The product concept says "Evaluator scores result" and "Memory Agent updates long-term learning." The runtime has these capabilities, but the evaluator is deterministic and memory is mostly retrieval/storage/embedding/humanization. That is not bad, but the architecture should describe it accurately.

Evidence:

- `PostMemoryEvaluationService::evaluate()` computes weighted deterministic scores (`app/app/Services/Orchestrator/PostMemoryEvaluationService.php:14`).
- Orchestrator logs `post_memory_eval` with provider `ollama` and `modelsResolved['evaluator']`, but `modelsResolved` does not define an evaluator model in the classifier output (`app/app/Services/Orchestrator/OrchestratorService.php:1893`, `app/app/Services/BosskuAi/PromptRouteClassifier.php:98`).
- Memory humanization can call `LlmGateway`, but memory storage/search are service logic around embeddings/text search (`app/app/Services/BosskuAi/MemoryService.php:41`, `app/app/Services/BosskuAi/MemoryService.php:140`).

Recommendation:

- Document evaluator as deterministic v1 scoring.
- If an LLM evaluator is desired later, add an explicit `evaluator_model` setting and route it through usage tracking.

### Medium: Docs Mix Web-App Runtime With Claude/Codex/Cursor Toolkit Architecture

Impact: Some docs describe Claude Code slash-command behavior and single-call skill loading, while the Laravel web app has a separate orchestration runtime. This can confuse decisions about whether BosskuAI is "single-call by default" or a multi-stage AI software team.

Evidence:

- `docs/multi-agent-architecture.md` focuses on Claude Code/Codex/Cursor surfaces and says default is single-call (`docs/multi-agent-architecture.md:5`).
- Laravel orchestration docs describe a web-app pipeline with optional planner/executor/auditor/security/final reviewer stages (`docs/orchestration.md:3`).
- Runtime docs mention `ModelRouter` resolving provider/model by role, but the main service path uses `PromptRouteClassifier`, Settings role models, and `ModelFallbackService` (`docs/orchestration.md:48`, `app/app/Services/BosskuAi/PromptRouteClassifier.php:98`).

Recommendation:

- Split docs into "BosskuAI Web Runtime" and "BosskuAI Toolkit/IDE Patterns."
- Use the same role names and capability table across docs/UI.

## Agent Role Matrix

| Role | Config source today | Actual provider path | Fallback behavior | Cost tracking | Unique value |
|---|---|---|---|---|---|
| Router | `RuntimeSettings::routerModel()` via `ModelRoutingConfig::router()` | `ModelFallbackService` -> `LlmGateway`; direct Ollama unless raw Claude/GPT or DB role route triggers router | Config fallback list | Only if delegated through `ModelRouter`; otherwise no `UsageEvent` | Useful for workflow/risk classification, but skipped only for short low-risk routes |
| Planner / Orchestrator | `orchestrator_model` or `reasoning_model` | Same gateway path | Config fallback list | Incomplete | Strong value: creates target files, checklist, confidence, handoff |
| Executor | executor profile model settings | Same gateway path | Config fallback list per profile | Incomplete | Strong value for authoring changes, but should not always be paired with review for trivial work |
| Auditor | `auditor_model` | Same gateway path | Config fallback list | Incomplete | High value when reviewing evidence; not needed for every simple edit |
| Security Auditor | `security_auditor_model` | Same gateway path | Config fallback list | Incomplete | High value for auth/payment/deploy/full audit; deterministic no-evidence guards help |
| Final Reviewer | `final_reviewer_model` | Same gateway path | Config fallback list | Incomplete | Useful final gate for high-risk workflows; extra latency for routine tasks |
| Writer / Direct Answer | `writer_model`, `direct_answer_model` | Same gateway path | Config fallback list | Incomplete | Good low-cost alternative to full pipeline |
| Memory Agent | embeddings + optional humanize model | Ollama embed and optional `LlmGateway::chat()` for human summary | Humanize catches errors and falls back to truncated content | Incomplete for humanize call | Useful long-term learning; not a full autonomous memory LLM |
| Evaluator | no explicit model setting found | deterministic PHP scoring | none | run-step token estimate only | Useful rubric, but not a multi-LLM evaluator |

## Cost And Scalability Assessment

### Call Count

Typical call counts by workflow:

- `direct_answer`: router may run, then direct answer.
- `orchestrator_executor`: router, planner, executor.
- `orchestrator_executor_auditor`: router, planner, executor, auditor.
- `orchestrator_executor_auditor_security_final_reviewer`: router, planner, executor, auditor, security auditor, final reviewer, then deterministic post-memory evaluation.

These calls are sequential, not parallel. Latency compounds.

### Token Duplication

The design repeatedly passes conversation, memory, route, plan, executor output, read previews, and audit payloads between stages. This improves evidence flow but duplicates context. The current budget guard limits executor target files, not total run tokens or dollar cost.

### Budget Enforcement

Not sufficient today. DB route budget columns exist, docs describe route budget behavior, but runtime enforcement was not found.

### Fallback Reliability

Static fallback lists work for model failures at the `ModelFallbackService` layer. DB route fallback provider/model does not work today. Provider health-aware fallback does not work today.

### Observability

Run-step metadata is useful, but usage/cost ledger is incomplete and provider attribution can be wrong for non-Ollama calls. This blocks reliable cost-per-agent, cost-per-provider, and quality-per-dollar analysis.

## Single LLM Vs Multi-LLM Decision

### When Single LLM Is Better

- Short Q&A, docs explanation, small one-file edits, and low-risk bugs.
- When user wants speed more than independent review.
- When the model has enough context and no author-bias failure mode.
- When usage/cost tracking is incomplete and you cannot quantify multi-call cost.

### When Multi-Agent Is Justified

- High-risk code: auth, payment, billing, deployment, database migrations, secrets, permissions.
- Full repo audit/review where coverage matters more than latency.
- Write-then-review workflows where independent critique catches author bias.
- Tasks requiring different context windows or prompts: planner scoping, executor patching, auditor evidence checking.

### What Is Missing Before Multi-Agent Can Be The Default

- Complete usage/cost ledger for every model call.
- Accurate provider/model attribution in run steps and usage events.
- Real fallback/health/budget enforcement.
- Saved single-LLM vs multi-agent benchmark runs showing quality delta, cost ratio, and latency ratio.
- Acceptance rule such as: multi-agent must improve graded quality by at least 0.10 average score or catch high-severity findings missed by single-LLM, while staying under an approved cost/latency multiplier.

## Verification Results

No live LLM calls or benchmark calls were made.

Backend checks, Docker:

- `docker compose ps`: backend, frontend, nginx, postgres, and redis were running; postgres healthy.
- `docker compose exec -T backend php artisan test --filter=ModelRouterTest`: passed, 3 tests / 5 assertions.
- `docker compose exec -T backend php artisan test --filter=LlmGateway`: passed, 10 tests / 16 assertions.
- `docker compose exec -T backend php artisan test --filter=ModelFallbackService`: passed, 2 tests / 8 assertions.
- `docker compose exec -T backend php artisan test --filter=WorkflowPipelineGates`: passed, 8 tests / 11 assertions.
- `docker compose exec -T backend php artisan test --filter=PostMemoryEvaluationService`: passed, 1 test / 10 assertions.
- `docker compose exec -T backend php artisan test --filter=ModelRoutingApiTest`: passed, 1 test / 12 assertions.
- PHPUnit emitted existing doc-comment metadata deprecation warnings for PHPUnit 12 compatibility; those warnings are unrelated to this audit.

Frontend checks, Vitest:

- `npm.cmd run test -- routing-dashboard`: initial sandbox run failed with `EPERM: operation not permitted, lstat 'C:\Users\Safwan Hakim'`; rerun with elevated workspace access passed, 1 test.
- `npm.cmd run test -- usageNormalize`: initial sandbox run failed with the same EPERM; rerun with elevated workspace access passed, 3 tests.

## Fix Backlog

### Critical Fixes

1. Make one routing system authoritative.
   - Recommended v1: Settings -> Models remains the primary route source.
   - Mark DB model routes experimental until role propagation, provider construction, fallbacks, health, and budgets are complete.

2. Pass stage role and run IDs through every LLM call.
   - `ModelFallbackService::chatWithFallbacks()` should accept and forward role, run ID, run step ID, and metadata to `LlmGateway`.
   - All orchestrator agents should pass their true role.

3. Track every LLM call.
   - Move usage tracking into `LlmGateway::chat()` or another shared lower layer.
   - Include direct Ollama/Ollama Cloud calls.
   - Include fallback attempts and failures, not only final successes.

4. Fix provider attribution.
   - Carry `provider_used` from `ModelFallbackService` into every orchestrator result and `run_steps.provider`.
   - Stop hardcoding `ollama` in executor/auditor/final reviewer run-step logs.

### Next Best Improvements

5. Implement DB route fallback and budget enforcement or remove the UI fields.
6. Build provider instances from DB provider rows by type/slug, not only hardcoded app-provider registration.
7. Implement real provider health/model sync, or downgrade docs/UI copy to "manual/reference."
8. Add route-health/budget/fallback tests that prove non-primary paths are honored.
9. Add explicit evaluator model support only if you really want an LLM evaluator; otherwise document deterministic scoring clearly.

### Optional Benchmark Work

10. Run `scripts/eval_llm_quality.py` twice with the same case set: single-LLM and multi-agent.
11. Run `scripts/eval_token_budget.py` with recorded usage for the same cases.
12. Store benchmark summaries in `evals/runs/<run-id>/summary.md`.
13. Promote multi-agent by default only if quality gain clearly beats latency/cost overhead.

## Final Recommendation

Use **hybrid conditional multi-agent** as the product truth.

Do not sell the current architecture as "more LLMs equals better output." The better claim is:

> BosskuAI uses a fast single-path flow for ordinary work and escalates to planner, executor, reviewer, security auditor, and final reviewer only when the task risk justifies the extra calls.

That claim matches the strongest parts of the code today and avoids overpromising until routing, usage/cost, provider health, and benchmark proof are completed.
