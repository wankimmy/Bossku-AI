---
name: bosskuai-api-design
description: Use this for API contract design across REST, GraphQL, and event-driven interfaces, including resource modeling, versioning, errors, pagination, idempotency, and integration-facing correctness.
---

# BosskuAI API Design

Use this skill when the main question is **the shape of the contract** between systems, not only the code that implements it.

## How this differs from nearby skills

- **`bosskuai-software-architecture`**: decides the broader system boundaries; this skill designs the API contract that lives at those boundaries.
- **`bosskuai-engineering-delivery`**: implements an API once the contract is clear; this skill decides what the contract should be.
- **`bosskuai-coding-best-practices`**: improves handler or client code quality; this skill focuses on external behavior and compatibility.

## Mindset

- API design is product design for developers and integrators.
- Backward compatibility is cheaper than partner breakage.
- Ambiguous error handling becomes operational pain later.
- Make the safe path the easy path: idempotency, pagination, versioning, and explicit contracts should be first-class.

## Design lenses

Apply whichever are relevant:

**Resource and domain modeling**
- Are resources named after business concepts rather than database tables?
- Do operations reflect domain actions clearly?
- Are side effects explicit?

**Contract shape**
- Is the contract consistent across endpoints, fields, filters, and mutations?
- Are required vs optional fields explicit?
- Are null semantics clear?

**Versioning and evolution**
- What changes are additive and safe?
- What would break existing clients?
- Is versioning path-based, header-based, schema-based, or event-versioned, and why?

**Errors and reliability**
- Are errors machine-readable and human-readable?
- Are retry-safe operations idempotent?
- Are timeouts, async completion, and partial failure states represented explicitly?

**Pagination, filtering, and performance**
- Is pagination stable and scalable?
- Are filtering and sorting contracts predictable?
- Does the contract encourage efficient usage patterns?

**Security and abuse**
- Are auth scopes or permissions explicit at the contract level?
- Are dangerous fields or expansions exposed unnecessarily?
- Could the API be abused for scraping, enumeration, or replay?

**Events and webhooks**
- Are events named after facts that happened, not commands?
- Are ordering, replay, and deduplication expectations explicit?
- Is signature verification or authenticity part of the contract?

## MCP integrations

| Tool | Role | Degradation |
|------|------|-------------|
| Postman MCP Server | Collection sync, client code generation, API discovery, mock servers, test execution, docs publishing, security audit | Without Postman MCP, work from OpenAPI specs and manual HTTP clients |

## Workflow

1. **Read the current contract surface** — docs, OpenAPI, GraphQL schema, event schemas, handlers, and tests. If Postman MCP is available, sync collections to get the live contract state.
2. **Model the domain objects and operations** — name the resources, actions, and key invariants.
3. **Choose the contract style intentionally** — REST, GraphQL, command/event, or mixed. Match it to client needs and operational realities.
4. **Design for evolution** — identify additive changes, breaking changes, deprecation path, and versioning strategy.
5. **Design the unhappy paths** — auth failures, validation failures, conflicts, retries, timeouts, async completion, and rate limiting.
6. **Check integrator ergonomics** — consistency, discoverability, pagination, filtering, webhook replays, examples, and field naming.
7. **State the compatibility risks** — name what breaks existing consumers and what is safe to ship incrementally.
8. **Validate with tooling** — If Postman MCP is available: sync the designed contract to a collection, generate mock servers for consumer testing, and run API test suites against the mock to verify contract correctness before implementation.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not expose internal database shape as the public contract without a reason.
- Do not add versioning complexity unless you can name the compatibility problem it solves.
- Do not design errors as freeform strings only.
- Do not design webhook or event flows without replay, idempotency, and authenticity in mind.

## Output format

```text
API summary:
  Style: [REST / GraphQL / events / mixed]
  Primary consumers: [who integrates with it]
  Core resources or operations: [list]

Contract recommendations:
  Resource model: [shape]
  Request/response patterns: [shape]
  Errors: [shape]
  Pagination/filtering: [shape]
  Versioning/evolution: [strategy]
  Auth/permissions: [model]

Compatibility risks:
  [change] — [who breaks] — [safe rollout]

Integrator concerns:
  [issue] — [impact] — [improvement]

Smallest safe next step:
  [what to change first]
```

## References

- `../../references/checklists/api-design-checklist.md`
- `../../references/playbooks/api-design-playbook.md`
