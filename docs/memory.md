# Memory System

BosskuAI maintains persistent memory across runs. Memory entries capture facts, decisions, and patterns that are useful context for future tasks. The memory system uses pgvector for semantic search, so the orchestrator can retrieve relevant memories by meaning rather than exact keyword match.

## Memory Entry Structure

Each memory entry stores:

- `content` — the fact or decision text
- `embedding` — a pgvector embedding of the content for semantic search
- `source_run_id` — the run that produced this memory
- `confidence_score` — how confident the system is that this fact is still accurate (0.0–1.0)
- `created_at` / `last_confirmed_at` — timestamps used for staleness detection
- `tags` — optional categorization (e.g. `architecture`, `decision`, `constraint`)
- `conflict_ids` — IDs of other memories that contradict this one

## How Memories Are Created

After each run, `MemoryExtractorService` scans the final review and executor outputs for extractable facts. Patterns that trigger extraction:

- Decisions made during the run ("we decided to use X because Y")
- Constraints discovered ("the API rate limit is 100 req/s")
- Architecture facts established ("the payments module uses Strategy pattern")
- Configuration values confirmed ("staging DB is on port 5433")

Extracted candidates are filtered through a confidence scoring step before being persisted. Low-confidence extractions (below `0.5`) are discarded.

## Semantic Search with pgvector

Memory retrieval uses PostgreSQL's `pgvector` extension. At the start of each run, `OrchestratorService` embeds the user prompt and runs:

```sql
SELECT *, 1 - (embedding <=> $query_embedding) AS similarity
FROM memories
WHERE confidence_score > 0.3
ORDER BY similarity DESC
LIMIT 5;
```

The top 5 results above similarity threshold `0.7` are injected into the run context. This means the model has relevant past decisions in its prompt without you needing to repeat them.

If pgvector is not available (non-Docker setup), memory falls back to SQLite full-text search with lower recall.

## Confidence Scoring

Confidence starts at `0.9` when a memory is first created by a high-quality run (audit score >= 0.7). It degrades over time:

- **Time decay**: confidence decreases by `0.02` per week for facts tagged `configuration` or `constraint` (these change more often), and `0.005` per week for architectural decisions (these are more stable)
- **Contradiction**: if `MemoryConflictDetector` finds a newer memory that contradicts an existing one, the older memory's confidence drops to `0.3`
- **Confirmation**: if a run cites a memory and the final review confirms it was accurate, the memory's confidence is boosted by `0.05` (capped at `1.0`)

## Staleness Detection

Memories are considered stale when:
- `confidence_score < 0.4` AND `last_confirmed_at` is more than 30 days ago

Stale memories are still returned by semantic search but are annotated with a `[stale]` marker in the context injection so the model can treat them with appropriate skepticism. They appear in the **Memory Streams** tab on `/brain` with a yellow indicator.

## MemoryConflictDetector

`MemoryConflictDetector` runs after each new memory is persisted. It:

1. Embeds the new memory
2. Searches for existing memories with embedding similarity above `0.8`
3. For each high-similarity pair, calls a lightweight contradiction-check prompt: "Do these two statements contradict each other? Yes/No and why."
4. If contradiction is confirmed, both memories are updated: `conflict_ids` is populated and both entries are surfaced in the **Conflicts** tab on `/brain`

Conflicts are not auto-resolved. A human must decide which memory is authoritative. Resolving a conflict via the UI sets the winning memory's confidence to `0.9` and archives the loser.

## Linking Memories to Runs

Every memory has a `source_run_id`. On the run detail page, you can see which memories were injected at the start of the run and which new memories were extracted afterward. This bidirectional link means:

- If a run produced a bad outcome, you can inspect whether a bad memory contributed
- If a memory turns out to be wrong, you can find all runs that cited it

## Memory Retention Policy

By default, memories are retained indefinitely. You can configure a retention window in `config/bossku.php`:

```php
'memory' => [
    'retention_days' => null, // null = forever
    'auto_archive_stale_after_days' => 90,
],
```

With `auto_archive_stale_after_days` set, memories that have been stale for the configured period are archived (not deleted) and no longer returned by semantic search.
