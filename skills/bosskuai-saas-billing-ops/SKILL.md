---
name: bosskuai-saas-billing-ops
description: Use this for SaaS subscription lifecycle, invoices, failed payments, dunning, tax/receipt flows, plan changes, entitlement gating, and billing operations.
---

# Bosskuai Saas Billing Ops

Use this for SaaS subscription lifecycle, invoices, failed payments, dunning, tax/receipt flows, plan changes, entitlement gating, and billing operations.

## Fast Path

1. Map lifecycle: trial, active, past_due, cancelled, refunded, grace period.
2. Separate payment state from entitlement state.
3. Handle webhook idempotency, retry, reconciliation, and manual support recovery.
4. Define invoice/receipt/tax fields before launch.

## Default Checks

- Map lifecycle: trial, active, past_due, cancelled, refunded, grace period.
- Separate payment state from entitlement state.
- Handle webhook idempotency, retry, reconciliation, and manual support recovery.
- Define invoice/receipt/tax fields before launch.
- Verify cancellation, failed payment, plan change, and refund edge cases.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-saas-billing-ops-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-saas-billing-ops-playbook.md`
- `../../references/checklists/saas-billing-ops-checklist.md`
