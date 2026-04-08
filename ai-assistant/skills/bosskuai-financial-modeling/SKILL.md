---
name: bosskuai-financial-modeling
description: Use this for financial modeling, revenue projections, ARR/MRR forecasts, runway, burn rate, unit economics, pricing model math, scenarios, sensitivity analysis, month-end close, reconciliation, journal entries, and SOX-style controls.
---

# Financial Modeling

## When to use
- User asks about revenue projections, ARR/MRR forecasts, or growth trajectory
- Designing or stress-testing a pricing model
- Calculating burn rate, cash runway, or fundraising timing
- Building unit economics (CAC, LTV, payback period, contribution margin)
- Scenario planning (what happens if churn doubles, if we hire 3 engineers, etc.)
- Answering "how long is our runway" or "when do we break even"
- Evaluating pricing changes, new monetization tiers, or enterprise packaging

## How this differs from nearby skills
- **vs paid-acquisition-monetization:** paid-acquisition optimizes CAC/LTV and channel ROI in an operational context. financial-modeling builds the underlying numeric model — projections, scenarios, and sensitivity analysis — that informs those decisions.
- **vs product-strategy:** product-strategy sets direction and prioritizes bets. financial-modeling quantifies the financial consequences of those bets.
- **vs planning-execution:** planning-execution structures the work. financial-modeling answers "does the business math work?"

## MCP requirements
- None required. All modeling is done analytically from stated assumptions.
- Optional: Exa for industry benchmarks (churn rates, CAC norms, gross margin targets).

## Workflow

### 1. Define model type
Identify which model(s) the task requires:
- Revenue forecast (subscription, usage-based, marketplace, transactional)
- Unit economics snapshot (CAC, LTV, payback, contribution margin)
- Runway calculator (burn rate vs cash on hand)
- Pricing model design (tier structure, price elasticity, packaging)
- Full P&L projection (revenue, COGS, OpEx, net margin)

### 2. State all assumptions explicitly
Before any numbers, surface every assumption:
- Starting user/customer count
- Growth rate (MoM or YoY) and its basis
- Conversion rates at each funnel stage
- ARPU / ACV / GMV per transaction
- Gross margin and major cost drivers
- Headcount plan and salary benchmarks
- Churn/retention rates

Label each assumption as: **Input** (user-provided), **Benchmark** (industry norm), or **Estimate** (reasoned guess).

### 3. Build layer-by-layer
Follow the dependency chain:
```
Users/Leads → Activation/Conversion → Paying Customers → Revenue
Revenue - COGS = Gross Profit
Gross Profit - OpEx = EBITDA / Net Burn
Cash on Hand / Monthly Burn = Runway (months)
```
Build each layer before moving to the next. No skipping.

### 4. Run bear / base / bull scenarios
- **Bear:** Pessimistic assumptions (growth -40%, churn +50%, costs +20%)
- **Base:** Most likely assumptions
- **Bull:** Optimistic assumptions (growth +40%, churn -30%, better conversion)
Present all three in a side-by-side table.

### 5. Sensitivity analysis
Identify the 3-5 assumptions with highest impact on the output metric. Show how the output changes as each assumption moves ±20% and ±50%. Common high-sensitivity levers:
- Monthly growth rate
- Churn rate
- Gross margin
- CAC / sales cycle length

### 6. Output clean tables
Produce structured, readable output (see Output Format below). Every cell must trace to a labeled assumption.

## Output format

**Assumption table:**
| Assumption | Value | Type | Notes |
|---|---|---|---|

**Monthly/quarterly projections (12-24 months):**
| Month | Users | Revenue | COGS | Gross Profit | OpEx | Net Burn | Cash |
|---|---|---|---|---|---|---|---|

**Scenario comparison:**
| Metric | Bear | Base | Bull |
|---|---|---|---|

**Sensitivity table (top levers):**
| Assumption | -50% | -20% | Base | +20% | +50% |
|---|---|---|---|---|---|

**Key takeaways:** 3-5 bullet points summarizing findings (runway date, break-even, critical risks).

## Guardrails
- Every number must trace to a named assumption. No hidden constants.
- Label all estimates as Input / Benchmark / Estimate — never present guesses as facts.
- Flag when an assumption is aggressive vs conservative.
- Do not present a single projection as "the forecast" without scenarios.
- Sensitivity analysis is mandatory when any high-impact assumption is uncertain.
- Do not combine bear on revenue with bear on costs (that double-counts pessimism) unless modeling a true stress scenario.
- When runway is under 6 months in base case, flag it prominently as a critical issue.
