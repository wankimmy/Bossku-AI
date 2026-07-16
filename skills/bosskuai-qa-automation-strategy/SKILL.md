---
name: bosskuai-qa-automation-strategy
description: Use this for automated testing strategy, Playwright/Cypress, feature tests, integration tests, regression suites, fixtures, CI gates, and release confidence.
---

# Bosskuai Qa Automation Strategy

Use this for automated testing strategy, Playwright/Cypress, feature tests, integration tests, regression suites, fixtures, CI gates, and release confidence.

## Fast Path

1. Start from critical user journeys and high-risk money/auth/data flows.
2. Use feature/integration tests for backend invariants; use E2E sparingly for cross-page flows.
3. Make tests deterministic with fixtures, factories, and seeded permissions.
4. Run fast checks on PR and slower E2E/nightly where useful.

## Default Checks

- Start from critical user journeys and high-risk money/auth/data flows.
- Use feature/integration tests for backend invariants; use E2E sparingly for cross-page flows.
- Make tests deterministic with fixtures, factories, and seeded permissions.
- Run fast checks on PR and slower E2E/nightly where useful.
- Every production bug should add one regression test or explicit non-test rationale.

## When To Open The Playbook

Open `../../references/playbooks/bosskuai-qa-automation-strategy-playbook.md` only when the task needs detailed workflow, implementation examples, or release-grade depth.

## Output Quality

- Start with the verdict or action.
- Separate confirmed facts, assumptions, and risks.
- Include exact files, commands, tests, metrics, or rollback triggers when relevant.
- Do not claim legal, security, or cost certainty without evidence.

## References

- `../../references/playbooks/bosskuai-qa-automation-strategy-playbook.md`
- `../../references/checklists/qa-automation-strategy-checklist.md`
