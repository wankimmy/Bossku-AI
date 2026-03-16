# Example Output: Product Review

This is an example of the style the agent should produce.

## Problem framing

The product is trying to help small operations teams coordinate approvals and handoffs. The current idea is directionally useful, but the requirement is still too vague in three places:

- who owns a task versus who can claim it
- what happens when nobody picks it up
- what counts as completion versus reassignment

## Findings

1. The workflow definition is incomplete because assignment, claim, escalation, and reassignment rules are missing.
2. The current scope mixes workflow engine behavior with module-specific business rules.
3. Success criteria are not testable yet because there is no stated SLA, ownership rule, or audit requirement.

## Recommended next slice

- Define one narrow queue workflow first:
  - task created
  - candidate group notified
  - eligible user claims
  - task completed or reassigned
- Add explicit acceptance criteria for:
  - claim behavior
  - timeout/escalation
  - audit trail

## Confidence

- Confirmed from requirement text: moderate
- Inferred from missing workflow details: high
- Needs user/product confirmation: escalation rule, ownership semantics, SLA
