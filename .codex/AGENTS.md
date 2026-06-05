# BosskuAI for Codex

Use [`../AGENTS.md`](../AGENTS.md) as the canonical cross-tool contract. This file keeps Codex-specific deltas only.

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

Map Codex’s UI to BosskuAI phases (use whatever models your org enables):

| Phase | Typical Codex mapping | Purpose |
|---|---|---|
| Plan / orchestrate | Strong reasoning / planner-capable agent | Scope, risks, tests, target files |
| Execute | Faster / coding-oriented agent | Diffs and implementation |
| Audit / review | Strong reasoning reviewer | Correctness, security, gaps |

Suggested alignments when available: GPT-5.5-class for planning and final review; Kimi K2.6-class for execution — see [`agents/model-router.md`](../agents/model-router.md) and [`app/config/bossku_models.php`](../app/config/bossku_models.php).

Trivial tasks may skip the phase split (still show the indicator).

## Codex defaults

- Load the minimum relevant BosskuAI skill set from [`../skill-index.json`](../skill-index.json).
- Ask 1-3 clarification questions before broad multi-file changes when scope is unclear.
- Read code and nearby docs before making repo-specific claims.
- Prefer [`../packages/bossku-ai/skills/bossku-ai/SKILL.md`](../packages/bossku-ai/skills/bossku-ai/SKILL.md) as the slim Codex entrypoint when appropriate.

## Shared memory

- Read [`../ai-assistant/memory/active-continuation.md`](../ai-assistant/memory/active-continuation.md) first when it contains live work.
- If [`../ai-assistant/memory/semantic-memory.sqlite3`](../ai-assistant/memory/semantic-memory.sqlite3) exists, query it before opening broad memory files.
- Follow [`../ai-assistant/references/memory-first-handoff-protocol.md`](../ai-assistant/references/memory-first-handoff-protocol.md) for durable writes.

## References

- [`../AGENTS.md`](../AGENTS.md)
- [`../ai-assistant/references/workspace-layer-architecture.md`](../ai-assistant/references/workspace-layer-architecture.md)
- [`../agents/model-router.md`](../agents/model-router.md)

## Obsidian session log (mandatory at session end)

After completing any meaningful task, append **one JSON line** to the journal file.
This is the only required action — a background watcher syncs it to Obsidian automatically.

**PowerShell (preferred):**
```powershell
Add-Content "$env:USERPROFILE\.cursor\obsidian-journal.jsonl" ('{"tool":"Cursor","project":"Bossku-AI","prompt":"FIRST_PROMPT","summary":"ONE_LINE_SUMMARY","ts":"' + (Get-Date -Format 'o') + '"}')
```

**Rules:**
- Replace `Bossku-AI` with the repo folder name (e.g. `festivent-app`, `Bossku-AI`, `iman-quest`)
- Replace `FIRST_PROMPT` with the user's first message (max 300 chars, escape inner quotes with `\"`)
- Replace `ONE_LINE_SUMMARY` with a one-line description of what was done
- Run **once per session** at the very end — not after every reply
- Skip trivial sessions (single lookups, typo fixes, one-liner answers)