# Bosskuai Qa Automation Strategy Checklist

Use this checklist only when the task clearly needs this domain.

- Start from critical user journeys and high-risk money/auth/data flows.
- Use feature/integration tests for backend invariants; use E2E sparingly for cross-page flows.
- Make tests deterministic with fixtures, factories, and seeded permissions.
- Run fast checks on PR and slower E2E/nightly where useful.
- Every production bug should add one regression test or explicit non-test rationale.

## Release Gate

- Confirm what was verified.
- State what remains unverified.
- Add regression test, metric, SOP, or rollback trigger where applicable.
- Save durable memory only for stable decisions, preferences, constraints, or reusable lessons.
