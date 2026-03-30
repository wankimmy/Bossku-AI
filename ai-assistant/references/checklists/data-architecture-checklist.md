# Data Architecture Checklist

- Who owns each entity, field, and source of truth?
- Are model boundaries, invariants, and derived fields explicit?
- Can schema changes roll out additively with backfill and rollback plans?
- Do indexes, partitions, and query paths match the real access patterns?
- Are analytics/event pipelines idempotent, replay-safe, and clear about grain?
- Are retention, deletion, archival, and privacy obligations reflected in the design?
- What mixed-version or dual-write risks exist during migration?
