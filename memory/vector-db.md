# Vector DB options

BosskuAI supports **semantic retrieval** for narrowing context. Pick one backbone and keep metadata beside it.

## Recommended setups

| Mode | Store | Typical use |
|---|---|---|
| Local dev | **ChromaDB** (persisted volume) | Fast iterations, notebooks, prototypes |
| Self-hosted prod | **Qdrant** | Scale, ACL at gateway, clustering |
| Metadata + hybrid | **SQLite** (local) / **PostgreSQL** (teams) | Source, tags, importance, TTL, audit trails |

Today’s repo-backed flows often use **`semantic-memory.sqlite3`** + embeddings through `auto_memory.py`, and the **Docker MVP** uses **Postgres + pgvector** for imported SKILL/playbook embeddings. Align new deployments with whichever surface is canonical for *your* workspace.

## Retrieval contract

1. **Query first** with a short task phrase (top-k 3–8).
2. **Inject summaries**, not raw dumps, into prompts.
3. **Re-rank** by recency + importance when metadata exists.
4. **Deduplicate** near-identical summaries (`memory-policy.md`).

## What not to embed

Never index secrets (`memory-policy.md`).

## Operational notes

- Back up the metadata DB and vector store together when possible.
- For multi-user teams, isolate namespaces per repo or tenant at the ingestion layer.
