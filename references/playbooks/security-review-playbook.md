# Security Review Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when reviewing a feature, workflow, or system for security and operational risk.

## Workflow

1. Identify assets, actors, and trust boundaries.
2. Trace who can read, write, approve, and override.
3. Check abuse paths, privilege escalation, data leaks, fraud angles, injection paths, secrets handling, and unsafe defaults.
4. Review auth, authorization, validation, rate limiting, privacy exposure, and recovery paths.
5. Review auditability, observability, and recovery paths.
6. Separate confirmed risks from inferred risks.

## Output expectation

- key risks
- severity and impact
- likely abuse paths
- missing controls
- recommended mitigations
