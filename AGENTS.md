# BosskuAI Workspace Layer

BosskuAI is a repo-local operating layer for Claude Code, Cursor, and Codex. It keeps routing, memory, verification, and handoff behavior consistent across tools.

## Activation

- The standalone word `bossku` activates BosskuAI mode.
- If the user names a skill, load that skill first.
- For trivial tasks, skip heavy routing and answer directly.

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

## Always-on model flow

Meaningful work uses a three-phase model pattern by default:

1. **Plan:** strongest/frontier model for decomposition, architecture, risk, and test strategy.
2. **Execute:** lower-cost execution model for concrete edits and straightforward implementation.
3. **Audit:** strongest/frontier model for diff review, security/business-logic risks, verification gaps, and next action.

Escalate execution back to the frontier model when the task touches auth, payments, privacy, tenant isolation, prompt injection, billing ops, production, data loss, migrations, security, multi-service architecture, or repeated failed attempts.

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
- `scripts/bosskuai model route "<task>"` explains model routing and risk escalation.
- `scripts/bosskuai continuation show|claim|clear` makes cross-tool continuation explicit.
- `scripts/bosskuai runs complete <run_id> --summary "..."` saves outcome/audit memory and clears continuation.
- `scripts/bosskuai memory doctor` checks memory/log/vector health.
- `scripts/bosskuai eval token --run-id <id>` emits or scores real token usage cases.

Reference: `ai-assistant/references/no-ui-command-center.md`.

## Output Quality

- Keep protocol chatter internal unless the user asks for it.
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

- `skill-index.json`
- `WORKSPACE-ONBOARDING.md`
- `ai-assistant/references/workspace-layer-architecture.md`
- `ai-assistant/references/memory-first-handoff-protocol.md`

<claude-mem-context>
# Memory Context

No shared handoff is active in this starter repo. Use `ai-assistant/memory/active-continuation.md` only for unfinished work, then clear it after use.
</claude-mem-context>
