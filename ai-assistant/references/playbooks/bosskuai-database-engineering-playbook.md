# BosskuAI Database Engineering Playbook

Senior-level reference for schema design, indexing, and migration safety across MariaDB, MySQL, PostgreSQL, and SQLite. Each section pairs the wrong-way pattern with the right-way fix and the verification step that proves it.

## Audit flow

1. Read the migration history (`migrations/`), the live schema (`SHOW CREATE TABLE`, `\d+` in psql, `.schema` in sqlite3), and the slow query log.
2. List the top 10 most-hit queries (from query log or APM) and run `EXPLAIN`/`EXPLAIN ANALYZE` on each.
3. Check constraints: foreign keys, unique indexes, soft-delete uniqueness, NOT NULL, CHECK.
4. Inspect index size vs table size; oversized indexes signal duplication.
5. Verify with a representative-data benchmark before/after each change.

## Cross-driver one-liner reality check

| Feature                       | MariaDB     | MySQL 8     | PostgreSQL  | SQLite      |
|-------------------------------|-------------|-------------|-------------|-------------|
| Partial unique indexes        | via gen col | via gen col | native      | native      |
| Generated/virtual columns     | yes         | yes         | yes         | yes         |
| Native `JSON` indexing        | computed    | computed    | GIN/jsonb_path_ops | extracted col + index |
| `RETURNING` in DML            | 10.5+       | no          | yes         | yes         |
| Concurrent index build        | online DDL  | online DDL  | `CONCURRENTLY` | partial    |
| Window functions              | 10.2+       | 8.0+        | yes         | 3.25+       |
| Online schema change tools    | gh-ost / pt-osc | gh-ost / pt-osc | pg_repack | n/a (small DBs) |

Test runs on SQLite by default in many Laravel/Rails projects. **Anything that depends on driver-specific behavior must also be tested on the production driver.**

## Recommended commands

```sql
-- PostgreSQL
EXPLAIN (ANALYZE, BUFFERS, VERBOSE) SELECT ...;
SELECT relname, n_live_tup, n_dead_tup FROM pg_stat_user_tables ORDER BY n_dead_tup DESC LIMIT 20;
SELECT * FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 20;

-- MySQL / MariaDB
EXPLAIN FORMAT=JSON SELECT ...;
SELECT * FROM information_schema.statistics WHERE table_schema = 'app';
SHOW ENGINE INNODB STATUS\G

-- SQLite (tests)
EXPLAIN QUERY PLAN SELECT ...;
PRAGMA index_list('users');
```

---

## Worked anti-patterns and fixes

### 1. Composite index column order doesn't match the query

**Wrong**

```sql
-- Index
CREATE INDEX idx_orders_status_tenant ON orders(status, tenant_id);

-- Hot query
SELECT * FROM orders WHERE tenant_id = 7 AND created_at > '2025-01-01' ORDER BY created_at DESC LIMIT 50;
```

The index leads with `status` but the query filters by `tenant_id` first. The DB falls back to a scan or uses the index inefficiently.

**Right** — index column order should match the query's filter+sort order:

```sql
-- Drop the wrong one, create the right one
DROP INDEX idx_orders_status_tenant ON orders;
CREATE INDEX idx_orders_tenant_created
  ON orders (tenant_id, created_at DESC);
```

Rule of thumb (B-tree): equality columns first, then range, then sort. Columns used only in `SELECT` go nowhere in the index unless you want a covering index.

**Verify** — `EXPLAIN ANALYZE` (Postgres) or `EXPLAIN FORMAT=JSON` (MySQL). The plan should show `Index Scan` (or `ref` in MySQL `type`) and an `Index Cond` matching all your WHERE columns.

### 2. Soft-delete uniqueness — driver matrix

A unique index on `email` blocks recreating a deleted user. The fix differs per driver.

**PostgreSQL — partial index (clean):**

```sql
CREATE UNIQUE INDEX users_email_active_unique
  ON users(email) WHERE deleted_at IS NULL;
```

**SQLite — partial index (same syntax):**

```sql
CREATE UNIQUE INDEX users_email_active_unique
  ON users(email) WHERE deleted_at IS NULL;
```

**MySQL 8 / MariaDB — generated column + unique:**

```sql
ALTER TABLE users
  ADD COLUMN email_active VARCHAR(255)
    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) VIRTUAL,
  ADD UNIQUE KEY users_email_active_unique (email_active);
```

**Verify** — for each driver:

```sql
INSERT INTO users(email, deleted_at) VALUES ('a@x.com', NOW());
INSERT INTO users(email, deleted_at) VALUES ('a@x.com', NULL);   -- must succeed
INSERT INTO users(email, deleted_at) VALUES ('a@x.com', NULL);   -- must fail
```

### 3. Migration that locks the production table

**Wrong**

```sql
ALTER TABLE orders ADD COLUMN tax_cents BIGINT NOT NULL DEFAULT 0;
```

On MySQL 5.x or with certain storage engines, this rewrites the entire table while holding a metadata lock. On a 50M-row table this takes 30 minutes; the app is down for 30 minutes.

**Right — Postgres ≥ 11 / MySQL 8:** `ADD COLUMN ... DEFAULT` is metadata-only:

```sql
ALTER TABLE orders ADD COLUMN tax_cents BIGINT NOT NULL DEFAULT 0;
-- Verify it's instant by EXPLAIN-ing the catalog change time
```

**Right — older versions, or large NOT NULL backfills:**

```sql
-- Step 1: nullable column, no rewrite
ALTER TABLE orders ADD COLUMN tax_cents BIGINT NULL;

-- Step 2: backfill in batches, low-priority
UPDATE orders SET tax_cents = 0 WHERE tax_cents IS NULL AND id BETWEEN 1 AND 10000;
-- repeat in chunks; sleep between batches

-- Step 3: enforce NOT NULL after backfill
ALTER TABLE orders ALTER COLUMN tax_cents SET NOT NULL;

-- Step 4 (Postgres): add a NOT VALID FK or CHECK, then VALIDATE
ALTER TABLE orders ADD CONSTRAINT orders_tax_nonneg CHECK (tax_cents >= 0) NOT VALID;
ALTER TABLE orders VALIDATE CONSTRAINT orders_tax_nonneg;
```

For online schema changes on huge MySQL/MariaDB tables, use `gh-ost` or `pt-online-schema-change`.

**Verify** — staging: run the migration on a copy of production data with concurrent writes. Time it. Confirm no replication lag spike.

### 4. EXPLAIN read pitfalls

Reading EXPLAIN tells you what the planner thinks. EXPLAIN ANALYZE tells you what actually happened.

**Wrong** — trust EXPLAIN cost numbers across queries. They're only comparable for the same query under the same stats.

**Right — what to actually look for:**

| Symptom in plan                      | Likely cause                                  | Fix                                       |
|--------------------------------------|----------------------------------------------|-------------------------------------------|
| `Seq Scan` on a large table          | No index, or index can't be used due to function on column | Add index; rewrite query to be sargable |
| `Index Scan` but `Filter:` removes most rows | Index leads to too many tuples | Add filtered/partial index, or composite |
| `rows=` in EXPLAIN ≠ `actual rows=` (large skew) | Stale stats | `ANALYZE table` or autovacuum tuning |
| `Hash Join` with high `Hash Cond` cost | Wrong join order | Add index on join key, or restructure |
| `Bitmap Heap Scan` with high `recheck_cond` | Many false positives | Tighten predicate; cluster table |
| `Sort` with `Sort Method: external merge` | Sort exceeded `work_mem` | Increase `work_mem` for the session, or pre-sort with index |

For MySQL, watch for `type: ALL` (full scan), missing `key`, and `Using filesort` / `Using temporary`.

**Verify** — pre-change EXPLAIN ANALYZE → make change → post-change EXPLAIN ANALYZE on the same data. Wall time, not cost, is the metric.

### 5. JSON column with no usable index

**Wrong**

```sql
SELECT * FROM events WHERE payload->>'order_id' = '12345';
```

Without an index on the extracted path, this scans every row.

**Postgres — GIN on jsonb_path_ops:**

```sql
ALTER TABLE events ALTER COLUMN payload TYPE jsonb USING payload::jsonb;
CREATE INDEX events_payload_idx ON events USING GIN (payload jsonb_path_ops);
```

Or a targeted expression index for one frequent path:

```sql
CREATE INDEX events_order_id_idx ON events ((payload->>'order_id'));
```

**MySQL/MariaDB — generated column + index:**

```sql
ALTER TABLE events
  ADD COLUMN order_id VARCHAR(64) GENERATED ALWAYS AS (JSON_UNQUOTE(payload->'$.order_id')) STORED,
  ADD INDEX events_order_id_idx (order_id);
```

**Verify** — `EXPLAIN` on the lookup query. Plan should use the new index, not a sequential scan.

### 6. Foreign keys with no `ON DELETE` policy

**Wrong**

```sql
CREATE TABLE invoices (
  id BIGSERIAL PRIMARY KEY,
  customer_id BIGINT REFERENCES customers(id)
);
```

Default `ON DELETE NO ACTION` blocks customer deletion forever. Adding `ON DELETE CASCADE` blindly might silently delete an audit trail you legally need to keep.

**Right** — match the rule to the business requirement, document it in the migration:

```sql
-- Cascade is fine for child detail rows that have no meaning without the parent
ALTER TABLE invoice_lines
  ADD CONSTRAINT invoice_lines_invoice_id_fk
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE;

-- Restrict (default) is right for FK that protects business records
ALTER TABLE invoices
  ADD CONSTRAINT invoices_customer_id_fk
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT;

-- Set NULL is right when the relationship is informational, not structural
ALTER TABLE orders
  ADD CONSTRAINT orders_promo_id_fk
  FOREIGN KEY (promo_id) REFERENCES promos(id) ON DELETE SET NULL;
```

**Verify** — write tests that delete a parent and assert the documented cascade/restrict/null behavior.

### 7. UUIDv4 primary key on a hot table

**Wrong**

```sql
CREATE TABLE events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),     -- v4: random
  ...
);
```

Random UUIDs scatter inserts across the B-tree → page splits, write amplification, worse cache locality. On a high-write table you'll see the WAL/redo size explode.

**Right** — sortable IDs (ULID, UUIDv7, snowflake) so inserts are append-mostly:

```sql
-- Postgres 17+ supports uuidv7() natively; for now use a function or extension.
CREATE TABLE events (
  id UUID PRIMARY KEY DEFAULT uuidv7(),
  ...
);
```

Or for many web apps, a `BIGSERIAL` / `BIGINT` autoincrement is still the right answer; only switch to UUIDs when you need offline-generated IDs or external sharding.

**Verify** — measure insert throughput and B-tree depth before/after. Random UUIDs typically halve insert throughput on big tables compared to sequential IDs.

### 8. Counter caches that drift

**Wrong** — denormalized counter on the parent that the app updates by hand:

```php
$post->update(['comments_count' => $post->comments_count + 1]);   // race
```

Concurrent requests lose increments.

**Right A — atomic in SQL:**

```php
DB::table('posts')->where('id', $postId)->increment('comments_count');
```

**Right B — derive on read with `withCount`** when the count is small or rare:

```php
Post::withCount('comments')->find($postId);
```

**Right C — materialized view / triggers** when the count is read-heavy and the source is well-defined:

```sql
-- Postgres trigger that maintains the counter atomically
CREATE FUNCTION posts_bump_count() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF (TG_OP = 'INSERT') THEN
    UPDATE posts SET comments_count = comments_count + 1 WHERE id = NEW.post_id;
  ELSIF (TG_OP = 'DELETE') THEN
    UPDATE posts SET comments_count = comments_count - 1 WHERE id = OLD.post_id;
  END IF;
  RETURN NULL;
END $$;
CREATE TRIGGER comments_count_trg AFTER INSERT OR DELETE ON comments
  FOR EACH ROW EXECUTE FUNCTION posts_bump_count();
```

Add a periodic reconciliation job that recomputes the truth and alerts on drift.

**Verify** — load test concurrent inserts; final count must match `SELECT count(*)` from the source table.

### 9. Index bloat / duplicate indexes

**Wrong** — the schema accumulates indexes over years; nobody removes the ones that the new composites cover.

**Right — periodic audit:**

**Postgres:**

```sql
SELECT schemaname, relname, indexrelname, idx_scan, idx_tup_read,
       pg_size_pretty(pg_relation_size(indexrelid)) AS size
FROM pg_stat_user_indexes
ORDER BY idx_scan ASC, pg_relation_size(indexrelid) DESC
LIMIT 30;
```

Indexes with `idx_scan = 0` over a meaningful window (one full business cycle) are candidates for removal.

**MySQL:**

```sql
SELECT * FROM sys.schema_unused_indexes;
```

Drop with `ALGORITHM=INPLACE, LOCK=NONE` (MySQL) or `DROP INDEX CONCURRENTLY` (Postgres).

**Verify** — drop on staging, re-run the query workload, confirm no plan regressions before doing it in prod.

### 10. Migration safety in a Laravel project

**Wrong** — single migration that drops a column the running app still reads:

```php
Schema::table('users', function ($t) { $t->dropColumn('legacy_field'); });
```

Deploy → workers still on old code → exceptions.

**Right** — multi-step rollout:

1. **Release N**: stop reading the column, ship.
2. **Release N+1**: stop writing the column, ship.
3. **Release N+2**: drop the column.

Same pattern for renames (add new, dual-write, backfill, switch reads, drop old).

**Verify** — between steps, run an extended canary period. The column drop never coincides with the code change that stops using it.

---

## Production audit matrix

| Layer       | Check                                                | Tool / command                              |
|-------------|------------------------------------------------------|---------------------------------------------|
| Hot queries | EXPLAIN ANALYZE on top 10                            | slow log + APM                              |
| Indexes     | Composite indexes ordered to match WHERE+ORDER       | schema review                               |
| Bloat       | No unused index above N MB                           | `pg_stat_user_indexes` / `sys.schema_unused_indexes` |
| Stats       | Auto-vacuum / auto-analyze healthy                   | `pg_stat_user_tables.last_autoanalyze`      |
| Constraints | FK, NOT NULL, unique present where business requires | schema review                               |
| Soft delete | Uniqueness works on production driver, not just SQLite | per-driver insert test                    |
| Migration   | Online-safe; no full table rewrite under lock        | staging timing                              |
| Backups     | Restored monthly, not just taken                     | restore drill log                           |
| Drift       | Counters reconcile to source-of-truth                | nightly job + alert                         |
| Multi-driver| Tests run against production driver in CI            | CI matrix                                   |

## Output expectation

When auditing, return:

1. **Findings table** — table.column or query, severity, plan evidence, fix.
2. **Smallest fix sequence** — minimum P0/P1 set.
3. **Verification** — exact EXPLAIN, before/after timing, or per-driver test.
4. **De-scope** — what is intentionally not touched yet, and why.

## Further reading

- `bosskuai-laravel-development-playbook.md` — soft-delete, transactions, migration safety in framework context.
- `bosskuai-mongodb-playbook.md` — document model decisions for the cases SQL is the wrong tool.
- `bosskuai-redis-caching-queues-playbook.md` — when to cache vs add an index.
