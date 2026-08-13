---
name: bosskuai-observability-sre
description: Use this for logs, metrics, tracing, alerts, SLOs, health checks, dashboards, incident detection, and production reliability instrumentation.
---

# BosskuAI Observability & SRE

Use this skill when the question is **whether production failure would be noticed**, and how fast.

## How this differs from nearby skills

- **`bosskuai-incident-response`**: runs an incident already in progress; this skill builds the detection that starts it.
- **`bosskuai-performance-profiling`**: diagnoses a known slow path; this skill tells you which path is slow in production.
- **`bosskuai-devops-iac`**: builds the deployment pipeline; this skill instruments what the pipeline ships.

## Start from user impact, not dashboards

Define the SLI first: the measurable thing a user experiences (request success rate, checkout latency, job completion time). Dashboards and alerts derive from it. A dashboard built before an SLI measures whatever was easy to collect.

## Instrument for correlation

Every log line for a request or job should carry enough identity to reconstruct a story:

- Request id, propagated across services and into background jobs.
- Tenant/org id and user id where privacy allows.
- Job id and attempt number for queued work.
- Domain ids that matter for recovery: payment, invoice, refund, webhook event.
- Trace/span id when distributed tracing exists.

## Alert on symptoms, not causes

Alert when users are affected: error rate, latency percentile breach, queue age growth, failed payment or webhook spikes, drop in a core business event. Cause-based alerts (CPU, memory) belong on dashboards unless they directly predict user pain. Every alert needs an owner and a runbook link, or it trains people to ignore alerts.

## Health checks

Separate **liveness** (is the process up) from **readiness** (can it serve). Readiness should check the dependencies the app truly needs: database, cache, queue, storage, and critical external services. A readiness check that always returns 200 is worse than none.

## Guardrails

- Never log secrets, tokens, full card data, passwords, or unredacted personal data.
- Watch cardinality: unbounded label values on metrics get expensive fast.
- Sample high-volume traces, but never sample away errors.
- Do not add an alert without defining the response to it.
- Retention and sampling are cost decisions; state them explicitly rather than defaulting.

## Output format

```text
User-impacting SLI: [what it measures]
SLO (if set): [target and window]

Current coverage:
  Logs: [what exists, what is missing]
  Metrics: [what exists, what is missing]
  Traces: [what exists, what is missing]
  Health checks: [liveness / readiness state]

Gaps:
  P0/P1/P2 - [blind spot] - [what would go unnoticed] - [instrumentation to add]

Alerts: [signal - threshold - owner - runbook]
Cost note: [retention / cardinality / sampling implications]
Verification: [how the signal was confirmed to fire]
```

## References

- `../../references/checklists/observability-sre-checklist.md`
