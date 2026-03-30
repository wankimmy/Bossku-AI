# Root Cause Investigation Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What is the exact expected behavior, actual behavior, scope, environment, and time window?
- What entity IDs, request IDs, job IDs, webhook IDs, or idempotency keys link the evidence together?
- What does the business flow look like from trigger to final side effect?
- What does the database state show before, during, and after the incident?
- What do logs, queue/job traces, webhook histories, or external system records show?
- Which business invariant first breaks, and where?
- Is the root cause code logic, data state, async handoff, race/transaction gap, retry/idempotency failure, or config drift?
- What immediate containment, durable fix, test, and monitor would prevent recurrence?
