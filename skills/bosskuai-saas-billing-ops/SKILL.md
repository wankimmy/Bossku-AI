---
name: bosskuai-saas-billing-ops
description: Use this for SaaS subscription lifecycle, invoices, failed payments, dunning, tax/receipt flows, plan changes, entitlement gating, and billing operations.
---

# BosskuAI SaaS Billing Ops

Use this skill when **money state and access state can disagree**: subscriptions, invoices, dunning, plan changes, refunds, and entitlement gating.

## How this differs from nearby skills

- **`pricing`**: decides what to charge; this skill makes the charging mechanism correct.
- **`bosskuai-paid-acquisition-monetization`**: plans monetization strategy; this skill runs the billing system behind it.
- **`churn-prevention`**: reduces voluntary churn; this skill reduces involuntary churn from failed payments.
- **`bosskuai-business-logic-review`**: general workflow review; this skill covers billing-specific state machines.

## Separate payment state from entitlement state

This is the decision most billing bugs trace back to. Payment status is what the processor says; entitlement is what the product grants. Model them as two fields with an explicit mapping, so a webhook delay never silently revokes a paying customer's access, and a refund never leaves access on.

## Lifecycle to model explicitly

`trialing → active → past_due → grace → cancelled → refunded`, plus reactivation. For each transition define: what triggers it, what entitlement results, what the customer is told, and whether it is reversible.

## Edge cases that break in production

- **Proration on plan change**: upgrade mid-cycle, downgrade mid-cycle, and downgrade that must take effect at period end.
- **Failed payment**: retry schedule, dunning messaging, when access degrades, and when data is retained but locked.
- **Webhook delivery**: out-of-order events, duplicates, and events for objects the app has not created yet.
- **Refunds and chargebacks**: partial refunds, and whether a chargeback revokes access immediately.
- **Trial abuse**: repeated trials per card, email, or tenant.
- **Currency and tax**: rounding, tax-inclusive vs exclusive display, and invoice fields required for the jurisdiction.
- **Cancellation timing**: immediate vs end-of-period, and what happens to seats and data.

## Guardrails

- Every webhook handler is **idempotent**, keyed on the processor's event id. Assume at-least-once delivery.
- The processor is the source of truth for payment state. Reconcile rather than infer.
- Never grant entitlement from a client-side success callback. Wait for the server-side event.
- Log the payment, invoice, subscription, and refund ids on every billing action for support recovery.
- Provide a manual support path to fix a stuck account without direct database editing.
- Do not test billing changes only on the happy path.

## Output format

```text
Processor: [Stripe / other, and API version if it matters]
State model:
  Payment states: [list]
  Entitlement states: [list]
  Mapping: [payment -> entitlement]

Findings:
  P0/P1/P2 - [flow] - [failure] - [fix]

Webhook handling: [events consumed, idempotency key, retry behavior]
Reconciliation: [how drift is detected and corrected]
Support recovery: [how a human fixes a stuck account]
Verification: [tests or processor test-mode events run]
```

## References

- `../../references/checklists/saas-billing-ops-checklist.md`
