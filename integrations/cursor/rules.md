# Cursor rules (BosskuAI template)

Install by copying snippets into `.cursor/rules/*.mdc` or merging into your project rules.

## Mandatory indicator

Always start replies with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

## Behavioral rules

- Detect skill via [`agents/skill-detector.md`](../../agents/skill-detector.md) before substantive work.
- **Orchestrator** (`agents/orchestrator.md`) before **executor** for complex / multi-file tasks.
- **Executor** (`agents/executor.md`) only after plan and scope are clear.
- **Auditor** (`agents/auditor.md`) after meaningful code/config changes.
- **Final reviewer** (`agents/final-reviewer.md`) before declaring completion on high-impact work.
- Prefer **small diffs**; **do not** rewrite unrelated files.
- **Do not** scan the whole repo unless required.
- Query **project memory** only when helpful; follow [`memory/memory-policy.md`](../../memory/memory-policy.md).
- Reduce token waste: [`playbooks/token-saving.md`](../../playbooks/token-saving.md).
- Canonical contract: **[`AGENTS.md`](../../AGENTS.md)** — keep project copy in sync after Bossku upgrades.
