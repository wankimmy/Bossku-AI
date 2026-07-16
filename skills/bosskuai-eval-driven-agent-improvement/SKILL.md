---
name: bosskuai-eval-driven-agent-improvement
description: Use this for agent eval design, routing tests, retrieval tests, LLM quality cases, regression harnesses, scorecards, and continuous agent improvement.
---

# Bosskuai Eval Driven Agent Improvement

Use this for agent eval design, routing tests, retrieval tests, LLM quality cases, regression harnesses, scorecards, and continuous agent improvement.

## Fast Path

1. Turn recurring agent failures into small deterministic or LLM-judged eval cases.
2. Separate routing, retrieval, workflow, quality, token, and safety evals.
3. Track pass rate and false confidence, not just green checks.
4. Avoid overfitting exact phrases; add fresh generalization cases.

## Default Checks

- Turn recurring agent failures into small deterministic or LLM-judged eval cases.
- Separate routing, retrieval, workflow, quality, token, and safety evals.
- Track pass rate and false confidence, not just green checks.
- Avoid overfitting exact phrases; add fresh generalization cases.
- Require changelog entry for behavior changes.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-eval-driven-agent-improvement-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-eval-driven-agent-improvement-playbook.md`
- `../../references/checklists/eval-driven-agent-improvement-checklist.md`
