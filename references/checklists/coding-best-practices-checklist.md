# Coding Best Practices Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Have you read the nearby code and current project conventions first?
- For meaningful changes, did you plan before implementing?
- For behavior changes or risky fixes, did you use a test-first or test-guided approach where practical?
- Does the change fit the existing module structure and naming patterns?
- Is the solution simple, readable, and easy to maintain?
- Are responsibilities kept in the right place instead of being mixed together?
- Is validation and error handling appropriate for the stack and use case?
- Are functions and files reasonably focused, without avoidable deep nesting or sprawling logic?
- Does the change remain testable, even if tests are not being added in this step?
- Are edge cases and failure paths handled cleanly?
- Is the change the smallest safe fix before considering a broader refactor?
- Did you review the diff and run the relevant verification steps available in the repo?
