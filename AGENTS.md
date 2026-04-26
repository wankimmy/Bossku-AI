# BosskuAI Workspace Layer

BosskuAI is a repo-local operating layer for Claude Code, Cursor, and Codex. It keeps routing, memory, verification, and handoff behavior consistent across tools.

## Activation

- The standalone word `bossku` activates BosskuAI mode.
- If the user names a skill, load that skill first.
- For trivial tasks, skip heavy routing and answer directly.

## Contract

For meaningful work:

1. Classify the task.
2. Load one primary skill and at most one secondary skill from `skill-index.json`.
3. Read repo evidence before making repo-specific claims.
4. Plan, execute, then verify.
5. Write memory only for durable lessons or unfinished handoff.

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

Specialists are opt-in by clear task evidence. Deprecated aliases should route to their replacement skill.

## Memory

Shared memory lives in `ai-assistant/memory/`.

- Read `active-continuation.md` first only when it contains live work.
- If `semantic-memory.sqlite3` exists, query it before broad memory reads.
- Treat retrieval as a narrowing tool, not proof.
- Follow `ai-assistant/references/memory-first-handoff-protocol.md` for read/write order.

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
