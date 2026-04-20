---
name: bosskuai-coding-best-practices
description: Use this for implementation quality, maintainability, readability, testing expectations, error handling, naming, and pragmatic best-practice checks that still fit the current repo conventions.
---

# BosskuAI Coding Best Practices

Use this skill when writing or reviewing code that should be easy to change, hard to misuse, and aligned with the existing codebase.

## Operating principles

- Prefer boring, readable code over clever code.
- Follow current repo conventions unless they are clearly harmful.
- Optimize for the three expensive failures: wrong behavior, production breakage, and code that is hard to change.
- For new behavior, bug fixes, and risky refactors: prefer test-first or test-guided work when practical.
- Debug with hypotheses, not random edits.

## What good code looks like

- single-responsibility functions and modules
- explicit side effects and error paths
- meaningful names
- shallow control flow with guard clauses
- no hidden shared mutable state
- validation at system boundaries
- tests or verification that prove the claimed behavior

## Common problems to catch

- god functions or objects
- primitive obsession where domain values need stronger types
- boolean traps and unreadable call sites
- swallowed exceptions
- magic values
- copy-paste logic drift
- shotgun surgery across too many files for one behavior

## Local decision guide

- local cleanup: one function or module is unclear but isolated
- revamp: the same structural problem repeats across many files
- architecture escalation: boundaries or contracts are the real problem

Load `bosskuai-code-revamp` or `bosskuai-software-architecture` when the issue is bigger than a local fix.

## Verification bias

- reproduce before fixing when debugging
- add or run the narrowest test that proves the behavior
- re-read the saved diff before declaring success
- call out what could not be verified

## Guardrails

- Do not impose a foreign pattern or abstraction just because it is fashionable.
- Do not add abstraction without repeated need.
- Do not mix a broad refactor into a bug fix unless the user asked for both.
- Do not mirror a bad existing pattern silently; fix proportionally and call it out.

## Output

Return:

- conventions observed
- findings or improvement opportunities
- whether the change should stay local, become a revamp, or escalate
- testing guidance or verification gaps

## References

- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/playbooks/coding-best-practices-playbook.md`
- `../../references/checklists/engineering-delivery-checklist.md`
