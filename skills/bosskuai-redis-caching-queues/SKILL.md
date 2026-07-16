---
name: bosskuai-redis-caching-queues
description: Use this for Redis caching, Laravel queues, cache invalidation, locks, rate limits, sessions, Horizon-style worker operations, and queue performance debugging.
---

# BosskuAI Redis Caching and Queues

Use this skill when Redis, queues, caching, sessions, distributed locks, rate limits, or background workers affect correctness or performance.

## Operating principles

- Cache only when the source of truth and invalidation rule are clear.
- Make queued jobs idempotent, observable, and safe to retry.
- Use locks for coordination, not as a substitute for database constraints.
- Separate queue names by priority and workload type.
- Size Redis memory and eviction policy intentionally.

## Checklist

- Cache key names include tenant/user/context where needed.
- TTL and invalidation rule are defined.
- Job timeout, retry count, backoff, uniqueness, and failure handling are explicit.
- Workers are supervised and can be restarted safely during deployment.
- Long-running jobs chunk work and do not serialize heavy Eloquent models blindly.
- Rate limits protect expensive endpoints and third-party APIs.
- Redis is not publicly exposed and has persistence/backup settings appropriate to its role.

## Guardrails

- Do not cache authorization-sensitive data without tenant/user scoping.
- Do not use cache to hide slow queries before checking indexes.
- Do not rely on Redis as the only durable source of business-critical truth.
- Do not let failed jobs pile up without alerting.

## Output format

```text
Workload: [cache / queue / lock / rate limit / session]
Correctness risk: [main risk]
Recommended pattern: [Laravel/Redis pattern]
Operational settings: [workers, TTL, retry, monitoring]
Verification: [tests/commands/logs]
```

## References

- `../../references/playbooks/bosskuai-redis-caching-queues-playbook.md`
- `../../references/checklists/redis-caching-queues-checklist.md`
- `../../references/checklists/expert-cofounder-stack-checklist.md`
