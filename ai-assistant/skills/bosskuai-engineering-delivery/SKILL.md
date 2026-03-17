---
name: bosskuai-engineering-delivery
description: Use this for disciplined software implementation and review work that should follow planning-first execution, test-guided development where practical, review-before-finalization, and explicit verification.
---

# BosskuAI Engineering Delivery

Use this skill when the task is implementation-heavy and needs a reliable engineering workflow rather than just isolated coding advice.

## Workflow

1. Classify whether the task needs planning, implementation, security review, docs verification, or code review first.
2. Read nearby code, tests, docs, and conventions before editing.
3. For meaningful changes, create a small implementation plan before writing code.
4. Prefer test-first or test-guided development when changing behavior, fixing bugs, or touching risky flows.
5. Implement the smallest safe change that fits the current architecture.
6. Review the diff for correctness, regressions, security, business logic, and missing tests.
7. Run the relevant verification steps available in the repo.
8. In the final handoff, separate what was verified from what could not be verified.

## Output expectation

- task classification
- plan or implementation sequence
- testing strategy
- security-sensitive areas
- verification steps run
- residual risks or gaps

## References

- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/playbooks/engineering-delivery-playbook.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
