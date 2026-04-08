---
name: bosskuai-sales-strategy
description: Use this for sales positioning, ICP definition, pipeline strategy, founder-led sales, objection handling, pricing narrative, and turning product value into a repeatable buying motion.
---

# BosskuAI Sales Strategy

Use this skill when the task is about **converting interest into revenue** — closing deals, building pipeline, or shaping the sales motion.

## How this differs from nearby skills

- **`bosskuai-marketing-growth`**: generates demand and awareness; this skill converts that demand into deals. Load together for full GTM coverage.
- **`bosskuai-launch-commercialization`**: the full launch plan; this skill supplies the sales strategy component.
- **`bosskuai-product-strategy`**: shapes what the product is; this skill shapes how to sell what exists.
- **`bosskuai-market-analysis`**: provides competitive intelligence; load first if positioning relative to competitors is unclear.

## Mindset

- Buyers don't buy products — they buy outcomes and risk reduction.
- Founder-led sales is the best feedback mechanism before hiring a sales team.
- A deal dies from trust gaps, not feature gaps. Build trust first.
- Every "no" has a reason — surface it and address it, or disqualify fast.

## Sales motion by stage

| Stage | Right motion | Why |
|-------|-------------|-----|
| Pre-PMF | Founder-led direct outreach | Learning > closing; founder credibility opens doors |
| Early traction | Inbound-assisted + founder demo | Qualify fast, close personally |
| Growth | SDR/AE handoff + structured process | Repeatable motion, shorter cycles |
| Scale | Channel + self-serve + enterprise track | Parallel motions for different segments |

## Workflow

1. **Define the buyer map**:
   - **ICP** (Ideal Customer Profile): company size, industry, geography, tech stack, trigger
   - **Buyer**: who signs the contract and controls budget?
   - **Champion**: who wants it to happen and benefits from the win?
   - **User**: who uses it daily? (may differ from buyer)
   - **Blocker**: who can kill the deal? (IT, legal, finance, competitor user)

2. **Frame the value in their terms** — Translate product features into outcomes the buyer cares about:
   - Business value: revenue increase, cost reduction, risk reduction, compliance
   - Quantify where possible: "reduces X from 3 hours to 20 minutes" beats "saves time"
   - ROI framing: payback period, cost-per-outcome comparison vs status quo

3. **Select the right sales motion for the stage** (see table above).

4. **Apply MEDDIC-lite for deal qualification**:
   - **M**etrics: what does success look like in numbers for the buyer?
   - **E**conomic Buyer: do we have access to the person who controls budget?
   - **D**ecision Criteria: what does the buyer use to evaluate options?
   - **D**ecision Process: what are the steps to reach a yes? Who else is involved?
   - **I**dentify Pain: what is the cost of inaction? Is it urgent?
   - **C**hampion: do we have someone who will advocate for us internally?

5. **Surface and address objections proactively**:
   - Price: "too expensive" → ROI framing, ROI calculator, risk reversal (trial, success guarantee)
   - Trust: "haven't heard of you" → case studies, reference calls, founder credibility
   - Timing: "not now" → uncover real reason; is it budget cycle, competing priority, or a soft no?
   - Competition: "we use X" → focus on unmet need X doesn't address, not feature comparison
   - Risk: "how do I know this will work?" → proof of concept, pilot, phased rollout

6. **Define the pipeline stages and exit criteria**:
   - Prospecting → Qualified → Demo/Discovery → Proposal → Negotiation → Closed
   - Each stage needs a concrete exit criterion — what must be true to advance?

7. **Identify required sales assets** — What does the team need to close deals that doesn't exist yet? (case studies, pricing sheet, comparison guide, ROI calculator, security questionnaire template)

## Pipeline operations

When the task is operational (managing active deals) rather than strategic:

**Call summary**: Transform call notes or transcripts into structured summaries — key discussion points, decisions made, objections raised, action items with owners, and draft follow-up email.

**Pipeline review**: Analyze pipeline health — total pipeline value, stage distribution, deal velocity, stale deals (no activity in 14+ days), deals at risk (past close date, champion gone quiet). Generate a weekly action plan with the top 3 deals to advance.

**Forecasting**: Generate weighted sales forecasts in best/likely/worst scenarios from pipeline data. Weight by stage probability and deal-specific confidence. Flag concentration risk (>30% of forecast in one deal).

**CRM integration**: When CRM MCP is available, pull pipeline data directly. Otherwise, work from pasted pipeline exports, CSV data, or described deal status.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not build a complex sales process before you have 10 paying customers — learn the motion before systematizing it.
- Do not optimize for pipeline volume at the expense of deal quality in the early stage.
- Do not guess at buyer objections — run discovery calls and listen.
- Do not position against competitors directly unless the buyer brings them up.

## Output format

```
Buyer map:
  ICP: [profile]
  Buyer / Champion / User / Blocker: [roles]
Value framing: [outcome in buyer's terms + quantification]
Sales motion: [which motion + why for this stage]
MEDDIC qualification:
  Metrics / Economic Buyer / Decision Criteria / Decision Process / Pain / Champion
Objections and responses:
  [objection] → [response + asset needed]
Pipeline stages:
  [stage] → [exit criteria]
Sales assets needed: [list]
Next pipeline or conversion move: [specific action]
```

## References

- `../../references/checklists/sales-strategy-checklist.md`
- `../../references/playbooks/sales-strategy-playbook.md`
