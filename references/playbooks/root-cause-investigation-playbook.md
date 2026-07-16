# Root Cause Investigation Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when debugging incidents or high-value bugs that require more than a code read.

## Workflow

1. Define the incident window, affected entities, and expected vs actual behavior.
2. Trace the business flow through code, DB writes, jobs, queues, webhooks, and external calls.
3. Build a timeline from logs, DB state, and operational evidence.
4. Identify the earliest broken invariant and classify the root cause.
5. Recommend containment, durable fix, and the missing test or monitor.

## Output expectation

- incident summary
- evidence timeline
- confirmed root-cause findings
- data/log checks used
- containment and durable fix
