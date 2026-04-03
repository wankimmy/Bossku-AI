---
name: bosskuai-performance-profiling
description: Use this for CPU/memory profiling, bottleneck diagnosis, query optimization, caching strategy, flame graph reading, and turning performance intuition into evidence-backed fixes.
---

# BosskuAI Performance Profiling

Use this skill when the main question is **why is this slow, why is memory growing, or where is the bottleneck** — not just "make this faster" in the abstract.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: delivers a feature or fix with a quality workflow; this skill is the diagnostic and optimization loop that precedes or wraps that delivery when performance is the concern.
- **`bosskuai-coding-best-practices`**: improves code quality and readability; this skill specifically targets runtime efficiency with profiler-guided evidence.
- **`bosskuai-software-architecture`**: evaluates structural scalability tradeoffs; this skill measures actual runtime behavior before recommending structural changes.
- **`bosskuai-data-architecture`**: designs schemas and pipelines; load alongside this skill when query plans or index gaps are the bottleneck.

## Mindset

- Never optimize without a measurement. Intuition about bottlenecks is usually wrong.
- The bottleneck is almost never where you think it is until a profiler proves it.
- Fix the root cause, not the symptom — a slow query fixed with a cache is still a slow query.
- Every optimization has a cost: complexity, staleness risk, memory trade-off. Name it.

## Profiling lenses

**CPU profiling**
- Where is wall-clock time actually spent?
- Is time in user code, I/O wait, or kernel calls?
- Are there hot loops that can be vectorized, batched, or pre-computed?

**Memory profiling**
- Is heap growth unbounded or bounded?
- Are objects retained past their useful lifetime (reference leaks)?
- Are allocations concentrated in one code path?

**Query and database performance**
- What does the query plan say? Is a sequential scan happening where an index is expected?
- Are N+1 query patterns present?
- Are connection pool limits or lock contention visible?

**Caching strategy**
- Is the expensive computation or I/O cacheable? What is the invalidation contract?
- Is the current cache hit rate measurable?
- Is the cache introducing stale data risk without a clear TTL or eviction policy?

**Network and I/O**
- Are sequential remote calls that could be batched or parallelized?
- Are payload sizes or serialization costs significant?
- Are timeouts set correctly, or is slow tail latency masking a partial failure?

**Concurrency**
- Are there lock contention points visible under load?
- Is thread/goroutine/async task count growing with load?
- Are background jobs competing with request-path work for the same resources?

## Workflow

1. **Define the performance target first** — Latency p50/p99? Throughput (requests/sec)? Memory ceiling? Cache hit rate? Without a target, there is no done.

2. **Reproduce the slow case** — Create a reproducible scenario: load test, benchmark, specific query, or trace. If it cannot be reproduced, profiling output is noise.

3. **Collect profiler evidence** — Choose the right profiler for the stack:
   - Node.js: `--prof` V8 profiler, Clinic.js, or `0x` for flame graphs
   - Python: `cProfile`, `py-spy` (for production-safe sampling), `memory_profiler`
   - Go: `pprof` (CPU + memory + goroutine traces)
   - JVM: async-profiler, Java Flight Recorder
   - SQL: `EXPLAIN ANALYZE`, `pg_stat_statements` (Postgres), slow query log (MySQL)
   - General: OpenTelemetry spans with timing breakdowns

4. **Read the flame graph** — Identify the widest bars. These are the actual hot paths. Ignore narrow bars — they are not the bottleneck regardless of how ugly the code looks.

5. **Form a bottleneck hypothesis** — Name the candidate: slow query, N+1, lock contention, large allocation loop, network call in hot path, or missing cache. State the evidence from the profiler output.

6. **Fix the root cause** — Apply the smallest targeted fix:
   - N+1 → eager load or batch fetch
   - Missing index → add index, verify plan changes
   - Hot loop allocation → object pool, pre-compute, or reduce allocation frequency
   - Sequential I/O → batch or parallelize
   - Hot recomputation → memoize or cache with explicit TTL
   - Lock contention → reduce critical section scope or use non-blocking data structure

7. **Measure after the fix** — Re-run the same profiler scenario. Confirm the bottleneck is gone and no regression was introduced. State the before/after delta.

8. **Name the trade-off** — Every optimization introduces a cost. Document: complexity added, staleness risk if caching, memory overhead if pre-computing, maintenance burden of the new pattern.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not recommend an optimization without profiler evidence. "This looks slow" is not a bottleneck diagnosis.
- Do not add a cache before confirming the underlying operation is actually the bottleneck.
- Do not optimize code that is not on the critical path — premature optimization adds complexity with no benefit.
- Do not treat a p50 improvement as success if p99 is still broken for users.

## Output format

```text
Performance target:
  Metric: [latency / throughput / memory / cache hit rate]
  Current baseline: [measured value]
  Target: [goal value]

Bottleneck diagnosis:
  Profiler used: [tool and method]
  Hot path identified: [function / query / call chain]
  Evidence: [flame graph reading / query plan / trace summary]
  Hypothesis: [root cause]

Fix applied:
  Change: [description]
  Type: [index / batch / cache / parallelism / allocation reduction / other]
  Trade-off: [complexity / staleness / memory / maintenance]

Before / after:
  Before: [measured value]
  After: [measured value]
  Delta: [improvement %]

Residual risks:
  [anything not verified, regressions to watch, load scenarios not tested]
```

## References

- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/data-architecture-checklist.md`
