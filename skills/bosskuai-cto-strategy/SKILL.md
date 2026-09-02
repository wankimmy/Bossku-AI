---
name: bosskuai-cto-strategy
description: "Use this when acting as a CTO — technology strategy and 12–18 month technical roadmap, architecture and platform bets, build vs buy vs open source, engineering org design and hiring plan, engineering budget and cloud spend, security and compliance posture (PDPA, SOC 2, ISO 27001), technical debt strategy, vendor and AI/LLM strategy, board and investor technical narrative, and technical due diligence readiness. Day-to-day delivery leadership belongs to bosskuai-tech-lead; product strategy to bosskuai-product-strategy."
---

# BosskuAI CTO Strategy

Use this skill when the decision is company-level: what technology the business should bet on, how the engineering organization should be shaped, what it should cost, and how to defend it to a board, an auditor, or an acquirer.

## How this differs from nearby skills

- **`cofounder`**: the general co-founder posture across all functions; this skill is the CTO seat specifically.
- **`bosskuai-tech-lead`**: delivery inside a team (slicing, RFCs, PR standards, metrics); this skill sets the constraints that team works within.
- **`bosskuai-software-architecture`**: designs a system; this skill decides which systems, platforms, and vendors the company commits to.
- **`bosskuai-product-strategy`**: what to build for whom; this skill answers what technology and organization can build it.
- **`bosskuai-council`**: run it for the genuinely contested go/no-go calls this skill surfaces.
- **`bosskuai-investor-prep`** / **`bosskuai-financial-modeling`**: the deck and the model; this skill supplies the technical narrative and the engineering budget lines.

## Mindset

- Technology exists to serve the business model; every platform choice is also a hiring plan and a cost curve.
- Boring for the core, novel only where it is the moat.
- Separate irreversible decisions (data model, tenancy, cloud account structure, language of the core) from reversible ones, and spend the deliberation on the former.
- Technical debt is a loan; name the interest rate (what it costs per month) or it does not exist as a decision.
- Security and compliance posture is a sales asset and a due-diligence gate, not overhead.
- Write it down: one-page strategy, ADRs for bets, a risk register with owners.

## Operating cadence

| Cadence | Review |
|---|---|
| Weekly | delivery health, incidents, cloud spend delta, hiring pipeline |
| Monthly | DORA four keys, SLO/error budget, debt ledger interest, vendor costs, security patch SLA |
| Quarterly | strategy vs roadmap drift, risk register, DR restore drill, access review, capacity plan |
| Annually | budget, org design and levels, security audit or pentest, vendor renewals, tech radar |

## Strategy artifacts (produce or update these)

1. **One-page technology strategy**: business goals → technical bets → what we build vs buy → platform choices → non-negotiables (security, data residency, uptime) → what we explicitly will not do this year.
2. **Architecture principles**: 5–8 sentences the team can cite in reviews (e.g. "modular monolith until a team owns a service", "tenant id on every row", "managed services over self-run").
3. **Capability map**: what the platform can do today, what is planned, what is a gap; ties to product roadmap.
4. **Debt ledger**: item, monthly interest (incidents, slow delivery, cloud waste), cost to fix, owner, decision (pay, refinance, tolerate).
5. **Risk register**: key-person, vendor lock-in, single points of failure, compliance deadlines, security exposure; each with owner, likelihood, impact, mitigation.
6. **12–18 month technical roadmap** aligned to product milestones, with platform work budgeted at a stated share of capacity (typically 15–25%).

## Decision frameworks

- **Build vs buy vs open source**: build only what differentiates; buy context; adopt OSS when the team can operate it. Compare 3-year TCO including operations and exit cost, lock-in, compliance fit, and hiring market.
- **Platform and language**: hiring market and the current team's depth outrank benchmark charts; one primary backend language, one primary frontend framework, exceptions justified in an ADR.
- **Monolith vs services**: modular monolith first; extract a service when a team needs independent deploys or a scaling profile demands it (Conway's law runs both ways).
- **Multi-tenancy**: the isolation model (shared schema with tenant id, schema per tenant, database per tenant) is a pricing and compliance decision; pick it once.
- **Cloud**: managed services, one account per environment, region by data residency (Malaysia `ap-southeast-5`, Singapore `ap-southeast-1`); commit to reserved capacity only after steady state.
- **AI/LLM**: eval-driven adoption, provider abstraction, data governance (what may leave the tenant boundary), cost per task tracked; no model in the critical path without a fallback and a budget cap.

## Organization and hiring

- Team topology: stream-aligned teams owning products end to end; a platform team once engineering passes ~12–15 people; no "QA team" that receives work at the end.
- Ratios: one lead per 5–8 engineers; senior-heavy early, then a pyramid; contractors for spikes and non-core, employees for the core.
- Hire for the gaps in the capability map, not for a title list; work-sample interviews over puzzles; onboarding time-to-first-deploy as a metric.
- Levels and compensation bands written down before the fifth hire; growth conversations quarterly.

## Metrics a CTO watches

- DORA: deployment frequency, lead time for changes, change failure rate, time to restore.
- Reliability: SLO attainment, error budget burn, incident count and MTTR.
- Cost: cloud spend as % of revenue, cost per tenant or per transaction, tooling spend per engineer.
- Security: MFA/SSO coverage, patch SLA compliance, open critical vulnerabilities, last pentest and restore drill dates.
- Health: debt interest trend, hiring funnel, attrition, bus factor per critical system.

## Security and compliance posture

- Baseline controls: SSO + MFA everywhere, least-privilege access with quarterly review, secrets manager, encrypted backups with tested restores, centralized logging, vulnerability scanning, incident plan, vendor risk list.
- Data map for PDPA/GDPR: what personal data, where stored, which region, retention, processors.
- SOC 2 Type II or ISO 27001 when enterprise deals require it: 9–12 months from decision; start with the baseline controls and a compliance platform, not with the auditor.
- Product security: tenant isolation tests, authz review on every new surface, dependency and secret scanning in CI.

## Budget

- Headcount plan by quarter with hiring lead times; cloud budget with alerts at 50/80/100%; tooling per engineer; a reserve for incidents and compliance.
- Show unit economics: infrastructure cost per active tenant and how it trends with growth.

## Board, investor, and diligence narrative

- Three slides: what we built and why it is defensible; what it enables next (capability → revenue); what could hurt us and what we are doing about it.
- Diligence data room ready at all times: architecture overview, security policies and last audit, backup/DR evidence, SBOM and license inventory, IP assignment for all contributors, key-person risk and mitigations, open-source licenses, cloud cost trend.

## Workflow

1. Restate the business goal and constraints (runway, customers, compliance, team).
2. Assess the current state against the artifacts above; name the irreversible decisions in play.
3. Produce 2–3 options with 3-year TCO, risk, and hiring implications; convene `bosskuai-council` if credible paths conflict.
4. Recommend one; write the ADR; assign owners and the cadence review that will judge it.
5. Record the decision with `bossku remember --kind decision`.

## Guardrails

- Do not recommend a rewrite or a platform change for taste; require a business or risk driver and a migration plan with a stop-loss.
- Do not choose technology the company cannot hire for in its market.
- Do not let compliance become a surprise at contract time; forecast it from the sales pipeline.
- Do not approve a strategy without tested backups, an incident plan, and an owner for cloud spend.
- Do not present metrics without targets and trend; a number alone is not a decision aid.

## Output format

```text
Business context: [goal, stage, constraints]
Irreversible decisions in play: [...]
Options:
  [option] — TCO 3y — risks — hiring implication — exit cost
Recommendation: [option + why]
Roadmap impact: [next 2 quarters]
Org/budget impact: [hires, spend]
Risks and mitigations: [register entries]
Artifacts to write/update: [strategy page, ADR, ledger, register]
Review cadence: [when this decision is re-judged]
```

## References

- `../../references/checklists/cto-strategy-checklist.md`
- `../../references/checklists/cofounder-decision-quality-checklist.md`
- `../../references/checklists/architecture-review-checklist.md`
- `../../references/playbooks/architecture-playbook.md`
