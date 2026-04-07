---
name: bosskuai-deep-research
description: Use this skill for in-depth investigation tasks — multi-source research, due diligence, technology evaluation, and evidence synthesis with citations. Produces sourced executive summaries, not strategy.
---

# BosskuAI Deep Research

## When to use

- Explicit "research X in depth" or "investigate X" requests
- Due diligence on a technology, vendor, or competitor
- Technology evaluation requiring current ecosystem data
- Multi-source fact-finding before a major product or business decision
- Background research to feed `bosskuai-market-analysis`, `bosskuai-investor-prep`, or `bosskuai-competitor-intelligence`

## How this differs from nearby skills

- **`bosskuai-market-analysis`**: interprets findings strategically (positioning, TAM, whitespace). Deep research is the investigation engine that feeds it — pure sourced synthesis, not strategy.
- **`bosskuai-competitor-intelligence`**: tracks competitors on a repeating cadence with comparison matrices. Deep research is a one-time deep-dive on any topic, not necessarily competitive.
- **`bosskuai-search-first`**: a lightweight lookup skill for quick answers. Deep research involves multi-step queries, cross-referencing, and confidence-rated synthesis.

## MCP requirements

| Tool | Role | Degradation |
|------|------|-------------|
| Exa | Web search (required) | Without Exa, fall back to WebSearch/WebFetch and flag reduced coverage |
| Firecrawl | Full-page scraping of key sources (optional) | Skip deep-read step; summarise from search snippets instead |

## Workflow

1. **Understand the research goal** — Restate the exact question in one sentence. Confirm scope: is this a technology review, market sizing, person/company profile, or regulatory landscape? Note the decision this research will inform.

2. **Decompose into 3–5 sub-questions** — Break the goal into discrete answerable questions. Example: "Evaluate Temporal.io" → (a) what problem does it solve, (b) adoption and community signal, (c) pricing and licensing, (d) alternatives and trade-offs, (e) known production issues.

3. **Execute multi-source search** — For each sub-question, run at least two independent searches via Exa. Vary query phrasing. Target primary sources (official docs, GitHub, SEC filings, analyst reports) and secondary sources (community forums, review sites, journalism). Log every URL used.

4. **Deep-read 3–5 key sources** — Use Firecrawl (or WebFetch) to retrieve full content of the highest-signal pages. Do not rely on search snippets alone for claims you will assert.

5. **Cross-reference and verify** — For every material claim, check that at least two independent sources agree or that the primary source is authoritative (official docs, public filings). Flag contradictions explicitly.

6. **Synthesize with attribution and confidence** — Write a structured report. Every factual assertion carries an inline citation. Label each finding with a confidence level: HIGH (multiple independent sources), MEDIUM (one strong source), LOW (inferred or single unverified source).

## Output format

```
## Executive Summary
2–4 sentence answer to the research question.

## Findings

### [Theme 1]
Finding text [Source: URL, date]. Confidence: HIGH/MEDIUM/LOW.

### [Theme 2]
...

## Gaps and open questions
What was not findable; what would need primary research.

## Sources
Numbered list: [1] Title, URL, accessed date.

## Methodology note
Search queries used, tools used, date range.
```

## Guardrails

- Every factual claim must carry a source citation — no unsourced assertions.
- Cross-reference required before marking confidence HIGH.
- Prefer sources published within the last 12 months unless the topic is historical.
- Acknowledge gaps explicitly — do not paper over missing data with confident-sounding prose.
- Label confidence levels consistently; never inflate to HIGH without two independent sources.
- Do not conflate summary snippets with full-source reading — deep-read before asserting specifics.
- If Exa is unavailable, state reduced coverage at the top of the report.
