# Engineering Delivery Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when implementing, refactoring, or reviewing code that should follow a disciplined engineering workflow.

## Workflow

1. Classify the task and decide whether planning, docs verification, security review, or implementation should happen first.
2. Read the nearest code, tests, docs, and conventions before editing.
3. For meaningful changes, plan before implementation.
4. Prefer test-first or test-guided development when the task changes behavior, fixes a bug, or touches risky code.
5. Implement the smallest safe change that fits the existing architecture.
6. Review the resulting diff for correctness, regressions, security, business logic, and missing tests.
7. Run the relevant verification steps available in the repo.
8. Separate confirmed verification from unverified assumptions in the final handoff.

## Output expectation

- task classification
- implementation or review plan
- affected files and risks
- testing and verification strategy
- security-sensitive areas
- final verification status
