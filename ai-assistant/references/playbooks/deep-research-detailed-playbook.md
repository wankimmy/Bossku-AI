# Deep Research Playbook

## When to use
- A decision requires current, cited evidence from multiple sources
- Market sizing, competitive analysis, technology evaluation, or regulatory research
- Whenever training-knowledge alone is insufficient or potentially stale
- Research tasks where source credibility and recency matter

## Prerequisites
- Exa MCP configured (`EXA_API_KEY` set) — required for semantic web search
- Firecrawl MCP configured (`FIRECRAWL_API_KEY` set) — optional but recommended for full-page extraction
- If neither is available: fall back to WebSearch + WebFetch

## Phase 1: Scoping (≤ 30 minutes)

**Goal:** Define precisely what you are researching so you do not drift.

1. Restate the research question in one sentence.
2. Define what a "good answer" looks like — what would change your decision?
3. Identify source types needed:
   - Primary: official reports, company data, government statistics
   - Secondary: analyst reports, academic papers, reputable journalism
   - Signals: job postings, pricing pages, community discussions
4. Set a time budget. Timebox discovery to 30 min; deep-dive to 60 min.
5. Name 3 hypotheses you expect to confirm or refute.

## Phase 2: Discovery with Exa

**Goal:** Cast a wide net. Find the best sources before going deep.

1. Design 3–5 distinct search angles — do not just vary keywords:
   - Angle 1: direct question ("what is X market size 2024")
   - Angle 2: expert perspective ("analyst report X industry trends")
   - Angle 3: practitioner perspective ("startup founder X challenge")
   - Angle 4: contrarian view ("X market overcrowded / declining")
   - Angle 5: signal-based ("X company hiring / funding / launching")
2. Use `mcp__exa__search` with `type: "neural"` for semantic queries.
3. Add `start_published_date` to filter for recent results (e.g., last 12 months for trend questions).
4. Capture: URL, title, snippet, publication date, source type for every result.
5. Rate top 10 results by: recency × authority × relevance.

## Phase 3: Deep Dive with Firecrawl

**Goal:** Extract full content from the top sources.

1. Select top 5 sources from Phase 2.
2. Use `mcp__firecrawl__scrape` with `formats: ["markdown"]` for each.
3. Extract only the relevant sections — do not process entire pages.
4. For paywalled content: note the block; use abstract / executive summary instead.
5. Cross-validate: if two sources give different numbers for the same metric, note the discrepancy and weight by source authority.

## Phase 4: Synthesis

**Goal:** Turn raw findings into structured insight.

1. Cluster findings by theme (not by source).
2. For each cluster, identify:
   - Consensus view (multiple sources agree)
   - Minority view (credible sources disagree)
   - Uncertain / conflicting (evidence mixed)
3. Weight sources:
   - High: primary data, peer-reviewed, government statistics
   - Medium: reputable analyst reports, established journalism
   - Low: blog posts, unattributed claims, anonymous forums
4. Name explicit gaps: what you searched for but could not find.
5. Flag recency: note if key findings are >18 months old.

## Phase 5: Output

**Report structure:**

```markdown
## Research Report: <Question>

### Summary (3 sentences max)
<Answer the research question directly>

### Key Findings
- <Finding> — Confidence: high/med/low | Source: [citation]
- ...

### Market / Industry Data
| Metric | Value | Source | Date |
|--------|-------|--------|------|
| ...    | ...   | ...    | ...  |

### Contradictions / Gaps
- <Conflicting data point + how weighted>
- <Gap: could not find X because Y>

### Strategic Implications
1. <Implication specific to this product/decision>

### Sources
1. [Title](URL) — Type: primary/secondary | Date: YYYY-MM
```

## Quality Gates

- [ ] Every factual claim has a cited source
- [ ] At least 3 independent sources consulted
- [ ] Contradictions surfaced, not silently resolved
- [ ] Recency of key data points noted
- [ ] Gaps named explicitly
- [ ] Confirmation bias checked: at least one disconfirming angle searched

## Common Pitfalls

**Confirmation bias** — only searching for evidence that supports a prior belief. Fix: always include a contrarian angle.

**Recency bias** — over-weighting the most recent article. Fix: note publication dates; look for longer-term trends.

**Secondary source inflation** — citing a blog that cites an analyst that cited primary data. Fix: trace claims to primary source when possible.

**Scope drift** — starting with "what is the market size" and ending up researching competitor features. Fix: restate the original question at Phase 4.

**Precision theater** — reporting "the market is $47.3B" as if it is exact. Fix: always note the source's methodology and confidence interval.
