# Architecture Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when judging module boundaries, layering, scaling tradeoffs, or structural refactors.

## Workflow

1. Identify the current architecture from code, not only docs.
2. Map the main boundaries, dependencies, and side-effect paths.
3. Evaluate whether the requested change fits the current structure.
4. Separate local fixes from architectural changes.
5. Explain tradeoffs, rejected alternatives, and long-term implications.

## Output expectation

- current architecture summary
- structural risks
- boundary recommendations
- tradeoffs
- suggested direction
