# Plan Log

Use this file for compact pre-execution plans that should survive across chats, tools, or longer workstreams.

Write here only when the task is non-trivial **and** the plan is likely to matter later:

- multi-step or multi-file execution
- architecture or product decisions
- likely handoff or continuation
- user explicitly asks for a plan first

Do **not** dump raw chain-of-thought or speculative brainstorming here. Keep each entry compact, actionable, and easy for another model to reuse.

## Entry template

```markdown
## YYYY-MM-DD — [Cursor | Codex | Claude | other] — [Short title]

- **User goal:** one line
- **Why this plan was stored:** architecture / multi-step / likely handoff / explicit planning request / other
- **Planned approach:** 3-6 short bullets
- **Expected files or surfaces:** paths, services, docs, or tools
- **Open assumptions / risks:**
- **Status:** planned / in-progress / superseded / completed
- **Follow-up note:** link or pointer to `learning-log.md` entry when execution finishes
```

## Promotion guidance

- If the plan becomes a durable workflow used repeatedly, promote it into a playbook, checklist, skill, or rule.
- If the task finishes, keep the plan entry concise and record outcomes in `learning-log.md`.
## 2026-06-23 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** plan
- **Captured at:** 2026-06-23T03:41:51+00:00

```text
Tier 1 Bossku-AI improvements landed: (1) Kernel topology parity test + behavioral gap inventory (5 tests) - proves DefaultPipelineGraph and WorkflowRouteHelper agree on agent sequences, documents unported gaps (fusion features, designer/clarification/council nodes, executor revision rounds); Kernel NOT flipped to default, cutover deferred until gaps closed. (2) Permission ruleset: PermissionRule + PermissionRuleset (last-match-wins wildcard) + BashArity (command label dictionary) + decide()/rulesetForRole() on AgentToolPermissionService (11 tests); ask-flow wiring into ExecutorApprovalService deferred. (3) System Context algebra: ContextKey + ContextSource + ReconcileResult + Generation + SystemContext + FileSource/ValueSource (12 tests); replaces ad-hoc prompt fragment injection, wiring into OrchestratorService deferred. (4) Typed HITL: InteractionKind enum (Confirmation/CheckboxConfirmation/Questions/SuggestTasks) + Interaction + InteractionReply with deterministic idempotency keys, target-revision stale detection, resume-write serialization (12 tests); builds on Kernel Hil/ApprovalInterruptBridge, wiring into ClarificationService deferred. (5) Memory two-layer: MemoryStoreInterface (ingest/search/browse/get/forget/recordUsage) + InMemoryMemoryStore reference + MemoryStoreConformance trait (10 assertions, 63 total); DatabaseMemoryStore adapter + config binding deferred. All 47 new tests green (181 assertions); 90-test regression sweep green (277 assertions). Sources: paperclip (typed interactions, memory two-layer, atomic checkout), langgraph (checkpointing/conformance, Command/interrupt), opencode (permission ruleset, bash arity, system context algebra), ECC (autonomous loops, instinct learning, GAN evaluator - Tier 2/3 deferred).
```
## 2026-06-23 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** plan
- **Captured at:** 2026-06-23T04:01:31+00:00

```text
Tier 2 Bossku-AI improvements landed: (6) LLM Route four-axis decomposition: Route + Endpoint + Auth + Framing + RouteRegistry in app/Services/Llm/Routing/; adding a provider is a 5-line Route entry (DeepSeek/Moonshot/ZAI/DashScope/OpenRouter all reuse OpenAiChatFraming); 15 tests green. (7) Atomic checkout: TaskCheckoutService + CheckoutConflictException + migration (bossku_ai_task_checkouts) + TaskCheckout model; single-SQL conditional UPDATE as lock primitive, 409 on conflict, lock-token release, force-release escape hatch, same-agent re-checkout on resume; 12 tests green. (8) Heartbeat procedure: added 9-step per-turn loop + final-disposition checklist to agents/orchestrator.md and agents/executor.md (paperclip contract). (9) Capability-gated host services: Capability + CapabilityManifest + CapabilityDeniedException in app/Services/Agents/Capabilities/; prefix-matching (broader satisfies narrower), least-privilege by construction; 13 tests green. (10) ECC instinct system: Instinct + InstinctStore in app/Services/BosskuAi/Learning/; confidence 0.3-0.9 logarithmic, project-scoping via git-remote hash, promotion at 2+ projects + confidence>=0.8; 15 tests green. (11) GAN generator-evaluator rubric: EvaluationRubric + EvaluationScore in app/Services/BosskuAi/Gan/; weighted (design 0.3/originality 0.2/craft 0.3/functionality 0.2), threshold 7.0, Be Ruthlessly Strict; 12 tests green. (12) Ralphinho RFC-driven DAG: WorkUnit + WorkUnitTier in app/Services/BosskuAi/Ralphinho/; complexity tiers (trivial 1/small 3/medium 5/large 7 stages), separate context windows per stage, DAG dependency blocking; 13 tests green. (13) De-sloppify loop principle: added dedicated section to agents/executor.md - Two focused agents outperform one constrained agent; implementer is thorough, separate cleanup pass removes slop; pairs with bosskuai-taste for UI. Total Tier 2: 80 new tests (176 assertions); 127-test regression sweep (442 assertions) green.
```
## 2026-06-23 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** plan
- **Captured at:** 2026-06-23T04:14:44+00:00

```text
Tier 3 Bossku-AI improvements landed: (14) Background subagent task contract: SubagentTaskContract (don't-poll instruction + result envelope) + BackgroundJobService (start/complete/fail/deliver lifecycle) + background mode in TaskSubagentService; 11 tests green. (15) Plugin Hooks surface: HookRegistry (on/trigger/clear, (input,output)=>void mutation pattern, ~30 hook names); 9 tests green. (16) customize-bosskuai built-in skill: SKILL.md that activates when model touches AGENTS.md/skill-index.json/bossku_models.php and injects real schemas + contract rules. (17) Loop-status observability: LoopStatusInspector + LoopStatusReport (repeated tool calls, parse errors, overdue steps, max-iterations detection); 7 tests green. (18) Hook runtime controls: HookProfile (minimal/standard/strict profiles via BOSSKU_HOOK_PROFILE + BOSSKU_DISABLED_HOOKS env vars); 5 tests green. (19) AI-aware PR template: .github/PULL_REQUEST_TEMPLATE.md with Model Used field (provider, model ID, context window, capabilities). (20) Agent Kanban: KanbanCard (Backlog/Ready/Running/Review/Merged/Archived/Blocked state machine + scope overlap + acceptance-required); 7 tests green. (21) Revisioned plan documents: RevisionedDocument + Revision (append-only, optimistic locking via baseRevisionId, stale-target detection); 8 tests green. (22) Completion-signal mechanism: CompletionSignal (magic phrase + threshold N consecutive signals to stop autonomous loops); 5 tests green. Total Tier 3: 52 new tests (129 assertions). Cumulative Tier 1+2+3: 179 new tests (486 assertions). 90-test existing regression sweep (277 assertions) green - zero breakage.
```
