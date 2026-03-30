# Code Revamp Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when the codebase needs more than a local patch but a full rewrite would be too risky.

## Workflow

1. Study the current structure from real code, not assumptions.
2. Identify the smallest safe change that could solve the problem without a revamp.
3. If that is not enough, define why the current structure is blocking correctness, maintainability, or delivery.
4. Preserve stable conventions and framework-native patterns where possible.
5. Break the revamp into incremental slices with explicit behavior-preservation goals.
6. State migration risks, test needs, and rollback options before execution.

## Output expectation

- current structure and constraints
- minimal-change option
- revamp justification
- incremental slices
- verification approach
- migration risks
