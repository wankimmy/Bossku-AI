# Bosskuai Observability Sre Playbook

## Purpose

Use this for logs, metrics, tracing, alerts, SLOs, health checks, dashboards, incident detection, and production reliability instrumentation.

## Operating Principles

- Define user-impacting SLI before adding dashboards.
- Log request id, user/tenant id where safe, job id, payment/refund ids, and correlation ids.
- Alert on symptoms, not noise: error rate, latency, queue age, failed payment/webhook spikes.
- Add health/readiness checks for app, DB, Redis, queue, storage, and external dependencies.
- Write incident runbook and rollback trigger with owner.

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
