# Orchestration Pipeline

BosskuAI's run execution passes through ordered services. The **workflow** chosen at routing time decides which agents actually run—not every prompt uses executor → auditor → final-reviewer.

## Workflow matrix

| Workflow | Agents that run | Typical prompt |
|----------|----------------|------------------|
| `direct_answer` | Fast model only | Short Q&A, smoke `test` |
| `writer_only` | Writer | Marketing copy, docs without repo edits |
| `orchestrator_only` | Planner + optional preflight reads | Explain codebase, survey without edits |
| `orchestrator_executor` | Planner → executor (+ narrow tests) | Create/fix/update files (default) |
| `orchestrator_executor_auditor` | + auditor | Repo audit/review/scan |
| `orchestrator_executor_auditor_security` | + security auditor | Full repo audit (`audit full`) |
| `…_final_reviewer` | + final reviewer | High-risk (auth, payment, deploy) |

Routing flags (`needs_auditor`, `needs_security_auditor`, `needs_final_reviewer`) **and** the workflow string must both allow a stage. Skipped agents emit `agents_skipped` SSE with `skipped_agents` in routing artifacts.

Optional env: `BOSSKU_DEFAULT_WORKFLOW=orchestrator_executor` (see `config/bossku.php`); heuristics in `DeterministicTaskClassifier` and `PromptRouteClassifier` override this per prompt.

### Pre-execution clarification

`BOSSKU_ORCHESTRATOR_CLARIFICATION_MODE` (Settings → `orchestrator_clarification_mode`):

| Mode | Behavior |
|------|----------|
| `smart` (default) | Skip when the prompt is clear; otherwise ask 0–3 questions (prefer one) |
| `always` | Ask before every run until the user answers once |
| `off` | Never ask pre-execution clarification |

## The Five Services

```
OrchestratorService
  └─► PlannerService
        └─► ExecutorService
              └─► AuditorService
                    └─► FinalReviewerService
```

### OrchestratorService

The entry point for every run. It receives the raw user prompt and is responsible for:

- **Intent parsing** — determining what kind of task this is (code generation, debugging, question, deployment, etc.)
- **Memory query** — checking pgvector for semantically similar past runs and injecting relevant context
- **Skill selection** — querying the skill index to find the highest-quality matching skill for this request
- **Model routing** — calling `ModelRouter` to resolve which provider/model should handle this run, based on role, configured routes, and available providers
- **Context assembly** — merging skill content, memory snippets, soul guidance, and the user prompt into a structured context object passed downstream

The orchestrator does not produce user-visible output. It produces a `RunContext` object.

### PlannerService

Receives the `RunContext` and produces a structured `ExecutionPlan` — an ordered list of steps. Each step has:

- A `type` (e.g. `code_edit`, `terminal_command`, `http_request`, `file_read`)
- A `description` of the intended action
- A `risk_level` assigned by `RiskClassifier`
- An `approval_required` flag set when risk is `high` or `critical`

Steps are persisted to the database immediately after planning. This means you can inspect the plan before execution begins by querying `GET /api/runs/{id}/steps`.

### ExecutorService

Walks the plan step by step. For each step:

1. Checks `approval_required` — if true, pauses and emits an `approval_pending` SSE event, waiting for a human approval signal before continuing
2. Invokes the appropriate tool (file editor, shell runner, HTTP client, etc.)
3. Records the step result (stdout, diff, response body) and updates the step status to `completed` or `failed`
4. Emits an SSE event so the UI updates in real time

If a step fails, the executor marks the run as `failed` and stops — it does not silently continue with a broken state.

### AuditorService

Runs after all executor steps complete. It reviews the accumulated diffs and outputs against four lenses:

- **Quality** — correctness, test coverage, edge cases
- **Security** — injection risks, secret exposure, unsafe shell usage
- **Performance** — N+1 queries, missing indexes, unbounded loops
- **Maintainability** — readability, naming, documentation

Findings are stored as structured audit items on the run record and surfaced in the run detail view.

### FinalReviewerService

The last gate. It answers three questions:

1. Was the original user intent fully satisfied?
2. Are there unresolved risks the user should know about?
3. What is the recommended next step?

The final review summary is the primary text returned to the user as the run's completion message.

## SSE Streaming

Every state transition emits a Server-Sent Event on `/api/runs/{id}/stream`. Event types:

| Event | When |
|---|---|
| `plan_ready` | PlannerService finishes |
| `step_started` | ExecutorService begins a step |
| `step_completed` | Step finishes successfully |
| `step_failed` | Step fails |
| `approval_pending` | Step requires human approval |
| `approval_granted` | Approval received, execution resumes |
| `audit_complete` | AuditorService finishes |
| `run_complete` | FinalReviewerService finishes |
| `run_failed` | Run terminated due to error |

The Nuxt UI subscribes to this stream on the run detail page and updates the step timeline in real time without polling.

## Skill Selection

`OrchestratorService` calls `SkillMatcherService` with the user prompt. The matcher:

1. Embeds the prompt using the configured embedding model
2. Runs a cosine similarity search against all active skills in the skill index
3. Returns the top-ranked skill whose `quality_score` is above the configured threshold (default `0.6`)
4. Falls back to the `cofounder` skill if no specialist matches

The selected skill's `SKILL.md` content is injected into the system prompt for the planning and execution model calls.

## Step Persistence

All steps are written to the `run_steps` table before execution begins. This means:

- The plan is inspectable before anything runs
- Partial progress survives a process restart (the executor can resume from the last incomplete step)
- The brain page can show step-level telemetry across many runs
