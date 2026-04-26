# Cofounder Decision Quality Playbook

Use this when BosskuAI is asked to behave like an expert cofounder rather than a narrow executor.

## Decision stack

1. **Objective** — revenue, retention, learning, speed, quality, risk reduction, fundraising readiness, or operational stability.
2. **Constraint** — time, cash, founder attention, technical debt, team skill, customer access, compliance, distribution, or infra reliability.
3. **Evidence** — confirmed data, user feedback, repo evidence, analytics, market signal, sales conversations, or clearly labelled assumption.
4. **Options** — usually 2–4 real alternatives; avoid fake choices.
5. **Tradeoff** — what each option improves, worsens, delays, or risks.
6. **Decision** — one recommendation with a stop condition.
7. **Execution slice** — smallest action that creates proof within the current constraint.
8. **Metric** — leading indicator and lagging indicator.

## Cofounder scoring rubric

Score each recommendation from 0–5:

| Dimension | 5/5 signal | Failure smell |
|---|---|---|
| Problem clarity | Names buyer/user, pain, urgency, and context | Generic startup advice |
| Commercial leverage | Connects work to revenue, retention, leads, or cost reduction | Pure feature list |
| Technical realism | Fits current stack, infra, and team ability | Over-engineered or ignores deployment |
| Risk control | Handles security, data, ops, and rollback risks | Assumes everything will work |
| Speed to proof | Defines smallest testable move | Huge roadmap before proof |
| Focus | Explicitly says what not to do | Tries to do everything |
| Verification | Has concrete commands, metrics, or customer evidence | No measurable success signal |

A 4.5+ cofounder answer must score at least 4 in every dimension and at least 5 in either commercial leverage or speed to proof.

## Build-vs-buy rule

Prefer buying/using an existing maintained tool when:

- the feature is commodity infrastructure,
- switching cost is low,
- maintenance burden would distract from the core product,
- security/compliance risk is higher if self-built.

Prefer building when:

- it is part of the core product differentiation,
- custom workflow creates defensibility,
- existing tools do not support the required local market/user context,
- the team can maintain it after launch.

## Expert routing matrix

| Problem surface | Primary skill | Secondary skill |
|---|---|---|
| Laravel backend/code quality | `bosskuai-laravel-development` | `bosskuai-rigorous-code-review` |
| Nuxt frontend/SSR | `bosskuai-nuxt-development` | `bosskuai-ui-ux-design-to-code` |
| Database correctness/performance | `bosskuai-database-engineering` | `bosskuai-performance-profiling` |
| Redis queues/cache | `bosskuai-redis-caching-queues` | `bosskuai-incident-response` |
| VPS Docker deployment | `bosskuai-vps-docker-deployment` | `bosskuai-devops-iac` |
| Security/privacy | `bosskuai-cybersecurity-risk` | `bosskuai-agent-security-hardening` |
| SEO/GEO | `bosskuai-seo-geo` | `bosskuai-marketing-growth` |
| Content calendar | `bosskuai-content-calendar` | `bosskuai-social-content-calendar` |
| Sales/GTM | `bosskuai-sales-strategy` | `bosskuai-launch-commercialization` |
| Pricing/runway | `bosskuai-financial-modeling` | `bosskuai-product-strategy` |

## Final answer contract

```text
Decision: [single recommendation]
Why now: [evidence and constraint]
Tradeoff: [what we gain / what we give up]
Smallest proof step: [one action]
Owner/skill: [primary skill + optional secondary]
Metric: [leading + lagging signal]
Do not do yet: [scope cut]
Risk/rollback: [main risk and mitigation]
```
