# Engineering Delivery Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Did you classify whether the task needs planning, implementation, review, security review, or docs verification before coding?
- For non-trivial work, did you plan the change before implementation?
- Did you read nearby code, tests, docs, and conventions before editing?
- For new features, bug fixes, or risky refactors, did you use a test-first or test-guided approach where practical?
- Did you cover unit, integration, or end-to-end risks appropriate to the change?
- Did you validate all external or user-controlled inputs at system boundaries?
- Did you avoid hardcoded secrets, insecure defaults, and unsafe error leakage?
- Did you keep the change as the smallest safe change that fits the current architecture?
- Did you review the diff for unintended changes?
- Did you run the relevant verification steps available in this repo, such as build, types, lint, tests, coverage, or security checks?
- If verification could not be completed, did you say exactly what was not verified?
