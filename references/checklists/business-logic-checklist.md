# Business Logic Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- What are the core entities and state transitions?
- What must never happen?
- What approvals, handoffs, or ownership changes exist?
- What happens on retries, duplicates, partial completion, and cancellation?
- Who can override or reverse the action?
- Are time-based rules, thresholds, or SLAs defined?
- What are the side effects and downstream dependencies?
