---
name: bosskuai-customer-success-support
description: Use this for support SOPs, ticket triage and SLAs, human-led onboarding to first value, account health, renewals, and turning repeated tickets into product fixes. Cancel flows and dunning live in churn-prevention.
---

# BosskuAI Customer Success & Support

Use this skill to design **how customers get helped and retained**: support operations, onboarding to first value, and the loop that turns tickets into product fixes.

## How this differs from nearby skills

- **`churn-prevention`**: campaigns and tactics against churn; this skill builds the support and success operation underneath.
- **`onboarding`**: designs the in-product activation flow; this skill covers the human side and what happens after.
- **`bosskuai-operations`**: general business process design; this skill is customer-facing specifically.
- **`bosskuai-customer-discovery`**: research to learn what to build; this skill serves existing customers.

## Support structure

Define these before writing macros or buying a tool:

- **Categories**: what kinds of requests exist, and which are actually bugs.
- **Severity**: what counts as urgent, tied to customer impact rather than customer volume.
- **SLA per severity**: first response and resolution targets you can actually meet.
- **Owner and escalation path**: who handles it, and who it goes to when stuck, including out of hours.

An SLA without an owner is a wish. Publish only targets you would be comfortable reporting against.

## Onboarding targets first value, not setup

Account creation is not activation. Identify the specific moment the product delivers its first real result for this customer, and design the onboarding checklist backwards from that moment. Measure time-to-first-value, not completion of a setup wizard.

## Close the loop

A support system that only answers tickets scales linearly with customers. Each repeated question should exit the queue permanently by becoming one of:

- A product fix, when the interface caused the confusion.
- A documentation or in-product hint, when the concept caused it.
- An automation or macro, when the request is legitimate and routine.

Review the top repeated tickets on a regular cadence and assign each an exit route. Track how many tickets were eliminated, not only how many were answered.

## Signals worth tracking

- Time-to-first-value and activation rate.
- Churn with a recorded reason, gathered at cancellation while context is fresh.
- Support burden per customer and per plan tier, which reveals unprofitable segments.
- Repeat-contact rate, which reveals answers that did not actually resolve.
- Expansion and renewal signals, so success work is not purely reactive.

## Account health and renewals

- Health score: reuse the weighted model in `churn-prevention` (usage, support sentiment, billing) instead of inventing one; agree the action for each band with the account owner.
- Renewal review at least 30 days before each renewal: value delivered against the goals set at kickoff, open risks, expansion signals; the renewal email carries that recap.
- Sales-to-success handoff: goals, stakeholders, promised features, and deadlines move from the deal record into the success plan before kickoff.

## Guardrails

- Do not promise timelines for fixes that engineering has not committed to.
- Do not let support absorb a product defect indefinitely because a workaround exists.
- Never handle personal data in tickets beyond what is needed; support tooling is a common privacy leak.
- Escalation paths must work when the usual owner is unavailable.
- Measure churn reasons from customers, not from internal assumptions.

## Output format

```text
Support model:
  Categories: [list]
  Severity + SLA: [level - definition - response/resolution target]
  Owners and escalation: [who, and after what]

Onboarding:
  First value moment: [what it is]
  Steps to it: [checklist]
  Current time-to-first-value: [measure or unknown]

Ticket loop:
  [repeated ticket] - [root cause] - [exit route: product / docs / automation]

Metrics tracked: [list, with current baseline where known]
Health and renewals: [health inputs and bands, next renewal date and owner per key account, handoff fields]
Risks: [what breaks at 10x customers]
```

## References

- `../../references/checklists/customer-success-support-checklist.md`
