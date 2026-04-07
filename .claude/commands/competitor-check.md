# BosskuAI Competitor Check

Activate the `bosskuai-competitor-intelligence` skill for systematic competitor monitoring.

## Intent

Run a structured competitive intelligence sweep — pricing, features, hiring signals, funding, and positioning gaps. Produce a comparison matrix and strategic recommendations.

## Instructions

1. Load `ai-assistant/skills/bosskuai-competitor-intelligence/SKILL.md` before acting.
2. Identify the competitor set (direct, indirect, emerging). Ask if not provided.
3. For each competitor, gather signals in this order:
   - Pricing page via Firecrawl: `mcp__firecrawl__scrape`
   - Job postings (hiring signals — what they're building): Exa search
   - Recent news / funding: `mcp__exa__search` with company name + recent date filter
   - Feature changes: check product changelog or blog
4. Build a comparison matrix with categories relevant to the product.
5. Identify positioning gaps — white space competitors are not addressing.
6. Classify each competitor signal: copy / differentiate / ignore / monitor.
7. Produce strategic recommendations with reasoning.

## Output

- Competitor Matrix (feature/pricing comparison table)
- Signal Change Log (what is new since last check)
- Hiring Signal Summary (what roles suggest their roadmap direction)
- Positioning Gap Analysis
- Strategic Recommendations (3–5 actions ranked by impact)

## Quality gates

- At least one primary source per competitor (direct page scrape, not just rumor)
- Hiring signals interpreted with caveats (correlation, not certainty)
- No recommendations without supporting evidence
- Indirect competitors included — do not only track direct ones
