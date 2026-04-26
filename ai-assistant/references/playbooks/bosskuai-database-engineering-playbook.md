# BosskuAI Database Engineering Playbook

## Engine decision guide

- MySQL/MariaDB: common Laravel default; strong transactional relational workload support.
- PostgreSQL: stronger advanced SQL, partial indexes, JSONB, constraints, analytics-friendly queries.
- SQLite: excellent local/dev/test and small embedded workloads; limited high-concurrency write use.
- MongoDB: good for document-shaped data with bounded growth and known query patterns.

## Query review workflow

1. Identify real query shape: filters, joins, order, group, pagination, cardinality.
2. Check schema constraints and indexes.
3. Use `EXPLAIN` / `EXPLAIN ANALYZE` where possible.
4. Fix the data model before adding caches.
5. Validate migration safety for production volume.

## Safe migration pattern

1. Add nullable column or new table.
2. Backfill in chunks.
3. Deploy code that writes both old and new shape if required.
4. Verify consistency.
5. Add constraint / not-null / unique index.
6. Remove old path after confidence window.

## Engine-specific expert notes

### MariaDB / MySQL

- InnoDB foreign keys require matching column types, indexes, and compatible collations.
- Use composite indexes in left-prefix order matching `WHERE` + `ORDER BY` paths.
- MySQL/MariaDB do not support PostgreSQL-style partial indexes. For soft-delete uniqueness, use generated column patterns or another real database invariant.
- Check `EXPLAIN FORMAT=JSON` for access type, rows examined, filesort, temporary tables, and index condition pushdown.
- Avoid online DDL surprises on large tables; choose `ALGORITHM=INPLACE/INSTANT` only when supported by version and operation.

### PostgreSQL

- Use partial unique indexes for conditions like `WHERE deleted_at IS NULL`.
- Use `EXPLAIN (ANALYZE, BUFFERS)` when measuring real query cost.
- Consider `jsonb` only when the query/update shape benefits from document fields; otherwise normalize.
- Use GIN indexes for `jsonb`/array/search cases only when query patterns justify write cost.
- Use advisory locks for cross-process coordination, not as a substitute for constraints.

### SQLite

- Excellent for local/dev/test but not equivalent to MySQL/PostgreSQL DDL behavior.
- Beware type affinity, foreign key enforcement settings, concurrency limits, and partial feature differences.
- Tests using SQLite may miss migration/index/constraint issues that fail in production DBs.

### MongoDB

- Model documents around bounded aggregate roots, not vague “flexibility”.
- Every query path needs a compound index in the same order as equality, sort, then range fields.
- Check aggregation pipeline memory and `$lookup` usage before large production data.
- Avoid unbounded arrays inside hot documents.
- Use schema validation for required fields and data shape.
- Tune write concern/read concern based on durability needs.

## Index review checklist

For each slow or critical query, capture:

```text
Query shape: WHERE / JOIN / ORDER BY / GROUP BY / LIMIT
Cardinality: estimated row count and selectivity
Current index: name + columns + uniqueness
Plan evidence: EXPLAIN output summary
Proposed index: exact DDL + reason
Write cost: affected inserts/updates
Rollback: how to drop safely
```

## Migration safety checklist

- Break destructive changes into expand → backfill → dual-write/read → contract.
- Chunk backfills by primary key and make them restartable.
- Add constraints only after bad data is cleaned.
- Add indexes concurrently/online when supported.
- Include rollback or safe forward-only recovery.
- Treat migration safety as a deployment concern, not only a schema concern.
