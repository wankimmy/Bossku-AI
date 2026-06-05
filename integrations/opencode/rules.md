# OpenCode rules (BosskuAI template)

*OpenCode* (and similar OSS coding front-ends): treat these Markdown bullets as **system-level rules** wherever your IDE stores them.

Expose the same invariant contract as Cursor:

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
| Routing | [`agents/model-router.md`](../../agents/model-router.md) · `ai-assistant/config/model-router.yaml` |
| Tokens | [`playbooks/token-saving.md`](../../playbooks/token-saving.md) |
| Auditor | [`agents/auditor.md`](../../agents/auditor.md) |

## Memory summaries

Use `scripts/bosskuai` wrappers or `python3 ai-assistant/scripts/auto_memory.py` identical to Cursor/Codex; OpenCode inherits **file‑based sync**, not SaaS teleportation.
