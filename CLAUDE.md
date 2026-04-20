# BosskuAI for Claude Code

Use [`AGENTS.md`](AGENTS.md) as the canonical cross-tool contract. This file keeps Claude-specific deltas only.

## Model Mapping

For meaningful tasks:

- Planning, architecture, and ambiguous analysis: `claude-opus-4-6`
- Execution and implementation: `claude-sonnet-4-6`

Trivial tasks may skip the two-phase split.

## Claude Defaults

- Load the minimum relevant BosskuAI skill set from [`skill-index.json`](skill-index.json).
- Ask clarification questions before broad multi-file changes when scope is unclear.
- Keep routing and protocol chatter internal unless the user asks for it or a handoff needs it.
- Use normal prose when clarity matters; terse output is fine when the task is straightforward.

## Shared Memory

- Read [`ai-assistant/memory/active-continuation.md`](ai-assistant/memory/active-continuation.md) first when it contains live work.
- If [`semantic-memory.sqlite3`](ai-assistant/memory/semantic-memory.sqlite3) exists, query it before opening broad memory files.
- Follow [`ai-assistant/references/memory-first-handoff-protocol.md`](ai-assistant/references/memory-first-handoff-protocol.md) for durable writes.

## References

- [`AGENTS.md`](AGENTS.md)
- [`ai-assistant/references/workspace-layer-architecture.md`](ai-assistant/references/workspace-layer-architecture.md)
