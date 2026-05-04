# Bosskuai Saas Billing Ops Playbook

## Purpose

Use this for SaaS subscription lifecycle, invoices, failed payments, dunning, tax/receipt flows, plan changes, entitlement gating, and billing operations.

## Operating Principles

- Map lifecycle: trial, active, past_due, cancelled, refunded, grace period.
- Separate payment state from entitlement state.
- Handle webhook idempotency, retry, reconciliation, and manual support recovery.
- Define invoice/receipt/tax fields before launch.
- Verify cancellation, failed payment, plan change, and refund edge cases.

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
