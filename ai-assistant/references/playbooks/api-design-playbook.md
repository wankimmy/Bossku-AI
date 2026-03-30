# API Design Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when shaping REST, GraphQL, internal API, webhook, or event contracts.

## Workflow

1. Read the current API docs, schema, handlers, and tests.
2. Model the business resources, operations, and invariants.
3. Design the contract for consistency, errors, retries, and evolution.
4. Review auth, abuse, pagination, and integrator ergonomics.
5. Recommend the smallest safe contract change and rollout path.

## Output expectation

- contract summary
- resource and operation model
- compatibility risks
- client or integrator concerns
- recommended next contract slice
