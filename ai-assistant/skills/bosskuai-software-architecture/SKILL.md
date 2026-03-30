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
