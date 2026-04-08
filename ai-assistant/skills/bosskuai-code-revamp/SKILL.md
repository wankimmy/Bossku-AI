---
name: bosskuai-code-revamp
description: Use this for safe code modernization, structural cleanup, legacy refactors, and revamps that should still respect the current codebase structure and avoid unnecessary churn.
---

# BosskuAI Code Revamp

Use this skill when the right answer is bigger than a local patch but still needs to be grounded in how the current codebase works.

## How this differs from nearby skills

- **`bosskuai-coding-best-practices`**: fixes quality at the local implementation level; this skill handles structural changes that span multiple modules.
- **`bosskuai-software-architecture`**: redesigns system boundaries and architecture; this skill focuses on safe code-level structural refactoring within existing architecture.
- **`bosskuai-engineering-delivery`**: manages the full delivery workflow; this skill supplies the revamp strategy within that workflow.
- **`bosskuai-codebase-analysis`**: reads and maps the current structure; load it first before justifying any revamp.

## Mindset

- A revamp is justified only when the current structure materially blocks maintainability, correctness, delivery speed, or safety.
- The enemy of a good revamp is an all-at-once rewrite — prefer incremental slices with passing tests at each step.
- Behavior preservation is non-negotiable. If a revamp changes observable behavior, it is no longer a refactor.
- Every revamp needs a rollback path — if slice 3 of 5 breaks production, can you ship without it?
- Simplification is the highest form of revamp — reduce cyclomatic complexity, eliminate dead code, and flatten deep nesting before restructuring.

## Is a revamp justified?

Apply this decision matrix before proposing structural changes:

| Condition | Signal | Verdict |
|-----------|--------|---------|
| Bug is in one function | Isolated defect | Local fix — no revamp |
| Pattern is bad in one module | Localized tech debt | Local improvement |
| Same bad pattern in 5+ modules | Systemic tech debt | Revamp candidate |
| Current structure blocks a required feature | Delivery blocked | Revamp justified |
| Tests are impossible because of entanglement | Testability crisis | Revamp justified |
| Security or data-integrity risk from current structure | Safety risk | Revamp justified (urgent) |
| Someone dislikes how the code looks | Preference | No — follow existing patterns |

**Rule**: If you cannot name the concrete harm caused by the current structure, the revamp is not justified yet.

## Migration patterns

### Strangler Fig (preferred for large legacy migrations)
1. Build the new module alongside the old one.
2. Route new traffic to the new module; old traffic still goes to legacy.
3. Incrementally migrate use cases until the old module has zero callers.
4. Delete the old module only when all traffic has moved.

### Module extraction (preferred for god classes)
1. Identify the single responsibility that should be extracted.
2. Create the new module with a clean interface.
3. Replace each call site one by one — do not bulk-replace.
4. Delete the old implementation when all call sites are updated.

### Interface introduction (preferred for tightly coupled dependencies)
1. Define the interface that the dependent code should rely on.
2. Implement the interface against the existing code (wrap it).
3. Update consumers to use the interface.
4. Replace the implementation later as needed.

## Workflow

0. **Simplify first**: Before any structural revamp, run a simplification pass — remove dead code and unused imports, flatten nested conditionals (replace nested ternaries with early returns or switch statements), improve naming for clarity, and reduce nesting depth. If simplification alone resolves the problem, stop here.
1. **Read first**: Use `bosskuai-codebase-analysis` to map the current structure before proposing any changes.
2. **Separate local from structural**: Is this fixable with a 10-line change? If yes, it is not a revamp.
3. **State the justification**: Name the concrete harm that the revamp will resolve (delivery blocked, safety risk, testability crisis).
4. **Define behavior that must stay stable**: Write or locate the tests that prove behavior is unchanged after the revamp.
5. **Choose the migration pattern**: Strangler fig / module extraction / interface introduction — state which and why.
6. **Slice the work**: Define 2–5 independently deployable slices, each with a clear exit criterion.
7. **Define rollback**: How do you revert each slice without data loss or broken deployments?
8. **Validate**: After each slice, run tests, lint, type checks, and manual smoke tests.

## Migration risk matrix

| Risk | How to mitigate |
|------|----------------|
| Breaking callers | Map all call sites before changing a module; update atomically |
| Behavior regression | Lock existing behavior in tests before refactoring |
| Schema/data migration | Ensure backward-compatible schema changes; deploy code before data migration |
| Deployment ordering | Old code must work with new schema; new code must work with old schema until cutover |
| Incomplete migration | Feature flag to switch between old/new path during transition |

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not start a revamp without a passing test suite that covers the behavior to be preserved.
- Do not do a "big bang" rewrite of a system that is currently in production — always slice.
- Do not mix refactoring and feature work in the same PR or branch.
- Do not delete old code until the new path has been proven in production.
- Do not revamp just because the code is old — age is not a defect. Concrete harm is the bar.

## Output format

```
Revamp justified?
  Current structure harm: [specific problem — delivery blocked / safety risk / testability crisis]
  Verdict: [yes / no / local fix sufficient]

Minimal-change alternative:
  [describe the local fix that avoids a full revamp, if one exists]

Revamp direction (if justified):
  Migration pattern: [Strangler Fig / Module Extraction / Interface Introduction]
  Target structure: [description of the end state]
  Behavior that must stay stable: [list]

Migration slices:
  Slice 1: [what changes + exit criterion + rollback if this slice fails]
  Slice 2: [what changes + exit criterion + rollback]
  ...

Risk matrix:
  [risk] — [mitigation]

Rollback strategy:
  [how to revert the revamp at each slice point]

Residual risks:
  [what cannot be fully mitigated]
```

## References

- `../../references/playbooks/code-revamp-playbook.md`
- `../../references/checklists/code-revamp-checklist.md`
