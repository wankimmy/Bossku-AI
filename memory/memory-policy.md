# Memory policy

## Must never store

- API keys, tokens, passwords
- Raw production secrets (.env blobs, connection strings with credentials)
- Private user data unless the user explicitly approves retention and minimization

Store **patterns** (“rotate key X”), not **values**.

## Retrieval policy

1. Query vector memory **only when** it reduces ambiguity or encodes durable decisions relevant to this task.
2. If retrieval returns noise, discard — do **not** treat hits as authoritative without file evidence.
3. Cap injected memory to **short bullets**; summarize long runs first (`summarizer.md`).

## Write policy

- Write after **durable** plans, **decisions**, **verified learnings**, or **explicit handoffs**.
- Do not log trivial chat.
- Prefer **one fact per memory line** — easier dedup + merge later.

## Deduplication

- Canonicalize wording before insert (lowercase verbs, stable nouns).
- If similarity ≥ project threshold → **merge** summaries and bump `updated_at`.

## Deletion / export

- Users must **delete** mistaken or stale rows (CLI or UI once built).
- **Export** portable JSON/LD + markdown for portability (`readable-memory-format.md`).

## Cross-links

- Handoff protocol: [`../ai-assistant/references/memory-first-handoff-protocol.md`](../ai-assistant/references/memory-first-handoff-protocol.md)
- CLI: [`../ai-assistant/scripts/auto_memory.py`](../ai-assistant/scripts/auto_memory.py)
