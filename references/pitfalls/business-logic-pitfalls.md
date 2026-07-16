# Business logic pitfalls

Recurring workflow and state mistakes that pass tests for the happy path but fail in production.

## Money and inventory

- **Fulfillment without payment confirmation** — shipping or granting access when payment is “pending” or webhook-delayed.
- **Double spend of credits or inventory** — concurrent requests decrementing the same pool without atomic checks or idempotency keys.
- **Refunds that don’t reverse side effects** — stock, usage, or entitlements left wrong after a partial cancel.

## Identity of the transaction

- **Non-idempotent creates** — retries or double-clicks create duplicate orders, invoices, or tickets.
- **Missing idempotency keys** on external side effects (charges, emails, shipments).

## State machines

- **Unreachable or sticky states** — no path to terminal success/failure, or jobs stuck with no timeout or escalation.
- **Illegal transitions** allowed via a secondary code path (admin tool, migration, API v2) that bypasses the main guard.

## Time and boundaries

- **Off-by-one on dates** — subscriptions ending a day early/late across time zones.
- **SLA and expiry** — “expires_at” compared in local time vs UTC inconsistently.

## Permissions and segregation

- **Self-approval** — same user submits and approves, or role checks only on the UI.
- **Tenant leakage** — filtering by `user_id` but not `org_id` (or equivalent) on shared tables.

## Cross-module effects

- **Event consumers assuming order** — downstream assumes event A always arrives before B.
- **Silent partial failure** — one service succeeds while a compensating action in another fails with no saga or reconciliation.
