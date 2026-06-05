# Memory schema (BosskuAI)

BosskuAI memory appears in multiple **surfaces** today:

| Surface | Purpose |
|---|---|
| `ai-assistant/memory/*.md` | Human-readable durable logs |
| `ai-assistant/scripts/auto_memory.py` + SQLite / vectors | Retrieval + embeddings |
| Docker MVP Postgres + pgvector (`app/`) | Imported markdown knowledge + run telemetry |

Use this JSON-shaped record as the **canonical exchange format** across tools and future UIs (`ui/memory-viewer-spec.md`). Timestamps should be ISO-8601 in UTC unless the project dictates otherwise.

## Record shape

```json
{
  "id": "memory_001",
  "type": "project_decision",
  "project": "bossku-ai",
  "summary": "Use Kimi K2.6 as default executor and GPT-5.5 as orchestrator.",
  "source": "user_instruction",
  "tags": ["model-routing", "architecture"],
  "created_at": "auto",
  "updated_at": "auto",
  "importance": "high"
}
```

### Fields

| Field | Required | Notes |
|---|---|---|
| `id` | yes | Stable unique id (`memory_*`, UUID, etc.) |
| `type` | yes | Align with UI memory types (`memory-policy.md`) |
| `project` | recommended | Repo or product key |
| `summary` | yes | Compact human-readable line(s) |
| `source` | yes | `user_instruction`, `agent`, `audit`, `import`, … |
| `tags` | optional | Search facets |
| `created_at`, `updated_at` | yes | Prefer server-set **auto** in apps |
| `importance` | optional | `low` / `medium` / `high` |

### Types (recommended enum)

Mirror product UI:

- `user_preference`
- `project_rule`
- `architecture_decision`
- `code_standard`
- `bug_history`
- `deployment_note`
- `session_summary`
- `skill_knowledge`

### `auto_memory.py` kind to schema `type` (bridge)

Documentation-only mapping until a UI normalizes rows. CLI kinds come from `remember --kind …` in [`../ai-assistant/scripts/auto_memory.py`](../ai-assistant/scripts/auto_memory.py).

| `auto_memory` `--kind` | Suggested schema `type` | Notes |
|---|---|---|
| `durable` | `architecture_decision` or `project_rule` | Stable decisions / constraints |
| `plan` | `session_summary` | Compact plan for the turn |
| `learning` | `skill_knowledge` or `bug_history` | Use tags to disambiguate |
| `bug` | `bug_history` | Defect patterns |
| `market` | `skill_knowledge` | Positioning / competitive notes |
| `continuation` | `session_summary` | Handoff state |

Older one-line references to CLI kinds in this doc still apply; new UIs should project through this table.

## Implementation pointers

- Read/write protocols: [`../ai-assistant/references/memory-first-handoff-protocol.md`](../ai-assistant/references/memory-first-handoff-protocol.md)
- CLI: [`../ai-assistant/scripts/auto_memory.py`](../ai-assistant/scripts/auto_memory.py)
