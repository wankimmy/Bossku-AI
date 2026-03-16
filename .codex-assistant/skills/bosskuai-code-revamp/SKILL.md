---
name: bosskuai-code-revamp
description: Use this for safe code modernization, structural cleanup, legacy refactors, and revamps that should still respect the current codebase structure and avoid unnecessary churn.
---

# BosskuAI Code Revamp

Use this skill when the right answer is bigger than a local patch but still needs to be grounded in how the current codebase works.

## Workflow

1. Read the current code, nearby modules, and project conventions before proposing structural changes.
2. Separate what can be solved with a minimal local change from what truly needs a revamp.
3. Preserve proven boundaries, naming patterns, and framework conventions unless there is a strong reason to improve them.
4. Define the target structure, what behavior must stay stable, and what risks the revamp introduces.
5. Prefer incremental, testable slices over all-at-once rewrites.
6. State why the revamp is justified, what the smallest safe first step is, and how to reduce migration risk.

## Output expectation

- current structure summary
- why a revamp is or is not justified
- minimal-change alternative
- recommended revamp direction
- migration or rollout slices
- residual risks

## References

- `../../references/playbooks/code-revamp-playbook.md`
- `../../references/checklists/code-revamp-checklist.md`
