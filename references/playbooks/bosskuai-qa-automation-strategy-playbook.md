# Bosskuai Qa Automation Strategy Playbook

## Purpose

Use this for automated testing strategy, Playwright/Cypress, feature tests, integration tests, regression suites, fixtures, CI gates, and release confidence.

## Operating Principles

- Start from critical user journeys and high-risk money/auth/data flows.
- Use feature/integration tests for backend invariants; use E2E sparingly for cross-page flows.
- Make tests deterministic with fixtures, factories, and seeded permissions.
- Run fast checks on PR and slower E2E/nightly where useful.
- Every production bug should add one regression test or explicit non-test rationale.

## Review Flow

1. Define the user/business impact.
2. Identify the trust boundary, data boundary, cost boundary, or operational boundary.
3. Inspect the smallest source-of-truth files first.
4. Propose the smallest safe change.
5. Add verification: test, metric, log, alert, rollback trigger, or customer signal.
6. Save durable learning only when it changes future behavior.

## Anti-patterns

- Optimizing a non-measured problem.
- Making broad architecture claims without repo evidence.
- Skipping rollback, audit, or support recovery.
- Storing secrets, temporary instructions, or untrusted claims in memory.
- Using generic SaaS advice without product-stage context.

## Done Bar

- Clear recommendation.
- Concrete implementation or SOP.
- Verification path.
- Main risk and rollback.
- Memory/handoff updated when useful.
