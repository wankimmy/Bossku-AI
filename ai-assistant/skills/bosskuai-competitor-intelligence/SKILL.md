---
name: bosskuai-competitor-intelligence
description: Use this skill for structured competitor tracking — feature comparison matrices, pricing intelligence, messaging changes, hiring signals, and strategic implications. Repeatable, cadence-friendly monitoring with delta detection.
---

# BosskuAI Competitor Intelligence

## When to use

- "What are our competitors doing?" — feature, pricing, or messaging changes
- Building or updating a competitor comparison matrix
- Tracking a specific competitor's pricing, hiring, or funding signals
- Preparing a competitive landscape section for a pitch deck or strategy doc
- Setting up a repeating competitor monitoring cadence
- Pre-launch competitive positioning check

## How this differs from nearby skills

- **`bosskuai-market-analysis`**: defines positioning strategy and TAM/SAM/SOM. Competitor intelligence is the structured, repeatable tracking layer — raw data and comparison matrices — that informs that strategy but doesn't make the strategic call.
- **`bosskuai-deep-research`**: a one-time deep-dive on any topic. Competitor intelligence is cadence-driven, structured around fixed tracking dimensions, and optimised for delta detection over time.
- **`bosskuai-browser-automation`**: the execution layer. Load browser-automation alongside this skill when a competitor's site is JS-rendered and Exa/Firecrawl cannot extract the needed data.

## MCP requirements

| Tool | Role | Degradation |
|------|------|-------------|
| Exa | Web search for each competitor and dimension (required) | Without Exa, use WebSearch; flag reduced freshness |
| Firecrawl | Full-page scraping of pricing/features pages (required) | Without Firecrawl, rely on Exa snippets; mark data as lower-confidence |
| Playwright | JS-rendered site scraping (optional) | Load `bosskuai-browser-automation` if needed |

## Workflow

1. **Define the competitor set** — List direct, adjacent, and substitute competitors. For each, record: name, URL, primary ICP, pricing model. Confirm the set with the user if it is being built fresh.

2. **Establish tracking dimensions** — Choose from the standard dimensions below or customise per task:
   - Pricing (plans, tiers, price points, trial terms)
   - Feature set (core features, recent additions, roadmap signals)
   - Messaging and positioning (headline, value prop, target persona implied)
   - Hiring (open roles → signals about investment areas)
   - Funding and news (press releases, Crunchbase, LinkedIn announcements)
   - Customer sentiment (G2, Capterra, App Store, Reddit, Twitter)

3. **Search and scrape each dimension** — For each competitor × dimension pair, run at least one Exa search and one Firecrawl page pull (pricing page, careers page, etc.). Log the URL and retrieval timestamp for every data point.

4. **Build the comparison matrix** — Populate a structured table: rows = competitors, columns = tracking dimensions. Include a "Last verified" date per cell. Flag cells where data could not be confirmed.

5. **Identify changes since last check** — Compare against the previous snapshot (if one exists in `ai-assistant/memory/`). Highlight changes: new feature, price change, messaging shift, funding event, key hire.

6. **Draw strategic implications** — For each significant change, state: "This means we should consider..." Keep implications concrete and actionable, not generic.

## Output format

```
## Competitor Intelligence Report — [Date]

### Comparison Matrix
| Competitor | Pricing | Core Features | Positioning | Hiring Signals | Last Verified |
|-----------|---------|--------------|-------------|----------------|---------------|
| ...       | ...     | ...          | ...         | ...            | YYYY-MM-DD    |

### Change Log (vs. previous report)
- [Competitor]: [What changed] — [Source URL] — [Date]

### Strategic Implications
1. [Implication + recommended action]
2. ...

### Data quality notes
- Cells marked [INFERRED] are based on indirect signals, not confirmed primary sources.
- Data freshness: all cells verified within [date range].
```

## Guardrails

- Label every data point with its source URL and retrieval date — data decays fast.
- Distinguish confirmed data (pricing page, press release) from inferred data (estimated revenue, assumed roadmap) — mark inferred cells explicitly.
- Respect robots.txt on all scraped domains.
- Rate-limit scraping: minimum 2 seconds between requests per domain.
- Do not republish scraped content verbatim — summarise and attribute.
- Keep competitor matrices in `ai-assistant/memory/` so delta detection works across sessions.
- Never present inferred pricing as confirmed without a direct source.
