---
name: bosskuai-marketing-growth
description: Use this for marketing strategy, distribution, positioning, go-to-market planning, channels, messaging, growth loops, and turning attention into adoption.
---

# BosskuAI Marketing and Growth

Use this skill when the task is about **getting attention, demand, adoption, or distribution** — turning a product into something people find, try, and recommend.

## How this differs from nearby skills

- **`bosskuai-market-analysis`**: produces the market intelligence that informs this strategy; load first if positioning is unclear.
- **`bosskuai-sales-strategy`**: converts marketing-generated demand into closed deals; load alongside for full GTM coverage.
- **`bosskuai-launch-commercialization`**: orchestrates the full launch across engineering, marketing, and sales; this skill supplies the marketing component.
- **`bosskuai-paid-acquisition-monetization`**: paid channels and monetization specifically; load alongside when the growth strategy includes paid spend.
- **`bosskuai-seo-geo`**: organic discoverability through search and generative engines; load alongside for content and SEO strategy.

## Mindset

- Distribution is a product feature. Build it in, don't bolt it on after launch.
- Retention before acquisition: a leaky bucket cannot be filled with more water.
- The riskiest growth assumption is "if we build it, they will come."
- Channels have stages — what works at zero users fails at 1000, and what works at 1000 fails at 100k.

## Growth frameworks to apply

### AARRR funnel (Pirate Metrics)
Identify the weakest stage and fix it before optimizing others:
- **Acquisition**: how do people discover and reach the product?
- **Activation**: do they have a great first experience and reach the "aha moment"?
- **Retention**: do they come back? What is the D1, D7, D30 retention?
- **Revenue**: do they pay? What is ARPU, LTV, conversion rate?
- **Referral**: do they tell others? What drives word of mouth or virality?

### Growth loop identification
Sustainable growth comes from compounding loops, not linear campaigns:
- **Viral loop**: user action creates exposure for new users (sharing, inviting, embedding)
- **Content loop**: content generates SEO/GEO traffic → signups → more content creators
- **Paid loop**: CAC < LTV/3 → reinvest in paid → more users → more revenue
- **Product-led loop**: users get value → use more → invite others → more usage
- **Community loop**: users create community value → attract new users → community grows

## Workflow

1. **Define the audience precisely** — Segment by: job title, company size, geography, maturity (early adopter vs mainstream), trigger (what causes them to seek this now?).

2. **Clarify the core message and positioning**:
   - What is the one thing the product does better than any alternative?
   - What is the positioning statement: "For [ICP], [product] is the [category] that [key benefit], unlike [alternatives] which [limitation]."
   - What proof points support the claim? (case studies, numbers, social proof)

3. **Select channels for the current stage**:
   - Pre-PMF: founder-led outbound, communities, direct conversations, content
   - Early traction: content + SEO, community, partnerships, referral
   - Growth stage: paid acquisition, product-led growth, partnerships, events
   - Scale: brand, demand gen, channel sales, international expansion
   - Match channel choice to: ICP concentration, budget, team skill, payback window.

4. **Map the activation path** — From first touch to "aha moment": What is the first action that signals the user will retain? How many steps does it take? Where do users drop off?

5. **Identify the growth loop** — Which loop fits the product and current stage? What is the minimum viable version of that loop?

6. **Recommend experiments** — Prioritize experiments that test the highest-risk assumption in the marketing strategy. Small, fast, and measurable. Define what "this worked" looks like before running.

## Guardrails

- Do not recommend paid acquisition before retention is understood — you will spend to fill a leaky bucket.
- Do not recommend more channels than the team can execute well.
- Do not confuse vanity metrics (views, followers) with growth metrics (activated users, revenue, retention).
- Do not copy a competitor's channel strategy without understanding why it works for them and whether it applies.

## Output format

```
Audience: [segment / trigger / ICP]
Positioning: "For [ICP], [product] is the [category] that [key benefit], unlike [alternatives]."
Proof points: [list]
AARRR audit: [stage → current status → key metric → gap]
Growth loop: [type → how it works → minimum viable version]
Channel plan (by stage):
  [channel] — [why] — [tactic] — [success metric]
Activation path: [first touch → aha moment → steps → drop-off]
Experiments to run (priority order):
  [hypothesis] — [test] — [success criteria]
Won't do (yet): [channels or tactics deprioritized and why]
```

## References

- `../../references/playbooks/marketing-growth-playbook.md`
- `../../references/checklists/marketing-growth-checklist.md`
