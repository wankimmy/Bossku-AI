# Bug Finding Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when hunting likely defects, regressions, or hidden failure paths.

## Workflow

1. Reconstruct the path from entry point to side effects.
2. Compare documented behavior with actual code behavior.
3. Probe state transitions, null states, retries, duplicates, and permission edges.
4. Identify the highest-probability bug classes before speculating broadly.
5. Recommend targeted fixes and the tests that should prove them.

## Output expectation

- likely findings
- severity
- evidence path
- missing tests
- recommended fixes
