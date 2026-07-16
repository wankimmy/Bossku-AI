# Competitor Intelligence Playbook

## When to use
- Establishing a competitor baseline for a new market or product area
- Quarterly competitive reviews before planning cycles
- Responding to a competitor move (pricing change, new feature, funding)
- Building a competitive comparison matrix for sales or investor decks
- Monitoring hiring signals to infer competitor roadmap direction

## Prerequisites
- Exa MCP configured (`EXA_API_KEY` set) — required for signal scanning
- Firecrawl MCP configured (`FIRECRAWL_API_KEY` set) — required for pricing/feature scraping
- Playwright MCP available — optional, for rendering JS-heavy pages
- Competitor list defined: name, domain, known product URL(s)

## Phase 1: Define Scope

1. **Name the competitors:** Direct (same ICP, same problem), indirect (adjacent solution), and emerging (funded startups in the space).
2. **Define dimensions to track:**
   - Pricing: tiers, seat model, free tier, trial policy
   - Features: core capabilities, differentiators, gaps
   - GTM: positioning, target segments, key messages
   - Signals: hiring, funding, press, partnerships, community activity
3. **Set cadence:** One-time baseline vs. ongoing monitoring.
4. **Timebox:** Baseline takes 2–4 hours. Ongoing scan per competitor: 15–20 min.

## Phase 2: Pricing and Feature Scrape (Firecrawl)

**Scrape pricing pages:**
```
Use mcp__firecrawl__scrape on:
- <competitor.com>/pricing
- <competitor.com>/plans
- <competitor.com>/features
formats: ["markdown"]
```

**Extract per competitor:**
- Plan names and prices (monthly + annual)
- Seat/usage limits per plan
- Feature flags per tier
- Free tier / trial details
- Any "Contact us" tiers (signals enterprise ambitions)

**Log format:**
```markdown
| Competitor | Plan | Price/mo | Seats | Key Features | Free Tier |
|------------|------|----------|-------|--------------|-----------|
| Acme       | Pro  | $49      | 5     | API, SSO     | 14-day    |
```

**Notes:**
- If Firecrawl fails on JS-rendered pages, use Playwright to screenshot and extract text
- Record the scrape date — pricing data goes stale fast
- If no pricing page found: note as "pricing hidden" (enterprise signal)

## Phase 3: Signal Scan (Exa)

**Run 4–5 Exa queries per competitor:**

```
Search queries:
1. "<Competitor> new feature OR product update OR changelog" — last 90 days
2. "<Competitor> funding OR raised OR Series" — last 12 months
3. "<Competitor> hiring <role>" — last 60 days (infer roadmap from roles)
4. "<Competitor> review OR compared to OR vs" — user sentiment
5. "<Competitor> partnership OR integration OR launched" — last 90 days
```

**What to capture from signals:**
- **Funding:** Stage, amount, investors → runway and ambition signal
- **Hiring:** Engineering focus areas → where they're investing technically
- **Reviews:** G2, Capterra, Reddit, HN — raw user sentiment, top complaints
- **Launches:** ProductHunt, press releases — new capabilities
- **Partnerships:** Distribution and integration signals

## Phase 4: Positioning Analysis

**Find their marketing voice:**
1. Homepage headline + subheadline (what problem, for whom)
2. Top 3 use cases they emphasize
3. Who they name as competitors (their own comparison pages)
4. ICP signals: company size language, industry focus, persona (developer vs. manager)

**Find their content strategy:**
- Blog / changelog cadence
- SEO keywords they're targeting (check title tags)
- Case studies: which customer segments, which outcomes

**Identify their moat claim:**
- Technology differentiation ("only tool that...")
- Network effects ("X companies already on platform")
- Ecosystem ("integrates with 100+ tools")
- Brand/trust ("SOC2, enterprise-grade")

## Phase 5: Gap and Opportunity Analysis

**For each competitor, identify:**

1. **Their stated weaknesses** — from reviews and comparison pages
2. **Your gaps** — features they have that you don't
3. **Shared gaps** — what neither of you solves (opportunity)
4. **Overserved segments** — where they're complex/expensive for simpler use cases

**Positioning opportunity matrix:**
```markdown
| Dimension    | Us | Competitor A | Competitor B | Gap/Opp |
|--------------|----|--------------|--------------|---------|
| Onboarding   | ✓  | ✗            | ✓            | Parity  |
| API-first    | ✓  | ✗            | ✗            | Lead    |
| Enterprise   | ✗  | ✓            | ✓            | Gap     |
| Pricing      | ↓  | ↑            | ↑            | Lead    |
```

## Phase 6: Output — Competitive Intelligence Report

```markdown
## Competitive Intelligence Report: <Date>

### Landscape Summary
<2–3 sentences: who the key players are and the competitive dynamic>

### Competitor Profiles

#### <Competitor Name>
- **Positioning:** <headline from homepage>
- **ICP:** <who they target>
- **Pricing:** <tier summary>
- **Differentiators:** <top 2–3 claims>
- **Weaknesses (user-reported):** <from reviews>
- **Recent signals:** <funding / launches / hires in last 90 days>
- **Threat level:** High / Medium / Low | **Reason:** <one sentence>

### Pricing Comparison
[pricing table]

### Feature Matrix
[feature comparison table]

### Signal Log
| Date | Competitor | Signal Type | Summary | Implication |
|------|------------|-------------|---------|-------------|
| ...  | ...        | Hiring      | 5 ML engineers | Building AI features |

### Strategic Implications
1. <Implication + recommended action>

### Monitoring Schedule
- Next pricing scrape: <date + 30 days>
- Next signal scan: <date + 14 days>
```

## Ongoing Monitoring Setup

For recurring competitive monitoring, use a cron-style checklist:

**Weekly (15 min):**
- [ ] Exa scan: new funding / press / launch announcements
- [ ] Check competitor changelog / blog RSS

**Monthly (1 hour):**
- [ ] Re-scrape pricing pages
- [ ] Pull new reviews from G2 / Capterra
- [ ] Re-run hiring signal scan

**Quarterly (half-day):**
- [ ] Full Phase 2–5 refresh
- [ ] Update competitive matrix in pitch deck / sales materials
- [ ] Brief sales / product team on changes

## Rate Limiting Rules (external scraping)

- Minimum 2 seconds between Firecrawl requests to the same domain
- Maximum 10 pages per run per domain
- Respect `robots.txt` — check `<domain>/robots.txt` before scraping
- Do not scrape login-protected pages or behind-paywall content
- If blocked (429 / bot detection): stop, do not retry automatically

## Common Pitfalls

**Only tracking direct competitors** — indirect and emerging competitors often signal where the market is moving. Include at least one "emerging" player.

**Treating pricing pages as ground truth** — pricing pages are marketing. Verify with trial signups or community reports for actual pricing behavior.

**Ignoring hiring signals** — 5 new ML engineer postings is a stronger product signal than a blog post. Always scan job boards.

**Over-indexing on features** — competitor features matter less than whether your target customers care about them. Anchor analysis to ICP.

**Not dating the data** — competitive intel has a short shelf life. Always timestamp every data point.

**Confirmation bias in gap analysis** — don't just look for evidence your product is better. Actively find where competitors are ahead and what it would take to close the gap.
