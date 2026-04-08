---
name: bosskuai-operations
description: Use this for business operations work including vendor management, process documentation, change management, capacity planning, operational risk, SOPs, RACI, and cross-functional operating cadence.
---

# BosskuAI Operations

Use this skill when the task is **operational execution outside pure code**: vendor management, SOPs, process documentation, change management, capacity planning, handoffs, RACI, or operating cadence.

## How this differs from nearby skills

- **`bosskuai-planning-execution`**: plans product or project delivery; this skill designs the operating process around recurring work.
- **`bosskuai-launch-commercialization`**: coordinates launch readiness; this skill handles ongoing operational systems and change adoption.
- **`bosskuai-product-strategy`**: decides what to build and why; this skill ensures the organization can execute and support it.
- **`bosskuai-legal-compliance`**: flags legal and regulatory risk; this skill routes operational controls and escalations.

## Mindset

- Operations work succeeds when ownership, cadence, and exception paths are explicit.
- A process that only works when one person remembers it is not a process yet.
- Good SOPs make the normal path clear and the escalation path safer.
- Vendor and capacity decisions are risk decisions with cost, reliability, and accountability tradeoffs.
- Change management is product work for the internal team.

## Operations lenses

**Process design**
- What triggers the process?
- Who owns each step?
- What inputs and outputs are required?
- What exceptions or approvals exist?
- What evidence proves the process happened?

**Vendor management**
- What service is provided and how critical is it?
- Who owns the relationship and renewal?
- What are the SLAs, costs, exit options, and data risks?
- What access, security, or compliance review is required?

**Change management**
- Who is affected?
- What behavior must change?
- What training, comms, or support is needed?
- How will adoption and failure be measured?

**Capacity planning**
- What demand signal drives staffing or tooling?
- What threshold triggers action?
- What is the bottleneck: people, process, system, budget, or vendor?
- What happens when demand exceeds capacity?

## Workflow

### Phase 1 - Frame the operation

1. Identify the operating area: vendor, process, change, capacity, incident follow-up, compliance handoff, or recurring cadence.
2. Name the outcome the process must produce.
3. Identify stakeholders, decision owners, operators, approvers, and affected teams.
4. Read existing docs, tickets, runbooks, contracts, dashboards, or meeting notes before redesigning.

### Phase 2 - Map current state

5. Write the current flow from trigger to completion.
6. Mark handoffs, approvals, waiting states, rework loops, and failure points.
7. Identify where work is invisible: no owner, no SLA, no queue, no evidence, no escalation.
8. Capture constraints: budget, compliance, tooling, staffing, contractual terms, or customer commitments.

### Phase 3 - Design target state

9. Define the smallest process that makes ownership and escalation clear.
10. Add RACI only where ambiguity is real.
11. Define input checklist, output artifact, cadence, SLA, and review loop.
12. For vendors, add renewal owner, data access, exit path, and fallback plan.
13. For change, add communication plan, enablement, adoption metric, and support channel.

### Phase 4 - Operationalize

14. Turn the design into an SOP, checklist, runbook, decision log, or project board.
15. Define how success and drift will be reviewed.
16. State what is deliberately deferred to avoid over-process.
17. Capture durable lessons in shared memory or references when the process becomes reusable.

## Reusable artifacts

- **SOP**: use when operators need the same process more than once.
- **RACI**: use when ownership or approval ambiguity is causing delay.
- **Runbook**: use when the process handles an operational event or exception.
- **Vendor register**: use when third-party ownership, renewal, data, or cost risk needs tracking.

## Guardrails

- Do not create heavyweight process for a one-off task.
- Do not assign owners, dates, or vendor commitments as facts unless the user or docs confirm them.
- Do not treat legal, privacy, finance, or security approvals as optional when vendor/data risk is present.
- Do not hide tradeoffs behind vague wording like "streamline"; name the friction and the owner.
- Do not make SOPs that omit exception handling.

## Output format

```text
Operations summary:
  Area: [vendor / process / change / capacity / cadence]
  Outcome: [what must happen]
  Stakeholders: [owners / operators / approvers]
  Current state: [confirmed / inferred]

Target process:
  Trigger: [event]
  Steps: [short list]
  Owner/RACI: [roles]
  SLA or cadence: [timing]
  Evidence: [artifact]
  Escalation: [path]

Risks and controls:
  [risk] - [control] - [owner]

Smallest next step:
  [action]
```

## References

- `../../references/checklists/operations-checklist.md`
- `../../references/playbooks/operations-playbook.md`
- `../../references/checklists/legal-compliance-checklist.md`
