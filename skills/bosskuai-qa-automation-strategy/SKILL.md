---
name: bosskuai-qa-automation-strategy
description: Use this for automated testing strategy, Playwright/Cypress, feature tests, integration tests, regression suites, fixtures, CI gates, and release confidence.
---

# BosskuAI QA Automation Strategy

Use this skill when deciding **what to test, at which level, and what gates a release** — not when writing one specific test.

## How this differs from nearby skills

- **`test-driven-development` / `bosskuai-tdd-loop`**: drive a single unit of code test-first; this skill designs the suite above them.
- **`bosskuai-integration-testing`**: designs contracts and test doubles at module seams; this skill allocates effort across all levels.
- **`bosskuai-browser-automation`**: drives a browser for a task; this skill decides when a browser test is worth its cost.
- **`bosskuai-laravel-verification`**: Laravel-specific verification commands; this skill is framework-neutral.

## Allocate by risk, not by coverage percentage

Start from the flows where failure is expensive: authentication, payments, permissions and tenancy, data deletion, and anything that sends money or mail. Cover those deeply. Coverage percentage is a weak target; an untested refund path at 90% coverage is still an untested refund path.

## Choose the cheapest level that can catch the bug

- **Unit**: pure logic, calculations, state machines, edge-case branches. Fast, run on every change.
- **Feature/integration**: the level most backend invariants belong at — real database, real routing, faked third parties. Best value per test.
- **Contract**: the boundary with an external service, so an upstream change fails locally rather than in production.
- **End-to-end**: only for genuine cross-page or cross-system journeys. Slowest and most brittle; keep the set small and stable.

Push a test down a level whenever the same bug would be caught there.

## Determinism is the whole game

A flaky suite is worse than a small suite, because it teaches the team to re-run instead of investigate. Enforce: seeded factories and fixtures, controlled clock, no reliance on test execution order, no live network calls, explicit waits on state rather than sleeps, and isolated per-test data.

## CI gating

Fast checks (lint, types, unit, feature) block the PR. Slow suites (full E2E, cross-browser, performance) run on merge or nightly, with a named owner for failures. A gate nobody can fix gets disabled within a month.

## Guardrails

- Every production bug adds one regression test, or a written reason it does not.
- Do not assert on implementation details that change with every refactor.
- Do not mock the thing under test.
- Quarantine flaky tests with an owner and a deadline; never with a permanent skip.
- Test data must not depend on the state of a shared environment.

## Output format

```text
Risk map: [flow] - [failure cost] - [current coverage]

Recommended allocation:
  Unit: [what belongs here]
  Feature/integration: [what belongs here]
  Contract: [external boundaries]
  E2E: [the short list]

Gaps:
  P0/P1/P2 - [untested risk] - [test to add] - [level]

CI gates: [what blocks PR / what runs nightly]
Flake risks: [sources of nondeterminism found]
Verification: [suite actually run, and result]
```

## References

- `../../references/checklists/qa-automation-strategy-checklist.md`
