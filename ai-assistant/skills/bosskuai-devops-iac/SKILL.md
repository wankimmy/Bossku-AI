---
name: bosskuai-devops-iac
description: Use this for CI/CD, containers, deployment workflows, infrastructure as code, secrets handling, environment promotion, rollback design, and delivery reliability across operational systems.
---

# BosskuAI DevOps / IaC

Use this skill when the main question is **how software is built, shipped, configured, and operated**, not only how the feature code works.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: focuses on application delivery workflow; this skill covers pipeline, deployment, runtime, and infra concerns around that workflow.
- **`bosskuai-polyglot-engineering`**: explains stack-specific tooling; this skill designs and reviews the operational lifecycle around those tools.
- **`bosskuai-cybersecurity-risk`**: analyzes security risk; this skill uses that lens specifically for CI/CD, infra, secrets, runners, and supply chain.

## Mindset

- If you cannot reproduce it, you cannot trust it.
- Deployability is part of architecture, not an afterthought.
- The fastest pipeline is the one that fails early and rolls back safely.
- Secrets, runners, and IaC state are production attack surfaces.

## Operational lenses

**Build and artifact integrity**
- Are builds deterministic enough to trust?
- Are artifacts immutable and traceable to source?
- Is dependency provenance visible?

**CI/CD flow**
- Do checks fail early with useful signals?
- Are promotion environments explicit?
- Is rollback or re-deploy obvious and safe?

**Containers and runtime**
- Are images minimal, reproducible, and non-root where possible?
- Are env vars, volumes, and health checks explicit?
- Does runtime config drift from IaC or repo defaults?

**Infrastructure as code**
- Who owns state and change approval?
- Are plans reviewed before apply?
- Is drift detection part of the workflow?

**Secrets and supply chain**
- Are secrets injected securely rather than committed or baked into images?
- Are CI runners over-privileged?
- Are third-party actions/modules pinned and reviewed?

**Observability and recovery**
- Are logs, metrics, alerts, and health checks enough to operate safely?
- What is the rollback path?
- What fails closed versus open?

## Workflow

1. **Read the actual operational files** — CI configs, Dockerfiles, compose files, IaC modules, deploy scripts, and environment docs.
2. **Map the delivery path** — source -> build -> test -> artifact -> deploy -> runtime -> rollback.
3. **Identify the control points** — approvals, secrets, drift checks, environment promotion, and rollback triggers.
4. **Review the artifact/runtime boundary** — build images, runtime config, migrations, secrets, and health checks.
5. **Review infra safety** — IaC state handling, module reuse, blast radius, and change review.
6. **Review failure and recovery** — partial deploys, failed migrations, unhealthy pods/services, and rollback or roll-forward strategy.
7. **Recommend the smallest operational hardening slice** — improve reliability and safety without inventing platform theater.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not add platform complexity that the current team cannot operate.
- Do not recommend manual hotfix culture over reproducible deploy paths.
- Do not treat CI secrets, runners, or third-party actions as implicitly trusted.
- Do not design rollback as a hand-wavy future concern.

## Output format

```text
Delivery path:
  [source -> build -> test -> deploy -> runtime]

Operational risks:
  CI/CD: [findings]
  Containers/runtime: [findings]
  IaC/state: [findings]
  Secrets/supply chain: [findings]
  Observability/recovery: [findings]

Hardening recommendations:
  [change] — [why] — [smallest safe implementation]

Rollback and failure handling:
  [current state] — [gap] — [improvement]
```

## References

- `../../references/checklists/devops-iac-checklist.md`
- `../../references/playbooks/devops-iac-playbook.md`
