# Financial Modeling Playbook

## When to use
- Building a fundraise financial model
- Runway planning and cash management
- Pricing model evaluation (seats vs. usage vs. flat rate)
- Unit economics analysis before scaling paid acquisition
- Scenario planning before a major hire or cost commitment
- Board and investor update preparation

## Prerequisites
- Business model defined (how revenue is generated)
- At least some actuals available (even 1–2 months)
- Key assumptions identified (not yet validated, but named)
- Modeling tool available (spreadsheet or equivalent)

## Phase 1: Model Architecture

**Decide the model type before building:**

| Model Type | Use When |
|------------|----------|
| Bottom-up SaaS | Subscription revenue, known pricing tiers |
| Transaction-based | Revenue per transaction or usage event |
| Services / project | Revenue tied to headcount or hours |
| Marketplace | GMV × take rate |
| Hybrid | Multiple revenue streams |

**Model structure (3 sheets minimum):**
1. **Assumptions** — every input variable, clearly labeled, one cell per assumption
2. **P&L** — revenue, COGS, gross margin, operating expenses, EBITDA, cash
3. **Unit Economics** — CAC, LTV, payback period, cohort analysis if applicable

Optional (for fundraise):
- **Scenarios sheet** — base / optimistic / pessimistic toggles
- **Use of funds** — headcount plan tied to revenue milestones
- **Cap table** — pre/post dilution

## Phase 2: Revenue Model

**Bottom-up SaaS template:**

```
Revenue = New Customers × ACV + Retained Customers × ACV × (1 + expansion rate)

Where:
New Customers = Leads × conversion rate
Leads = (organic + paid + outbound) per month
ACV = average contract value (annualized)
Retention = 1 - monthly churn rate
Expansion = net revenue retention - 100%
```

**State each assumption explicitly:**
- Monthly new lead volume — by channel
- Lead-to-close conversion rate — blended and by channel
- ACV — by plan tier (weighted average)
- Monthly churn rate — by cohort or blended
- Net revenue retention — if expansion revenue exists

**Revenue model pitfalls:**
- Don't model "new customers" without modeling the funnel that generates them
- Don't use annual churn when your billing is monthly (or vice versa)
- Don't include expansion revenue without evidence of upsell behavior

## Phase 3: Cost Model

**COGS (directly tied to delivering revenue):**
- Hosting / infrastructure — model as % of revenue or fixed + variable
- Third-party APIs / data costs — per customer or per transaction
- Customer support FTE — model by support ticket volume per customer

**Gross Margin benchmark targets:**
- SaaS: 70–85% gross margin
- Marketplace: 50–70%
- Services: 30–50%

**Operating Expenses:**
```
R&D: Engineering + product salaries + contractors
S&M: Sales FTE + marketing spend + tools
G&A: Finance, legal, HR, office, insurance
```

**Headcount modeling:**
- Model FTE by role, not just headcount
- Include loaded cost (salary × 1.2–1.3 for benefits + taxes + equity)
- Tie each hire to a specific revenue or milestone trigger

## Phase 4: Unit Economics

**Customer Acquisition Cost (CAC):**
```
CAC = Total Sales + Marketing Spend / New Customers Acquired
(in same period — use prior quarter's spend if there's a lag)
```

**Lifetime Value (LTV):**
```
LTV = ARPU × Gross Margin % × (1 / Monthly Churn Rate)

Or: LTV = ACV × Gross Margin % × Average Customer Lifetime (in years)
```

**LTV:CAC ratio:**
- < 1:1 — destroying value
- 1:3 — breakeven / marginal
- 3:1 — target for healthy growth
- > 5:1 — underinvesting in growth

**Payback period:**
```
Payback = CAC / (ARPU × Gross Margin %)
Target: < 12 months for SMB, < 18 months for mid-market, < 24 months for enterprise
```

**Net Revenue Retention (NRR):**
```
NRR = (Starting MRR + Expansion - Contraction - Churn) / Starting MRR × 100
Target: > 100% (meaning expansion offsets churn)
```

## Phase 5: Scenario Planning

**Three scenarios — always build all three:**

| Scenario | Description | Key Driver Assumption |
|----------|-------------|----------------------|
| **Base** | Most likely outcome, conservative but not pessimistic | Conversion rate at current observed level |
| **Optimistic** | Outperformance on 1–2 key levers | 30–50% better conversion or lower churn |
| **Pessimistic** | One thing goes wrong | Churn doubles, or lead volume halves |

**For each scenario, output:**
- Revenue at 12, 18, 24 months
- Cash runway in months
- Break-even month (if applicable)
- Headcount required

**Sensitivity analysis (single-variable):**
Run the model holding all else equal while varying:
1. Monthly churn rate (±1%, ±2%)
2. Lead volume (±25%, ±50%)
3. ACV (±20%)
4. Gross margin (±10%)

Identify which variable has the largest impact on runway — that is the metric to track most closely.

## Phase 6: Runway and Cash Management

**Runway formula:**
```
Runway (months) = Cash Balance / Monthly Net Burn

Net Burn = Cash Out - Cash In (revenue received, not billed)
Gross Burn = Total cash out (before any revenue)
```

**Runway planning rules:**
- Always know your runway to the day, not just the month
- Fundraise when you have 6+ months runway remaining — not 2
- Model the impact of every major hire on runway before committing
- Keep 3 months of expenses in reserve — do not count it in operating runway

**Use of funds template:**
```markdown
| Category | Amount | Purpose | Milestone Unlocked |
|----------|--------|---------|-------------------|
| Engineering (2 FTE × 12 mo) | $X | Build Y feature | Reach $Xk ARR |
| Sales (1 AE × 12 mo) | $X | Close 20 customers | 20 paying customers |
| Marketing | $X | Paid CAC experiments | Prove CAC < $Y |
| G&A + buffer | $X | Legal, tools, ops | — |
| **Total Ask** | **$X** | | |
```

## Phase 7: Model Output Format

```markdown
## Financial Model Summary: <Company> | <Date>

### Assumptions
| Assumption | Base | Source / Rationale |
|------------|------|--------------------|
| Monthly new leads | X | Current organic + outbound |
| Conversion rate | X% | Last 30 days actuals |
| ACV | $X | Weighted avg of current plans |
| Monthly churn | X% | Last 90 days average |

### 12-Month Snapshot
| Month | MRR | Customers | Burn | Cash |
|-------|-----|-----------|------|------|
| M1    | $X  | X         | $X   | $X   |
| M12   | $X  | X         | $X   | $X   |

### Unit Economics
- CAC: $X
- LTV: $X
- LTV:CAC: X:1
- Payback: X months
- NRR: X%

### Scenarios
| | Base | Optimistic | Pessimistic |
|-|------|------------|-------------|
| 12-mo MRR | $X | $X | $X |
| Runway | X mo | X mo | X mo |

### Key Risks
1. <Risk + if it fires, what happens to model>
```

## Common Pitfalls

**Top-down market sizing for revenue projections** — "If we get 1% of the $10B market" is not a revenue model. Build from first principles: funnel inputs × conversion rates.

**Not separating billed vs. recognized revenue** — annual contracts paid upfront inflate cash but not MRR. Model both.

**Ignoring churn in early months** — even 2% monthly churn compounding creates a significant drag by month 12. Always model it.

**Headcount without productivity ramp** — a new AE won't close at full quota on Day 1. Model a 3–4 month ramp period.

**Hockey stick without a named inflection** — if the model shows flat growth then 10x growth, you must name the specific event that causes it (product launch, partnership, paid channel turn-on).

**Single scenario thinking** — presenting only the base case to investors signals overconfidence. Always build and share all three.
