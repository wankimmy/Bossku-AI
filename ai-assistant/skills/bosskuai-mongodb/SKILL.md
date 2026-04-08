---
name: bosskuai-mongodb
description: Use this for MongoDB work including collection design, indexes, aggregation pipelines, query performance, migrations, schema validation, backups, and MongoDB MCP-assisted database inspection.
---

# BosskuAI MongoDB

Use this skill when the task involves **MongoDB data design or operations**: collections, documents, indexes, aggregation pipelines, migrations, backups, Atlas, or MongoDB MCP usage.

## How this differs from nearby skills

- **`bosskuai-data-architecture`**: decides broad data ownership and modeling tradeoffs; this skill applies MongoDB-specific patterns.
- **`bosskuai-performance-profiling`**: profiles general bottlenecks; this skill focuses on MongoDB query plans, indexes, and aggregation behavior.
- **`bosskuai-engineering-delivery`**: implements code changes; this skill designs and verifies MongoDB-specific data work.
- **`bosskuai-cybersecurity-risk`**: audits general security; this skill covers MongoDB access, validation, and operational risks in context.

## Mindset

- MongoDB schema flexibility is a responsibility, not permission to drift.
- Model documents around access patterns and consistency needs.
- Indexes are product infrastructure: they encode which queries are allowed to be fast.
- Aggregation pipelines should be explainable, testable, and bounded.
- Backups and rollback plans are part of migration correctness.

## MCP posture

Use MongoDB MCP or trusted database tooling when available to inspect:

- Database and collection names
- Sample documents and schema shape
- Existing indexes and query plans
- Aggregation explain output
- Atlas cluster status, backups, or performance insights

Treat live data as sensitive. Prefer read-only inspection. Redact PII and secrets from summaries.

## MongoDB lenses

**Document contract**
- Required fields, optional fields, null semantics, schema validation, and versioning.
- Embedded subdocuments, references, and array growth boundaries.

**Query shape**
- Filters, sorts, projections, pagination, collation, and search behavior.
- Whether the index supports the complete query path or only part of it.

**Aggregation**
- Stage ordering, cardinality growth, memory use, `$lookup` cost, and explain output.
- Whether pipeline output is deterministic and covered by tests.

**Operations**
- Backup/restore, replica set health, sharding, access control, connection pooling, and migration observability.

## Verification options

- Use `explain()` for critical reads and aggregations when database access is available.
- Use fixture-backed tests when changing ODM models, validation, or migration behavior.
- Use dry-run counts and batch progress logs before destructive or large backfills.

## Workflow

### Phase 1 - Orient

1. Identify the MongoDB surface: collection design, query optimization, aggregation, migration, validation, backup, or incident.
2. Read application code, ODM models, migrations, schema validation rules, tests, and current query paths.
3. If database access is available, inspect metadata before sampling documents.
4. Confirm environment: local, staging, production, Atlas, self-hosted, replica set, or sharded cluster.

### Phase 2 - Model documents

5. Map primary access patterns: point reads, list pages, search, analytics, writes, and background jobs.
6. Decide embed vs reference based on update frequency, document growth, transaction boundaries, and query shape.
7. Identify invariants that need schema validation, unique indexes, transactions, or app-level checks.
8. Check document size growth and unbounded arrays.

### Phase 3 - Index and query review

9. Match indexes to the actual filters, sorts, projections, and cardinality.
10. Review compound index order and whether queries can use prefix keys.
11. Use explain plans for critical queries when possible.
12. Check for collection scans, memory-heavy sorts, regex risks, and aggregation stages that explode cardinality.

### Phase 4 - Migrations and operations

13. Design migrations as resumable, idempotent, batched, and observable.
14. Plan backfills with write pressure, locks, rollback, and mixed-version app behavior in mind.
15. Verify backup and restore posture before destructive changes.
16. For sharded clusters, check shard keys, chunk distribution, and cross-shard query risks.

## Guardrails

- Do not sample or print sensitive document contents unless necessary and approved.
- Do not recommend dropping indexes or collections without an explicit rollback and production-impact review.
- Do not rely on "schemaless" as a design answer; state the intended document contract.
- Do not add indexes blindly; name the query and expected benefit.
- Do not run production migrations without batching, monitoring, and a stop condition.

## Output format

```text
MongoDB summary:
  Surface: [design / query / aggregation / migration / operations]
  Collections: [names]
  Access patterns: [reads/writes]
  Environment: [local/staging/prod/unknown]

Findings:
  Modeling: [issues / ok]
  Indexes: [issues / ok]
  Queries or pipelines: [issues / ok]
  Migration safety: [issues / ok]
  Security/data handling: [issues / ok]

Recommended path:
  [change] - [why] - [verification] - [rollback]
```

## References

- `../../references/checklists/mongodb-checklist.md`
- `../../references/playbooks/data-architecture-playbook.md`
- `../../references/checklists/security-risk-checklist.md`
