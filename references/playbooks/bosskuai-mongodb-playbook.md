# bosskuai-mongodb Full Playbook

Original detailed operating notes moved out of SKILL.md to reduce prompt bloat.

---

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

## MongoDB expert coverage addendum

For MongoDB document model review, cover:

- document shape and bounded aggregate root,
- compound index order for equality, sort, and range fields,
- aggregation pipeline memory and `$lookup` risk,
- schema validation for required fields,
- write concern/read concern for durability,
- unbounded arrays and hot document growth,
- backup and restore test.

---

## Worked anti-patterns and fixes

Each example pairs the wrong-shape with the right-shape and a verification step. These are the patterns that bite Mongo at MVP-and-early-scale stage — not exotic ones.

### 1. Unbounded array on a hot document

**Wrong**

```js
// Each user document grows forever
{
  _id: ObjectId("..."),
  email: "x@y.com",
  loginEvents: [
    { ts: ISODate("..."), ip: "1.2.3.4", ua: "..." },
    // ... 100k+ entries after a year
  ]
}
```

What breaks: documents > 16MB cap → write fails. Even before the cap, every read of `user.email` hauls megabytes of array data through the network. Indexes on the array bloat too.

**Right** — pull events into their own collection:

```js
// users
{ _id: ObjectId("..."), email: "x@y.com" }

// user_login_events
{ _id: ObjectId("..."), userId: ObjectId("..."), ts: ISODate("..."), ip: "...", ua: "..." }
db.user_login_events.createIndex({ userId: 1, ts: -1 })
```

Use the bounded-array pattern only when the array has a hard upper bound (last 50 events, top 10 tags, etc.) and you implement that bound at the application layer with `$slice`:

```js
db.users.updateOne(
  { _id: userId },
  { $push: { recentEvents: { $each: [event], $slice: -50 } } }
)
```

**Verify** — `db.users.aggregate([{ $project: { size: { $bsonSize: "$$ROOT" } } }, { $sort: { size: -1 } }, { $limit: 10 }])`. Top 10 docs should be near the median size, not orders of magnitude larger.

### 2. Compound index column order doesn't match the query

**Wrong**

```js
db.orders.createIndex({ status: 1, tenantId: 1, createdAt: -1 })

// Hot query
db.orders.find({ tenantId: "t_123", createdAt: { $gte: ISODate("2025-01-01") } })
         .sort({ createdAt: -1 })
         .limit(50)
```

The index leads with `status`. Query doesn't filter on `status`. Mongo can't use the index efficiently — a collection scan or partial index use.

**Right** — equality columns first, then range, then sort:

```js
db.orders.dropIndex("status_1_tenantId_1_createdAt_-1")
db.orders.createIndex({ tenantId: 1, createdAt: -1 })
```

Mongo uses the **ESR rule** (Equality, Sort, Range) for compound indexes. `tenantId` is equality, `createdAt` is the sort and range — that order serves both.

**Verify**

```js
db.orders.find({ tenantId: "t_123", createdAt: { $gte: ISODate("2025-01-01") } })
         .sort({ createdAt: -1 }).limit(50).explain("executionStats")
```

Look for `IXSCAN`, `executionTimeMillis` < expected, and `totalDocsExamined ≈ nReturned` (no big scans-then-filter).

### 3. `$lookup` blowing up memory in aggregation

**Wrong**

```js
db.orders.aggregate([
  { $match: { tenantId: "t_123" } },
  { $lookup: { from: "products", localField: "items.productId", foreignField: "_id", as: "products" } },
  { $lookup: { from: "users", localField: "userId", foreignField: "_id", as: "user" } },
  { $lookup: { from: "shipments", localField: "_id", foreignField: "orderId", as: "shipments" } },
  // ...
])
```

What breaks: each `$lookup` materializes joined documents into the pipeline. Three `$lookup`s on a 100k-order tenant = millions of objects in memory. Pipeline crosses 100MB → fails or spills to disk.

**Right rules:**

- Push `$match` as early as possible to reduce document count *before* the lookups.
- Use `pipeline` form of `$lookup` to filter on the joined side too:

```js
{
  $lookup: {
    from: "products",
    let: { ids: "$items.productId" },
    pipeline: [
      { $match: { $expr: { $in: ["$_id", "$$ids"] } } },
      { $project: { name: 1, sku: 1 } }   // only fields we need
    ],
    as: "products"
  }
}
```

- Add `{ allowDiskUse: true }` only as a temporary mitigation; fix the pipeline shape, don't rely on disk.
- Consider denormalization if the join is on every read — a snapshot of `productName` on the order avoids the lookup entirely. Tradeoff: writes must keep snapshots fresh.

**Verify** — `.explain("executionStats")` and look at `executionTimeMillis` and any stage with high `nReturned` that gets filtered down later. Move that filter earlier.

### 4. Schema-less drift across versions

**Wrong** — five years in, the `users` collection has documents with:

```js
{ _id, email, name }                          // v1
{ _id, email, displayName, profile: {} }       // v2
{ _id, email, displayName, profile: { ... } }  // v3 with required fields in profile
{ _id, email, profile: { displayName, ... }}   // v4 — moved displayName
```

Application code becomes a maze of `user.displayName || user.profile?.displayName || user.name`.

**Right rules:**

1. **Explicit `schemaVersion` field** on every document.
2. **Schema validation at the database level** — JSON Schema enforces shape:

```js
db.runCommand({
  collMod: "users",
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["email", "schemaVersion", "createdAt"],
      properties: {
        schemaVersion: { bsonType: "int", minimum: 1 },
        email:         { bsonType: "string", pattern: "^.+@.+$" },
        displayName:   { bsonType: "string" },
      }
    }
  },
  validationLevel: "strict",
  validationAction: "error"
})
```

3. **Lazy migration on read** when full backfill is risky:

```js
function loadUser(id) {
  const u = db.users.findOne({ _id: id });
  if (u.schemaVersion < CURRENT) {
    return migrateInPlace(u);   // returns up-to-date shape, optionally writes back
  }
  return u;
}
```

4. **Eventual full backfill** for query consistency — once 99% of reads have lazily migrated, do the final sweep.

**Verify** — run `db.users.countDocuments({ schemaVersion: { $lt: CURRENT } })`. Number must be non-increasing day over day.

### 5. Wrong write concern on critical writes

**Wrong**

```js
db.orders.insertOne(order)   // default write concern, returns once the primary acknowledges
```

If the primary crashes after acknowledging but before replicating, the order is lost.

**Right** — for financially critical writes:

```js
db.orders.insertOne(order, { writeConcern: { w: "majority", j: true, wtimeout: 5000 } })
```

`w: "majority"` waits for replication to a majority of replica set members. `j: true` waits for the journal commit. `wtimeout` prevents indefinite blocking.

For non-critical, high-volume writes (analytics events), the looser default is the right call — performance matters more than the rare lost event.

**Verify** — kill the primary mid-write in staging. With majority + journal, the next election preserves the write. Without, you'll see lost orders.

### 6. Migration without resumability

**Wrong**

```js
// Backfill: add `tenantId` to every order based on user lookup
db.orders.find({}).forEach(order => {
  const user = db.users.findOne({ _id: order.userId });
  db.orders.updateOne({ _id: order._id }, { $set: { tenantId: user.tenantId } });
});
```

Crashes halfway → no idea where you are. Re-running re-processes everything. On a 10M-doc collection, that's hours.

**Right** — batched, resumable, observable:

```js
const BATCH_SIZE = 1000;
let lastId = ObjectId("000000000000000000000000");

while (true) {
  const batch = db.orders.find({
    _id:      { $gt: lastId },
    tenantId: { $exists: false }       // resumability: skip already-migrated
  }).sort({ _id: 1 }).limit(BATCH_SIZE).toArray();

  if (batch.length === 0) break;

  // Bulk lookup users in one call
  const userIds = [...new Set(batch.map(o => o.userId))];
  const users = db.users.find({ _id: { $in: userIds } }).toArray();
  const userMap = new Map(users.map(u => [u._id.toString(), u]));

  const ops = batch.map(order => ({
    updateOne: {
      filter: { _id: order._id },
      update: { $set: { tenantId: userMap.get(order.userId.toString())?.tenantId } }
    }
  }));

  const result = db.orders.bulkWrite(ops, { ordered: false });
  lastId = batch[batch.length - 1]._id;

  print(`migrated ${result.modifiedCount}, lastId=${lastId}, remaining=...`);
  sleep(100);   // throttle write pressure
}
```

Then add a verification pass:

```js
db.orders.countDocuments({ tenantId: { $exists: false } })   // must be 0
```

### 7. ObjectId vs UUID, and pagination

**Wrong** — using OFFSET-style pagination on a large collection:

```js
db.orders.find({ tenantId: "t_123" }).sort({ createdAt: -1 }).skip(5000).limit(50)
```

`skip` reads and discards 5000 docs. Page 100 is hundreds of times slower than page 1.

**Right** — keyset pagination on a sortable ID:

```js
// Page 1
const page1 = db.orders.find({ tenantId: "t_123" })
                       .sort({ _id: -1 })
                       .limit(50).toArray();
const lastId = page1[page1.length - 1]._id;

// Page 2
db.orders.find({ tenantId: "t_123", _id: { $lt: lastId } })
         .sort({ _id: -1 })
         .limit(50)
```

ObjectId is monotonically-ish increasing (timestamp prefix), so sorting by `_id` is effectively sorting by creation time. For strict time ordering, sort by `createdAt`.

**Verify** — `.explain("executionStats")` for page 1 vs page 100. Both should examine `~50` documents. With `skip`, page 100 examines 5050.

### 8. Atlas / hosted-Mongo specifics

If you're on Atlas (most MVPs are), these are the sharp edges that are NOT obvious from generic Mongo docs:

| Surface | What to know |
|---|---|
| **IP allowlist** | Default is "0.0.0.0/0" if you click through. Lock to your VPS / GitHub Actions IPs. |
| **Connection string** | Includes credentials. NEVER commit. Atlas will rotate keys for you — use that. |
| **Performance Advisor** | Atlas suggests indexes from query patterns. Read it weekly; do not adopt suggestions blindly — they can suggest indexes that duplicate existing ones. |
| **Backup** | Continuous backup on M10+ tier. Test the restore monthly with a forked cluster — having backups ≠ being able to restore. |
| **Tier change** | Scaling up triggers a primary failover. Plan it during a maintenance window even if Atlas calls it "rolling." |
| **Free tier (M0)** | Hard limits on connections, storage, concurrent ops. Production on M0 will fail on the first traffic spike — move to M10+ before launch. |
| **VPC peering** | Required to keep DB traffic off the public internet. Set this up before the first paying customer, not after. |

## Production audit matrix

| Layer       | Check                                                | Tool / command                              |
|-------------|------------------------------------------------------|---------------------------------------------|
| Doc shape   | Top 10 docs are near median size                     | `$bsonSize` aggregation                     |
| Schema      | Validation enabled with `validationAction: error`    | `db.runCommand({ listCollections: 1 })`     |
| Indexes     | Compound indexes follow ESR rule                     | `db.coll.getIndexes()` + query review       |
| Plans       | Hot queries use `IXSCAN`, not `COLLSCAN`             | `.explain("executionStats")`                |
| Aggregation | No pipeline without early `$match`; no naked `$lookup` on big collections | review `db.system.profile` slow ops |
| Pagination  | Keyset on hot lists, not skip                        | code review                                 |
| Write concern | Critical writes use `w: "majority", j: true`       | code review                                 |
| Migration   | Resumable, batched, idempotent                       | migration script review                     |
| Backup      | Restore drill within last 30 days                    | drill log                                   |
| Security    | IP allowlist scoped, VPC peering, MongoDB auth on    | Atlas console / connection string review    |
| Atlas tier  | Not running production on free tier (M0)             | Atlas console                               |

## Output addendum — when SQL is the better answer

Cofounder responsibility: don't recommend Mongo just because the team knows it. Use Mongo when:

- The data is genuinely document-shaped and document boundaries match transaction boundaries.
- You don't need cross-document joins on every read.
- Schema actually evolves frequently in early product.

Use Postgres/MariaDB instead when:

- You have many-to-many relationships that drive most queries.
- You need strong cross-row constraints (foreign keys, unique across joins).
- You'll be doing analytics (window functions, complex GROUP BYs).
- Your team is more comfortable with SQL — operational cost dominates "right tool for the job" at MVP stage.

If unsure, default to relational. The "schemaless wins" argument is usually post-rationalization for skipping schema design, not a real benefit at MVP scale.
