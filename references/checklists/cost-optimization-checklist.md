# Bosskuai Cost Optimization Checklist

Use this checklist only when the task clearly needs this domain.

- Measure before optimizing: request volume, token usage, queue duration, DB load, storage, vendor API calls.
- Separate product quality modes: cheap default, frontier escalation, batch/offline jobs.
- Cache only safe, scoped, invalidatable data.
- Track cost per active user, per job, per lead, or per successful transaction.
- Never reduce cost by weakening security, audit, or critical correctness.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.

- Track LLM cost separately from general API cost when model usage is material.
