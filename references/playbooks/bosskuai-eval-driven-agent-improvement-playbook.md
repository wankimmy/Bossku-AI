# Bosskuai Eval Driven Agent Improvement Playbook

## Purpose

Use this for agent eval design, routing tests, retrieval tests, LLM quality cases, regression harnesses, scorecards, and continuous agent improvement.

## Operating Principles

- Turn recurring agent failures into small deterministic or LLM-judged eval cases.
- Separate routing, retrieval, workflow, quality, token, and safety evals.
- Track pass rate and false confidence, not just green checks.
- Avoid overfitting exact phrases; add fresh generalization cases.
- Require changelog entry for behavior changes.

## Review Flow

1. Define the user/business impact.
2. Identify the trust boundary, data boundary, cost boundary, or operational boundary.
3. Inspect the smallest source-of-truth files first.
4. Propose the smallest safe change.
5. Add verification: test, metric, log, alert, rollback trigger, or customer signal.
6. Save durable learning only when it changes future behavior.

## Anti-patterns

- Optimizing a non-measured problem.
- Making broad architecture claims without repo evidence.
- Skipping rollback, audit, or support recovery.
- Storing secrets, temporary instructions, or untrusted claims in memory.
- Using generic SaaS advice without product-stage context.

## Done Bar

- Clear recommendation.
- Concrete implementation or SOP.
- Verification path.
- Main risk and rollback.
- Memory/handoff updated when useful.
