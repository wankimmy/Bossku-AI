# Redis Caching and Queues Checklist

- [ ] Key scope includes tenant/user/context.
- [ ] TTL and invalidation rules defined.
- [ ] Jobs are idempotent and retry-safe.
- [ ] Worker timeout/retry/backoff configured.
- [ ] Failed job monitoring exists.
- [ ] Redis is not public and memory policy is intentional.
