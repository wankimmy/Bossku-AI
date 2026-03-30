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

### Phase 5 — Verify and apply the Definition of Done

Run the full DoD checklist before declaring the task complete. **Do not declare done until every item passes.**

**Files applied:**
- [ ] Every file change is written to disk — not drafted in a code block, actually saved
- [ ] No planned change is missing from the applied set

**Correctness:**
- [ ] Re-read the original requirement verbatim — implementation satisfies it exactly
- [ ] Edge cases covered: empty input, null/undefined, boundary values, error paths
- [ ] No regressions: adjacent behavior unchanged or intentionally changed and documented

**Verification run:**
- [ ] Build/compile passes without errors
- [ ] All affected tests pass — run them, do not assume
- [ ] Lint/type checks pass if applicable
- [ ] Migrations are backwards-compatible; deployment order is safe

**Triple-check:**
- [ ] Implementation re-read from the actual saved file — not from memory, not from the conversation
- [ ] Self-diff review: the change does exactly what it claims, no more, no less
- [ ] If new tests were written: they actually run and pass

**Security minimum:**
- [ ] No new trust boundaries without validation
- [ ] No secrets hardcoded or logged
- [ ] Auth/permissions unchanged or explicitly reviewed

**Rollback and handoff:**
- [ ] Rollback path confirmed: can this be reverted or feature-flagged off without data loss?
- [ ] Anything that could NOT be verified is named explicitly — do not silently omit gaps

**If any item fails: fix it, then re-run the checklist. Never declare done with failing items.**

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

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not declare done until the full Definition of Done checklist passes — "it works on my machine" is not done.
- Do not mix feature work and refactoring in the same PR — separate commits keep rollback clean.
- Do not skip test-guide for bug fixes — a failing test that reproduces the bug is the proof the fix is real.

## Output format

```
Task classification: [type + risk level]
Implementation plan: [what changes, where, test strategy, rollback]
Test strategy: [unit/integration/E2E breakdown, what is tested, what is not]
Security-sensitive areas: [if any]

Definition of Done — status:
  Files applied: [✅ all saved / ❌ missing: list]
  Requirement satisfied: [✅ confirmed / ❌ gap: describe]
  Edge cases checked: [✅ / ❌ unchecked: list]
  Build/tests pass: [✅ / ❌ failing: describe]
  Triple-check done: [✅ re-read from file / ❌]
  Self-diff review: [✅ clean / ❌ issue found: describe]
  Security minimum: [✅ / ❌ gap: describe]

Rollback strategy: [feature flag / migration safety / revert path]
Residual risks or gaps: [what could not be verified — named explicitly]
```

## References

- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/playbooks/engineering-delivery-playbook.md`
- `../../references/checklists/coding-best-practices-checklist.md`
- `../../references/checklists/security-risk-checklist.md`
- `../../references/checklists/verification-checklist.md`
