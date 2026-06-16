# BosskuAI for Claude Code

Use [`AGENTS.md`](AGENTS.md) as the canonical cross-tool contract. This file keeps Claude-specific deltas only.

## Mandatory indicator

Every response must begin with:

```text
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|final-reviewer>
Model Role: <planner|coder|reviewer|researcher>
Memory Used: <yes|no>
```

## Model mapping

For meaningful tasks, use the phase split Claude exposes (adapt names to your subscription):

| Phase | Default model | Purpose |
|---|---|---|
| Plan / orchestrate | `claude-opus-4-7` | Decompose task, inspect risks, choose approach, decide tests |
| Execute | `claude-sonnet-4-6` | Implement straightforward edits and mechanical changes |
| Audit / final review | `claude-opus-4-7` | Review diff, security/business logic, verification gaps |

This mirrors BosskuAI’s orchestrator→executor→auditor semantics. The **Docker / Laravel orchestrator** uses `app/config/bossku_models.php` for OpenAI/other providers — see [`agents/model-router.md`](agents/model-router.md).

Escalate execution to Opus when the task touches payments, auth, secrets, privacy, migrations, data loss, multi-service architecture, or repeated failed attempts.

Trivial tasks may skip the phase split (still show the indicator).

## Claude defaults

- For Claude Opus 4.7 API usage, prefer adaptive thinking / effort controls where available; do not assume manual extended-thinking budgets are accepted.
- Load the minimum relevant BosskuAI skill set from [`skill-index.json`](skill-index.json).
- **Ponytail (lazy senior dev) is ON by default** — simplest thing that works: YAGNI → stdlib → native → installed dep → one line → minimum code; deletion over addition, fewest files. Never lazy about validation, security, accessibility, data-loss, or the one runnable check. Off: "stop ponytail". Skill: [`ai-assistant/skills/bosskuai-ponytail/SKILL.md`](ai-assistant/skills/bosskuai-ponytail/SKILL.md).
- **Anti-slop is ON by default** — no generic placeholders (Jane Doe/Acme), no filler verbs (Elevate/Seamless/Unleash), no fake-perfect numbers, no em-dash decoration. Any frontend/UI/design generation: load [`bosskuai-taste`](ai-assistant/skills/bosskuai-taste/SKILL.md) first (Design Read, reach past LLM defaults, real design systems + real images, pre-flight check).
- Use `bosskuai-permanent-memory-orchestration` when the task involves memory, vector DB, model routing, or cross-tool context.
- Ask clarification questions before broad multi-file changes when scope is unclear.
- Use normal prose when clarity matters; terse output is fine when the task is straightforward.
- Deep multi-agent flows: see [`commands/`](commands/) and [`docs/multi-agent-architecture.md`](docs/multi-agent-architecture.md).

## Shared memory

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
