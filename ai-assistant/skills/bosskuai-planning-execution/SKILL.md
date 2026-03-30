---
name: bosskuai-planning-execution
description: Use this for roadmap planning, execution slicing, milestone sequencing, launch planning, prioritization, and turning strategy into realistic step-by-step plans.
---

# BosskuAI Planning and Execution

Use this skill when the task is to **turn decisions into an executable plan** — from strategy down to the next concrete action.

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: defines what to build and why; this skill sequences and slices the delivery of that strategy.
- **`bosskuai-project-management`**: runtime tracking of in-flight work; load after this skill to manage execution once the plan is agreed.
- **`bosskuai-engineering-delivery`**: implements the engineering slice; this skill shapes what that slice is.
- **`bosskuai-launch-commercialization`**: the full launch plan; this skill supplies the execution sequencing and milestone structure for it.

## Mindset

- Outcomes first, outputs second — every milestone should map to a customer or business outcome, not just a shipped feature.
- Risk-adjusted sequencing: do the riskiest, most uncertain work first to generate learning, not last.
- Plans are hypotheses; they decay as reality arrives. Build in explicit checkpoints to revise.
- A plan with no "won't do" list is not a plan.

## Workflow

1. **Define the outcome and timeframe** — Write the goal as a measurable outcome: not "build the dashboard" but "enable ops managers to self-serve reporting by [date], reducing support requests by 30%." Set the horizon (sprint, quarter, half-year).

2. **Identify the constraints** — Team size, skills, dependencies, budget, calendar (holidays, freeze periods), and non-negotiable deadlines. Surface them before slicing.

3. **Prioritize using impact/effort or RICE**:
   - **RICE**: Reach × Impact × Confidence / Effort
   - **Impact/effort**: 2×2 matrix — do high-impact/low-effort first
   - Challenge every item that is "high impact and high effort" — verify the impact claim.

4. **Sequence phases and milestones**:
   - Phase 0: reduce uncertainty (prototype, spike, design, discovery)
   - Phase 1: core loop working end-to-end (thin vertical slice)
   - Phase 2: harden and complete (edge cases, error states, observability)
   - Phase 3: scale and optimize (performance, integrations, growth)
   - Each milestone needs: goal, deliverable, acceptance criteria, owner, date.

5. **Map dependencies and assumptions** — What must be true or done before each milestone? Which dependencies are internal vs external? Which assumptions need validation before committing to a phase?

6. **Define validation gates** — At what point do we check if we should continue, pivot, or stop? Make these explicit so the plan is not a death march.

7. **Recommend the smallest next plan** — What is the lowest-risk first action that reduces uncertainty and builds momentum? Start there, not at phase 3.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not plan phases 2–3 in detail before phase 1 is validated.
- Do not accept "we'll figure it out" for high-risk dependencies.
- Do not skip the "won't do this quarter" list — scope creep kills delivery cadence.
- If the team has no capacity, the plan is wrong, not the team.
- Distinguish between a plan that is ambitious-but-doable vs one that is aspirational cover for no real commitment.

## Output format

```
Outcome goal: [measurable result + timeframe]
Constraints: [team, budget, deadlines, dependencies]
Priority stack (RICE/impact-effort):
  P1: [item] — Reach / Impact / Confidence / Effort
  ...
Milestone plan:
  Phase 0 — [goal] / [deliverable] / [acceptance criteria] / [date]
  Phase 1 — ...
  Phase 2 — ...
Dependencies and assumptions: [item + risk if wrong]
Validation gates: [what triggers a pivot decision]
Won't do (this cycle): [list]
Recommended first action: [specific, owned, dated]
```

## References

- `../../references/playbooks/planning-playbook.md`
- `../../references/checklists/planning-checklist.md`
