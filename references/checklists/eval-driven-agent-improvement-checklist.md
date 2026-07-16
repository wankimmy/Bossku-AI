# Bosskuai Eval Driven Agent Improvement Checklist

Use this checklist only when the task clearly needs this domain.

- Turn recurring agent failures into small deterministic or LLM-judged eval cases.
- Separate routing, retrieval, workflow, quality, token, and safety evals.
- Track pass rate and false confidence, not just green checks.
- Avoid overfitting exact phrases; add fresh generalization cases.
- Require changelog entry for behavior changes.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.
