# BosskuAI Rules

Use [`AGENTS.md`](../../AGENTS.md) as the canonical contract.

## Mandatory indicator

Every response must begin with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

See [`agents/skill-detector.md`](../../agents/skill-detector.md).

## Behavior

- `bossku` activates BosskuAI mode for the current request.
- For meaningful work: classify, load the minimum relevant skill set, orchestrate/plan before large edits, then execute.
- After code changes, apply auditor checklist; final-reviewer summary before declaring done when stakes warrant.
- Ask clarification questions before broad multi-file changes when scope is unclear.
- Read [`ai-assistant/memory/active-continuation.md`](../../ai-assistant/memory/active-continuation.md) first when it contains live work.
- If [`semantic-memory.sqlite3`](../../ai-assistant/memory/semantic-memory.sqlite3) exists, query it before opening broad memory files.
