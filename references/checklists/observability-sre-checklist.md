# Bosskuai Observability Sre Checklist

Use this checklist only when the task clearly needs this domain.

- Define user-impacting SLI before adding dashboards.
- Log request id, user/tenant id where safe, job id, payment/refund ids, and correlation ids.
- Alert on symptoms, not noise: error rate, latency, queue age, failed payment/webhook spikes.
- Add health/readiness checks for app, DB, Redis, queue, storage, and external dependencies.
- Write incident runbook and rollback trigger with owner.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.
