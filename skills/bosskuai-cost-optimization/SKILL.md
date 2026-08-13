---
name: bosskuai-cost-optimization
description: Use this for cloud/server/API/model cost control, token budgets, queue sizing, storage costs, vendor spend, and unit economics.
---

# BosskuAI Cost Optimization

Use this skill when spend is the problem: infrastructure, vendor APIs, model tokens, storage, or the unit economics underneath them.

## How this differs from nearby skills

- **`bosskuai-performance-profiling`**: makes something faster; this skill makes it cheaper, which is sometimes the opposite decision.
- **`bosskuai-financial-modeling`**: models company-level revenue and runway; this skill attacks the cost line items feeding it.
- **`bosskuai-token-saver`**: reduces context cost inside an agent session; this skill covers total product spend including models.
- **`bosskuai-ai-model-selection`**: picks a model for capability; this skill sets the cost envelope it must fit.

## Measure before optimizing

Name the cost driver with a number before changing anything. Request volume, tokens per call, queue duration, database load, egress, storage growth, vendor call counts. Optimizing an unmeasured cost usually moves work rather than removing it, and the savings claim cannot be checked afterwards.

## Where SaaS spend actually concentrates

- **Model/API calls**: prompt size, retries, context re-sent per turn, and calls made on paths users never see.
- **Egress and bandwidth**: often invisible until the bill; check media and export paths.
- **Storage growth**: logs, backups, uploaded media, soft-deleted rows, and old analytics events.
- **Always-on compute**: idle instances, oversized workers, and environments nobody uses.
- **Queue and retry loops**: failed jobs retrying forever cost real money.
- **Per-seat vendor tools**: seats provisioned and never reclaimed.

## Tiering, not blanket downgrades

Separate quality modes rather than degrading everything: a cheap default path, escalation to a stronger model or larger instance only when the task warrants it, and batch/offline processing for work with no latency requirement. This preserves quality where users notice and cuts cost where they do not.

## Caching rules

Cache only what is safe, scoped, and invalidatable. Every cache needs a key that includes tenant and permission scope, an explicit TTL or invalidation event, and a decision about what a stale read would cost. Caching authorization-dependent data on a shared key is a security bug wearing a performance costume.

## Unit economics

Track cost per active user, per job, per lead, or per successful transaction. Absolute spend rising is not automatically bad; cost per successful outcome rising is.

## Guardrails

- Never reduce cost by weakening security, audit trails, tenant isolation, or backup integrity.
- Do not cut observability to the point where the next incident goes undetected. That trade shows up later at a higher price.
- Do not claim a saving without a before and after number.
- Watch for cost shifted rather than removed: moving work to a queue still burns compute.
- Check whether committed-use or reserved pricing beats optimization effort before rewriting code.

## Output format

```text
Measured baseline:
  [cost driver] - [current number] - [source of measurement]

Biggest levers:
  1. [change] - [estimated saving] - [risk] - [effort]

Unit economics: [cost per user / job / transaction, before -> after]
Quality tiers: [cheap default / escalation / batch]
Guardrails preserved: [security, audit, observability untouched]
Verification: [how the saving will be confirmed]
```

## References

- `../../references/checklists/cost-optimization-checklist.md`
