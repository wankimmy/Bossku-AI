# BosskuAI Deep Research

Activate the `bosskuai-deep-research` skill for a multi-source investigation.

## Intent

Run a rigorous, cited research session using Exa (semantic web search) and Firecrawl (full-page content extraction). Produce a structured report with sources, confidence levels, and identified gaps.

## Instructions

1. Load `ai-assistant/skills/bosskuai-deep-research/SKILL.md` before acting.
2. Scope the research question precisely — restate it before starting.
3. Design 3–5 distinct search angles (not just variations of the same query).
4. Use Exa MCP for discovery: `mcp__exa__search` with semantic queries.
5. Use Firecrawl MCP for deep-dive on top 5 sources: `mcp__firecrawl__scrape`.
6. Cluster findings by theme. Flag contradictions between sources.
7. Weight sources by recency, authority, and primary vs secondary.
8. Produce a structured report — see output format in the skill file.
9. Name gaps explicitly: what you could not find and why it matters.

## Output

- Research question (restated)
- Key findings (bulleted, each with inline citation)
- Source quality table (source, type, recency, weight)
- Identified gaps and recommended follow-up
- Confidence level per major finding (high / medium / low)

## Quality gates

- No unsourced claims
- Contradictions between sources surfaced, not resolved arbitrarily
- Recency bias noted where applicable
- Primary sources preferred over secondary when available
