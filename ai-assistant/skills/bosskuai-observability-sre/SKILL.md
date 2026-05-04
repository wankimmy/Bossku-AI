---
name: bosskuai-observability-sre
description: Use this for logs, metrics, tracing, alerts, SLOs, health checks, dashboards, incident detection, and production reliability instrumentation.
---

# Bosskuai Observability Sre

Use this for logs, metrics, tracing, alerts, SLOs, health checks, dashboards, incident detection, and production reliability instrumentation.

## Fast Path

1. Define user-impacting SLI before adding dashboards.
2. Log request id, user/tenant id where safe, job id, payment/refund ids, and correlation ids.
3. Alert on symptoms, not noise: error rate, latency, queue age, failed payment/webhook spikes.
4. Add health/readiness checks for app, DB, Redis, queue, storage, and external dependencies.

## Default Checks

- Define user-impacting SLI before adding dashboards.
- Log request id, user/tenant id where safe, job id, payment/refund ids, and correlation ids.
- Alert on symptoms, not noise: error rate, latency, queue age, failed payment/webhook spikes.
- Add health/readiness checks for app, DB, Redis, queue, storage, and external dependencies.
- Write incident runbook and rollback trigger with owner.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-observability-sre-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-observability-sre-playbook.md`
- `../../references/checklists/observability-sre-checklist.md`
