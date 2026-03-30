---
name: bosskuai-business-logic-review
description: Use this for workflow review, state transitions, approval and assignment design, edge cases, rule consistency, and finding hidden business logic flaws in products or systems.
---

# BosskuAI Business Logic Review

Use this skill when the main risk is **incorrect workflow rules**, not syntax errors or UI polish.

## How this differs from nearby skills

- **`bosskuai-rigorous-code-review`**: catches implementation bugs and code quality issues; load alongside when a code diff may encode wrong rules.
- **`bosskuai-bug-finding`**: traces known failure paths; load when a specific defect is already suspected.
- **`bosskuai-root-cause-investigation`**: correlates business-rule failures with logs, DB rows, job traces, and real incident evidence.
- **`bosskuai-product-strategy`**: defines what the rules should be; this skill audits what they actually are.
- **`bosskuai-cybersecurity-risk`**: checks whether rules can be exploited; load alongside when auth, permissions, or financial logic is involved.

## Concrete examples (hidden logic failures)

Use these as patterns to hunt for in any domain — they are not exhaustive.

1. **Fulfillment without payment verification** — Orders move to `SHIPPED` or digital entitlements activate when `payment_status` is still `PENDING` or when only a client-side “success” page is shown. Webhook delay or PSP failure leaves inventory or access granted without settled funds.

2. **Partial cancellation, full charge** — User removes line items from a subscription or cart after a billing job runs; the next invoice still uses stale quantities or proration is skipped because cancel and bill are separate cron paths with no shared invariant check.

3. **Concurrent inventory oversell** — Two checkouts pass “stock > 0” reads and both decrement; no transactional guard or idempotent reservation, so sellable quantity goes negative or orders are accepted when stock is zero.

## Mindset

- A business logic bug is often silent — it produces wrong outcomes without throwing errors.
- Rules are only as safe as their **invariants**: what must always or never be true.
- State machines are the right mental model for most workflow logic.
- Cross-module side effects are the most dangerous — a rule change in one place silently breaks another.

## Workflow

1. **Map entities and lifecycle states** — For each core entity (order, user, invoice, job, booking), list all possible states and the transitions between them. Ask: are there states the system can reach that it should not?

2. **Define invariants** — Ask what must always be true and what must never happen:
   - "An order can only be fulfilled if payment is confirmed."
   - "A user cannot approve their own request."
   - "A balance cannot go negative without explicit overdraft permission."
   List these explicitly; they become the test of the review.

3. **Trace the happy path and failure paths** — Follow the full flow from trigger to terminal state. Then ask: what happens at each step if the expected precondition is false?

4. **Check handoffs and ownership transitions** — Who holds responsibility at each state? What happens if the handoff is missed? Who can undo or override?

5. **Check approval, retry, and reversal logic**:
   - Can approvals be bypassed or skipped under any conditions?
   - Does retry produce duplicates or unintended side effects?
   - Is reversal (refund, cancel, undo) handled at every state where it is reachable?

6. **Check time-based and threshold rules** — Deadlines, expiry, rate limits, quota checks, SLA breaches. Ask: what happens exactly at the boundary?

7. **Check cross-module side effects** — When state changes in one module, what does it trigger elsewhere? Are all downstream consumers aware of every possible transition?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not confuse input validation with business rules — they are different concerns.
- Do not assume that because a rule is implemented it is the right rule.
- Do not skip edge states (empty, deleted, expired, locked) — most logic bugs live there.
- Prefer state machine diagrams over prose when mapping transitions.

## Output format

```
Entity map:
  [entity] — states: [list] — transitions: [list]

Invariants:
  [list of "must always / never" rules]

Violations found:
  [entity/state/rule] — [what the code does] — [what it should do]

Dangerous assumptions:
  [assumption] — [what breaks if false]

Edge cases:
  [case] — [behavior] — [risk]

Recommended rule changes:
  [change] — [reason] — [smallest safe fix]
```

## References

- `../../references/checklists/business-logic-checklist.md`
- `../../references/pitfalls/business-logic-pitfalls.md`
