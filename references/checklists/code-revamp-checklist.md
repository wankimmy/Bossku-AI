# Code Revamp Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Have you read the current module structure, adjacent files, and framework conventions first?
- Can the task be solved with a smaller local change that fits the existing architecture?
- What specific pain justifies a revamp: correctness, maintainability, complexity, or delivery speed?
- Which boundaries, names, and patterns should remain stable?
- What behavior must not change during the revamp?
- Can the revamp be split into incremental, testable steps?
- What are the rollback or fallback options if the revamp introduces risk?
