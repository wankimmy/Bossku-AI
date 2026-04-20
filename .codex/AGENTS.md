# BosskuAI for Codex

Use [`../AGENTS.md`](../AGENTS.md) as the canonical cross-tool contract. This file keeps Codex-specific deltas only.

## Model Mapping

For meaningful tasks:

- Planning and architecture: `gpt-5.4`
- Execution and implementation: `gpt-5.4-mini`

Trivial tasks may skip the split.

## Codex Defaults

- Load the minimum relevant BosskuAI skill set from [`../skill-index.json`](../skill-index.json).
- Ask 1-3 clarification questions before broad multi-file changes when scope is unclear.
- Read code and nearby docs before making repo-specific claims.
- Keep routing chatter internal unless the user asks for it or a handoff needs it.

## Shared Memory

- Read [`../ai-assistant/memory/active-continuation.md`](../ai-assistant/memory/active-continuation.md) first when it contains live work.
- If [`../ai-assistant/memory/semantic-memory.sqlite3`](../ai-assistant/memory/semantic-memory.sqlite3) exists, query it before opening broad memory files.
- Follow [`../ai-assistant/references/memory-first-handoff-protocol.md`](../ai-assistant/references/memory-first-handoff-protocol.md) for durable writes.

## References

- [`../AGENTS.md`](../AGENTS.md)
- [`../ai-assistant/references/workspace-layer-architecture.md`](../ai-assistant/references/workspace-layer-architecture.md)
