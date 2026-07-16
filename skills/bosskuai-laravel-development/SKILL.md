---
name: bosskuai-laravel-development
description: Use this for expert Laravel backend development, audits, queues, Eloquent, migrations, validation, service boundaries, testing, security, performance, and production readiness.
---

# BosskuAI Laravel Development

Use this skill when building, auditing, or refactoring Laravel applications.

## Operating principles

- Follow the current project conventions first; improve only where the convention creates risk.
- Prefer Laravel-native features before custom abstractions: Form Requests, Policies, Jobs, Events, Notifications, Resources, Collections, casts, scopes, service container, config, queues, scheduler, and tests.
- Keep controllers thin: validate, authorize, call use case/service, return response.
- Put business rules in explicit domain/application services when they are reused or stateful.
- Treat migrations, queues, jobs, scheduled commands, and webhooks as production surfaces.

## Checklist

- Auth and authorization: policies/gates present for tenant/user-sensitive actions.
- Input boundaries: Form Request or explicit validation; no trusted request payloads.
- Eloquent: avoid N+1, ambiguous mass assignment, hidden lazy loading, over-broad `select *`, and model events with surprising side effects.
- Database: transactions around multi-write state changes; constraints match business rules.
- Queues: idempotent jobs, retry/backoff, timeout, failure logging, and safe serialization.
- APIs/webhooks: signature verification, replay protection, idempotency keys, response contracts, and audit trail.
- Testing: feature tests for user-visible behavior, unit tests for domain rules, integration tests for external boundaries.
- Performance: pagination, indexes, eager loading, caching with invalidation, and query profiling.

## Guardrails

- Do not add repositories/services just to look enterprise.
- Do not bypass authorization because an admin route exists.
- Do not swallow exceptions without observability.
- Do not use raw SQL unless the performance or database feature justifies it; parameterize when used.
- Do not recommend package installs before checking Laravel built-ins.

## Output format

```text
Laravel version / evidence checked: [files]
Main finding: [one sentence]
Risks:
  P0/P1/P2 — [issue] — [why it matters] — [fix]
Implementation notes: [files / commands]
Verification: [tests / static analysis / commands]
Unverified: [anything not checked]
```

## References

- `../../references/playbooks/bosskuai-laravel-development-playbook.md`
- `../../references/checklists/laravel-development-checklist.md`
- `../../references/checklists/expert-cofounder-stack-checklist.md`
