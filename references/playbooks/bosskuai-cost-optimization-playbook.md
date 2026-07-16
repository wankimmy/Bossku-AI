# Bosskuai Cost Optimization Playbook

## Purpose

Use this for cloud/server/API/model cost control, token budgets, queue sizing, storage costs, vendor spend, and unit economics.

## Operating Principles

- Measure before optimizing: request volume, token usage, queue duration, DB load, storage, vendor API calls.
- Separate product quality modes: cheap default, frontier escalation, batch/offline jobs.
- Cache only safe, scoped, invalidatable data.
- Track cost per active user, per job, per lead, or per successful transaction.
- Never reduce cost by weakening security, audit, or critical correctness.

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

- Track LLM cost separately from general API cost when model usage is material.
