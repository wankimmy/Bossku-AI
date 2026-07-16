# Bosskuai Saas Billing Ops Checklist

Use this checklist only when the task clearly needs this domain.

- Map lifecycle: trial, active, past_due, cancelled, refunded, grace period.
- Separate payment state from entitlement state.
- Handle webhook idempotency, retry, reconciliation, and manual support recovery.
- Define invoice/receipt/tax fields before launch.
- Verify cancellation, failed payment, plan change, and refund edge cases.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.
