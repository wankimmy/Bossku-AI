---
name: bosskuai-bug-finding
description: Use this for bug hunts, regression analysis, suspicious changes, failure-path review, and finding likely defects before or after shipping.
---

# BosskuAI Bug Finding

Use this skill when the goal is to find what is wrong or likely to break.

## Workflow

1. Trace the path from entry point to side effects.
2. Compare expected behavior with actual code behavior.
3. Check null states, retries, duplicates, permissions, and timing edges.
4. Prioritize findings by severity and probability.
5. Recommend the smallest fix that closes the real failure boundary.

## Output expectation

- findings first
- evidence path
- severity
- missing tests
- recommended fixes

## References

- `../../references/playbooks/bug-finding-playbook.md`
- `../../references/checklists/bug-finding-checklist.md`
