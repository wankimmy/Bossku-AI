# DevOps / IaC Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when reviewing CI/CD, containers, deployment flows, or infrastructure as code.

## Workflow

1. Map the delivery path from source to runtime.
2. Review build integrity, promotion gates, and rollback handling.
3. Inspect container/runtime config and infra state ownership.
4. Review secrets, runners, and supply-chain exposure.
5. Recommend the smallest operational hardening slice with clear recovery impact.

## Output expectation

- delivery-path summary
- operational risk map
- rollback and recovery assessment
- secrets/supply-chain concerns
- prioritized hardening step
