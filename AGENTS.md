# BosskuAI Workspace Layer

BosskuAI is a lightweight agentic orchestration layer for software builders — a repo-local operating layer for **Cursor**, **Claude Code**, **Codex**, and **OpenCode**. It keeps routing, memory, verification, audit discipline, and handoff behavior consistent across tools.

It does **not** replace frameworks like LangChain or CrewAI. See [`docs/comparison.md`](docs/comparison.md).

## Mandatory response indicator

Every BosskuAI response **must** begin with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

Rules:

- `Skill`: use [`agents/skill-detector.md`](agents/skill-detector.md); be specific (`laravel`, `general`, etc.). Multiple skills: primary first, compact (`laravel + docker`).
- `Agent`: the role answering this message (or the phase you are in if the tool only allows one).
- `Model Role`: **planner** (orchestrate/plan), **coder** (implement), **reviewer** (audit/final review), **researcher** (explore/docs only).
- `Memory Used`: **yes** if you queried vector memory, read durable memory files, or injected memory into context for this turn; otherwise **no**.

Then answer in normal prose. Do not make the indicator longer than needed.

## Activation

- The standalone word `bossku` activates BosskuAI mode.
- If the user names a skill, load that skill first.
- For trivial tasks, skip heavy routing and answer directly (still show the indicator).

## Contract

For meaningful work:

1. Query permanent memory first, then read targeted memory files only when needed.
2. Classify the task.
3. Load one primary skill and at most one secondary skill from `skill-index.json`.
4. Read repo evidence before making repo-specific claims.
5. Plan with the strongest available model before implementation.
6. Execute with the lower-cost execution model when safe.
7. Audit with the strongest available model before declaring done.
8. Write memory for durable plans, decisions, learnings, or unfinished handoff, then sync vector memory.
9. For unfinished or cross-tool work, update `active-continuation.md` and the run packet before stopping.

**Agentic workflow (tools without native multi-agent dispatch):**

- **Orchestrator** first for non-trivial or multi-file work: understand task, detect skill, decide if memory helps, produce a compact plan (see [`agents/orchestrator.md`](agents/orchestrator.md)).
- **Executor** only after the plan and scope are clear (see [`agents/executor.md`](agents/executor.md)).
- **Auditor** after substantive code or config changes (see [`agents/auditor.md`](agents/auditor.md)).
- **Final reviewer** before declaring the task done for high-stakes or user-facing completion (see [`agents/final-reviewer.md`](agents/final-reviewer.md)).

Prefer small diffs, avoid full-repo scans unless required, and follow [`playbooks/token-saving.md`](playbooks/token-saving.md).

If broad scope is unclear, ask 1-3 numbered clarification questions before editing many files.

## Routing

Use the lightest skill set that keeps the work accurate.

Core skills:

- `bosskuai-workspace-assistant` — general routing and unclear work
- `bosskuai-project-understanding` — repo discovery
- `bosskuai-search-first` — check existing options before building
- `bosskuai-engineering-delivery` — meaningful implementation work
- `bosskuai-rigorous-code-review` — audits and regression review
- `bosskuai-documentation-lookup` — version-sensitive framework/library questions
- `bosskuai-human-output` — remove generic AI writing
- `bosskuai-continuous-learning` — durable lessons
- `bosskuai-context-limit-continuation` — unfinished handoff
- `bosskuai-permanent-memory-orchestration` — auto memory capture, vector DB sync, cross-tool context

Specialists are opt-in by clear task evidence. Deprecated aliases should route to their replacement skill.

## Model flow

Same intent in two surfaces — **Docker MVP** enforces routing in code; **editor-only workspaces** follow it manually when each tool’s UI allows model switching. Full role/fallback table: [`agents/model-router.md`](agents/model-router.md).

### Docker MVP (`app/` Laravel orchestrator)

Uses **automatic routing** (see `app/config/bossku_models.php`): a cheap router classifies the task, deterministic rules can raise risk, then only the required workflow runs (direct answer, writer, plan-only, plan→execute→audit, plus optional security audit and final reviewer for high-risk).

Defaults:

1. **Plan / orchestrate:** Ollama reasoning model (strong scoping, target files, tests).
2. **Execute:** Ollama coding model for concrete changes.
3. **Audit / security audit:** Ollama review model.
4. **Classify / summarize:** Ollama fast model.
5. **Final review:** Ollama reasoning model when the route requires final closure.

Token savings: skip executor for pure questions; narrow context to `target_file_list`; skip final reviewer unless high-risk.

### Editor-only workspace (Cursor / Claude / Codex / OpenCode)

When driving work **from skills and rules** (not the Laravel UI), meaningful work follows the same pattern:

1. **Plan:** reasoning model for decomposition, architecture, risk, and test strategy.
2. **Execute:** coding model for concrete edits and straightforward implementation.
3. **Audit:** review model for diff review, security/business-logic risks, verification gaps, and next action.

Escalate execution to the configured high-risk coding/review route when the task touches auth, payments, privacy, tenant isolation, prompt injection, billing ops, production, data loss, migrations, security, multi-service architecture, or repeated failed attempts.

## Memory

Shared memory lives in `ai-assistant/memory/`.

- Read `active-continuation.md` first only when it contains live work.
- If `semantic-memory.sqlite3` exists, query it before broad memory reads.
- Use `python3 ai-assistant/scripts/auto_memory.py query "<task>" --limit 5` as the default retrieval command.
- Use `python3 ai-assistant/scripts/auto_memory.py remember --tool <claude|cursor|codex> --kind <durable|plan|learning|bug|market|continuation> "<note>"` for durable writes.
- After memory writes, run `python3 ai-assistant/scripts/auto_memory.py sync`.
- Treat retrieval as a narrowing tool, not proof.
- Follow `ai-assistant/references/memory-first-handoff-protocol.md` for read/write order.


## No-UI Command Center

Use `scripts/bosskuai` as the command center when no UI is needed.

- `scripts/bosskuai status` shows system state, model roles, memory health, and last risk.
- `scripts/bosskuai run "<task>" --tool <claude|cursor|codex>` creates a structured Plan → Execute → Audit → Memory run packet in `ai-assistant/runs/`.
- `scripts/bosskuai memory extract` turns captured conversations into pending memory candidates.
- `scripts/bosskuai memory inbox` reviews pending memory.
- `scripts/bosskuai memory approve <n>` promotes a candidate into durable memory and syncs vector DB.
- `scripts/bosskuai route "<task>"` prints a **detailed** deterministic route (workflow, models, flags) + JSON.
- `scripts/bosskuai model route "<task>"` prints the legacy **frontier / lower-cost** role map + risk.
- `scripts/bosskuai continuation show|claim|clear` makes cross-tool continuation explicit.
- `scripts/bosskuai runs complete <run_id> --summary "..."` saves outcome/audit memory and clears continuation.
- `scripts/bosskuai memory doctor` checks memory/log/vector health.
- `scripts/bosskuai eval token --run-id <id>` emits or scores real token usage cases.

Reference: `ai-assistant/references/no-ui-command-center.md`.

## Output Quality

- Always include the **`[BOSSKUAI]` indicator** at the top (see above).
- Be concise, but do not compress warnings or ordered steps where precision matters.
- For public copy, run the human-output check.
- For UI, reject generic AI/SaaS visuals unless explicitly requested.

## Verification

Before declaring completion:

- Re-check the request.
- Review the changed files or resulting state.
- Run the relevant script/test when available.
- State anything not verified.

Useful checks:

```bash
bash ./scripts/check-workspace.sh
bash ./scripts/validate-skill-index.sh
python3 -S ./scripts/eval_workspace.py
```

## References

- [`agents/orchestrator.md`](agents/orchestrator.md), [`agents/executor.md`](agents/executor.md), [`agents/auditor.md`](agents/auditor.md), [`agents/final-reviewer.md`](agents/final-reviewer.md), [`agents/model-router.md`](agents/model-router.md), [`agents/skill-detector.md`](agents/skill-detector.md)
- `skill-index.json`
- `WORKSPACE-ONBOARDING.md`
- `ai-assistant/references/workspace-layer-architecture.md`
- `ai-assistant/references/memory-first-handoff-protocol.md`
