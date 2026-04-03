---
name: bosskuai-software-architecture
description: Use this for software architecture, module boundaries, system design, integration design, layering decisions, structural refactors, and long-term maintainability tradeoffs.
---

# BosskuAI Software Architecture

Use this skill when the main question is **structural** — where does this belong, how should components relate, and what are the long-term consequences of this design?

## How this differs from nearby skills

- **`bosskuai-code-revamp`**: executes a structural change once the direction is decided; this skill decides what the direction should be.
- **`bosskuai-codebase-analysis`**: orients you in an unfamiliar codebase; load first if the current structure is unclear before making architectural recommendations.
- **`bosskuai-engineering-delivery`**: implements the chosen architecture; this skill decides what to build.
- **`bosskuai-rigorous-code-review`**: reviews a specific diff; this skill reviews the structural design that diff embodies.

## Mindset

- The best architecture decision is usually the smallest commitment that preserves future optionality.
- Coupling is the enemy — hidden coupling is the worst kind.
- Read the code before making structure claims. Docs are aspirational; code is actual.
- Non-functional requirements (scalability, availability, consistency, latency) constrain structure more than feature requirements do.

## Architectural lenses to apply

Apply whichever are relevant; skip clearly irrelevant ones:

**Layering and responsibility**
- Is logic in the right layer (presentation, application, domain, infrastructure)?
- Is there leakage across layer boundaries (UI calling DB directly, domain importing HTTP)?
- Is the domain model anemic (no behavior) or over-entangled (too many dependencies)?

**Module and service boundaries**
- Does each module have a single clear owner and a stable public contract?
- Are bounded contexts explicit (DDD) or implicit and blurry?
- Where is the coupling highest? Is it necessary or accidental?

**Data ownership and consistency**
- Who owns each piece of data? Can two services write to the same record?
- Is eventual consistency acceptable, or is strong consistency required?
- Are CQRS or event sourcing patterns appropriate, or over-engineering for the current scale?

**Integration and external dependencies**
- Are external integrations behind abstraction boundaries (anti-corruption layers)?
- Are service contracts versioned and backwards-compatible?
- Is the system resilient to partial failures (circuit breakers, retries with backoff, timeouts)?

**Scalability and operability**
- Where are the bottlenecks if load increases 10×? 100×?
- Are stateful components identifiable and independently scalable?
- Is observability (logging, tracing, metrics) a first-class concern in the design?

**Security architecture**
- Are trust boundaries explicit?
- Does the design apply least-privilege (services only access what they need)?
- Are secrets injected at runtime, never baked in?

## Advanced patterns guide

Use this section when a team is considering event sourcing, CQRS, or horizontal scaling patterns. These patterns carry significant complexity cost — consult this section to confirm fit before recommending them.

### Event sourcing

**What it is:** Store the history of state changes as an immutable log of domain events, rather than the current state. Current state is derived by replaying events.

**When it fits:**
- Audit trail is a first-class product requirement (financial ledgers, compliance workflows, health records)
- The product needs temporal queries ("what was the state at time T?")
- Business events are intrinsically meaningful and must be observable by multiple consumers independently
- The team is willing to invest in read model projection infrastructure

**When it does not fit:**
- Simple CRUD apps with no audit or temporal query requirements
- Small teams without experience operating event stores
- When the "history" requirement can be met by a simple `updated_at` timestamp and an audit log table
- When querying current state is the dominant access pattern and projections would add latency without benefit

**Anti-patterns to watch:**
- Using event sourcing as the persistence layer for every entity regardless of whether events are meaningful
- Treating every field change as a domain event (creates noise; events should represent business facts, not technical mutations)
- Failing to version events — event schema evolution in a durable log is significantly harder than schema migration
- Not defining explicit projection rebuild strategies before going to production

### CQRS (Command Query Responsibility Segregation)

**What it is:** Separate the write model (commands, business rules) from the read model (queries, views optimized for consumption). Write and read sides can use different stores.

**When it fits:**
- Read and write throughput requirements are significantly different and one side is a bottleneck
- Query shape is fundamentally different from write shape (complex joins vs simple entity writes)
- The read side can tolerate eventual consistency (async projection updates)
- Event sourcing is in use (CQRS is a natural complement, not a requirement)

**When it does not fit:**
- Simple domains where a single database model serves reads and writes adequately
- When strong consistency is required everywhere — CQRS typically introduces eventual consistency on the read side
- When the team cannot afford to maintain two models (write and read) independently
- As a solution to a performance problem that a database index or query optimization would solve

**Anti-patterns to watch:**
- Applying CQRS to every aggregate regardless of whether the read/write impedance mismatch exists
- Building a CQRS system without a clear projection update strategy (how stale is "too stale"?)
- Using CQRS to avoid writing good queries rather than because the domain genuinely needs it

### Horizontal scaling patterns

**Sharding**
- Partition data across nodes by a shard key so each node owns a subset of the dataset.
- Shard key choice is critical and nearly irreversible: choose a key with high cardinality and even distribution.
- Hot shards (one shard receiving disproportionate traffic) eliminate the benefit — validate shard key distribution before committing.
- Cross-shard queries and transactions become expensive or impossible — design to avoid them.
- When to use: dataset is too large for a single node; write throughput exceeds single-node capacity.

**Partitioning**
- Partition a table by a range or hash of a column (e.g. date range, tenant ID) within a single logical database.
- Less operationally complex than sharding — supported natively by most major databases.
- Improves query performance when the partition key matches the most common query filter.
- When to use: very large tables where queries are naturally scoped to a partition; time-series data; archival patterns.

**Federation (functional sharding)**
- Split different domains or features into separate databases (not tables), each owned by a specific service.
- Reduces cross-domain coupling; each service scales independently.
- Eliminates cross-domain JOINs — enforce domain boundaries at the application layer.
- When to use: microservices architecture with well-defined bounded contexts; when one domain's I/O is throttling another's performance.

**Decision guide:**

| Scale problem | Preferred pattern |
|--------------|------------------|
| Single large table with time-based queries | Table partitioning |
| Multi-tenant data at high volume per tenant | Sharding by tenant ID |
| Cross-service data coupling and scaling bottleneck | Federation |
| Distributed write load across homogeneous data | Sharding |

## Workflow

1. **Read the current architecture** — Identify structure from code, not docs. Map: modules, layers, entry points, key data flows, external dependencies.

2. **Draw the C4 Level 2 (Container) or Level 3 (Component) view** — Name components, their responsibilities, and the connections between them. Distinguish synchronous vs asynchronous.

3. **Apply the architectural lenses** above — Work through each relevant lens and note concerns.

4. **Distinguish local fix from structural need** — Can this be solved with a small change inside the current boundary? Or does it require moving the boundary? Apply the principle: prefer local fixes unless the structure materially blocks correctness, maintainability, or delivery.

5. **Write an Architecture Decision Record (ADR)** when recommending a structural direction:
   - **Context**: what is the problem and why does the current structure not serve it?
   - **Decision**: what change is recommended?
   - **Consequences**: what gets better, what gets harder, what risks remain?
   - **Alternatives considered**: what other options were evaluated and why rejected?

6. **Surface the residual risks** — Every architecture has tradeoffs. Name them explicitly so the user can make an informed decision.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not recommend microservices for a team of two. Match architecture complexity to team size and scale requirements.
- Do not propose a rewrite when a smaller boundary adjustment solves the problem.
- Do not conflate "best in theory" with "best for this team now."
- If recommending event sourcing, CQRS, or a distributed pattern, require a concrete reason why a simpler approach fails.

## Output format

```
Architecture summary: [current structure in plain language]
Structural concerns (by lens):
  Layering: [findings]
  Boundaries: [findings]
  Data ownership: [findings]
  Integrations: [findings]
  Scalability: [findings]
ADR:
  Context: [problem]
  Decision: [recommended direction]
  Consequences: [better / harder / risks]
  Alternatives: [what was rejected and why]
Local fix vs structural change: [verdict + reasoning]
Residual risks: [what remains after the recommendation]
```

## References

- `../../references/playbooks/architecture-playbook.md`
- `../../references/checklists/architecture-review-checklist.md`
