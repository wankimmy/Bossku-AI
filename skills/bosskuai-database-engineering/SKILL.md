---
name: bosskuai-database-engineering
description: Use this for SQL and NoSQL database design across MariaDB, MySQL, PostgreSQL, SQLite, MongoDB, indexing and index design, query plans, transactions and locking, safe online migrations, constraints, multi-tenant schemas, connection pooling, backups, and data correctness.
---

# BosskuAI Database Engineering

Use this skill when schema, query behavior, indexes, migrations, locking, data consistency, or database choice affects the outcome.

## How this differs from nearby skills

- **`bosskuai-data-architecture`**: entity ownership, warehouses, pipelines, retention; this skill is the operational database underneath.
- **`bosskuai-performance-profiling`**: finds that the database is the bottleneck; this skill fixes the query, index, or schema.
- **`bosskuai-mongodb`** / **`bosskuai-redis-caching-queues`**: engine-specific depth; this skill covers relational engines in depth and MongoDB at design level.
- **`bosskuai-tenant-isolation-security`**: authorization boundaries; this skill provides the schema and index shape that make isolation cheap.
- **`bosskuai-laravel-development`** / **`bosskuai-go-development`**: the application's data-access layer; this skill owns what the engine does with it.

## Mindset

- The invariant lives in the database: constraints and unique indexes, not only application code.
- An index is a write cost and a storage cost paid to make one access path cheap; know which path.
- A plan is evidence; "it should be fast" is not. `EXPLAIN` before and after.
- Migrations run against production traffic; every step must be safe while the old code is still running.
- Test on the production engine: SQLite in tests hides MySQL/PostgreSQL behavior.

## Orient before changing anything

1. Engine and version (`SELECT version()`), and whether tests run on a different engine.
2. Migration tool and history; live schema (`\d+ table`, `SHOW CREATE TABLE`); existing indexes and their usage (`pg_stat_user_indexes`, `sys.schema_unused_indexes`).
3. Top queries by total time (`pg_stat_statements`, slow query log, APM) and the tables they hit.
4. Tenancy model, soft deletes, status columns, audit tables, and any triggers or guards that block bulk updates.

## Index design rules

- Composite order: equality columns first, then the range column, then `ORDER BY` columns; the leftmost prefix is what gets used.
- Cover the hot read (`INCLUDE` in PostgreSQL, extra trailing columns in MySQL) when the query is index-only critical.
- Selectivity matters: an index on `status` with three values rarely helps alone; combine with the selective column or use a partial index (`WHERE status = 'active'`).
- Unique indexes encode invariants, including soft-delete uniqueness (`UNIQUE (email) WHERE deleted_at IS NULL`, or a generated column on MySQL).
- PostgreSQL does not index foreign key columns automatically; MySQL does. Add them or deletes on the parent table lock and scan.
- Multi-tenant: `tenant_id` leads every composite index on tenant-scoped tables; queries always filter by it.
- JSON: index extracted keys (generated column + B-tree) or `jsonb_path_ops` GIN; do not index whole documents by reflex.
- Text search: `pg_trgm` GIN for `LIKE '%x%'`, `tsvector` for full text, `FULLTEXT` on MySQL; `LIKE '%x%'` on a B-tree never uses it.
- Remove unused and duplicate indexes; each one slows every write and bloats vacuum.

## Reading a plan

- Seq/full scan on a large table with a selective filter → missing or unusable index (function on column, type mismatch, leading wildcard).
- Nested loop with a large outer set → missing index on the inner join key or wrong join order from bad statistics; run `ANALYZE`.
- Sort or filesort without an index → add the `ORDER BY` columns to the index or paginate by key.
- Estimated rows far from actual → stale statistics, correlated columns (extended statistics), or parameter sniffing.
- High buffers/temp usage → work memory too small for the sort/hash or the query needs to touch less data.

## Query patterns that scale

- N+1: eager load or batch by IDs; look for query counts per request in the ORM log.
- Pagination: keyset (`WHERE (created_at, id) < (?, ?) ORDER BY created_at DESC, id DESC`) over `OFFSET` beyond a few pages.
- Counts on large tables: cached counters or estimates (`pg_class.reltuples`) for UI badges, exact counts only where money depends on them.
- Queues in the database: `SELECT ... FOR UPDATE SKIP LOCKED` with a bounded batch; separate table from business rows.
- Idempotency: unique key on the idempotency token; upsert (`ON CONFLICT` / `ON DUPLICATE KEY UPDATE`) instead of select-then-insert.
- Deadlocks: acquire locks in a consistent order, keep transactions short, retry on serialization failure.
- Bulk writes: batch of 500–5,000 rows per statement, chunked by primary key ranges, sleep between chunks under load.

## Online migration ladder (expand → migrate → contract)

1. Add nullable column or new table; deploy code that writes both.
2. Backfill in primary-key chunks with a resumable script; never one `UPDATE` on the whole table.
3. Add constraints without locking: PostgreSQL `NOT VALID` then `VALIDATE CONSTRAINT`; `CREATE INDEX CONCURRENTLY` (outside a transaction); MySQL 8 `ALGORITHM=INPLACE` or `INSTANT` for column adds, `gh-ost`/`pt-online-schema-change` for the rest.
4. Switch reads; deploy code that stops writing the old column.
5. Drop the old column or table in a later release, after backups.
- Renames are add + backfill + swap, never `RENAME COLUMN` under traffic.
- Adding a column with a constant default is instant on PostgreSQL 11+ and MySQL 8 `INSTANT`; a volatile default rewrites the table.
- Every migration states its lock impact and its rollback; forward-only migrations need a documented recovery.
- Finance and audit tables with guard triggers: bulk data rebases must disable or route around the guards deliberately, never by dropping constraints.

## Multi-tenancy shapes

| Shape | Pick when | Cost |
|---|---|---|
| Shared schema + `tenant_id` | many small tenants, one codebase | row-level discipline, RLS optional, noisy-neighbor risk |
| Schema per tenant | moderate tenant count, per-tenant migrations acceptable | migration fan-out, connection/pool sprawl, cross-tenant reporting harder |
| Database per tenant | compliance isolation, large tenants | operations multiply; automation mandatory |

Connection pooling: pgbouncer transaction mode breaks prepared statements, advisory locks, and `SET` per session; size pools per service against `max_connections`.

## Operational baseline

- Backups: automated, encrypted, off-box, with point-in-time recovery; restore drill quarterly with the time-to-restore recorded.
- Autovacuum tuned for hot tables; watch dead tuples and bloat; `REINDEX CONCURRENTLY` when needed.
- Slow query log or `pg_stat_statements` on; alert on connections near limit, replica lag, storage headroom, lock waits.
- Time stored in UTC (`timestamptz`); application time zone set once at the edge.
- Least-privilege database users per service; no application user with DDL rights in production.

## Guardrails

- Do not solve missing constraints only in application code.
- Do not add indexes without checking selectivity, write cost, and the plan; do not leave unused ones.
- Do not run a whole-table `UPDATE` or a locking DDL against production without a chunked or online plan.
- Do not use MongoDB as a shortcut for unclear relational modeling.
- Do not assume MySQL, MariaDB, PostgreSQL, and SQLite support the same DDL or locking.
- Do not claim a query is fast without the plan on production-sized data.

## Output format

```text
Database engine: [engine/version] - Tests run on: [same | different engine]
Invariant to protect: [rule]
Schema/query findings:
  P0/P1/P2 — [issue] — [fix]
Index plan: [index + access path it serves + write cost note]
Migration plan: [expand → backfill → contract steps, lock impact, rollback]
Verification: [EXPLAIN before/after, constraints checked, restore drill date]
```

## References

- `../../references/playbooks/bosskuai-database-engineering-playbook.md`
- `../../references/checklists/database-engineering-checklist.md`
- `../../references/checklists/tenant-isolation-security-checklist.md`
