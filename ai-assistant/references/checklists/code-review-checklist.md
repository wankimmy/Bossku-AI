# Code Review Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use alongside `bosskuai-rigorous-code-review`. Work through each section; skip only if clearly irrelevant to the diff.

## Correctness

- [ ] Does the change do what it claims in the PR description?
- [ ] Are edge cases handled: null/undefined/empty, zero, negative, max values?
- [ ] Are concurrency risks addressed: race conditions, shared mutable state, ordering?
- [ ] Is partial failure handled correctly — no silent data corruption on error?
- [ ] Is idempotency preserved where expected (retries won't cause duplicates)?
- [ ] Are all return paths and error branches reachable and correct?

## Security

- [ ] Is all input validated and sanitized at every trust boundary?
- [ ] Are there injection risks: SQL, shell, LDAP, template, path traversal, deserialization?
- [ ] Are authentication and authorization checks in place and enforced consistently?
- [ ] Are secrets and credentials free from hardcoding, logging, and error-response leakage?
- [ ] Is cryptography using current recommended algorithms and key sizes?
- [ ] Are web surfaces protected from CSRF, XSS, and open redirects?
- [ ] Is there appropriate rate limiting on abuse-prone endpoints?
- [ ] Are sensitive operations logged (without leaking PII or secrets)?

## Performance & resources

- [ ] Are there N+1 queries or unbounded API calls inside loops?
- [ ] Are memory allocations bounded under load?
- [ ] Is blocking I/O kept off hot or latency-sensitive paths?
- [ ] Are DB queries using appropriate indexes (no full-table scans on large datasets)?
- [ ] Are caching assumptions validated — does cache invalidation hold?
- [ ] Are there potential lock contention or deadlock paths?

## Test coverage

- [ ] Does the change include tests for new behavior?
- [ ] Are failure paths and edge cases covered by tests?
- [ ] Do tests exercise the real code path, not mocked-away behavior that masks real bugs?
- [ ] Are existing tests still meaningful after this change (not accidentally passing)?
- [ ] Are integration or E2E tests needed given the risk level of this change?

## API contracts & backwards compatibility

- [ ] Does this change break any existing callers (internal or external)?
- [ ] Are schema changes additive-only, or do they remove/rename fields?
- [ ] Is versioning required for public or third-party-consumed contracts?
- [ ] Are deprecations communicated through code, docs, or migration guides?
- [ ] Are response shapes and status codes consistent with the existing API surface?

## Operational concerns

- [ ] Is logging useful, structured, and free from sensitive data?
- [ ] Are metrics or traces emitted so on-call can observe this in production?
- [ ] Is the migration path safe: DB migrations backwards-compatible, rollback tested?
- [ ] Is a feature flag or gradual rollout strategy in place for high-risk changes?
- [ ] Is the deployment order correct (avoid DB column removal before code removal)?

## Maintainability

- [ ] Does the change fit the existing module structure and naming patterns?
- [ ] Are responsibilities in the right layer (no logic leaking into the wrong layer)?
- [ ] Is the change the smallest safe fix, or is it larger than necessary?
- [ ] Are abstractions stable and non-accidental?
- [ ] Are error messages and logs actionable for future developers?

## AI/LLM-generated code markers

- [ ] Are all library methods and APIs real (not hallucinated)?
- [ ] Is error handling specific and actionable, not generic catch-all swallowing?
- [ ] Does validation match the actual domain logic, not generic placeholder checks?
- [ ] Does the naming and style match the project's conventions consistently?
- [ ] Is the logic proportionate to the problem, or is it over-engineered boilerplate?
