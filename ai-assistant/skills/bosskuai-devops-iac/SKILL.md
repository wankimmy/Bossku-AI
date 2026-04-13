---
name: bosskuai-devops-iac
description: Use this for CI/CD, containers, deployment workflows, infrastructure as code, secrets handling, environment promotion, rollback design, and delivery reliability across operational systems.
---

# BosskuAI DevOps / IaC

Use this skill when the main question is **how software is built, shipped, configured, and operated**, not only how the feature code works.

## How this differs from nearby skills

- **`bosskuai-engineering-delivery`**: focuses on application delivery workflow; this skill covers pipeline, deployment, runtime, and infra concerns around that workflow.
- **`bosskuai-docker`**: owns concrete `Dockerfile`, Docker Compose, `.env`, volume, network, and one-command local container startup work.
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

## Observability instrumentation

Use this section when the task involves adding or reviewing observability coverage, not just checking that alerts exist.

### Structured logging
- Every log line should carry: `timestamp`, `level`, `service`, `trace_id`, `request_id`, and a human-readable `message`.
- Logs should be machine-parseable (JSON). Avoid free-form concatenated strings in hot paths.
- Never log PII, secrets, or tokens — even in debug level.
- Log at the boundary, not inside business logic: entry and exit of service calls, errors, and state transitions.

### Distributed tracing (OpenTelemetry)
- Instrument at service entry points: HTTP handlers, queue consumers, cron triggers.
- Propagate the trace context across async boundaries: message queue headers, job payloads, outgoing HTTP headers.
- Add span attributes for: tenant/user ID (non-PII safe), operation name, and outcome (success/error).
- Use sampling for high-volume paths; never sample on errors — always capture error traces.

### SLOs and error budgets
- Define SLOs per service before instrumentation: availability (%), latency (p99 threshold), and error rate (%).
- Derive alerts from SLO burn rates, not raw thresholds — burn-rate alerts reduce noise and catch slow burns.
- Track error budget consumption as a deployment gate: if error budget is exhausted, require manual approval for new deployments.
- SLO targets should be agreed with the product team, not set unilaterally by engineering.

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
- `../../references/playbooks/devops-iac-playbook.md`
