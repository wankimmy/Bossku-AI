---
name: bosskuai-coding-best-practices
description: Use this for implementation quality, maintainability, readability, testing expectations, error handling, naming, and applying coding best practices in a way that still fits the current project conventions.
---

# BosskuAI Coding Best Practices

Use this skill when writing or reviewing code that should follow strong engineering practices without fighting the repository's existing structure.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: the full delivery workflow (plan → test → implement → review → verify); this skill is the quality lens applied within that workflow.
- **`bosskuai-rigorous-code-review`**: gates a diff for correctness and security; this skill shapes how to write good code in the first place.
- **`bosskuai-code-revamp`**: decides whether to refactor broadly; this skill guides code quality at the local implementation level.
- **`bosskuai-codebase-analysis`**: reads and maps the codebase; this skill applies quality standards to what is being written.

## Mindset

- The best code is the code that does exactly one thing well and is boring to read.
- Clever code is a liability — optimize for the next engineer reading it at 2am during an incident.
- Best practices are context-dependent: follow the repo's existing patterns unless they are clearly harmful.
- The three most expensive code problems: wrong behavior, broken in production, impossible to change.

## SOLID applied pragmatically

Apply only what is relevant for the current task and stack:

| Principle | What it means in practice |
|-----------|--------------------------|
| **Single Responsibility** | Each function/class does one thing. If you struggle to name it, it does too much. |
| **Open/Closed** | Prefer extension over modification of stable modules. |
| **Liskov Substitution** | Subtypes must honor the contract of the type they replace. |
| **Interface Segregation** | Small, focused interfaces over large catch-all ones. |
| **Dependency Inversion** | Depend on abstractions at module boundaries; inject at the edges. |

Apply SOLID at the module and function level — not as a reason to add abstraction where none is needed.

## Anti-patterns to catch

| Anti-pattern | Symptom | Fix |
|-------------|---------|-----|
| **God object / god function** | One class/function does everything | Split by responsibility |
| **Primitive obsession** | Raw strings/ints carry domain meaning | Introduce types or value objects |
| **Shotgun surgery** | One change requires editing 10 files | Consolidate behind a single boundary |
| **Feature envy** | Function obsessively accesses another module's internals | Move the behavior to the data owner |
| **Boolean trap** | `process(true, false, true)` — unreadable call sites | Replace with named parameters or enums |
| **Deep nesting** | 4+ indent levels | Early return / guard clauses / extraction |
| **Swallowed exceptions** | `catch (e) {}` or `catch (e) { return null }` | Log, rethrow, or handle explicitly |
| **Magic numbers/strings** | `if (status === 3)` | Named constant or enum |
| **Mutable shared state** | Global mutation, side effects at module init | Encapsulate, make explicit |
| **Copy-paste code** | Same block in 3+ places | Extract — but only when all copies are truly identical in intent |

## Quality checklist by category

### Naming
- Names express intent, not implementation. `getUserByEmail` not `fetchData`.
- Boolean names start with `is`, `has`, `can`, `should`.
- Functions are verbs. Types/classes are nouns.
- Abbreviations only for established domain terms.

### Functions
- Single responsibility — one return path when possible.
- Guard clauses first: handle error/edge cases at the top, happy path last.
- Side effects are explicit — functions that mutate state are named accordingly.
- No surprise mutations of input parameters.

### Error handling
- Errors carry context: what failed, why, what state was it in.
- Never catch an error and return a success value — this is a lie.
- Distinguish recoverable errors (return typed error) from unrecoverable (throw/panic).
- Input validation at system boundaries, not deep in business logic.

### Testability
- Pure functions are always better for logic.
- Side effects isolated to the edges (I/O, network, persistence).
- Avoid global state — it makes tests order-dependent.
- If code is hard to test, it has too many responsibilities.

### Stack-specific guidance
- **TypeScript/JavaScript**: strict mode, no implicit any, prefer const, use discriminated unions for error types, avoid `!` non-null assertions unless provably safe.
- **Python**: type hints for public APIs, avoid mutable default arguments, use dataclasses over dicts for structured data.
- **Go**: explicit error returns, no panic in library code, context propagation for cancellation, table-driven tests.
- **Dart/Flutter**: `const` constructors, `final` fields, prefer `sealed` classes for state, avoid `BuildContext` across async gaps.

## Decision: local fix vs revamp vs escalate

| Situation | Action |
|-----------|--------|
| A single function is unclear or fragile | Fix locally — no need for a broader change |
| A module has consistent bad patterns but is isolated | Improve locally, note in review |
| The pattern is repeated across many files | Propose as part of a code revamp — load `bosskuai-code-revamp` |
| The problem stems from architectural boundaries | Escalate to `bosskuai-software-architecture` |

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not impose patterns from a different stack or paradigm onto the current codebase.
- Do not add abstraction unless there are at least 3 concrete uses — YAGNI.
- Do not refactor code while fixing a bug in the same PR — they are different concerns.
- Do not follow a bad existing pattern just because it is already there — call it out, but fix proportionally.

## Output format

```
Conventions observed:
  Stack: [language / framework / runtime]
  Patterns in use: [naming, error style, test style]
  Quality level: [strong / mixed / weak]

Findings:
  [location] — [anti-pattern or quality issue] — [impact] — [fix]

Improvement recommendations:
  [recommendation] — [why it fits this stack/codebase] — [scope: local / module / revamp]

Decision: local improvement vs revamp vs escalate
  [verdict and reasoning]

Testing guidance:
  [what should be tested that isn't / how to improve testability]
```

## References

- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/playbooks/coding-best-practices-playbook.md`
- `../../references/checklists/engineering-delivery-checklist.md`
