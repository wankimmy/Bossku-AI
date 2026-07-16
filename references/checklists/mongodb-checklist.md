# MongoDB Checklist

> If the request is general, ambiguous, or touches many files, ask clarifying yes/no questions before acting.

- Are collections, document contracts, required fields, null semantics, and versioning clear?
- Are access patterns named before choosing embed vs reference?
- Do indexes match filters, sorts, projections, collation, uniqueness, and pagination paths?
- Are aggregation stages ordered to reduce cardinality and memory pressure early?
- Are migrations resumable, idempotent, batched, observable, and reversible where possible?
- Are backups, restore paths, permissions, and live-data handling reviewed before destructive changes?
- Are sensitive fields redacted from summaries, logs, fixtures, and sampled output?
