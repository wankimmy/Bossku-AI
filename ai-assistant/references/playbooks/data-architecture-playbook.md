# Data Architecture Playbook

Use this when reviewing models, migrations, data ownership, warehouses, or analytics pipelines.

## Workflow

1. Read the current schema, models, migrations, and pipeline definitions.
2. Map entity ownership, lifecycle, and derived copies.
3. Review migration safety, backfill needs, and rollback path.
4. Review access patterns, pipeline idempotency, and retention/privacy obligations.
5. Recommend the smallest safe data evolution path.

## Output expectation

- data model summary
- ownership and invariant map
- migration/pipeline risks
- privacy or retention concerns
- recommended rollout path
