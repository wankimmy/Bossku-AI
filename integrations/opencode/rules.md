# OpenCode rules (BosskuAI fallback template)

Prefer the generated `.opencode` harness installed by [`install.md`](install.md). Use this file only when an OpenCode setup cannot load `.opencode/opencode.jsonc`, `.opencode/agent/*.md`, or `.opencode/command/*.md`.

Expose the same invariant contract as the other BosskuAI tool surfaces:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

## Mandatory loads

| Topic | File |
|---|---|
| Contract | [`AGENTS.md`](../../AGENTS.md) |
| Skill map | [`agents/skill-detector.md`](../../agents/skill-detector.md) |
| Routing | [`agents/model-router.md`](../../agents/model-router.md) |
| Tokens | [`playbooks/token-saving.md`](../../playbooks/token-saving.md) |
| Auditor | [`agents/auditor.md`](../../agents/auditor.md) |

## Preferred harness files

| Purpose | File |
|---|---|
| OpenCode config | [`.opencode/opencode.jsonc`](../../.opencode/opencode.jsonc) |
| Working modes | [`.opencode/agent/`](../../.opencode/agent) |
| Slash commands | [`.opencode/command/`](../../.opencode/command) |

## Memory summaries

Use `scripts/bosskuai` wrappers or `python3 ai-assistant/scripts/auto_memory.py` identical to Cursor/Codex; OpenCode inherits **file-based sync**, not SaaS teleportation.
