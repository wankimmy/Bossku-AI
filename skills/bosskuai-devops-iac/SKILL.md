---
name: bosskuai-devops-iac
description: Use for infrastructure as code (Terraform/OpenTofu, Pulumi), environment promotion, release strategies (canary, blue-green, feature flags), drift, and rollback design. Pipeline YAML goes to bosskuai-ci-cd-pipelines; Dockerfiles and Compose to bosskuai-docker.
---

# BosskuAI DevOps / IaC

Use this skill when the main question is **how software is built, shipped, configured, and operated**, not only how the feature code works.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: focuses on application delivery workflow; this skill covers pipeline, deployment, runtime, and infra concerns around that workflow.
- **`bosskuai-docker`**: owns concrete `Dockerfile`, Docker Compose, `.env`, volume, network, and one-command local container startup work.
- **`bosskuai-polyglot-engineering`**: explains stack-specific tooling; this skill designs and reviews the operational lifecycle around those tools.
- **`bosskuai-cybersecurity-risk`**: analyzes security risk; this skill uses that lens specifically for CI/CD, infra, secrets, runners, and supply chain.
- **`bosskuai-ci-cd-pipelines`**: owns the workflow files that drive builds and promotion; this skill owns the infrastructure, runtime, and rollback design they operate against.
- **`bosskuai-aws-deployment`**: maps these principles to concrete AWS services; this skill stays platform-agnostic.
- **`bosskuai-vps-docker-deployment`**: the single-box implementation; this skill supplies the pipeline and infra principles behind it.
- **`bosskuai-observability-sre`**: instrumentation, SLOs, and burn-rate alerting; this skill only checks that a deploy gate reads them.

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
- Are logs structured (JSON or key-value), with consistent fields (trace_id, request_id, service, level, timestamp)?
- Are OpenTelemetry traces instrumented at service entry points and propagated across async boundaries?
- Are SLOs defined? Are error budgets tracked and visible to the team?
- Are distributed trace spans linked across services so a single user request can be followed end-to-end?
- What is the rollback path? What fails closed versus open?

## Workflow

1. **Read the actual operational files** — CI configs, Dockerfiles, compose files, IaC modules, deploy scripts, and environment docs.
2. **Map the delivery path** — source -> build -> test -> artifact -> deploy -> runtime -> rollback.
3. **Identify the control points** — approvals, secrets, drift checks, environment promotion, and rollback triggers.
4. **Review the artifact/runtime boundary** — build images, runtime config, migrations, secrets, and health checks.
5. **Review infra safety** — IaC state handling, module reuse, blast radius, and change review.
6. **Review failure and recovery** — partial deploys, failed migrations, unhealthy pods/services, and rollback or roll-forward strategy.
7. **Recommend the smallest operational hardening slice** — improve reliability and safety without inventing platform theater.

## Verification

```bash
terraform fmt -check && terraform validate
terraform plan -detailed-exitcode   # exit 2 means pending changes or drift
# plus the repo's IaC linters/scanners: tflint, trivy, checkov
```

## Guardrails

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

## Observability

Instrumentation, SLOs, and burn-rate alerting live in `bosskuai-observability-sre`; this skill only checks that a deploy gate reads them.

## Deployment verification

Use these patterns when a deployment touches a high-risk surface or when the team needs more confidence than a green CI pipeline provides.

### Canary analysis
1. Deploy to a small traffic slice (1–5%) first.
2. Define success criteria before the canary: error rate, latency p99, business KPI (e.g. conversion rate) must stay within X% of baseline.
3. Monitor for a defined bake time (minimum 10–15 minutes for stateless services; longer for stateful or async flows).
4. If success criteria hold: promote to 100%. If not: rollback immediately.
5. Never skip bake time to accelerate a release — that defeats the purpose.

### Blue-green health checks
- The "green" (new) environment must pass all health checks before traffic switches.
- Health checks must verify: application startup, DB connectivity, downstream dependency reachability, and at least one synthetic transaction.
- Keep "blue" (old) environment live for a minimum rollback window (e.g. 15 minutes) after full traffic switch.
- Do not tear down blue until the rollback window has passed and metrics are nominal.

### Feature flag–based deployment
- For high-risk changes, deploy the code (flag-off) separately from enabling it (flag-on).
- This decouples deployment risk from release risk.
- Define the flag cleanup date at creation time — feature flags left on indefinitely become technical debt.

## References

- `../../references/checklists/devops-iac-checklist.md`
