---
name: bosskuai-paid-acquisition-monetization
description: Use this for Google Ads, paid acquisition strategy, campaign structure, CAC logic, pricing and packaging decisions, and monetization planning tied to business reality.
---

# BosskuAI Paid Acquisition and Monetization

Use this skill when the task involves spending money to acquire users or customers, or deciding how to make money from the product.

## How this differs from nearby skills

- **`bosskuai-marketing-growth`**: organic demand generation and overall GTM strategy; this skill handles paid channels and monetization specifically.
- **`bosskuai-sales-strategy`**: closing individual deals; this skill handles the paid channel and pricing strategy that feeds the pipeline.
- **`bosskuai-launch-commercialization`**: full launch plan; this skill supplies the paid acquisition and monetization components.

## Maintenance (time-sensitive)

**Annual review (required):** Refresh channel benchmarks, attribution caveats, and platform UI/feature names at least once per calendar year — **recommended window: Q1**. Ad networks and auction dynamics change; verify before treating examples as current.

**Last reviewed:** 2026-03.

## Mindset

- Paid acquisition without positive unit economics is a money furnace, not a growth engine.
- Understand CAC/LTV before scaling spend. Scale only when the math works.
- Monetization is a product decision, not just a pricing decision — packaging drives conversion.
- The cheapest customer acquisition is a product people recommend. Paid channels amplify what already works.

## Unit economics — the foundation

Before any paid campaign, define:

| Metric | Formula | Target |
|--------|---------|--------|
| **CAC** | Total acquisition spend ÷ new customers acquired | < LTV / 3 |
| **LTV** | ARPU × Average customer lifespan | > 3× CAC |
| **Payback period** | CAC ÷ monthly gross margin per customer | < 12 months (SaaS) |
| **ROAS** | Revenue from ads ÷ ad spend | > 3× for e-commerce |
| **Blended CAC** | Total acquisition cost (all channels) ÷ new customers | Track alongside paid-only CAC |

If LTV < 3× CAC: fix retention and pricing before scaling spend.

## Paid channel comparison

| Channel | Best for | Targeting | Cost model | Minimum budget |
|---------|----------|-----------|-----------|----------------|
| **Google Search** | High-intent, transactional | Keywords + audience | CPC | USD 500–1000/month |
| **Google Performance Max** | Broad reach, conversion-optimized | Automated signals | CPA/tROAS | USD 1000/month |
| **Meta (Facebook/Instagram)** | Awareness, B2C, retargeting | Demographics, interests, lookalike | CPM/CPC | USD 300/month |
| **LinkedIn** | B2B decision-makers, enterprise | Job title, company, seniority | CPM/CPC | USD 1000/month |
| **YouTube** | Video awareness, product demos | Interest, intent, topics | CPV/CPM | USD 500/month |
| **TikTok** | Gen Z/Millennial B2C, viral | Interest, behavior | CPM | USD 500/month |
| **Apple Search Ads** | Mobile app installs, iOS | App Store search | CPT | USD 300/month |

**Rule**: Start with the channel your ICP uses when they have buying intent, not the cheapest channel.

## Campaign structure

For Google Search (most common starting channel):

```
Campaign: [product / goal]
  Ad group: [keyword cluster 1 — e.g. "AI assistant for teams"]
    Keywords: [exact / phrase match — 5–15 per ad group]
    Ads: [3 responsive search ads with varied hooks]
    Landing page: [dedicated, intent-matched page]
  Ad group: [keyword cluster 2]
    ...
```

Campaign structure principles:
- One campaign per goal (awareness vs conversion vs remarketing).
- One ad group per keyword intent cluster — do not mix branded and non-branded.
- One landing page per ad group intent — generic landing pages kill conversion.
- Negative keywords: add competitor brand terms and irrelevant query types from day one.

## Attribution models

| Model | How it assigns credit | When to use |
|-------|----------------------|-------------|
| **Last-touch** | 100% to the last channel before conversion | Simple, easy — misleads on multi-touch journeys |
| **First-touch** | 100% to the channel that first acquired the user | Useful for awareness spend evaluation |
| **Linear** | Equal credit across all touchpoints | Balanced view for multi-channel |
| **Data-driven** | ML-based, proportional to actual contribution | Best — requires sufficient conversion data (>50/month) |

For early-stage: use last-touch but also track first-touch. Avoid over-optimizing based on one attribution model.

## Monetization matrix

Choose the model that matches the buyer's willingness to pay and the product's value delivery pattern:

| Model | How it works | Best for | Risk |
|-------|-------------|---------|------|
| **Subscription (flat)** | Fixed monthly/annual fee | Predictable SaaS | Churn destroys LTV |
| **Usage-based** | Pay per API call, seat, GB, action | Variable-usage tools, infra | Revenue unpredictability |
| **Freemium** | Free tier + paid upgrades | High-volume, self-serve | High CAC if upgrade rate is low |
| **Seat-based** | Per user/seat | Collaborative tools | Caps on growth if team size limited |
| **Outcome-based** | Pay for result (% of savings, revenue) | High-trust, measurable outcomes | Complex to meter and enforce |
| **One-time** | Single purchase | Tools, templates, courses | No recurring revenue |
| **Tiered** | Good/Better/Best packages | SaaS with usage variance | Complexity; anchor pricing matters |

**Packaging principles:**
- The mid-tier is the anchor — price it to make the top tier feel like a bargain.
- Free tier should deliver real value but hit a meaningful limit — not a hobbled experience.
- Annual billing (with discount) dramatically improves LTV and reduces churn.

## Workflow

1. **Define the goal and constraints**: What conversion action? Country? Budget? Timeline? Current CAC/LTV baseline?
2. **Check unit economics**: Is the current LTV high enough to support paid acquisition? If not, fix pricing or retention first.
3. **Select the channel**: Match channel to ICP intent stage and budget.
4. **Build campaign structure**: One goal per campaign; one intent per ad group; one landing page per intent.
5. **Set budget and stop-loss**: Define the test budget, minimum conversion volume for statistical significance, and the stop criteria.
6. **Define attribution**: What model and what tool? (GA4, Meta Pixel, Segment, Amplitude?)
7. **Choose monetization model**: Match to buyer willingness and value delivery pattern.
8. **Design packaging**: Define tiers with anchor pricing logic.
9. **Define experiments**: What is the smallest paid test that produces useful signal?

## Stop-loss criteria

Stop a campaign if after the test budget:
- CTR < 1% on search (likely wrong keywords or weak ad copy)
- Landing page conversion rate < 2% (intent mismatch or UX failure)
- CAC > 2× LTV (spend is destroying value)
- Zero conversions after 3× target CPA spend

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not scale paid spend before achieving positive unit economics — it accelerates losses, not growth.
- Do not run paid to a generic homepage — landing pages must match ad intent.
- Do not launch LinkedIn campaigns under USD 1000/month budget — cost per click is too high to learn at lower budgets.
- Do not use last-touch attribution for strategic decisions — always layer in first-touch and multi-touch data.

## Output format

```
Unit economics baseline:
  CAC: [current or target] / LTV: [current or target] / Payback: [months]
  Verdict: ready to scale / needs pricing or retention fix first

Paid channel plan:
  Primary channel: [channel] — [why] — [budget] — [targeting approach]
  Fallback channel: [channel] — [why]
  Attribution model: [model + tool]

Campaign structure:
  [campaign] → [ad groups] → [landing pages]

Stop-loss criteria:
  [metric] < [threshold] after [test budget]

Monetization model:
  Model: [chosen model + why]
  Tiers / packaging: [Good / Better / Best with pricing logic]
  Annual billing: [discount offered]

Experiments to run:
  [hypothesis] — [test] — [success criteria] — [budget]

Won't do (yet):
  [channel or tactic] — [why]
```

## References

- `../../references/checklists/paid-acquisition-monetization-checklist.md`
- `../../references/playbooks/paid-acquisition-monetization-playbook.md`
