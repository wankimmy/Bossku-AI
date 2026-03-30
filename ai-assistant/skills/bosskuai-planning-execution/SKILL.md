---
name: bosskuai-planning-execution
description: Use this for roadmap planning, execution slicing, milestone sequencing, launch planning, prioritization, delivery coordination, ownership clarity, and keeping projects moving through to completion.
---

# BosskuAI Planning and Execution

Use this skill when the task is to **turn decisions into an executable plan and manage its delivery** — from strategy down to the next concrete action, and from kickoff through to shipped milestone.

Two modes:
- **Planning mode** — define goals, prioritize, sequence phases, map dependencies.
- **Delivery mode** — coordinate execution, clarify ownership, track progress, surface risks early.

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: defines what to build and why; this skill sequences and slices the delivery of that strategy.
- **`bosskuai-engineering-delivery`**: manages individual implementation tasks; this skill manages team-level coordination and milestone tracking across multiple tasks.
- **`bosskuai-launch-commercialization`**: the full launch plan; this skill supplies the execution sequencing, milestone structure, and delivery mechanics.

## Mindset

- Outcomes first, outputs second — every milestone should map to a customer or business outcome, not just a shipped feature.
- Risk-adjusted sequencing: do the riskiest, most uncertain work first to generate learning, not last.
- Plans are hypotheses; they decay as reality arrives. Build in explicit checkpoints to revise.
- A plan with no "won't do" list is not a plan.
- A plan without ownership is a wish. A deadline without a definition of done is a guess.
- The fastest way to recover a slipping project is to cut scope — not add hours.
- Status should surface risk early, not deliver surprises late.
- Every meeting or ceremony has a cost. Run only the ones where the output changes decisions.

## Part 1 — Planning workflow

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

## Part 2 — Delivery tracking

### RACI ownership

For each major workstream or deliverable, assign:
- **R** (Responsible): who does the work?
- **A** (Accountable): who owns the outcome and makes the final call?
- **C** (Consulted): whose input is needed before decisions?
- **I** (Informed): who needs to know the outcome?

Rule: Every deliverable has exactly one **A**. If there are two, there is no ownership.

### Milestone definitions of done

| Milestone type | Definition of done |
|----------------|-------------------|
| **Discovery** | Problem understood, solution hypothesis defined, scope agreed |
| **Architecture / Design** | Approach decided, risks surfaced, team aligned on technical direction |
| **Thin slice** | Core user flow works end-to-end, no polish, no edge cases |
| **Feature complete** | All planned functionality working, passing tests, no known P0/P1 bugs |
| **Hardened** | Load-tested, security-reviewed, edge cases handled, runbook written |
| **Launched** | Deployed to production, monitored, rollback tested |

### Risk register

Track project-level risks:

| Risk | Probability | Impact | Owner | Mitigation | Status |
|------|------------|--------|-------|------------|--------|
| [risk] | H/M/L | H/M/L | [name] | [action] | Open/Mitigated/Closed |

Review the risk register at every milestone and weekly standup.

### Sprint ceremony guide (lean version)

Use only what produces decisions. Cut the rest.

| Ceremony | Frequency | Duration | Output |
|----------|-----------|----------|--------|
| **Standup** | Daily | 15 min | Blockers surfaced, ownership confirmed |
| **Sprint planning** | Weekly / bi-weekly | 60 min | Committed scope, owned tasks |
| **Demo / review** | End of sprint | 30 min | Stakeholder feedback, scope adjustment |
| **Retrospective** | Monthly or post-milestone | 45 min | Process improvements, team health |

**Cut**: meetings that produce no decision and could be an async update.

### Status communication

- Lead with **risk**, not activity. "We completed X" is noise; "Y is at risk because Z" is signal.
- Status format: Green (on track) / Amber (risk identified, mitigation in place) / Red (blocked, decision needed).
- Include: current milestone, ETA confidence, top 3 risks, decision needed from stakeholders.
- Cadence: async written update weekly; sync meeting only when a decision is needed.

### Escalation criteria

Escalate when:
- A blocker cannot be resolved within 48 hours by the team.
- Scope or timeline assumptions have changed materially.
- A dependency is missed by its due date with no new ETA.
- A risk has moved from Amber to Red.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not plan phases 2–3 in detail before phase 1 is validated.
- Do not accept "we'll figure it out" for high-risk dependencies.
- Do not skip the "won't do this quarter" list — scope creep kills delivery cadence.
- If the team has no capacity, the plan is wrong, not the team.
- Do not add process overhead for a team of 2 — RACI on a napkin is enough.
- Do not set a deadline without also setting a scope. Pick two: scope, quality, time.
- Do not run standups as status reports — they are blocker-surfacing sessions.
- Do not let Amber stay Amber for more than one sprint without escalation or mitigation.

## Output format

**Planning mode:**
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

**Delivery mode:**
```
Project goal and deadline:
  Outcome: [specific, measurable result]
  Deadline: [date with confidence: hard / soft]

RACI:
  [workstream] — R: [name] / A: [name] / C: [names] / I: [names]

Milestone plan:
  [milestone] — [definition of done] — [target date] — [owner]

Dependencies:
  Internal: [Task A must complete before Task B: specific deliverable]
  External: [blocked on: specific thing from specific team/party by date]
  Assumption: [assumption — if wrong by date, replan needed]

Risk register:
  [risk] — Probability: H/M/L — Impact: H/M/L — Owner: [name] — Mitigation: [action]

Operating rhythm:
  [ceremony] — [frequency] — [duration] — [output]

Status: [Green/Amber/Red — current milestone — ETA — top risk — decision needed]

Next action: [single most important thing to do right now]
```

## References

- `../../references/playbooks/planning-playbook.md`
- `../../references/checklists/planning-checklist.md`
- `../../references/checklists/project-management-checklist.md`
- `../../references/playbooks/project-management-playbook.md`
