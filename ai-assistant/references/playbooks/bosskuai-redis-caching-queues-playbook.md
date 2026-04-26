# BosskuAI Redis Caching and Queues Playbook

## Cache decision tree

1. Is the query actually slow after indexing? If no, do not cache.
2. Is the response shared or user/tenant-specific? Scope the key.
3. Is stale data acceptable? Choose TTL.
4. Can writes invalidate the cache? Define invalidation event.
5. Can a stampede happen? Use locks or `remember` patterns carefully.

## Queue review flow

- Job payload size and serialization safety.
- Idempotency key or natural uniqueness.
- Timeout vs retry_after alignment.
- Backoff strategy for third-party APIs.
- Failed job alerting and replay plan.
- Worker restart during deploy.

## Laravel checks

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan horizon:status  # when Horizon is used
redis-cli INFO memory
redis-cli SLOWLOG GET 10
```

## Expert Redis/Laravel operations

### Cache correctness

Define these before caching:

```text
Source of truth: [DB/API/file]
Key shape: [tenant:user:resource:version]
TTL: [seconds + reason]
Invalidation event: [write/update/delete/job]
Staleness tolerance: [acceptable / unacceptable]
Stampede protection: [lock / warmup / stale-while-revalidate]
```

### Queue correctness

- `timeout` must be lower than `retry_after`.
- Use `tries` and `backoff` based on external dependency behavior.
- Jobs touching third-party APIs need idempotency keys.
- Jobs mutating DB state need transactions and duplicate detection.
- Long jobs should chunk work and record progress.
- Failed jobs need alerting, triage owner, and replay command.

### Worker deployment

- Run workers as separate supervised containers/processes.
- Restart workers after deployment so they pick up new code.
- Avoid memory leaks by setting max jobs/max time where applicable.
- Separate queues by priority: `critical`, `default`, `slow`, `external`.

### Redis production checks

```bash
redis-cli INFO memory
redis-cli INFO persistence
redis-cli CONFIG GET maxmemory-policy
redis-cli SLOWLOG GET 10
php artisan queue:failed
php artisan queue:monitor default,critical --max=100
```

### Common failure patterns

- Cache key missing tenant/user prefix causes data leakage.
- Caching hides missing DB indexes and creates stale-data bugs.
- Queue job serializes a full model that changes before execution.
- Queue worker timeout/retry_after mismatch creates duplicate side effects.
- Redis public exposure becomes an immediate critical incident.
