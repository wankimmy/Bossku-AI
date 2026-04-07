# Competitor Intelligence Checklist

## Pre-work
- [ ] Competitor set defined: direct, adjacent, substitutes
- [ ] Tracking dimensions selected from standard list (pricing, features, messaging, hiring, funding, sentiment)
- [ ] Previous snapshot available in `ai-assistant/memory/` for delta comparison (or first run noted)
- [ ] Exa MCP confirmed available
- [ ] Firecrawl confirmed available for pricing/feature pages
- [ ] robots.txt checked for any site to be scraped

## During
- [ ] At least one Exa search and one Firecrawl page pull per competitor × dimension pair
- [ ] URL and retrieval timestamp logged for every data point
- [ ] Pricing page scraped and tier structure captured
- [ ] Features page or product changelog reviewed
- [ ] Careers page reviewed for hiring signals
- [ ] Press, news, and Crunchbase checked for funding / announcement signals
- [ ] Review sites checked (G2, Capterra, Reddit, App Store) for sentiment signals
- [ ] Comparison matrix populated with "Last verified" date per cell
- [ ] Cells where data could not be confirmed marked [UNCONFIRMED]
- [ ] Inferred data (estimated revenue, assumed roadmap) marked [INFERRED]

## Quality gate
- [ ] Every data point has a source URL and retrieval date
- [ ] No confirmed vs. inferred data conflated in the matrix
- [ ] Change log lists only changes verified against previous snapshot
- [ ] Strategic implications are specific and actionable, not generic
- [ ] Matrix saved to `ai-assistant/memory/` for future delta detection
- [ ] Rate limiting respected (≥ 2s between requests per domain)
