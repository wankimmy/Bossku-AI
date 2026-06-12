# BosskuAI for Claude Code (template)

Upstream source in repo root: **`CLAUDE.md`**. Prefer keeping **one canonical** workspace `CLAUDE.md` that `@`/`include`‑links Bossku snippets or duplicates this preamble.

Use **[`AGENTS.md`](../../AGENTS.md)** as canonical policy.

## Persona

Structured engineering team metaphor:

- Orchestrator → plan / scope (`agents/orchestrator.md`)
- Executor → implement (`agents/executor.md`)
- Auditor → critique (`agents/auditor.md`)
- Final reviewer → ship checklist (`agents/final-reviewer.md`)

## Mandatory indicator

Always begin with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <...>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

## Loads

| Topic | Reference |
|---|---|
| Skill detection | [`agents/skill-detector.md`](../../agents/skill-detector.md) |
| Model routing | [`agents/model-router.md`](../../agents/model-router.md) · `always-on-model-router.md` |
| Token saving | [`playbooks/token-saving.md`](../../playbooks/token-saving.md) |
| Audit workflow | [`playbooks/code-audit.md`](../../playbooks/code-audit.md) · **`/plan`**, **`/verify`**, **`/quality-gate`** flows in `commands/` |
| Memory summary injection | [`memory/summarizer.md`](../../memory/summarizer.md), `scripts/bosskuai run ...` packets |
| Deep multi-agent realism | [`docs/multi-agent-architecture.md`](../../docs/multi-agent-architecture.md) |

## Handoff across tools

When stopping mid-task: update `ai-assistant/memory/active-continuation.md` per **AGENTS.md** and summarize for the **next surface** inside the orchestrator/header block another tool understands.
