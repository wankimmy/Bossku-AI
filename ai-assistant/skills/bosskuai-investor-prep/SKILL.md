---
name: bosskuai-investor-prep
description: Use this skill when preparing investor-facing materials — pitch decks, one-pagers, investor memos, financial models, or accelerator applications. Packages the business story for external stakeholders with consistent, defensible numbers.
---

# BosskuAI Investor Prep

## When to use

- Creating or revising a pitch deck
- Writing an investor memo or executive summary
- Building a one-pager for warm intros or cold outreach
- Preparing a financial model or unit economics sheet
- Applying to an accelerator or grant programme
- Practising for a partner meeting or investor Q&A
- Ensuring numbers are consistent across all fundraising materials

## How this differs from nearby skills

- **`bosskuai-product-strategy`**: shapes what to build and the product roadmap. Investor prep packages the existing strategy and traction into a narrative for external capital. Load product-strategy first if the strategy itself is not yet settled.
- **`bosskuai-market-analysis`**: produces market sizing and competitive intelligence. Load market-analysis to generate TAM/SAM/SOM and competitive context, then investor-prep to frame it for a deck.
- **`bosskuai-launch-commercialization`**: full go-to-market readiness. Investor prep focuses on the fundraising story, not launch execution.

## MCP requirements

| Tool | Role | Degradation |
|------|------|-------------|
| Exa | Investor research, comparable round data (optional) | Without Exa, flag that comparable raise data is from user-supplied context only |

No MCP required for core deck / memo creation.

## Workflow

1. **Inventory canonical facts** — Before writing a single slide, collect: current traction metrics (MRR/ARR, users, growth rate), pricing model, team bios, ask size, use of funds, runway, and any existing commitments. Do not proceed until these are either confirmed or explicitly flagged as TBD.

2. **Identify gaps** — List every missing number or claim that an investor will ask about. For each gap: decide if it can be calculated, estimated (with visible assumptions), or must stay as TBD. Never silently omit or fabricate.

3. **Choose the asset type**:
   - **Pitch deck** (12 slides): for warm meetings, Series A+ raises, demo days
   - **One-pager**: for cold outreach, warm intros, accelerator pre-screening
   - **Investor memo**: for data-room depth, bridge rounds, strategic angels who want full diligence material

4. **Draft with explicit logic** — For each financial projection or market size claim, show the calculation or assumption chain. Investors do not trust numbers without visible logic.

5. **Cross-check consistency** — Every number must appear consistently across all sections. MRR on slide 3 must match the financial model on slide 9. Run an explicit consistency pass before finalising.

## Pitch deck structure (12 slides)

| Slide | Content |
|-------|---------|
| 1 | Cover — company name, one-line value prop, contact |
| 2 | Problem — specific pain, for whom, status quo cost |
| 3 | Solution — what you do, how it works (product screenshot or demo) |
| 4 | Market — TAM/SAM/SOM with bottom-up logic |
| 5 | Business model — how you charge, unit economics, pricing tiers |
| 6 | Traction — key metrics, growth chart, social proof |
| 7 | Go-to-market — acquisition motion, channels, early wins |
| 8 | Competition — honest landscape, your defensible wedge |
| 9 | Team — relevant experience, why you |
| 10 | Financials — 3-year projection, key assumptions visible |
| 11 | Ask — raise amount, use of funds, milestones it buys |
| 12 | Appendix / backup slides — unit economics, cohort data, product roadmap |

## Output format

Depending on asset type requested:

**Deck:** Slide-by-slide content blocks with headline, body, and data callouts. Notes on visual layout suggestions.

**One-pager:** Single structured document: problem, solution, traction, team, ask — under 500 words.

**Investor memo:** Structured long-form document covering all 12 deck sections in prose, plus appendices.

All outputs include a "Numbers consistency check" section confirming every figure appears only once (or matches if repeated).

## Guardrails

- Every number must trace back to a named source of truth — no figures from memory or assumption without explicit labelling.
- No unverifiable claims (e.g., "fastest growing," "only solution") without supporting evidence.
- All assumptions in financial projections must be visible — never bury growth rates inside a formula.
- Projections must be defensible in a partner meeting — if you can't explain how you got there, don't include it.
- Do not overstate traction — investors verify; inflated numbers destroy credibility.
- If the company is pre-revenue, say so explicitly and pivot the traction slide to engagement, pipeline, or LOIs.
- TAM must use bottom-up logic when possible; flag top-down TAM numbers from analyst reports as directional only.
