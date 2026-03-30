---
name: bosskuai-launch-commercialization
description: Use this when you need a balanced cofounder workflow across engineering readiness, SEO/GEO, marketing, sales, monetization, country strategy, and product-market-fit planning for launching a product.
---

# BosskuAI Launch Commercialization

Use this skill when the real question is: "Can we launch this product successfully, make it attractive enough to find and buy, and learn our way to product-market fit?"

## How this differs from nearby skills

- **`bosskuai-marketing-growth`**: marketing strategy in depth; this skill calls into it as a component of the full launch.
- **`bosskuai-sales-strategy`**: sales motion and deal qualification in depth; this skill calls into it for the sales component.
- **`bosskuai-seo-geo`**: organic discoverability in depth; this skill calls into it for launch SEO/GEO readiness.
- **`bosskuai-paid-acquisition-monetization`**: paid channels and pricing in depth; this skill calls into it for the monetization component.
- **`bosskuai-product-strategy`**: defines what to build; this skill assesses whether what exists is ready to launch.

Load this skill when you need the full launch picture. Load individual component skills when you need depth in one area.

## Mindset

- A premature launch wastes distribution on a product that cannot retain users. A delayed launch burns runway.
- The minimum launchable product must pass three tests: works, gets found, converts.
- PMF is not declared — it is observed in retention, WOM referral, and inbound pull.
- The launch is not the end. It is the first data collection event for the iteration cycle.

## Launch readiness framework

### Gate 1 — Engineering readiness

| Check | Pass criterion |
|-------|---------------|
| Core user flow | Works end-to-end without manual intervention |
| Critical bugs | No P0/P1 bugs in the launch path |
| Security | Auth, data handling, and injection surfaces reviewed |
| Performance | Page load < 3s, API response < 500ms for key paths |
| Monitoring | Error tracking, uptime monitoring, and alerting in place |
| Rollback | Can you roll back within 15 minutes if something breaks? |

### Gate 2 — SEO/GEO readiness

| Check | Pass criterion |
|-------|---------------|
| Landing page | Answers the search intent in the first paragraph |
| Title and meta | Target keyword in title and meta description |
| Core Web Vitals | LCP < 2.5s, CLS < 0.1 |
| Structured data | Schema.org markup for product/service type |
| GEO signals | Entity named clearly; verifiable claims; answer-first format |

### Gate 3 — Marketing and messaging readiness

| Check | Pass criterion |
|-------|---------------|
| Positioning | Clear "for [ICP], [product] is the [category] that [benefit]" |
| Differentiation | One unique claim vs alternatives that is credible and specific |
| Proof points | ≥1 testimonial, case study, or quantified outcome |
| Channel | At least one distribution channel ready to activate on launch day |

### Gate 4 — Sales and monetization readiness

| Check | Pass criterion |
|-------|---------------|
| Pricing | Public pricing page or a pricing structure you can state in one sentence |
| Conversion path | User can buy/sign up without speaking to a human (for self-serve) |
| Trial / onboarding | User reaches "aha moment" within first session |
| Objection handling | Top 3 buyer objections have prepared responses |

## PMF signals to watch

You have PMF when you observe (not declare):
- Organic inbound without paid push
- Users who churn are upset when they leave
- NPS > 40 or >40% answer "very disappointed" to "what if this product disappeared?"
- Word-of-mouth referrals without a referral program
- Users who find workarounds rather than churning

Measure: D1/D7/D30 retention, NPS/CSAT, referral source attribution, support ticket themes.

## Workflow

1. **Assess engineering readiness**: Run Gate 1 checklist. Flag any blockers.
2. **Define target market and country**: ICP, buyer, user, trigger for purchase, geographic priority.
3. **Clarify positioning**: One-sentence "for [ICP]..." statement. Test it against 3 real users.
4. **SEO/GEO readiness**: Run Gate 2. Flag gaps. Load `bosskuai-seo-geo` if depth is needed.
5. **Marketing plan**: Audience, messaging, channels for launch day and first 30 days. Load `bosskuai-marketing-growth` for depth.
6. **Sales and monetization**: Pricing structure, conversion path, top objection responses. Load `bosskuai-sales-strategy` for depth.
7. **PMF measurement plan**: Define which signals to measure and at what cadence.
8. **Recommend the smallest launch plan**: What is the minimum needed to validate demand without overbuilding?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not launch if Gate 1 (engineering readiness) has unresolved P0 blockers.
- Do not launch without a monitoring and rollback plan.
- Do not confuse launch readiness with PMF — launch readiness means the product works; PMF means the market wants it.
- Do not declare PMF based on early adopter enthusiasm alone — early adopters are not the mainstream market.

## Output format

```
Launch readiness assessment:
  Engineering: [pass / blockers: list]
  SEO/GEO: [pass / gaps: list]
  Marketing: [pass / gaps: list]
  Sales/Monetization: [pass / gaps: list]
  Overall: [ready / not ready + critical path]

Target market:
  ICP: [profile]
  Buyer / User: [roles]
  Country priority: [list with rationale]
  Purchase trigger: [what causes them to seek this now]

Positioning:
  "For [ICP], [product] is the [category] that [key benefit], unlike [alternatives] which [limitation]."
  Proof points: [list]

Launch plan:
  Day 0 (launch): [actions]
  Week 1: [actions]
  Month 1: [actions]
  Channels: [channel — why — tactic]

Sales and monetization:
  Pricing: [model and tiers]
  Conversion path: [steps to purchase]
  Top objections: [objection → response]

PMF signals and measurement:
  Primary signal: [metric + threshold]
  Measurement cadence: [when and how]
  Iteration trigger: [if X, then change Y]

Risks and launch blockers:
  [blocker] — [severity] — [owner] — [resolution]

Next action:
  [single most important step to move toward launch]
```

## References

- `../../references/checklists/launch-commercialization-checklist.md`
- `../../references/playbooks/launch-commercialization-playbook.md`
- `../../references/checklists/engineering-delivery-checklist.md`
- `../../references/checklists/sales-strategy-checklist.md`
- `../../references/checklists/seo-geo-checklist.md`
- `../../references/checklists/marketing-growth-checklist.md`
