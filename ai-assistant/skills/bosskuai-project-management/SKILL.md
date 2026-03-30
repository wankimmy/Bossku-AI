---
name: bosskuai-project-management
description: Use this for execution tracking, delivery management, dependencies, milestone control, ownership clarity, status communication, and keeping projects moving without losing focus.
---

# BosskuAI Project Management

Use this skill when the task is about coordinating execution, reducing slippage, clarifying ownership, or creating a working delivery rhythm.

## How this differs from nearby skills

- **`bosskuai-planning-execution`**: defines what to build and in what order (strategy + prioritization); this skill manages the execution of that plan (delivery, coordination, tracking).
- **`bosskuai-launch-commercialization`**: plans the go-to-market launch; this skill manages the project delivery mechanics underneath it.
- **`bosskuai-engineering-delivery`**: manages individual implementation tasks; this skill manages the team-level coordination and milestone tracking across multiple tasks.

## Mindset

- A plan without ownership is a wish. A deadline without a definition of done is a guess.
- The fastest way to recover a slipping project is to cut scope — not add hours.
- Status should surface risk early, not deliver surprises late.
- Every meeting, status update, or ceremony has a cost. Run only the ones where the output changes decisions.

## Core framework: RACI

For each major workstream or deliverable, assign:
- **R** (Responsible): who does the work?
- **A** (Accountable): who owns the outcome and makes the final call?
- **C** (Consulted): whose input is needed before decisions?
- **I** (Informed): who needs to know the outcome?

Rule: Every deliverable has exactly one **A**. If there are two, there is no ownership.

## Milestone structure

| Milestone type | Definition of done |
|----------------|-------------------|
| **Discovery** | Problem understood, solution hypothesis defined, scope agreed |
| **Architecture / Design** | Approach decided, risks surfaced, team aligned on technical direction |
| **Thin slice** | Core user flow works end-to-end, no polish, no edge cases |
| **Feature complete** | All planned functionality working, passing tests, no known P0/P1 bugs |
| **Hardened** | Load-tested, security-reviewed, edge cases handled, runbook written |
| **Launched** | Deployed to production, monitored, rollback tested |

## Risk register

Track project-level risks in a simple register:

| Risk | Probability | Impact | Owner | Mitigation | Status |
|------|------------|--------|-------|------------|--------|
| [risk] | H/M/L | H/M/L | [name] | [action] | Open/Mitigated/Closed |

Review the risk register at every milestone and weekly standup.

## Sprint ceremony guide (lean version)

Use only what produces decisions. Cut the rest.

| Ceremony | Frequency | Duration | Output |
|----------|-----------|----------|--------|
| **Standup** | Daily | 15 min | Blockers surfaced, ownership confirmed |
| **Sprint planning** | Weekly / bi-weekly | 60 min | Committed scope, owned tasks |
| **Demo / review** | End of sprint | 30 min | Stakeholder feedback, scope adjustment |
| **Retrospective** | Monthly or post-milestone | 45 min | Process improvements, team health |

**Cut**: meetings that produce no decision and could be an async update.

## Dependency mapping

Before each sprint, identify:
- **Internal dependencies**: Task B cannot start until Task A delivers `[specific output]`.
- **External dependencies**: Blocked on [third-party / team / approval] delivering `[specific thing]` by `[date]`.
- **Assumption dependencies**: This plan assumes `[assumption]`. If wrong by `[date]`, we need to replan.

## Status communication principles

- Lead with **risk**, not activity. "We completed X" is noise; "Y is at risk of slipping because Z" is signal.
- Status format: Green (on track) / Amber (risk identified, mitigation in place) / Red (blocked, decision needed).
- Include: current milestone, ETA confidence, top 3 risks, decision needed from stakeholders.
- Cadence: async written update weekly; sync meeting only when a decision is needed.

## Escalation criteria

Escalate when:
- A blocker cannot be resolved within 48 hours by the team.
- Scope or timeline assumptions have changed materially.
- A dependency is missed by its due date with no new ETA.
- A risk has moved from Amber to Red.

## Workflow

1. **Define the outcome**: What is the specific, measurable result this project must achieve? When?
2. **Build the RACI**: Assign R/A/C/I for every major workstream.
3. **Milestone plan**: Name milestones with clear definitions of done and target dates.
4. **Dependency map**: Surface all blockers and assumption dependencies.
5. **Risk register**: Populate with known risks; assign owners and mitigations.
6. **Operating rhythm**: Choose the minimum viable ceremony set for this team size and project.
7. **Status cadence**: Define how and when status is communicated.
8. **Identify the next action**: What is the single most important thing to do right now to move delivery forward?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not add process overhead for a team of 2 — RACI on a napkin is enough.
- Do not set a deadline without also setting a scope. Pick two: scope, quality, time.
- Do not run standups as status reports — they are blocker-surfacing sessions.
- Do not let Amber stay Amber for more than one sprint without escalation or mitigation.

## Output format

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

Status format: [Green/Amber/Red — current milestone — ETA — top risk — decision needed]

Next action: [single most important thing to do right now]
```

## References

- `../../references/checklists/project-management-checklist.md`
- `../../references/playbooks/project-management-playbook.md`
