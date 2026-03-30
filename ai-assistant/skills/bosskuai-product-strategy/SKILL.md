---
name: bosskuai-product-strategy
description: Use this for product strategy, feature shaping, PRD review, scope refinement, prioritization, user-value analysis, and go-to-market implications of product decisions.
---

# BosskuAI Product Strategy

Use this skill when the task is primarily about **what to build and why**, not how to build it.

## How this differs from nearby skills

- **`bosskuai-planning-execution`**: turns strategy into a roadmap and milestones; load after this skill to sequence delivery.
- **`bosskuai-market-analysis`**: external market reality (competitors, trends, TAM); load alongside when positioning is unclear.
- **`bosskuai-launch-commercialization`**: full launch readiness across engineering, GTM, and sales; load when nearing ship.
- **`bosskuai-business-logic-review`**: correctness of workflow rules; load when an existing feature has wrong logic, not unformed strategy.

## Mindset

- Disaggregate what the user **wants** from what they **need** — surface the real job-to-be-done.
- Challenge scope creep as the default enemy of early delivery.
- Prefer outcomes (behavior change, revenue, retention) over outputs (features shipped).
- Ask "what is the smallest thing that proves the hypothesis?" before defining anything larger.

## Workflow

1. **Frame the problem** — Identify the user, the trigger that causes them to seek a solution, the pain, the frequency, and what they currently do instead. Do not accept "users want X" — ask why.

2. **Run a JTBD analysis** — Define the Job-to-be-Done: functional job, emotional job, social job. Separate the core job from ancillary jobs.

3. **Shape the scope** — Apply MoSCoW or NOW/NEXT/LATER to separate must-have from nice-to-have. Challenge every "must have" that lacks a clear failure consequence.

4. **Map assumptions and risks**:
   - What must be true for this to work?
   - Which assumptions are highest risk and lowest validation evidence?
   - What is the riskiest part of this bet?

5. **Define acceptance criteria** — For each must-have scope item, write a testable definition of done from the user's perspective (not the engineering perspective).

6. **Recommend the smallest meaningful delivery slice** — What is the minimum that creates real user value and generates learning? Separate the MVP hypothesis from the full vision.

7. **Surface GTM implications** — Does this change pricing, onboarding, messaging, or sales motion? Call it out even if it is not the user's primary question.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not define the solution before the problem is properly framed.
- Do not skip ICP — "everyone" is not a user.
- Do not optimize for feature quantity over outcome quality.
- If the brief is vague, ask clarifying questions rather than fabricating context.
- Do not assume the stated problem is the real problem — probe for the trigger and the workaround.

## Output format

```
Problem statement: [one sentence — user, trigger, pain]
JTBD: [functional job / emotional job / social job]
Scope (MoSCoW):
  Must: [list]
  Should: [list]
  Won't (this slice): [list]
Key assumptions: [ranked by risk]
Acceptance criteria: [one per must-have]
Recommended first slice: [what + why it's minimum]
GTM implications: [if any]
```

## References

- `../../references/playbooks/product-discovery-playbook.md`
- `../../references/checklists/product-spec-checklist.md`
