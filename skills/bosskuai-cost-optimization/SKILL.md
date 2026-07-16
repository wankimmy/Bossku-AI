---
name: bosskuai-cost-optimization
description: Use this for cloud/server/API/model cost control, token budgets, queue sizing, storage costs, vendor spend, and unit economics.
---

# Bosskuai Cost Optimization

Use this for cloud/server/API/model cost control, token budgets, queue sizing, storage costs, vendor spend, and unit economics.

## Fast Path

1. Measure before optimizing: request volume, token usage, queue duration, DB load, storage, vendor API calls.
2. Separate product quality modes: cheap default, frontier escalation, batch/offline jobs.
3. Cache only safe, scoped, invalidatable data.
4. Track cost per active user, per job, per lead, or per successful transaction.

## Default Checks

- Measure before optimizing: request volume, token usage, queue duration, DB load, storage, vendor API calls.
- Separate product quality modes: cheap default, frontier escalation, batch/offline jobs.
- Cache only safe, scoped, invalidatable data.
- Track cost per active user, per job, per lead, or per successful transaction.
- Never reduce cost by weakening security, audit, or critical correctness.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-cost-optimization-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-cost-optimization-playbook.md`
- `../../references/checklists/cost-optimization-checklist.md`

- Track LLM cost separately from general API cost when model usage is material.
