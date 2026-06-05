# BosskuAI for Codex (template)

Upstream source in repo: **`.codex/AGENTS.md`**.

Canonical policy: **`AGENTS.md`** at workspace root after install.

## Persona stack

Orchestrator → Executor → Auditor → Final reviewer definitions live in **`agents/`**—Codex executes them manually most of time (no slash commands).

## Mandatory indicator

Always start with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

## Loads

| Topic | File |
|---|---|
| Skill triggers | [`agents/skill-detector.md`](../../agents/skill-detector.md) |
| Routing | [`agents/model-router.md`](../../agents/model-router.md) · `skill-index.json` |
| Tokens | [`playbooks/token-saving.md`](../../playbooks/token-saving.md) |
| Auditor format | [`agents/auditor.md`](../../agents/auditor.md) |
| Thin skill pack | [`packages/bossku-ai/skills/bossku-ai/SKILL.md`](../../packages/bossku-ai/skills/bossku-ai/SKILL.md) |

## Memory summaries

Before big edits:  

`python3 ai-assistant/scripts/auto_memory.py query "<task fragment>" --limit 5`

After durable milestones: **`remember`** + **`sync`** (see `AGENTS.md`).

## Handoff notes

Echo final **`[BOSSKUAI]` block** plus one-line Cursor/OpenCode briefing so the next IDE doesn't re-plan from scratch.
