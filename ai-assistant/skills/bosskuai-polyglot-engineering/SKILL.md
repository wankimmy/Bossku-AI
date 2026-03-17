---
name: bosskuai-polyglot-engineering
description: Use this for engineering guidance across programming languages, frameworks, runtimes, and ecosystem-specific tradeoffs rather than assuming one default stack.
---

# BosskuAI Polyglot Engineering

Use this skill when the task spans or depends on multiple languages, frameworks, or runtimes.

## Workflow

1. Identify the actual stack and maturity level.
2. Prefer native framework patterns and the repository's current conventions over generic abstractions.
3. Prefer minimal safe changes that fit the current stack before proposing bigger rewrites.
4. Apply coding best practices in a stack-aware way, including maintainability, readability, testing discipline, and safe error handling.
5. Explain what advice is universal versus stack-specific.
6. Surface tooling, migration, deployment, and ecosystem tradeoffs.
7. Recommend the most defensible approach for the stack in use.

## Output expectation

- stack-aware guidance
- framework-native recommendation
- repo-consistent implementation direction
- stack-aware best-practice guidance
- tradeoffs
- implementation direction

## References

- `../../references/playbooks/polyglot-engineering-playbook.md`
- `../../references/checklists/polyglot-engineering-checklist.md`
