# BosskuAI Agent OS — Kernel Roadmap

**Goal:** evolve BosskuAI from an opinionated multi-agent *product* into a true *agent operating system* by sliding a LangGraph-class execution **kernel** underneath the existing product layer — built **natively in PHP/Laravel**, no Python runtime.

**Decisions locked (2026-06-16):**
- Direction: **native PHP kernel** (one stack, one deploy, full control).
- Delivery: **full written roadmap first**, then implement phase by phase behind a feature flag.

---

## 1. Framing: what's missing is the kernel, not the product

Think of an OS. BosskuAI already has most layers:

| OS layer | BosskuAI today | Status |
|---|---|---|
| Drivers (model/provider) | `ModelRouter`, fallback chains, provider abstraction | ✅ strong |
| Syscalls (tools) | `Services/Tools`, command runner, file editor, HTTP | ✅ |
| Filesystem / long-term memory | pgvector memory, learning events, knowledge graph | ✅ strong |
| Shell / UI | dashboard, SSE stream, cross-tool harness | ✅ |
| Userland (apps) | 105 skills, ~28 personas, governance/soul, evals | ✅ unique to BosskuAI |
| **Kernel (scheduler + state + persistence + IPC)** | **hardcoded linear pipeline** | 🔴 **missing** |

LangGraph is *only* that missing kernel: a Pregel (BSP/superstep) graph engine over a typed shared-state blackboard, with durable checkpointing, interrupts, streaming, and a server/SDK/Studio. It ships no skills, personas, routing, or learning — the things BosskuAI is built around.

> **Strategy: keep everything BosskuAI has; add the kernel BosskuAI lacks.** Nodes are thin adapters over today's services, so the product layer is preserved while the engine is replaced.

---

## 2. Feature parity scorecard (target)

| # | LangGraph kernel pillar | BosskuAI today | Target phase |
|---|---|---|---|
| 1 | Arbitrary graph authoring (nodes/edges/conditional/compile) | hardcoded pipeline + 7 preset workflow strings | **P1** |
| 2 | Typed shared state + reducer channels | ad-hoc `RunContext` array | **P1** |
| 3 | Durable checkpointing (full-state snapshot per superstep) | `run_steps` store outputs only | **P1** |
| 4 | Resume-from-crash | partial (re-run) | **P1** |
| 5 | Time-travel / replay / fork | none | **P2** |
| 6 | Durable interrupts (suspend process, resume w/ human input) | `Approvals` + clarification (synchronous) | **P2** |
| 7 | Streaming-mode taxonomy (values/updates/messages/debug/checkpoints/tasks) | SSE `RunStreamEvent` | **P2** |
| 8 | Map-reduce fan-out (`Send` + barrier join) | `supervisor`/`child` runs | **P3** |
| 9 | Subgraph composition | flat agents | **P3** |
| 10 | Node-level caching (`CachePolicy`) | token budget only | **P3** |
| 11 | Per-node retry/timeout policy (declarative) | loop-until-fixed (imperative) | **P3** |
| 12 | Cross-thread store (namespaces/semantic/TTL) | pgvector memory | **P4** (formalize) |
| 13 | Server API: assistants/threads/crons/webhooks + SDK | API routes + runs | **P4** |
| 14 | Visual Studio / graph debugger | dashboard | **P4** |
| 15 | Functional API (`@task`/`@entrypoint`) | n/a | ⚪ skip (optional) |
| 16 | RemoteGraph (distributed nodes) | child runs | ⚪ skip (optional) |

**Kept, that LangGraph does NOT have:** skills system, personas, model routing+fallback, self-learning, governance/soul, evals, cross-tool harness. These become *userland* on the new kernel.

---

## 3. Kernel architecture (native PHP)

### 3.1 Proposed file layout

```
app/app/Services/Kernel/
  Channels/      ChannelInterface, LastValue, Topic,
                 BinaryOperatorAggregate, BarrierValue, EphemeralValue
  Graph/         GraphBuilder, CompiledGraph, GraphDefinition, EdgeSet, StateSchema
  Runtime/       GraphRunner, PregelLoop, Superstep, GraphContext
  Nodes/         NodeInterface, RouterNode, MemoryNode, PlannerNode, ExecutorNode,
                 AuditorNode, SecurityNode, FinalReviewerNode, SubgraphNode
  Types/         Command, Send, Interrupt, GraphInterrupt,
                 RetryPolicy, TimeoutPolicy, CachePolicy, StateSnapshot
  Checkpoint/    CheckpointSaverInterface, DatabaseCheckpointSaver, Checkpoint, CheckpointTuple
  Store/         StoreInterface, PgVectorStore        (wraps existing memory)
  Cache/         CacheStoreInterface, DatabaseCacheStore
```

New Eloquent models: `Checkpoint`, `CheckpointWrite`, `GraphDef`, and (P4) `Assistant`, `Thread`, `CronJob`, `Webhook`.

### 3.2 State + channels (LangGraph pillar 2)

`RunState` is the blackboard. Each key is a **channel** with reducer semantics, so parallel node writes merge deterministically.

```php
interface ChannelInterface {
    public function update(array $values): bool;   // returns true if changed
    public function get(): mixed;
    public function checkpoint(): mixed;           // serializable snapshot
    public function fromCheckpoint(mixed $v): static;
    public function consume(): bool;               // for ephemeral/barrier reset
}
```

| Channel | Semantics | BosskuAI use |
|---|---|---|
| `LastValue` | overwrite, last write wins | `plan`, `model_route`, `final_output` |
| `Topic` | append/accumulate | `audit_findings`, `tool_calls`, `messages` |
| `BinaryOperatorAggregate` | custom reducer fn | token totals, `add_messages`-style merge |
| `BarrierValue` | named join — waits for all expected writers | fan-out joins (P3) |
| `EphemeralValue` | reset each superstep | per-step scratch |

A `StateSchema` declares the channel set for a graph (the default-pipeline schema is fixed; custom graphs declare their own).

### 3.3 Graph definition (LangGraph pillar 1)

Graphs are **data** (`bossku_ai_graphs` table) authored via a fluent builder or JSON DSL — so the dashboard can edit them and the kernel can store/version them.

```php
$g = (new GraphBuilder($schema))
    ->addNode('router',   new RouterNode($router))
    ->addNode('memory',   new MemoryNode($memory))
    ->addNode('planner',  new PlannerNode($planner))
    ->addNode('executor', new ExecutorNode($executor))
    ->addNode('auditor',  new AuditorNode($auditor))
    ->addNode('security', new SecurityNode($security))
    ->addNode('final',    new FinalReviewerNode($final))
    ->addEdge(START, 'router')
    ->addEdge('router', 'memory')
    ->addEdge('memory', 'planner')
    ->addEdge('planner', 'executor')
    ->addConditionalEdges('executor', fn(RunState $s) => $s->get('workflow'), [
        'orchestrator_executor'                                   => END,
        'orchestrator_executor_auditor'                          => 'auditor',
        'orchestrator_executor_auditor_security'                 => 'security',
        'orchestrator_executor_auditor_security_final_reviewer'  => 'auditor',
    ])
    ->addConditionalEdges('auditor',  $routeAfterAudit,  ['security' => 'security', 'final' => 'final', END => END])
    ->addConditionalEdges('security', $routeAfterSec,    ['final' => 'final', END => END])
    ->addEdge('final', END)
    ->compile($checkpointer);
```

**The conditional routers reuse the existing classifier output** (`workflow` string + `needs_auditor`/`needs_security_auditor`/`needs_final_reviewer` flags from `DeterministicTaskClassifier` / `PromptRouteClassifier`). Routing logic is *reused, not reinvented*.

```php
interface NodeInterface {
    /** @return array<string,mixed>|Command  channel updates, or a routing Command */
    public function invoke(RunState $state, GraphContext $ctx): array|Command;
}
```

Node adapters are thin — `PlannerNode` calls `PlannerService`, `ExecutorNode` calls `ExecutorService`, etc. **No agent logic is rewritten.**

### 3.4 Runtime (the scheduler — Pregel/BSP)

`GraphRunner` runs **supersteps**:

1. **Plan** — compute active nodes (those with pending channel input via edges/`Send`).
2. **Execute** — invoke active nodes (P1 sequential; P3 parallel via queue workers); collect writes.
3. **Apply** — write to channels; reducers merge parallel writes deterministically.
4. **Checkpoint** — persist full state snapshot (§3.5).
5. **Stream** — emit `tasks`/`updates`/`checkpoints` events via `RunStreamEventService`.
6. Loop until no active nodes or `END`; honor `Command(goto)`, `Send`, `GraphInterrupt`.

### 3.5 Checkpointer (durable execution — pillars 3, 4)

`thread_id = run_id` (reuse `Run`). One checkpoint row per superstep.

```php
interface CheckpointSaverInterface {
    public function put(string $thread, Checkpoint $cp): string;
    public function getTuple(string $thread, ?string $checkpointId = null): ?CheckpointTuple;
    public function list(string $thread, int $limit = 50): array;
    public function putWrites(string $thread, string $checkpointId, array $writes): void;
    public function deleteThread(string $thread): void;
}
```

**Migrations:**

```
bossku_ai_checkpoints
  id (uuid, pk)
  thread_id (uuid -> bossku_ai_runs.id, cascade)
  checkpoint_id (uuid)         parent_checkpoint_id (uuid, nullable)
  channel_values (longText/json)   channel_versions (json)   versions_seen (json)
  next_nodes (json)            source (input|loop|interrupt|fork)   step (int)
  metadata (json)              created_at
  unique(thread_id, checkpoint_id)   index(thread_id, step)

bossku_ai_checkpoint_writes      -- pending writes for crash recovery
  checkpoint_id (uuid)  task_id (string)  idx (int)
  channel (string)      value (longText/json)
  primary key(checkpoint_id, task_id, idx)
```

- **Resume-from-crash:** load latest checkpoint → rebuild channels → continue from `next_nodes`.
- **Resume API:** `POST /api/runs/{id}/resume`.

### 3.6 Interrupts (durable HIL — pillar 6)

```php
function interrupt(mixed $value): mixed; // throws GraphInterrupt if no resume value present
```

- Inside a node, `interrupt($payload)` throws `GraphInterrupt`. The runner catches it → writes an `interrupt`-source checkpoint (state frozen) → creates/links an `Approval` (or clarification) row → sets `Run.status = interrupted` → emits an `interrupt` stream event → returns control. **The PHP process can die here.**
- **Resume:** `POST /api/runs/{id}/resume` with a `Command(resume: <human value>)` → reload checkpoint → inject resume value into the node's scratchpad → re-run that node.
- Maps directly onto existing `Approvals` + `ClarificationService`; `interrupt_before`/`interrupt_after` become per-node config on risky nodes (executor terminal commands, deploy, secret rotation).

### 3.7 Store, Send, Subgraph, Cache, Policies

- **Store (pillar 12):** `StoreInterface` (namespaces, `put/get/search/list_namespaces`, TTL) wrapping existing pgvector memory, so every node gets a uniform `ctx->store`.
- **Send (pillar 8):** `Send(node, state)` → runner schedules N parallel node instances; results join at a `BarrierValue`. Generalizes `supervisor`/`child` runs.
- **Subgraph (pillar 9):** `SubgraphNode` wraps a `CompiledGraph`; checkpoints nest under the parent thread.
- **Cache (pillar 10):** `CachePolicy(ttl, keyFn)`; key = `hash(node + input channels)`; `DatabaseCacheStore` (or Redis). Runner checks before invoking.
- **Policies (pillar 11):** per-node `RetryPolicy(maxAttempts, backoff, retryOn)` + `TimeoutPolicy(seconds)`. Personas keep the *judgment* ("loop until green"); the kernel enforces the *mechanics*.

---

## 4. Migration strategy — zero behavior change

The point of P1 is **not** to rewrite `OrchestratorService`. It is to run today's pipeline *as a compiled graph* so nothing changes for users while the engine gains kernel powers.

1. Build the kernel alongside the existing pipeline.
2. Define a **default graph** that reproduces the exact current flow:
   `START → router → memory → planner → executor → {auditor?} → {security?} → {final?} → END`.
3. Conditional edges read the existing classifier output (`workflow` + flags) — reuse, don't reinvent.
4. `OrchestratorService::run()` becomes a thin wrapper:
   build `RunState` from `prompt/conversation/options` → `GraphRunner->run($defaultGraph, $state, $emit)` → map final channels back to today's return array (`final_output`, steps, artifacts).
5. **Feature flag** `BOSSKU_KERNEL=graph|legacy` (default `legacy` until proven).
6. **Gate on evals:** the existing suite (routing/retrieval/workflow/indicator, currently 100%) must stay green on the `graph` engine before flipping the default.

Preserved by this approach: SSE streaming (`emit`), approvals, worktree isolation (`RunWorkspace`), personas, model routing, evals, self-learning. Earned: checkpoint/resume, time-travel, durable interrupts, fan-out, subgraphs, caching, retry policy.

---

## 5. Phased plan

### Phase 0 — Interfaces & scaffolding (no behavior change)
- Land interfaces only: `NodeInterface`, `ChannelInterface`, `CheckpointSaverInterface`, `StoreInterface`, `GraphContext`, `Command`, `Send`, `RunState`.
- ADR doc + this roadmap committed.
- **Exit:** code compiles, no runtime path touched.

### Phase 1 — Kernel (the unlock) 🔴 highest value
- Channels: `LastValue`, `Topic`, `BinaryOperatorAggregate`, `BarrierValue`, `EphemeralValue`. — ✅ done
- `GraphBuilder` / `CompiledGraph` / `GraphRunner` (single-threaded supersteps). — ✅ done
- `DatabaseCheckpointSaver` + 2 migrations (`checkpoints`, `checkpoint_writes`). — ✅ done (+ `InMemoryCheckpointSaver` for tests)
- Default graph = current pipeline; conditional edges from classifier. — ✅ done (`DefaultPipelineGraph`, topology verified against the workflow matrix)
- `BOSSKU_KERNEL` + `BOSSKU_KERNEL_MAX_STEPS` config flags. — ✅ done
- 7 node adapters over existing pipeline services. — ✅ increment 2 (`Nodes\Pipeline\{Router,Memory,Planner,Executor,Auditor,Security,FinalReviewer}Node` call the real services with their exact signatures)
- `OrchestratorService::run()` wrapper behind `BOSSKU_KERNEL` flag. — ✅ increment 2 (nullable ctor param + 6-line guard at top of `run()`; `dispatchToKernel()` → `KernelPipelineCoordinator`; default `legacy`, opt-out per call via `options['force_legacy']`)
- `KernelPipelineCoordinator` + `PipelineContext` (checkpoint-safe) + `KernelMode` flag helper. — ✅ increment 2
- `POST /api/runs/{id}/resume` (resume-from-checkpoint). — ⏳ kernel `resume()` + `KernelPipelineCoordinator::resume()` implemented + tested; HTTP controller wiring is the small remaining bit.
- **Exit:** ⏳ **eval suite 100% on both engines** — the user's gate on the live stack before flipping the default. Wiring + ordering + checkpoint/resume verified with service doubles.

#### Implementation log — Phase 1 increment 2 (2026-06-16)
The kernel now runs the **real** pipeline. Added `Kernel\KernelMode`, `Kernel\Pipeline\{PipelineContext, KernelPipelineCoordinator}`, and 7 `Kernel\Nodes\Pipeline\*Node` adapters (signature-faithful wrappers over `PlannerService`/`ExecutorService`/`AuditorService`/`SecurityAuditorService`/`FinalReviewerService`). `OrchestratorService` gained a nullable `KernelPipelineCoordinator` ctor param + a guarded `dispatchToKernel()` at the top of `run()`. **4 new tests (full suite 493 green, 0 failures)** prove: stage ordering matches the workflow matrix (executor-only → planner+executor; full → all 5 stages in order), checkpoints persist per superstep, the result maps to the `run()` envelope, and resolving the **real container-built `OrchestratorService`** dispatches through the kernel when `BOSSKU_KERNEL=graph`.

**Honest scope:** the `ExecutorNode` covers straight-through per-step execution; the legacy orchestrator's revision rounds, evidence reconciliation, command application, preflight survey, clarification, and specialist spawning are **not yet ported** — the kernel `dispatchToKernel()` builds a *minimal* `PipelineContext` from the classifier. Full context assembly + behavioral parity is the work the **eval suite gates** before `BOSSKU_KERNEL=graph` becomes the default. Legacy remains the default and is untouched.

#### Implementation log — increment 1 (2026-06-16)
Built the kernel engine end-to-end under `app/app/Services/Kernel/` (Channels, Types, Graph, Nodes, Runtime, Checkpoint, Store) + 2 migrations + `Checkpoint`/`CheckpointWrite` models + config flags. **14 new tests, full suite 457 green, 0 failures.** Verified: default-pipeline routing matches the legacy workflow matrix; `Command(goto)` overrides edges; checkpoints persist per superstep to `bossku_ai_checkpoints`; **a run that interrupts resumes from the DB checkpoint in a fresh runner with human input injected — state written before the suspend survives the restart.** Deferred to increment 2: real node adapters over the pipeline services, the `OrchestratorService::run()` flag-flip, and HTTP resume wiring — the behavior-sensitive integration that must be gated on the live eval suite.

### Phase 2 — Durability & HIL — ✅ kernel-complete (2026-06-16)
- `interrupt()` + `GraphInterrupt` → durable suspend/resume. — ✅ `GraphContext::interrupt($key, $request)` throws on first pass, returns the injected value on resume.
- Static interrupts: `interruptBefore`/`interruptAfter` per node + runner enforcement with one-shot resume bypass. — ✅
- Wire `Approvals` onto durable interrupts. — ✅ `ApprovalInterruptBridge`: interrupt → pending `Approval`; decided approvals → resume scratch (`approve`/`reject` both verified end-to-end).
- Time-travel: `GET /api/runs/{id}/checkpoints`, `POST /api/runs/{id}/fork {checkpoint_id, state_patch}`. — ✅ `CheckpointService` (history + fork-with-patch through reducers) + `CheckpointController` + routes.
- Streaming modes: `StreamMode` taxonomy (`values|updates|messages|custom|debug|checkpoints|tasks`); runner tags every emit. — ✅
- **Exit:** ✅ approve/reject resumes across a fresh runner ("process restart"); a run forks from any past checkpoint with edited state and the fork runs against the patched state.

#### Implementation log — Phase 2 (2026-06-16)
Added `App\Services\Kernel\Hil\ApprovalInterruptBridge`, `Checkpoint\CheckpointService`, `Runtime\StreamMode`; extended `GraphContext`, `GraphBuilder`, `CompiledGraph`, `GraphRunner`; new `CheckpointController` + `GET /runs/{id}/checkpoints` & `POST /runs/{id}/fork`. **10 new tests; full suite 467 green, 0 failures.** Note: the `ClarificationService` bridge and `interrupt_before` on the *real* risky pipeline nodes land with Phase 1 increment 2 (the node adapters), since they need the live pipeline; the kernel primitives they depend on are done and tested here.

### Phase 3 — Concurrency & policies — ✅ kernel-complete (2026-06-16)
- `Send` fan-out/fan-in. — ✅ frontier refactored to `PregelTask` (node + payload); `Command(send: [...])` fans out, joins at a `Topic`/reduce node. Map-reduce verified (square→sum).
- Subgraphs (`SubgraphNode`). — ✅ compiled graph as a node with input/output key slicing.
- Node cache (`CachePolicy` + `CacheStoreInterface` + `InMemoryCacheStore` + `DatabaseCacheStore` + `bossku_ai_node_cache` migration). — ✅
- Declarative `RetryPolicy` / `TimeoutPolicy` per node, enforced in the runner. — ✅ (retry reattempts + exhaustion; timeout is best-effort post-hoc — see note)
- Parallel node execution via queue workers. — ⏳ infra deferred; current loop runs the frontier sequentially but writes merge through reducers so semantics are parallel-ready. True preemptive timeouts also land with queue workers.
- **Exit:** ✅ a map-reduce graph runs, fans out, joins, and checkpoints correctly.

#### Implementation log — Phase 3 (2026-06-16)
Added `Runtime\PregelTask`, `Nodes\SubgraphNode`, `Types\{CachePolicy,TimeoutPolicy}`, `Cache\{CacheStoreInterface,InMemoryCacheStore,DatabaseCacheStore}` + `bossku_ai_node_cache` migration; extended `GraphContext` (per-task Send input), `GraphBuilder::addNode` + `CompiledGraph` (per-node policies), and rewrote `GraphRunner` (task frontier, Send fan-out, retry/timeout/cache enforcement). Checkpoint `next` stays backward-compatible (plain nodes serialize as strings; Send tasks as `{node,input}`). **9 new tests; full suite 476 green, 0 failures.** Note: native PHP can't preempt a synchronous call, so `TimeoutPolicy` is enforced post-hoc (a node that overran its budget raises, optionally caught by `RetryPolicy`); hard preemption needs the queue-worker execution model.

### Phase 4 — Platform — ✅ kernel/API-complete (2026-06-16)
- `Assistants` / `Threads` / `Crons` / `Webhooks` — tables + models + REST API. — ✅ (`bossku_ai_assistants`, `_threads`, `_cron_jobs`, `_webhooks` + `runs.thread_id/assistant_id`; full CRUD under `bossku.api`)
- `CronService` (due-logic via `Cron\CronExpression`) + `bossku:run-due-crons` command scheduled every minute. — ✅
- `WebhookDispatcher` (HMAC-signed, event-filtered, failure-isolated delivery via `Http`). — ✅
- Studio backend: `GraphController` + `GraphRegistry` expose graph topology (nodes/edges/branches) as pure data for visual rendering; pair with `/runs/{id}/checkpoints` + SSE + `/fork`. — ✅
- SDK: TS client `sdk/bossku-kernel.ts` over the platform API. — ✅
- `StoreInterface` reference impl `InMemoryStore` (namespaced KV + search). — ✅ (pgvector-backed impl is the integration step)
- **Exit:** ✅ save an assistant → schedule a cron → subscribe a webhook → fetch graph topology → list/fork checkpoints, all via API. The visual Studio *frontend component* (Nuxt/Vue) is the one remaining piece — its entire backend contract is now in place and tested.

#### Implementation log — Phase 4 (2026-06-16)
Added 5 migrations, models `Assistant`/`Thread`/`CronJob`/`Webhook` (+ `Run` thread relations), services `Platform\CronService` & `Platform\WebhookDispatcher`, `Graph\GraphRegistry` + `DefaultPipelineGraph::topology()`, controllers `Assistant`/`Thread`/`CronJob`/`Webhook`/`Graph` + routes, `RunDueCronsCommand` (+ scheduler entry), `Store\InMemoryStore`, and the TS SDK. **13 new tests; full suite 489 green, 0 failures.** Not built (frontend, untestable in PHPUnit): the visual Studio component itself.

---

## 6. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Behavior drift vs current pipeline | Feature flag + eval-suite gate on both engines before default flip |
| Checkpoint bloat (large state per step) | Store channel deltas + versions; prune old checkpoints (reuse `pruneOldEvents` pattern); cap retained history per run |
| PHP request lifecycle vs long runs | Run the loop in queued jobs/CLI workers (already used for supervisor/child), not web requests |
| Serialization of agent payloads | Strict JSON-serializable channel values; `StateSnapshot` versioning |
| Scope creep into LangGraph 1:1 API | Port *concepts*, keep BosskuAI-idiomatic naming; skip functional API & RemoteGraph unless needed |

---

## 7. Out of scope (deliberately not copied)
- Functional API (`@task`/`@entrypoint`) — graph API covers it.
- RemoteGraph / distributed nodes — child runs suffice until multi-host is a real need.
- Exact LangGraph public API surface — we match *semantics*, not signatures.

---

*Owner: BosskuAI core. Source comparison: `/langgraph` (LangGraph OSS) vs `/Bossku-AI`. Generated 2026-06-16.*
