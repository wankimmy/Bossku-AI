---
name: bosskuai-database-engineering
description: Use this for SQL and NoSQL database design across MariaDB, MySQL, PostgreSQL, SQLite, MongoDB, indexing, transactions, migrations, constraints, query plans, and data correctness.
---

# BosskuAI Database Engineering

Use this skill when schema, query behavior, indexes, migrations, data consistency, or database choice affects the outcome.

## Database coverage

- MariaDB / MySQL: InnoDB constraints, indexes, generated columns, JSON tradeoffs, online migration risk, replication-aware changes.
- PostgreSQL: constraints, partial indexes, JSONB, GIN/GiST, CTEs, advisory locks, `EXPLAIN ANALYZE`.
- SQLite: local/dev/test constraints, WAL mode, type affinity, concurrency limits, migration compatibility.
- MongoDB: document shape, compound indexes, aggregation pipelines, schema validation, write concern, and bounded document growth.

## Review checklist

- Data model matches the business invariant, not only the current UI.
- Constraints exist in the database for rules that must never be violated.
- Indexes match real `WHERE`, `JOIN`, `ORDER BY`, and uniqueness paths.
- Migrations are reversible or have a safe forward-only rollback plan.
- High-volume changes are chunked and safe for production traffic.
- Query plans are checked before claiming a query is fast.
- Soft deletes, tenancy, status transitions, and audit trails are modeled explicitly.

## Guardrails

- Do not solve missing constraints only in application code.
- Do not add indexes blindly; check selectivity and write cost.
- Do not use MongoDB as a shortcut for unclear relational modeling.
- Do not assume MySQL, MariaDB, PostgreSQL, and SQLite support the same DDL.

## Output format

```text
Database engine: [engine/version if known]
Invariant to protect: [rule]
Schema/query findings:
  P0/P1/P2 — [issue] — [fix]
Migration plan: [safe steps]
Index plan: [index + reason]
Verification: [EXPLAIN/tests/constraints checked]
```

## References

- `../../references/playbooks/bosskuai-database-engineering-playbook.md`
- `../../references/checklists/database-engineering-checklist.md`
- `../../references/checklists/expert-cofounder-stack-checklist.md`
