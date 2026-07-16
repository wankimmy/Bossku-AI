# ADR: Skill expansion and overlap control

- Date: 2026-03-30
- Status: Accepted

## Context

BosskuAI uses many small skills with explicit triggers. Without criteria, the roster drifts toward duplicate lenses, unclear activation, and maintenance debt.

## Decision

Add a **new skill** only when **all** of the following hold:

1. **Distinct trigger** — The task type is not already covered by an existing skill’s “when to use” without awkward stretching.
2. **Distinct workflow or output** — The skill adds a repeatable workflow, output shape, or guardrail set, not a paragraph that could live in memory or a checklist.
3. **Repeated need** — The situation has appeared more than once across sessions or projects (or is clearly central to the product, e.g. security).
4. **Sustainable size** — The skill can stay focused; if it grows past ~200–300 lines of unique guidance, split or extract a playbook/checklist instead.

Prefer **instead of** a new skill:

- A **checklist** for quality gates and pass/fail items
- A **playbook** for step-by-step execution templates
- A **pitfall** document for one-class failure modes
- An **ADR** for one-time architectural or policy decisions
- **Memory** (`project-understanding.md`, `agent-profile.md`) for repo- or team-specific facts

Use **skill-stocktake** periodically to merge overlaps and retire weak triggers.

## Consequences

- **Positive:** Clearer activation, easier onboarding, less contradiction between files.
- **Negative:** Some “nice to have” topics stay as future work until demand is proven — captured in `AGENTS.md` under future skill areas when useful.

## Alternatives considered

- **Maximize skill count** for coverage — rejected due to overlap and activation ambiguity.
- **Single mega-skill** — rejected; hurts load-minimal-skills discipline and discoverability.
