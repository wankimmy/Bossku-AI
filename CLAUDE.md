# BosskuAI for Claude Code

Use [`AGENTS.md`](AGENTS.md) as the canonical cross-tool contract. This file keeps Claude-specific deltas only.

## Model Mapping

For meaningful tasks, use the always-on phase split:

| Phase | Default model | Purpose |
|---|---|---|
| Plan | `claude-opus-4-7` | Decompose task, inspect risks, choose approach, decide tests |
| Execute | `claude-sonnet-4-6` | Implement straightforward edits and mechanical changes |
| Audit | `claude-opus-4-7` | Review diff, security/business logic, verification gaps, next action |

Escalate execution to `claude-opus-4-7` when the task touches payments, auth, secrets, privacy, migrations, data loss, multi-service architecture, or repeated failed attempts.

Trivial tasks may skip the split.

## Claude Defaults

- For Claude Opus 4.7 API usage, prefer adaptive thinking / effort controls where available; do not assume manual extended-thinking budgets are accepted.
- Load the minimum relevant BosskuAI skill set from [`skill-index.json`](skill-index.json).
- Use `bosskuai-permanent-memory-orchestration` when the task involves memory, vector DB, model routing, or cross-tool context.
- Ask clarification questions before broad multi-file changes when scope is unclear.
- Keep routing and protocol chatter internal unless the user asks for it or a handoff needs it.
- Use normal prose when clarity matters; terse output is fine when the task is straightforward.

## Shared Memory

- Read [`ai-assistant/memory/active-continuation.md`](ai-assistant/memory/active-continuation.md) first when it contains live work.
- Query vector memory before opening broad memory files:

  ```bash
  python3 ai-assistant/scripts/auto_memory.py query "<task summary>" --limit 5
  ```

- Write durable memory after meaningful planning/outcomes:

  ```bash
  python3 ai-assistant/scripts/auto_memory.py remember --tool claude --kind plan "<compact plan>"
  python3 ai-assistant/scripts/auto_memory.py remember --tool claude --kind learning "<outcome, verification, risks, next action>"
  ```

- Follow [`ai-assistant/references/memory-first-handoff-protocol.md`](ai-assistant/references/memory-first-handoff-protocol.md) for durable writes.
- When hooks are enabled, Claude Code captures user prompts and syncs vector memory automatically. Hooks are local-only and advisory.

## References

- [`AGENTS.md`](AGENTS.md)
- [`ai-assistant/references/workspace-layer-architecture.md`](ai-assistant/references/workspace-layer-architecture.md)
- [`ai-assistant/references/always-on-model-router.md`](ai-assistant/references/always-on-model-router.md)
- [`ai-assistant/skills/bosskuai-permanent-memory-orchestration/SKILL.md`](ai-assistant/skills/bosskuai-permanent-memory-orchestration/SKILL.md)
