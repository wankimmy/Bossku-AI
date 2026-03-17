---
name: bosskuai-business-logic-review
description: Use this for workflow review, state transitions, approval and assignment design, edge cases, rule consistency, and finding hidden business logic flaws in products or systems.
---

# BosskuAI Business Logic Review

Use this skill when the main risk is not syntax or UI polish but incorrect workflow logic.

## Workflow

1. Identify the core entities and lifecycle states.
2. Trace the happy path and the failure paths.
3. Ask what must never happen.
4. Check handoffs, approvals, retries, reversals, and ownership changes.
5. Check time-based rules, threshold logic, and cross-module side effects.

## Output expectation

- workflow summary
- logic gaps
- dangerous assumptions
- edge cases
- recommended rule changes

## References

- `../../references/checklists/business-logic-checklist.md`
