---
name: bosskuai-market-analysis
description: Use this for market analysis, competitor comparison, trend research, demand validation, positioning, pricing context, and strategy recommendations that require current market awareness.
---

# BosskuAI Market Analysis

Use this skill when the task is about **external market reality** rather than internal product design.

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: defines what to build and why; this skill informs the external context that validates or challenges that strategy.
- **`bosskuai-marketing-growth`**: execution of marketing; this skill produces the strategic intelligence that informs what to say and to whom.
- **`bosskuai-launch-commercialization`**: full launch readiness; load this skill first to build the market foundation for that plan.
- **`bosskuai-seo-geo`**: content discoverability; load alongside when search-intent data is part of demand validation.

## Mindset

- Verify before asserting — market claims decay fast; state confidence levels.
- Distinguish confirmed facts (product pages, pricing, reviews, public filings) from inference (estimated revenue, assumed positioning).
- Most markets are more crowded than founders believe; most niches are more defensible than VCs assume.
- The goal is **strategic implication**, not information accumulation.

## Workflow

1. **Define the exact market and segment** — Not "the SaaS market" but "B2B workflow automation for logistics ops teams in Southeast Asia." Be precise about geography, company size, buyer role, and use case.

2. **Size the market** (use bottom-up when possible):
   - **TAM**: Total Addressable Market — full theoretical demand if 100% market share
   - **SAM**: Serviceable Addressable Market — the segment you can realistically reach
   - **SOM**: Serviceable Obtainable Market — realistic capture in 12–24 months
   - Challenge top-down TAM numbers from analyst reports unless they match bottom-up estimates.

3. **Map competitors and substitutes**:
   - **Direct competitors**: same solution, same buyer
   - **Adjacent competitors**: different solution, same problem or same buyer
   - **Substitutes**: what buyers do today instead (spreadsheets, manual process, outsourcing)
   - For each: pricing, positioning, main strengths, main weaknesses, customer evidence (G2, Capterra, Twitter, Reddit, App Store)

4. **Validate demand signals** — Look for:
   - Search volume and keyword intent data
   - Community signals (Reddit threads, LinkedIn posts, Slack groups)
   - Paying customers already in the space (proof of willingness to pay)
   - Job postings (a company hiring for a role signals a pain they can't automate)
   - Review themes (what do customers love/hate about existing solutions?)

5. **Map positioning whitespace** — What attributes does no competitor own clearly? Price, ease, speed, vertical focus, compliance, integrations, support?

6. **Convert findings into strategy implications** — Every analysis section should end with: "This means we should..."

7. **State confidence and gaps** — What is confirmed from public evidence? What is inferred? What needs primary research?

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not accept TAM numbers without a bottom-up sanity check.
- Do not treat competitor feature parity as a moat — execution and distribution matter more.
- Do not present inference as fact; label it clearly.
- If market data is time-sensitive, say when it was gathered or recommend re-verification.

## Output format

```
Market definition: [segment, geography, buyer, use case]
Market size: TAM / SAM / SOM with method and confidence
Competitor map:
  [Competitor] — pricing / positioning / strengths / weaknesses / evidence
Substitutes: [what buyers use today]
Demand signals: [search, community, review, job posting evidence]
Positioning whitespace: [what no competitor owns clearly]
Strategic implications:
  - [finding] → [what to do]
Confidence and gaps: [confirmed / inferred / needs research]
```

## References

- `../../references/playbooks/market-research-playbook.md`
- `../../references/checklists/market-analysis-checklist.md`
