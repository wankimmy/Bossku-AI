---
name: bosskuai-engineering-delivery
description: Use this for disciplined software implementation work that should follow planning-first execution, test-guided development, review-before-finalization, and explicit verification.
---

# BosskuAI Engineering Delivery

Use this skill when the task is **implementation-heavy** and needs a reliable engineering workflow, not just isolated coding advice.

## How this differs from nearby skills

- **`bosskuai-coding-best-practices`**: guidance on how to write good code in a specific moment; this skill is the full delivery workflow around that.
- **`bosskuai-rigorous-code-review`**: gates a finished diff; this skill shapes the process that produces that diff.
- **`bosskuai-planning-execution`**: plans what to build; this skill implements it.
- **`bosskuai-software-architecture`**: designs structure; this skill delivers within that structure.

## Mindset

- The riskiest moment in delivery is when "it works on my machine" becomes "it is in production."
- Tests are not optional — they are the only proof that behavior is correct and stays correct.
- The diff review is the last safety net before merge; treat it as a serious gate.
- Observability and rollback are part of implementation correctness, not afterthoughts.

## Workflow

### Phase 1 — Classify and orient

1. Identify the task type: new feature, bug fix, refactor, integration, migration, or performance work. Each type has different risk and testing needs.
2. Read nearby code, tests, docs, and conventions before editing anything. Understand the current extension points and naming patterns.
3. Check if the change has security, performance, or data-migration implications — flag these before implementing.

### Phase 2 — Plan

4. For meaningful changes, write a short implementation plan:
   - What changes and where?
   - What stays the same?
   - What is the test strategy?
   - What is the rollback strategy?
   - Are there feature flags needed for risky changes?

### Phase 3 — Test-guide, then implement

5. **For bug fixes**: Write a failing test that reproduces the bug before fixing it. The test should pass when the fix is correct.
6. **For new behavior**: Write the test or acceptance criteria first, then implement to make it pass.
7. **For refactors**: Ensure existing tests pass before and after — no behavioral change should occur.
8. Apply the test pyramid: unit tests for logic, integration tests for behavior at module boundaries, E2E or smoke tests for critical user paths.
9. Test error paths, not just happy paths. Test boundary values. Test empty and null states.
10. Implement the **smallest safe change** that fits the current architecture. Flag scope creep.

### Phase 4 — Review the diff

11. Before marking done, review your own diff:
    - Correctness: does it do what it claims?
    - Regressions: does it break anything nearby?
    - Security: any new trust boundaries, secrets exposure, or auth gaps?
    - Business logic: are the rules encoded correctly?
    - Missing tests: are edge cases, failure paths, and the bug path covered?
    - Observability: is there logging or metrics for this in production?

### Phase 5 — Verify

12. Run available verification steps: build, lint, tests, type checks, security scan.
13. Check that migrations are backwards-compatible and the deployment order is safe (no column removal before code removal).
14. Confirm rollback path: can this be reverted or feature-flagged off without data loss?
15. Name what was verified and what could not be verified in the handoff.

## Feature flags

Use feature flags when:
- The change affects a significant user-facing surface
- The change has high rollback risk
- You want to test with a subset of users first
- The change has dependencies on other unreleased work

## Rollback strategy

Every meaningful change should have a rollback answer:
- **Feature flag**: disable the flag to revert behavior without code deployment
- **Migration**: ensure the new schema works with the old code; deploy code first, migrate second; only drop old columns in a later release
- **Service change**: verify old clients still work during the transition window

## Output expectation

```
Task classification: [type + risk level]
Implementation plan: [what changes, where, test strategy, rollback]
Test strategy: [unit/integration/E2E breakdown, what is tested, what is not]
Security-sensitive areas: [if any]
Verification steps run: [list]
Rollback strategy: [feature flag / migration safety / revert path]
Residual risks or gaps: [what could not be verified]
```

## References

- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/playbooks/engineering-delivery-playbook.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
- `../../references/checklists/verification-checklist.md`
